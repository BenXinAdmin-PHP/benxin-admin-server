<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   BxJwt 服务 — 双 guard 双令牌签发/校验 + Valkey 白黑名单（ADR-8）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

use app\common\exception\AuthException;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use think\facade\Cache;
use Throwable;

/**
 * 自建 JWT 服务层（lcobucci/jwt 作底层，HS256）。
 *
 * 设计要点（ADR-8）：
 * - 双 guard（admin / api）各自独立 secret，互不可验。
 * - 双令牌：access 短期（默认 2h）、refresh 长期（默认 14d）。
 * - claims：jti(唯一) / iss / aud(=guard) / iat / exp / uid / tenant_id / token_type；
 *   access 额外带 rjti（配对 refresh 的 jti），便于登出时反查并撤销 refresh 白名单。
 * - Valkey 名单（走 redis store，TTL 至 exp）：
 *     登录：refresh 的 jti 写白名单；登出：access 的 jti 写黑名单 + 删除 refresh 白名单。
 *     校验 access 查黑名单命中即拒；校验 refresh 查白名单存在才放行。
 * - key 约定：bxjwt:{guard}:bl:{jti}（黑）/ bxjwt:{guard}:wl:refresh:{jti}（白）。
 *
 * 本类不做轮换（refresh 不变，仅签发新 access；登出即失效）。
 */
class BxJwt
{
    public const TYPE_ACCESS  = 'access';
    public const TYPE_REFRESH = 'refresh';

    /** 各 guard 的 lcobucci 配置缓存（同一请求复用） */
    protected static array $configs = [];

    // ------------------------------------------------------------------
    // 签发
    // ------------------------------------------------------------------

    /**
     * 签发 access token。
     *
     * @param string $guard      守卫（admin / api）
     * @param array  $payload    业务载荷（uid / tenant_id）
     * @param string $refreshJti 配对 refresh 的 jti（写入 rjti claim）
     * @return array{token:string,jti:string,expires_in:int}
     */
    public static function issueAccess(string $guard, array $payload, string $refreshJti): array
    {
        return self::issue($guard, $payload, self::TYPE_ACCESS, self::guardConf($guard)['access_ttl'], [
            'rjti' => $refreshJti,
        ]);
    }

    /**
     * 签发 refresh token，并写入白名单（TTL = refresh_ttl）。
     *
     * @param string $guard
     * @param array  $payload 业务载荷（uid / tenant_id）
     * @return array{token:string,jti:string,expires_in:int}
     */
    public static function issueRefresh(string $guard, array $payload): array
    {
        $issued = self::issue($guard, $payload, self::TYPE_REFRESH, self::guardConf($guard)['refresh_ttl']);
        // refresh 白名单：存在才视为有效
        self::store()->set(self::wlKey($guard, $issued['jti']), 1, $issued['expires_in']);

        return $issued;
    }

    /**
     * 通用签发。
     *
     * @param array<string,mixed> $extraClaims
     * @return array{token:string,jti:string,expires_in:int}
     */
    protected static function issue(string $guard, array $payload, string $type, int $ttl, array $extraClaims = []): array
    {
        $config = self::config($guard);
        $now    = new DateTimeImmutable();
        $jti    = Uuid::v4();

        $builder = $config->builder()
            ->issuedBy((string) config('jwt.iss'))
            ->permittedFor($guard)
            ->identifiedBy($jti)
            ->issuedAt($now)
            ->expiresAt($now->modify("+{$ttl} seconds"))
            ->withClaim('uid', (int) ($payload['uid'] ?? 0))
            ->withClaim('tenant_id', (int) ($payload['tenant_id'] ?? 0))
            ->withClaim('token_type', $type);

        foreach ($extraClaims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        $token = $builder->getToken($config->signer(), $config->signingKey());

        return [
            'token'      => $token->toString(),
            'jti'        => $jti,
            'expires_in' => $ttl,
        ];
    }

    // ------------------------------------------------------------------
    // 校验
    // ------------------------------------------------------------------

    /**
     * 解析并校验令牌：签名 + iss + aud + 过期 + 类型 + 名单。
     *
     * @param string $guard
     * @param string $tokenStr   原始 token 串（不含 Bearer 前缀）
     * @param string $expectType 期望类型（access / refresh）
     * @return array{jti:string,uid:int,tenant_id:int,token_type:string,exp:int,rjti:?string}
     *
     * @throws AuthException 无效(401001) / 过期(401003) / refresh 失效(401004)
     */
    public static function parse(string $guard, string $tokenStr, string $expectType): array
    {
        $config = self::config($guard);

        // 1) 解析 + 验签 + iss/aud 约束（任一失败视为无效）
        try {
            $token = $config->parser()->parse($tokenStr);
            if (!$token instanceof UnencryptedToken) {
                throw AuthException::unauthorized();
            }
            $config->validator()->assert(
                $token,
                new SignedWith($config->signer(), $config->verificationKey()),
                new IssuedBy((string) config('jwt.iss')),
                new PermittedFor($guard),
            );
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable) {
            throw AuthException::unauthorized();
        }

        // 2) 过期单独判定，便于映射 401003
        if ($token->isExpired(new DateTimeImmutable())) {
            throw AuthException::expired();
        }

        $claims = $token->claims();
        $type   = (string) $claims->get('token_type', '');
        if ($type !== $expectType) {
            throw AuthException::unauthorized();
        }

        /** @var DateTimeImmutable $exp */
        $exp = $claims->get(RegisteredClaims::EXPIRATION_TIME);
        $jti = (string) $claims->get(RegisteredClaims::ID, '');

        // 3) 名单校验：access 查黑名单，refresh 查白名单
        if ($expectType === self::TYPE_ACCESS && self::isBlacklisted($guard, $jti)) {
            throw AuthException::unauthorized('登录已失效，请重新登录');
        }
        if ($expectType === self::TYPE_REFRESH && !self::isRefreshWhitelisted($guard, $jti)) {
            throw AuthException::refreshInvalid();
        }

        return [
            'jti'        => $jti,
            'uid'        => (int) $claims->get('uid', 0),
            'tenant_id'  => (int) $claims->get('tenant_id', 0),
            'token_type' => $type,
            'exp'        => $exp->getTimestamp(),
            'rjti'       => $claims->has('rjti') ? (string) $claims->get('rjti') : null,
        ];
    }

    // ------------------------------------------------------------------
    // 名单维护
    // ------------------------------------------------------------------

    /**
     * 拉黑 access 的 jti（登出）。TTL = 该 access 至 exp 的剩余秒数，过期自动清理。
     */
    public static function blacklistAccess(string $guard, string $jti, int $expTimestamp): void
    {
        $ttl = $expTimestamp - time();
        if ($jti === '' || $ttl <= 0) {
            return;
        }
        self::store()->set(self::blKey($guard, $jti), 1, $ttl);
    }

    /**
     * 撤销 refresh 白名单（登出）。
     */
    public static function revokeRefresh(string $guard, ?string $jti): void
    {
        if ($jti === null || $jti === '') {
            return;
        }
        self::store()->delete(self::wlKey($guard, $jti));
    }

    public static function isBlacklisted(string $guard, string $jti): bool
    {
        return $jti !== '' && self::store()->has(self::blKey($guard, $jti));
    }

    public static function isRefreshWhitelisted(string $guard, string $jti): bool
    {
        return $jti !== '' && self::store()->has(self::wlKey($guard, $jti));
    }

    // ------------------------------------------------------------------
    // 内部工具
    // ------------------------------------------------------------------

    /**
     * 构建/复用某 guard 的 lcobucci 配置（对称签名，签验同一 secret）。
     */
    protected static function config(string $guard): Configuration
    {
        if (isset(self::$configs[$guard])) {
            return self::$configs[$guard];
        }

        $secret = (string) (self::guardConf($guard)['secret'] ?? '');
        if (strlen($secret) < 32) {
            // 配置缺失或过短直接判 401，避免用弱/空密钥签发
            throw AuthException::unauthorized('JWT 密钥未正确配置');
        }

        return self::$configs[$guard] = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secret),
        );
    }

    /**
     * 取某 guard 配置（secret / ttl）。
     *
     * @return array{secret:string,access_ttl:int,refresh_ttl:int}
     */
    protected static function guardConf(string $guard): array
    {
        $conf = config('jwt.guards.' . $guard);
        if (!is_array($conf)) {
            throw AuthException::unauthorized('未知的认证 guard：' . $guard);
        }

        return $conf;
    }

    /**
     * 名单专用缓存 store（Valkey/Redis，TTL 精确）。
     */
    protected static function store()
    {
        return Cache::store((string) config('jwt.store', 'redis'));
    }

    protected static function blKey(string $guard, string $jti): string
    {
        return "bxjwt:{$guard}:bl:{$jti}";
    }

    protected static function wlKey(string $guard, string $jti): string
    {
        return "bxjwt:{$guard}:wl:refresh:{$jti}";
    }
}

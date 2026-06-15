<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   微信账号基类 — access_token 中心化缓存 + 防并发刷新锁 + 失效重试
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// | @updated   2026-06-13 22:50:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

use app\common\exception\WechatException;
use app\common\library\BxCache;
use app\common\library\ErrorCode;

/**
 * 微信账号能力基类（公众号 mp / 小程序 mini 共用，M4-B 核心）。
 *
 * access_token 中心化管理：
 * - 存 Valkey（key：wechat:token:{type}:{appid}，经 store 前缀实际为 bx:wechat:token:...），
 *   fpm 多进程/多实例共享同一 token，TTL = expires_in − 300s 提前量。
 * - ★防并发刷新锁：缓存 miss 时以 SET NX EX 抢锁（wechat:lock:token:{type}:{appid}，10s）——
 *   抢到者刷新+写缓存+释放锁（Lua 校验锁值仅删自己的锁）；未抢到者短暂 sleep 重读缓存；
 *   等待 5 次仍空则兜底强刷，杜绝多进程并发刷新致微信 token 互相失效。
 * - token 失效重试：微信返回 40001/42001/40014 → 清缓存强刷 + 重试一次，仍失败抛异常。
 * - token 仅存 Valkey，不落库、不进日志（§8）。
 */
abstract class WechatAccount
{
    public const API_BASE = 'https://api.weixin.qq.com';

    /** token/ticket 缓存提前量（秒）：TTL = expires_in − 300 */
    protected const TTL_AHEAD = 300;
    /** 防并发刷新锁有效期（秒） */
    protected const LOCK_TTL = 10;
    /** 未抢到锁时的等待重读次数 / 间隔（微秒） */
    protected const LOCK_WAIT_RETRIES = 5;
    protected const LOCK_WAIT_USLEEP  = 200000;
    /** 微信 access_token 失效类 errcode（触发清缓存重刷重试） */
    protected const TOKEN_INVALID_CODES = [40001, 42001, 40014];

    public function __construct(
        protected string $appId,
        protected string $appSecret,
        protected HttpClientInterface $http,
    ) {
    }

    /**
     * 账号类型（mp / mini），用于缓存 key 命名空间。
     */
    abstract protected function type(): string;

    public function appId(): string
    {
        return $this->appId;
    }

    // ------------------------------------------------------------------
    // access_token 中心化
    // ------------------------------------------------------------------

    /**
     * 取 access_token：优先 Valkey 缓存，miss 走防并发锁刷新。
     *
     * @param bool $forceRefresh 强刷（先清缓存再走刷新流程）
     */
    public function accessToken(bool $forceRefresh = false): string
    {
        $cacheKey = "wechat:token:{$this->type()}:{$this->appId}";
        if ($forceRefresh) {
            BxCache::forget($cacheKey);
        }

        return $this->rememberWithLock(
            $cacheKey,
            "wechat:lock:token:{$this->type()}:{$this->appId}",
            fn (): array => $this->fetchAccessToken(),
        );
    }

    /**
     * 调微信 cgi-bin/token 刷新（公众号/小程序通用）。
     *
     * @return array{value:string,ttl:int}
     */
    protected function fetchAccessToken(): array
    {
        $resp = $this->apiGet('/cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid'      => $this->appId,
            'secret'     => $this->appSecret,
        ], ErrorCode::WECHAT_TOKEN_FAILED);

        $token = (string) ($resp['access_token'] ?? '');
        if ($token === '') {
            throw new WechatException('微信未返回 access_token', ErrorCode::WECHAT_TOKEN_FAILED);
        }

        return ['value' => $token, 'ttl' => $this->cacheTtl((int) ($resp['expires_in'] ?? 7200))];
    }

    /**
     * 缓存 TTL = expires_in − 提前量（下限 60s 防御异常返回值）。
     */
    protected function cacheTtl(int $expiresIn): int
    {
        return max(60, $expiresIn - self::TTL_AHEAD);
    }

    /**
     * 读缓存 + 防并发刷新锁（token/ticket 共用套路）。
     *
     * @param callable():array{value:string,ttl:int} $fetch 真实刷新闭包
     */
    protected function rememberWithLock(string $cacheKey, string $lockKey, callable $fetch): string
    {
        $store = BxCache::store();

        $hit = $store->get($cacheKey);
        if (is_string($hit) && $hit !== '') {
            return $hit;
        }

        // 缓存 miss：SET NX EX 抢锁（用原生句柄，key 手动套 store 前缀与缓存同域）
        $handler   = $store->handler();
        $fullLock  = $store->getCacheKey($lockKey);
        $lockToken = bin2hex(random_bytes(8));
        $acquired  = (bool) $handler->set($fullLock, $lockToken, ['nx', 'ex' => self::LOCK_TTL]);

        if ($acquired) {
            try {
                $fresh = $fetch();
                $store->set($cacheKey, $fresh['value'], $fresh['ttl']);

                return $fresh['value'];
            } finally {
                // Lua 原子校验锁值，仅删自己的锁（锁超时被他人接管时不误删）
                $handler->eval(
                    "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) end return 0",
                    [$fullLock, $lockToken],
                    1,
                );
            }
        }

        // 未抢到锁：他人正在刷新，短暂等待后重读缓存
        for ($i = 0; $i < self::LOCK_WAIT_RETRIES; $i++) {
            usleep(self::LOCK_WAIT_USLEEP);
            $hit = $store->get($cacheKey);
            if (is_string($hit) && $hit !== '') {
                return $hit;
            }
        }

        // 兜底强刷：等待后缓存仍空（持锁者失败/超时），自行刷新避免饿死
        $fresh = $fetch();
        $store->set($cacheKey, $fresh['value'], $fresh['ttl']);

        return $fresh['value'];
    }

    // ------------------------------------------------------------------
    // 微信 API 调用收口
    // ------------------------------------------------------------------

    /**
     * GET 微信接口：errcode≠0 → WechatException（errmsg 透出、errcode 记录）。
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    protected function apiGet(string $path, array $query, int $bizCode = ErrorCode::WECHAT_API_ERROR): array
    {
        $resp    = $this->http->get(self::API_BASE . $path, $query);
        $errcode = (int) ($resp['errcode'] ?? 0);
        if ($errcode !== 0) {
            throw WechatException::fromApi($bizCode, $errcode, (string) ($resp['errmsg'] ?? ''));
        }

        return $resp;
    }

    /**
     * 带全局 access_token 调微信接口：token 失效（40001/42001/40014）
     * → 清缓存强刷 + 重试一次；仍失败抛异常。
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    protected function callWithToken(string $path, array $query = [], int $bizCode = ErrorCode::WECHAT_API_ERROR): array
    {
        try {
            return $this->apiGet($path, ['access_token' => $this->accessToken()] + $query, $bizCode);
        } catch (WechatException $e) {
            if (!in_array($e->errcode ?? 0, self::TOKEN_INVALID_CODES, true)) {
                throw $e;
            }

            return $this->apiGet($path, ['access_token' => $this->accessToken(true)] + $query, $bizCode);
        }
    }

    /**
     * POST 微信接口：access_token 走 query，业务参数走 JSON body（getuserphonenumber 等新版接口）。
     * errcode≠0 → WechatException（errmsg 透出、errcode 记录）。
     *
     * @param array<string,mixed> $body
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    protected function apiPost(string $path, array $body = [], array $query = [], int $bizCode = ErrorCode::WECHAT_API_ERROR): array
    {
        $resp    = $this->http->postJson(self::API_BASE . $path, $body, $query);
        $errcode = (int) ($resp['errcode'] ?? 0);
        if ($errcode !== 0) {
            throw WechatException::fromApi($bizCode, $errcode, (string) ($resp['errmsg'] ?? ''));
        }

        return $resp;
    }

    /**
     * 带全局 access_token POST 微信接口（callWithToken 的 POST 变体）：token 失效
     * （40001/42001/40014）→ 清缓存强刷 + 重试一次；仍失败抛异常。
     * 与 callWithToken 共用 access_token 中心化缓存与失效重试语义，仅 HTTP 方法不同。
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    protected function callWithTokenPost(string $path, array $body = [], int $bizCode = ErrorCode::WECHAT_API_ERROR): array
    {
        try {
            return $this->apiPost($path, $body, ['access_token' => $this->accessToken()], $bizCode);
        } catch (WechatException $e) {
            if (!in_array($e->errcode ?? 0, self::TOKEN_INVALID_CODES, true)) {
                throw $e;
            }

            return $this->apiPost($path, $body, ['access_token' => $this->accessToken(true)], $bizCode);
        }
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — C 端认证编排（刷新/登出）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\service;

use app\common\base\BxService;
use app\common\exception\AuthException;
use app\common\library\BxJwt;
use app\common\model\User;

/**
 * C 端认证服务（api guard）：收口刷新、登出两段逻辑，控制器只做参数与响应。
 * 登录（code2session/oauth + 手机号，签发走 BxJwt::issueForApi）留 M5-B。
 * guard 固定为 api，与 admin 完全隔离（独立密钥 + 独立 Valkey 名单 bx:bxjwt:api:*）。
 */
class AuthService extends BxService
{
    protected const GUARD = 'api';

    /**
     * 刷新：校验 refresh（含白名单）→ 签发新 access（沿用同一 refresh 的 jti，不轮换，复刻 admin）。
     *
     * @return array{access_token:string,token_type:string,expires_in:int}
     *
     * @throws AuthException 401003 过期 / 401004 失效
     */
    public function refresh(string $refreshToken): array
    {
        $claims = BxJwt::parse(self::GUARD, $refreshToken, BxJwt::TYPE_REFRESH);

        // refresh 仍有效，但用户可能已被停用/删除 → 视为刷新失效
        $user = User::where('id', $claims['uid'])->where('status', 1)->find();
        if ($user === null) {
            // 顺手撤销该 refresh 白名单，避免反复试探
            BxJwt::revokeRefresh(self::GUARD, $claims['jti']);
            throw AuthException::refreshInvalid();
        }

        $access = BxJwt::issueAccess(self::GUARD, [
            'uid'       => (int) $user->id,
            'tenant_id' => (int) $user->tenant_id,
        ], $claims['jti']);

        return [
            'access_token' => $access['token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $access['expires_in'],
        ];
    }

    /**
     * 登出：拉黑当前 access 的 jti + 撤销配对 refresh 的白名单。
     *
     * @param array $claims JwtAuth 注入的当前 access claims（jti / exp / rjti）
     */
    public function logout(array $claims): void
    {
        BxJwt::blacklistAccess(self::GUARD, (string) ($claims['jti'] ?? ''), (int) ($claims['exp'] ?? 0));
        BxJwt::revokeRefresh(self::GUARD, $claims['rjti'] ?? null);
    }
}

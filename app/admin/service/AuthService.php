<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 后台认证编排（登录/刷新/登出/自助改密）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// | @updated   2026-06-09 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\AuthException;
use app\common\exception\BusinessException;
use app\common\library\BxJwt;
use app\common\model\Admin;

/**
 * 后台认证服务：收口登录、刷新、登出三段逻辑，控制器只做参数与响应。
 * guard 固定为 admin（C 端 api 见 M5）。
 */
class AuthService extends BxService
{
    protected const GUARD = 'admin';

    /**
     * 账号密码登录：校验凭证 → 更新登录痕迹 → 签发双令牌（refresh 入白名单）。
     *
     * @return array{admin:Admin,tokens:array}|null 凭证/状态任一不符返回 null（防枚举统一处理）
     */
    public function login(string $username, string $password, string $ip): ?array
    {
        // 单租户恒 tenant_id=0；仅取启用账号
        $admin = Admin::where('tenant_id', 0)
            ->where('username', $username)
            ->where('status', 1)
            ->find();

        // 账号不存在 / 已禁用 / 密码错误，统一返回 null，文案不区分（防枚举）
        if ($admin === null || !password_verify($password, (string) $admin->password)) {
            return null;
        }

        // 更新登录痕迹
        $admin->last_login_at = date('Y-m-d H:i:s');
        $admin->last_login_ip = $ip;
        $admin->save();

        return [
            'admin'  => $admin,
            'tokens' => $this->issueTokens($admin),
        ];
    }

    /**
     * 刷新：校验 refresh（含白名单）→ 签发新 access（沿用同一 refresh 的 jti）。
     *
     * @return array{access_token:string,token_type:string,expires_in:int}
     *
     * @throws AuthException 401003 过期 / 401004 失效
     */
    public function refresh(string $refreshToken): array
    {
        $claims = BxJwt::parse(self::GUARD, $refreshToken, BxJwt::TYPE_REFRESH);

        // refresh 仍有效，但账号可能已被禁用/删除 → 视为刷新失效
        $admin = Admin::where('id', $claims['uid'])->where('status', 1)->find();
        if ($admin === null) {
            // 顺手撤销该 refresh 白名单，避免反复试探
            BxJwt::revokeRefresh(self::GUARD, $claims['jti']);
            throw AuthException::refreshInvalid();
        }

        $access = BxJwt::issueAccess(self::GUARD, [
            'uid'       => (int) $admin->id,
            'tenant_id' => (int) $admin->tenant_id,
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

    /**
     * 自助改密：验旧密码（失败统一文案，不泄露）→ 新密码 Argon2id 写入 →
     * 拉黑当前 access + 撤销当前 refresh，强制重登。
     *
     * @param array $claims JwtAuth 注入的当前 access claims
     */
    public function changePassword(Admin $admin, string $oldPassword, string $newPassword, array $claims): void
    {
        if (!password_verify($oldPassword, (string) $admin->password)) {
            throw new BusinessException('原密码不正确');
        }

        $admin->password = password_hash($newPassword, PASSWORD_ARGON2ID);
        $admin->save();

        // 改密后强制重登：当前会话立即失效
        $this->logout($claims);
    }

    /**
     * 签发双令牌：先发 refresh（写白名单）再发 access（携带 rjti 以便登出反查）。
     *
     * @return array{access_token:string,refresh_token:string,token_type:string,expires_in:int,refresh_expires_in:int}
     */
    protected function issueTokens(Admin $admin): array
    {
        $payload = [
            'uid'       => (int) $admin->id,
            'tenant_id' => (int) $admin->tenant_id,
        ];

        $refresh = BxJwt::issueRefresh(self::GUARD, $payload);
        $access  = BxJwt::issueAccess(self::GUARD, $payload, $refresh['jti']);

        return [
            'access_token'       => $access['token'],
            'refresh_token'      => $refresh['token'],
            'token_type'         => 'Bearer',
            'expires_in'         => $access['expires_in'],
            'refresh_expires_in' => $refresh['expires_in'],
        ];
    }
}

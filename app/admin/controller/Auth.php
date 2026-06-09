<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   后台认证 — POST /admin/v1/login|refresh|logout, GET /admin/v1/profile
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// | @updated   2026-06-09 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\AuthService;
use app\admin\service\ProfileService;
use app\admin\validate\AuthValidate;
use app\common\base\BxController;
use app\common\library\ErrorCode;
use app\common\model\Admin;
use think\Response;

/**
 * 后台认证闭环：登录 → 刷新 → 登出 → 当前管理员信息。
 * - login / refresh 不挂 JwtAuth（refresh 自校验 refresh token）。
 * - logout / profile 挂 JwtAuth（依赖已注入的登录主体）。
 */
class Auth extends BxController
{
    /**
     * 登录：账号 + 密码 → 签发 access + refresh。
     * POST /admin/v1/login
     */
    public function login(): Response
    {
        validate(AuthValidate::class)->scene('login')->check($this->request->post());

        $result = (new AuthService($this->app))->login(
            (string) $this->request->post('username', ''),
            (string) $this->request->post('password', ''),
            $this->request->ip(),
        );

        // 凭证/状态不符：统一文案防枚举（HTTP 200 + 业务码 401002）
        if ($result === null) {
            return $this->fail(ErrorCode::LOGIN_FAIL);
        }

        return $this->success($result['tokens'], '登录成功');
    }

    /**
     * 刷新：用 refresh 换新 access。
     * POST /admin/v1/refresh
     */
    public function refresh(): Response
    {
        validate(AuthValidate::class)->scene('refresh')->check($this->request->post());

        // 校验失败（过期/失效）由 AuthService 抛 AuthException → 全局映射 401003/401004
        $data = (new AuthService($this->app))->refresh(
            (string) $this->request->post('refresh_token', ''),
        );

        return $this->success($data, '刷新成功');
    }

    /**
     * 登出：拉黑当前 access + 撤销配对 refresh。
     * POST /admin/v1/logout（需登录）
     */
    public function logout(): Response
    {
        (new AuthService($this->app))->logout((array) $this->request->jwtClaims);

        return $this->success(null, '已退出登录');
    }

    /**
     * 当前管理员聚合信息：user + roles + menus(树) + perms（前端动态路由 + 按钮鉴权契约）。
     * GET /admin/v1/profile（需登录）
     */
    public function profile(): Response
    {
        /** @var Admin $admin */
        $admin = $this->request->adminUser;

        return $this->success((new ProfileService($this->app))->build($admin));
    }
}

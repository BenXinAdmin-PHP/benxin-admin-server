<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   C 端认证 — POST /api/v1/refresh|logout
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\api\service\AuthService;
use app\api\validate\AuthValidate;
use app\common\base\BxController;
use think\Response;

/**
 * C 端认证闭环（api guard）：刷新 → 登出。
 * - 登录（小程序 code2session+getPhoneNumber / H5 oauth+短信验证码）留 M5-B，
 *   签发统一走 BxJwt::issueForApi；本阶段以 APP_DEBUG 探针验证令牌闭环。
 * - refresh 不挂 JwtAuth（自校验 refresh token）；logout 挂 api JwtAuth。
 */
class Auth extends BxController
{
    /**
     * 刷新：用 refresh 换新 access。
     * POST /api/v1/refresh
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
     * POST /api/v1/logout（需登录）
     */
    public function logout(): Response
    {
        (new AuthService($this->app))->logout((array) $this->request->jwtClaims);

        return $this->success(null, '已退出登录');
    }
}

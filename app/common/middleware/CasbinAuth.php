<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   Casbin 权限中间件（骨架，逻辑留 M1）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * Casbin RBAC 鉴权中间件（M0 占位透传）。
 *
 * TODO M1：
 * - 基于 php-casbin 校验 (subject, domain=tenant_id, object=接口/按钮, action)；
 * - 单租户使用统一 domain；无权限抛权限异常 → 403xxx；
 * - 需在 JwtAuth 之后执行（依赖已注入的登录主体）。
 */
class CasbinAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // M0 不做任何权限校验，直接放行；真实逻辑见上方 TODO M1。
        return $next($request);
    }
}

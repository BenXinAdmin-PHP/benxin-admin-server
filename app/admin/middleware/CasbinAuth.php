<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   Casbin 鉴权中间件 — enforce 角色权限
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\middleware;

use app\common\exception\AuthException;
use app\common\library\ErrorCode;
use app\common\library\Result;
use app\common\service\CasbinService;
use Closure;
use think\Request;
use think\Response;

/**
 * 后台 RBAC 鉴权（admin guard）：须挂在 JwtAuth 之后。
 * - 入参 $perm：路由显式声明的所需权限（perms 串，如 system:admin:list）。
 * - 取当前管理员角色 code 列表，dom = tenant_id（恒 0），逐角色 enforce，
 *   任一 allow → 放行；全部 deny → 403000 + HTTP 403（统一信封，不泄露策略细节）。
 * - 超管 super_admin 因通配策略 p,super_admin,0,*,* 对任意 perm 命中 → 永远放行。
 */
class CasbinAuth
{
    public function handle(Request $request, Closure $next, string $perm = ''): Response
    {
        // 未声明所需权限的路由不做权限拦截（仅认证即可）
        if ($perm === '') {
            return $next($request);
        }

        // 依赖 JwtAuth 注入的登录主体；缺失说明中间件顺序错误或未认证
        $admin = $request->adminUser;
        if ($admin === null) {
            throw AuthException::unauthorized();
        }

        $roleCodes = $admin->roleCodes();
        $dom       = (int) $admin->tenant_id;

        if (CasbinService::enforceAny($roleCodes, $dom, $perm)) {
            return $next($request);
        }

        return Result::fail(ErrorCode::FORBIDDEN, '', null, 403);
    }
}

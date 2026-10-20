<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   管理员与角色写操作 — 限超级管理员
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-10-20 18:42:23
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\middleware;

use app\admin\service\AdminService;
use app\common\library\ErrorCode;
use app\common\library\Result;
use Closure;
use think\Request;
use think\Response;

/** 阻止获授部分管理权限的账号修改自身角色或接管更高权限账号。 */
class SuperAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorId = (int) ($request->adminId ?? 0);
        if ($actorId <= 0 || !(new AdminService(app()))->isSuperAdmin($actorId)) {
            return Result::fail(ErrorCode::FORBIDDEN, '', null, 403);
        }

        return $next($request);
    }
}

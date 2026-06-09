<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   权限探针 — GET /admin/v1/_perm_probe（M1-B 验证用，仅调试态注册）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\common\base\BxController;
use think\Response;

/**
 * 权限引擎验证探针：挂 JwtAuth + CasbinAuth:system:admin:list。
 * 仅用于 M1-B 实测 RBAC 放行/拒绝，路由仅在 APP_DEBUG=true 时注册，生产不暴露。
 */
class Probe extends BxController
{
    public function index(): Response
    {
        return $this->success(['ok' => true], 'permitted');
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 角色 bx_role
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 角色模型。
 */
class Role extends BxModel
{
    protected $name = 'role';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'         => 'integer',
        'tenant_id'  => 'integer',
        'sort'       => 'integer',
        'status'     => 'integer',
        'data_scope' => 'integer',
    ];
}

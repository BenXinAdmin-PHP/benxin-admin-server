<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — C 端用户 bx_user
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * C 端用户主表模型（ADR-16）。软删 + 租户作用域沿用 BxModel；
 * 不挂 create_by/create_dept、不接 Casbin（C 端无 RBAC）。
 */
class User extends BxModel
{
    protected $name = 'user';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'gender'    => 'integer',
        'status'    => 'integer',
    ];
}

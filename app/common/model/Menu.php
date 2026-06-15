<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 菜单/权限 bx_menu
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 菜单/权限模型（目录/菜单/按钮三态）。
 */
class Menu extends BxModel
{
    protected $name = 'menu';

    protected $hidden = ['deleted_at'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'parent_id' => 'integer',
        'type'      => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
        'visible'   => 'integer',
    ];
}

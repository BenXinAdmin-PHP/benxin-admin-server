<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 菜单 bx_menu
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 菜单模型。
 */
class Menu extends BxModel
{
    protected $name = 'menu';

    protected $hidden = ['deleted_at', 'tenant_id'];

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

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 系统公告 bx_notice
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 20:00:39
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 系统公告模型。
 */
class Notice extends BxModel
{
    protected $name = 'notice';

    protected $hidden = ['deleted_at'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'type'      => 'integer',
        'status'    => 'integer',
        'is_top'    => 'integer',
        'sort'      => 'integer',
    ];
}

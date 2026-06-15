<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 广告位 bx_banner
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 广告位模型。
 */
class Banner extends BxModel
{
    protected $name = 'banner';

    protected $hidden = ['deleted_at'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];
}

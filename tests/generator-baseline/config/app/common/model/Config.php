<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 配置中心 bx_config
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 配置中心模型。
 */
class Config extends BxModel
{
    protected $name = 'config';

    protected $hidden = ['deleted_at'];

    protected $type = [
        'id'           => 'integer',
        'tenant_id'    => 'integer',
        'is_sensitive' => 'integer',
        'sort'         => 'integer',
    ];
}

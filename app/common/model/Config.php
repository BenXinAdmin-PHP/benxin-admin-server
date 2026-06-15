<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 参数配置 bx_config
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 参数配置模型（分组 + key-value，敏感值 AES 加密入库）。
 */
class Config extends BxModel
{
    protected $name = 'config';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'           => 'integer',
        'tenant_id'    => 'integer',
        'is_sensitive' => 'integer',
        'sort'         => 'integer',
    ];
}

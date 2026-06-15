<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 字典数据项 bx_dict_data
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 字典数据项模型（按 dict_type 关联 bx_dict.type）。
 */
class DictData extends BxModel
{
    protected $name = 'dict_data';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'         => 'integer',
        'tenant_id'  => 'integer',
        'sort'       => 'integer',
        'status'     => 'integer',
        'is_default' => 'integer',
    ];
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 内容 bx_content
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// | @updated   2026-06-15 (M3-G: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 内容模型。
 */
class Content extends BxModel
{
    protected $name = 'content';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'          => 'integer',
        'tenant_id'   => 'integer',
        'category_id' => 'integer',
        'status'      => 'integer',
        'is_top'      => 'integer',
        'sort'        => 'integer',
        'view_count'  => 'integer',
    ];
}

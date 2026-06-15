<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 内容分类 bx_content_category
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// | @updated   2026-06-15 (M3-G: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 内容分类模型。
 */
class ContentCategory extends BxModel
{
    protected $name = 'content_category';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'parent_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];
}

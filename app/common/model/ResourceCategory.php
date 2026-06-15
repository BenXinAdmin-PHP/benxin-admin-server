<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 素材分类 bx_resource_category
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// | @updated   2026-06-15 15:06:31
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 素材分类模型。
 *
 * 手工补 tenant_id 入 $hidden（不外露 tenant_id/deleted_at，与 Resource 同口径）——
 * 生成器 $hidden 未覆盖 tenant_id，见完成报告「狗粮反馈」。
 */
class ResourceCategory extends BxModel
{
    protected $name = 'resource_category';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'parent_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 素材 bx_resource
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// | @updated   2026-06-15 15:06:31
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 素材模型。
 *
 * 手工补 tenant_id 入 $hidden（列表字段白名单：不外露 tenant_id/deleted_at，任务书要求）。
 * 生成器当前 $hidden 仅覆盖 deleted_at + 敏感字段，未隐藏 tenant_id —— 见完成报告「狗粮反馈」。
 */
class Resource extends BxModel
{
    protected $name = 'resource';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'               => 'integer',
        'tenant_id'        => 'integer',
        'category_id'      => 'integer',
        'size'             => 'integer',
        'transcode_status' => 'integer',
    ];
}

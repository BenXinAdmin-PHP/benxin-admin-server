<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 素材 bx_resource
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// | @updated   2026-06-15 15:06:31
// | @updated   2026-06-21 16:30:00（修正 $hidden 过时注释：tenant_id 已由 M3-G 隐藏）
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 素材模型。
 *
 * $hidden 隐藏 tenant_id/deleted_at（最小暴露，不外露框架内部维度字段），
 * 与生成器 M3-G 后默认范式一致（生成器 $hidden 已并入 tenant_id，无需再手工补）。
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

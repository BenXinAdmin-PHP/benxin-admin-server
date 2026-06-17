<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 页面 bx_page（通用页面搭建 schema 模型，M6-B）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 页面模型（ADR-21 单表 JSON）。
 * blocks 为整页区块有序数组，JSON 读出为关联数组、写入自动序列化。
 * $hidden 收口（M3-G sweep）：deleted_at / tenant_id 不外露；create_by/create_dept
 * 在渲染接口层另行白名单剔除。
 */
class Page extends BxModel
{
    protected $name = 'page';

    // blocks 作为 JSON 字段：读为数组、写自动 json_encode
    protected $json = ['blocks'];

    protected $jsonAssoc = true;

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'status'    => 'integer',
    ];

    // 状态常量
    public const STATUS_PUBLISHED = 1; // 启用/已发布
    public const STATUS_DRAFT     = 0; // 草稿
}

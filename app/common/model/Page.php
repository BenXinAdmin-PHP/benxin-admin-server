<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 页面 bx_page（通用页面搭建 schema 模型，M6-B）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// | @updated   2026-06-21 10:00:00（C2 ADR-26：seo 加入 JSON cast）
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 页面模型（ADR-21 单表 JSON）。
 * blocks 为整页区块有序数组、seo 为页面级 SEO 对象（C2 ADR-26），JSON 读出为关联数组、写入自动序列化。
 * seo 不入 $hidden（对外字段）：admin 详情返原始 i18n 对象供搭建器编辑，公开渲染走 renderBySlug 白名单解析。
 * $hidden 收口（M3-G sweep）：deleted_at / tenant_id 不外露；create_by/create_dept
 * 在渲染接口层另行白名单剔除。
 */
class Page extends BxModel
{
    protected $name = 'page';

    // blocks / seo 作为 JSON 字段：读为数组、写自动 json_encode
    protected $json = ['blocks', 'seo'];

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

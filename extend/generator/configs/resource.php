<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 素材(bx_resource)主表模块元数据（M-素材-A 吃狗粮，纯 CRUD）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:06:31
// +----------------------------------------------------------------------
//
// 纯 CRUD 标的（列表/查询/详情/单删 走生成器；上传/取流/批量删为手工槽，见 ResourceService）。
// ★只读型模块：素材经【上传手工槽】落库，物理字段（storage/path/url/file_name/original_name/
//   ext/mime/size/hash）+ media_type（按 finfo MIME+ext 自动归类，非手选）+ VOD 字段
//   （vod_media_id/transcode_status，ADR-19 预留）全部 readonly:true ——
//   排除 FILLABLE（防批量赋值越权，§8）+ 表单不渲染；列表仍按需展示。
//   生成的 save/update 仅作用于可写白名单 {category_id, name}（重命名/改分类），create 表单态备用。
// 查询条件：category_id(精确) + media_type(精确) + name(keyword 模糊)。
// M3-E：listOrder created_at desc；menuDir/menuPath 挂「素材管理」目录（与 resource_category 同目录）。
// 吃狗粮反馈见完成报告「狗粮反馈」小节（tenant_id 列表白名单缺口走一次性手工槽 model $hidden）。

return [
    'name'   => 'Resource',
    'plural' => 'resources',
    'cn'     => '素材',
    'perm'   => 'system:resource',

    // 列表排序（M3-E listOrder）：最新优先
    'listOrder' => ['created_at' => 'desc', 'id' => 'desc'],

    // seeder 菜单挂载（M3-E menuDir/menuPath）：「素材管理」目录，不存在自动建
    'menuDir'  => ['name' => 'Resource', 'title' => '素材管理', 'path' => '/resource', 'icon' => 'picture', 'sort' => 3],
    'menuPath' => '/resource/list',
    'menuIcon' => 'picture',
    'menuSort' => 2,

    'fields' => [
        'category_id' => [
            'search' => 'exact',
            'rule'   => 'integer|egt:0',
            'messages' => ['integer' => '所属分类非法'],
            'front'  => ['column' => false],
        ],
        'name' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:255',
            'messages'        => [
                'require' => '请输入素材名称',
                'max'     => '素材名称最长 255 字符',
            ],
            'front' => ['column' => ['minWidth' => 200, 'showOverflowTooltip' => true]],
        ],
        'media_type' => [
            'readonly' => true,
            'search'   => 'exact',
            'rule'     => 'in:image,video,audio,document,archive',
            'messages' => ['in' => '媒体类型非法'],
            'front'    => [
                'label'  => '类型',
                'column' => ['width' => 90, 'align' => 'center'],
            ],
        ],
        'storage' => [
            'readonly' => true,
            'rule'     => 'max:16',
            'front'    => [
                'label'  => '存储',
                'column' => ['width' => 90, 'align' => 'center'],
            ],
        ],
        'path' => [
            'readonly' => true,
            'rule'     => 'max:500',
            'front'    => ['column' => false],
        ],
        'url' => [
            'readonly' => true,
            'rule'     => 'max:500',
            'front'    => ['column' => false],
        ],
        'file_name' => [
            'readonly' => true,
            'rule'     => 'max:128',
            'front'    => ['column' => false],
        ],
        'original_name' => [
            'readonly' => true,
            'rule'     => 'max:255',
            'front'    => ['column' => false],
        ],
        'ext' => [
            'readonly' => true,
            'rule'     => 'max:16',
            'front'    => ['column' => false],
        ],
        'mime' => [
            'readonly' => true,
            'rule'     => 'max:128',
            'front'    => ['column' => false],
        ],
        'size' => [
            'readonly' => true,
            'rule'     => 'integer|egt:0',
            'front'    => [
                'label'  => '大小(字节)',
                'column' => ['width' => 110, 'align' => 'center'],
            ],
        ],
        'hash' => [
            'readonly' => true,
            'rule'     => 'max:64',
            'front'    => ['column' => false],
        ],
        'vod_media_id' => [
            'readonly' => true,
            'rule'     => 'max:128',
            'front'    => ['column' => false],
        ],
        'transcode_status' => [
            'readonly' => true,
            'rule'     => 'in:0,1,2,3,4',
            'messages' => ['in' => '转码态非法'],
            'front'    => [
                'label'  => '转码态',
                'column' => ['width' => 90, 'align' => 'center'],
            ],
        ],
    ],

    'front' => [
        'keywordPlaceholder' => '素材名称模糊查询',
        'columnOrder'        => ['name', 'media_type', 'size', 'storage', 'transcode_status'],
    ],
];

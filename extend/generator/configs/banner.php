<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 广告位(bx_banner)模块元数据（M4-A 吃狗粮首发，daterange 检验标的）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// | @updated   2026-06-12 18:00:00
// +----------------------------------------------------------------------
//
// M3-E 回炉后改用新声明（M4-A 手工接线 → 生成器复刻）：
//   ① image：image: true → 前端 XUpload 槽 + 列表 AuthImg 插槽列（宽度等仍走 front.column）。
//   ② menuDir/menuPath/menuIcon/menuSort：seeder 挂「内容管理」目录。
//   ③ start_at/end_at 用 form type=datetime（M4-A XFormDrawer 新增控件类型）。
// 永久手工槽（生成器不通用化，接线见 M4-A 手工产物 + web 仓 docs/CRUD-SCHEMA.md §7）：
//   生效区间为「双字段交集」语义（start_at/end_at 跨字段），非单字段 between——
//   前端搜索项 prop=effective daterange + 后端 BannerService/控制器区间条件均保持手工；
//   单字段区间已回炉为 search: 'daterange'（本模块不适用，故不声明）。

return [
    'name'   => 'Banner',
    'plural' => 'banners',
    'cn'     => '广告位',
    'perm'   => 'content:banner',

    // seeder 菜单挂载（M3-E menuDir/menuPath）：「内容管理」目录，不存在自动建
    'menuDir'  => ['name' => 'Content', 'title' => '内容管理', 'path' => '/content', 'icon' => 'document', 'sort' => 2],
    'menuPath' => '/content/banner',
    'menuIcon' => 'picture',
    'menuSort' => 3,

    'fields' => [
        'title' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:128',
            'messages'        => [
                'require' => '请输入标题',
                'max'     => '标题最长 128 字符',
            ],
            'front' => ['column' => ['minWidth' => 160, 'showOverflowTooltip' => true]],
        ],
        'image' => [
            'image'           => true,
            'create_required' => true,
            'rule'            => 'require|max:255',
            'messages'        => [
                'require' => '请上传图片',
                'max'     => '图片地址最长 255 字符',
            ],
            'front' => [
                'column' => ['width' => 110, 'align' => 'center'],
            ],
        ],
        'link' => [
            'rule'  => 'max:255',
            'front' => [
                'column' => false,
                'form'   => ['tip' => '点击跳转地址，可空'],
            ],
        ],
        'position' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => [
                'require' => '请输入位置标识',
                'max'     => '位置标识最长 64 字符',
            ],
            'front' => [
                'column' => ['width' => 120],
                'form'   => ['tip' => '广告位分组标识，如 home_top'],
            ],
        ],
        'sort' => [
            'rule'  => 'integer|egt:0',
            'front' => ['column' => ['width' => 70, 'align' => 'center']],
        ],
        'status' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
        'start_at' => [
            'rule'  => 'date',
            'front' => [
                'label'  => '生效开始',
                'column' => ['type' => 'time', 'width' => 170],
                'form'   => ['type' => 'datetime', 'tip' => '可空；为空表示立即生效'],
            ],
        ],
        'end_at' => [
            'rule'  => 'date',
            'front' => [
                'label'  => '生效结束',
                'column' => ['type' => 'time', 'width' => 170],
                'form'   => ['type' => 'datetime', 'tip' => '可空；为空表示长期有效'],
            ],
        ],
    ],

    'front' => [
        'keywordPlaceholder' => '标题/位置模糊查询',
        'columnOrder'        => ['title', 'image', 'position', 'status', 'start_at', 'end_at', 'sort'],
        'formOrder'          => ['title', 'image', 'link', 'position', 'sort', 'status', 'start_at', 'end_at'],
        // 永久手工槽：生效区间双字段交集搜索（prop=effective）的接线在 M4-A 手工产物，
        // 此处不留 TODO 项（手工产物即权威），约定见 config 头注释
    ],
];

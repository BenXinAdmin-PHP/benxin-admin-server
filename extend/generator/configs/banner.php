<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 广告位(bx_banner)模块元数据（M4-A 吃狗粮首发，daterange 检验标的）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// +----------------------------------------------------------------------
//
// 生成器现有可声明属性覆盖不到的项（M4-A 吃狗粮缺口，详见完成报告归档）：
//   ① image 图片：form=false → 前端 XUpload 手工槽（回炉候选 image: true）；
//      列表用 column type=slot（XTable 既有插槽列，页面手工提供 #image 预览模板）。
//   ② 生效区间 daterange 搜索：生成器 search 仅 keyword/exact，不支持区间
//      （回炉候选 search: 'daterange'）→ 前端搜索项 + 后端区间条件均生成后手工补。
//   ③ position 为字符串精确值，但生成器 exact 搜索强转 (int) → 只能并入 keyword 模糊
//      （回炉候选：exact 按字段类型分流 int/string）。
//   ④ start_at/end_at 用 form type=datetime（XFormDrawer A-2 新增控件类型，config 可声明）。

return [
    'name'   => 'Banner',
    'plural' => 'banners',
    'cn'     => '广告位',
    'perm'   => 'content:banner',
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
            'create_required' => true,
            'rule'            => 'require|max:255',
            'messages'        => [
                'require' => '请上传图片',
                'max'     => '图片地址最长 255 字符',
            ],
            'front' => [
                'column' => ['type' => 'slot', 'width' => 110, 'align' => 'center'],
                'form'   => false,
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
        'formOrder'          => ['title', 'link', 'position', 'sort', 'status', 'start_at', 'end_at'],
        'formManualSlots' => [
            [
                'after' => 'title',
                'note'  => 'image 广告图 XUpload 单图（必填，M4-A 黄金样板组件）+ 列表 #image 插槽 el-image 预览，见 web 仓 docs/CRUD-SCHEMA.md §7',
            ],
            [
                'after' => 'end_at',
                'note'  => '搜索区生效区间 daterange（prop=effective）→ 后端 list 过滤「与所选区间有交集」（start_at <= 区间末 且 end_at 为空或 >= 区间起），生成器暂不支持区间搜索（回炉候选）',
            ],
        ],
    ],
];

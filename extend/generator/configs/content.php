<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 内容(bx_content)模块元数据（M4-A 吃狗粮首发，富文本/图片手工槽标的）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// | @updated   2026-06-12 18:00:00
// +----------------------------------------------------------------------
//
// 首个真实业务字段标的。M3-E 回炉后改用新声明（M4-A 手工接线 → 生成器复刻）：
//   ① content：richtext: true → 后端 Service 自动注入 HtmlPurifier 净化 + 前端 XEditor 槽，
//      列表列默认跳过。
//   ② cover：image: true → 前端 XUpload 槽（column=false 故无 AuthImg 列）。
//   ③ view_count：readonly: true → 排除 fillable（服务端维护）+ 表单不渲染，列表仍显示。
//   ④ listOrder：is_top desc 置顶先行。
//   ⑤ menuDir/menuPath/menuIcon/menuSort：seeder 挂「内容管理」目录（不存在自动建）。
//   ⑥ publish_at 用 form type=datetime（M4-A XFormDrawer 新增控件类型）。
// 永久手工槽（生成器不通用化，接线约定见 web 仓 docs/CRUD-SCHEMA.md §7）：
//   category_id 跨模块分类树 treeSelect / 搜索 select 远程选项（数据源函数无法声明）。

return [
    'name'   => 'Content',
    'plural' => 'contents',
    'cn'     => '内容',
    'perm'   => 'content:info',

    // 列表排序（M3-E listOrder）：置顶优先
    'listOrder' => ['is_top' => 'desc', 'sort' => 'asc', 'id' => 'asc'],

    // seeder 菜单挂载（M3-E menuDir/menuPath）：「内容管理」目录，不存在自动建
    'menuDir'  => ['name' => 'Content', 'title' => '内容管理', 'path' => '/content', 'icon' => 'document', 'sort' => 2],
    'menuPath' => '/content/info',
    'menuIcon' => 'tickets',
    'menuSort' => 2,

    'fields' => [
        'category_id' => [
            'search'          => 'exact',
            'create_required' => true,
            'rule'            => 'require|integer|gt:0',
            'messages'        => [
                'require' => '请选择所属分类',
                'integer' => '所属分类非法',
                'gt'      => '所属分类非法',
            ],
            'front' => [
                'column' => false,
                'form'   => false,
            ],
        ],
        'title' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:200',
            'messages'        => [
                'require' => '请输入标题',
                'max'     => '标题最长 200 字符',
            ],
            'front' => ['column' => ['minWidth' => 220, 'showOverflowTooltip' => true]],
        ],
        'cover' => [
            'image' => true,
            'rule'  => 'max:255',
            'front' => ['column' => false],
        ],
        'summary' => [
            'rule'  => 'max:500',
            'front' => ['column' => false],
        ],
        'content' => [
            'richtext'        => true,
            'create_required' => true,
            'rule'            => 'require',
            'messages'        => ['require' => '请输入正文'],
        ],
        'author' => [
            'rule'  => 'max:64',
            'front' => ['column' => ['width' => 100]],
        ],
        'source' => [
            'rule'  => 'max:128',
            'front' => ['column' => false],
        ],
        'status' => [
            'rule'     => 'in:0,1,2',
            'messages' => ['in' => '状态非法（0草稿/1已发布/2已下架）'],
            'front'    => [
                'tsDoc'  => true,
                'search' => ['dict' => 'sys_content_status'],
                'column' => ['type' => 'dictTag', 'dict' => 'sys_content_status', 'width' => 90],
                'form'   => ['type' => 'select', 'dict' => 'sys_content_status', 'defaultValue' => 0],
            ],
        ],
        'is_top' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '置顶标记非法'],
            'front'    => [
                'label'  => '置顶',
                'column' => ['type' => 'dictTag', 'dict' => 'sys_yes_no', 'width' => 70, 'align' => 'center'],
                'form'   => ['type' => 'switch'],
            ],
        ],
        'sort' => [
            'rule'  => 'integer|egt:0',
            'front' => ['column' => ['width' => 70, 'align' => 'center']],
        ],
        'view_count' => [
            'readonly' => true,
            'rule'     => 'integer|egt:0',
            'front'    => [
                'label'  => '浏览量',
                'tsDoc'  => '浏览量（服务端维护，只读）',
                'column' => ['width' => 90, 'align' => 'center'],
            ],
        ],
        'publish_at' => [
            'rule'  => 'date',
            'front' => [
                'column' => ['type' => 'time', 'width' => 170],
                'form'   => ['type' => 'datetime', 'tip' => '可空；为空表示未定发布时间'],
            ],
        ],
    ],

    'front' => [
        'keywordPlaceholder' => '标题模糊查询',
        'columnOrder'        => ['title', 'status', 'is_top', 'author', 'view_count', 'sort', 'publish_at'],
        'formOrder'          => ['title', 'cover', 'summary', 'content', 'author', 'source', 'status', 'is_top', 'sort', 'publish_at'],
        // 跨模块数据源不通用化（同 role data_scope=5 边界），留 TODO 手工槽
        'formManualSlots' => [
            [
                'after' => 'title',
                'note'  => 'category_id 所属分类 treeSelect（跨模块数据源 getContentCategoryTree，必填）+ 搜索区分类 select 远程选项，见 web 仓 docs/CRUD-SCHEMA.md §7',
            ],
        ],
    ],
];

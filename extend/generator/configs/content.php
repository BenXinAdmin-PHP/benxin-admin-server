<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 内容(bx_content)模块元数据（M4-A 吃狗粮首发，富文本/图片手工槽标的）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// +----------------------------------------------------------------------
//
// 首个真实业务字段标的。生成器现有可声明属性覆盖不到的项（M4-A 吃狗粮缺口，详见完成报告归档）：
//   ① content 富文本：form=false 跳过自动控件 → 前端 XEditor 手工槽；后端净化（HtmlPurifier）
//      生成后手工接入 ContentService（回炉候选 richtext: true）。
//   ② cover 图片：form=false → 前端 XUpload 手工槽（回炉候选 image: true）。
//   ③ category_id 表单需跨模块分类树 treeSelect（数据源函数无法声明）→ form=false 手工槽。
//   ④ view_count 只读：form=false 挡住表单，但仍进 fillable → 生成后 Service 手工剔除
//      （回炉候选 readonly: true）。
//   ⑤ publish_at 用 form type=datetime（XFormDrawer A-2 新增控件类型，config 可声明）。

return [
    'name'   => 'Content',
    'plural' => 'contents',
    'cn'     => '内容',
    'perm'   => 'content:info',
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
            'rule'  => 'max:255',
            'front' => [
                'column' => false,
                'form'   => false,
            ],
        ],
        'summary' => [
            'rule'  => 'max:500',
            'front' => ['column' => false],
        ],
        'content' => [
            'create_required' => true,
            'rule'            => 'require',
            'messages'        => ['require' => '请输入正文'],
            'front'           => [
                'column' => false,
                'form'   => false,
            ],
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
            'rule'  => 'integer|egt:0',
            'front' => [
                'label'  => '浏览量',
                'tsDoc'  => '浏览量（服务端维护，只读）',
                'column' => ['width' => 90, 'align' => 'center'],
                'form'   => false,
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
        'formOrder'          => ['title', 'summary', 'author', 'source', 'status', 'is_top', 'sort', 'publish_at'],
        // 复杂控件/跨模块数据源不强行通用化（同 role data_scope=5 边界），留 TODO 手工槽
        'formManualSlots' => [
            [
                'after' => 'title',
                'note'  => 'category_id 所属分类 treeSelect（跨模块数据源 getContentCategoryTree，必填）+ cover 封面图 XUpload 单图（M4-A 黄金样板组件），见 web 仓 docs/CRUD-SCHEMA.md §7',
            ],
            [
                'after' => 'summary',
                'note'  => 'content 富文本正文 XEditor（wangEditor v5，必填；后端 HtmlPurifier 二次净化），见 web 仓 docs/CRUD-SCHEMA.md §7',
            ],
        ],
    ],
];

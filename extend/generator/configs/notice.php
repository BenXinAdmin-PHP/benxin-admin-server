<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 系统公告(bx_notice)模块元数据（M4-D D-2，★richtext 红利实证）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------
//
// ★吃 M3-E 红利闭环实证：content 声明 richtext:true →
//   生成器自动注入后端 HtmlPurifier::clean（净化防 XSS）+ 前端 XEditor 槽，
//   零手工接线，与 M4-A 手写 ContentService 行为等效。
// menuDir=消息管理：seeder 挂目录不写死 System（D-1 MessageMenuSeeder 已建，find-or-create 复用）。

return [
    'name'   => 'Notice',
    'plural' => 'notices',
    'cn'     => '系统公告',
    'perm'   => 'system:notice',

    // 置顶优先排序（M3-E listOrder）
    'listOrder' => ['is_top' => 'desc', 'sort' => 'asc', 'id' => 'desc'],

    'menuDir'  => ['name' => 'Message', 'title' => '消息管理', 'path' => '/message', 'icon' => 'chat-dot-round', 'sort' => 3],
    'menuPath' => '/message/notice',
    'menuIcon' => 'bell',
    'menuSort' => 2,

    'fields' => [
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
        'type' => [
            'search'          => 'exact',
            'create_required' => true,
            'rule'            => 'require|in:1,2',
            'messages'        => ['require' => '请选择类型', 'in' => '类型非法（1通知/2公告）'],
            'front'           => [
                'search' => ['dict' => 'sys_notice_type'],
                'column' => ['type' => 'dictTag', 'dict' => 'sys_notice_type', 'width' => 90],
                'form'   => ['type' => 'select', 'dict' => 'sys_notice_type', 'defaultValue' => 1],
            ],
        ],
        'content' => [
            'richtext'        => true,
            'create_required' => true,
            'rule'            => 'require',
            'messages'        => ['require' => '请输入正文'],
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
        'columnOrder'        => ['title', 'type', 'status', 'is_top', 'sort', 'publish_at'],
        'formOrder'          => ['title', 'type', 'content', 'status', 'is_top', 'sort', 'publish_at'],
    ],
];

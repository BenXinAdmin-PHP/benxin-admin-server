<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 内容分类(bx_content_category)树形模块元数据（M4-A 吃狗粮首发）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// | @updated   2026-06-12 18:00:00
// +----------------------------------------------------------------------
//
// 首个「非系统管理」树形标的：复刻 dept/menu 范式（有子拒删内建 + 绑定拒删声明）。
// 层级浅 → 子树策略 memory；有内容挂靠拒删（bx_content 软删表 → 走 Content Model 计数，不含已删行）。
// M3-E：menuDir/menuPath 声明 seeder 挂「内容管理」目录（不存在自动建）。

return [
    'name'            => 'ContentCategory',
    'plural'          => 'content-categories',
    'cn'              => '内容分类',
    'perm'            => 'content:category',
    'subtreeStrategy' => 'memory',

    // seeder 菜单挂载（M3-E menuDir/menuPath）
    'menuDir'  => ['name' => 'Content', 'title' => '内容管理', 'path' => '/content', 'icon' => 'document', 'sort' => 2],
    'menuPath' => '/content/category',
    'menuIcon' => 'folder-opened',
    'menuSort' => 1,

    // 绑定拒删：分类下仍有内容挂靠（bx_content.category_id）→ 422
    'deleteBindingGuards' => [
        ['table' => 'bx_content', 'fk' => 'category_id', 'cn' => '内容', 'model' => 'Content'],
    ],
    'fields' => [
        'parent_id' => [
            'rule'  => 'integer|egt:0',
            'front' => ['column' => false],
        ],
        'name' => [
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => [
                'require' => '请输入分类名称',
                'max'     => '分类名称最长 64 字符',
            ],
            'front' => ['column' => ['minWidth' => 180]],
        ],
        'sort'   => [
            'rule'  => 'integer|egt:0',
            'front' => ['column' => ['width' => 70, 'align' => 'center']],
        ],
        'status' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
        'icon'   => [
            'rule'  => 'max:128',
            'front' => [
                'column' => false,
                'form'   => ['tip' => 'Element Plus Icons 图标名，可空'],
            ],
        ],
    ],

    'front' => [
        'formOrder' => ['parent_id', 'name', 'icon', 'sort', 'status'],
    ],
];

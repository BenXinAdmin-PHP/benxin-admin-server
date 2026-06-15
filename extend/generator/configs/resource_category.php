<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 素材分类(bx_resource_category)树形模块元数据（M-素材-A 吃狗粮）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:06:31
// +----------------------------------------------------------------------
//
// 树形标的：复刻 dept/menu/content_category 范式（有子拒删内建 + 绑定拒删声明）。
// 层级浅 → 子树策略 memory；分类下有素材挂靠拒删（bx_resource 软删表 → 走 Resource Model 计数，不含已删行）。
// M3-E：menuDir/menuPath 声明 seeder 挂「素材管理」目录（不存在自动建，与 resource.php 同一目录）。
// M3-F：父级 treeSelect label=name（节点显示字段≠title，FrontendGenerator 自动补 treeProps:{label:'name'}）。

return [
    'name'            => 'ResourceCategory',
    'plural'          => 'resource-categories',
    'cn'              => '素材分类',
    'perm'            => 'system:resource:category',
    'subtreeStrategy' => 'memory',

    // seeder 菜单挂载（M3-E menuDir/menuPath）：「素材管理」目录，不存在自动建
    'menuDir'  => ['name' => 'Resource', 'title' => '素材管理', 'path' => '/resource', 'icon' => 'picture', 'sort' => 3],
    'menuPath' => '/resource/category',
    'menuIcon' => 'folder-opened',
    'menuSort' => 1,

    // 绑定拒删：分类下仍有素材挂靠（bx_resource.category_id）→ 422
    'deleteBindingGuards' => [
        ['table' => 'bx_resource', 'fk' => 'category_id', 'cn' => '素材', 'model' => 'Resource'],
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
        'sort' => [
            'rule'  => 'integer|egt:0',
            'front' => ['column' => ['width' => 70, 'align' => 'center']],
        ],
        'status' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
        'remark' => [
            'rule'  => 'max:255',
            'front' => ['column' => false],
        ],
    ],

    'front' => [
        'formOrder' => ['parent_id', 'name', 'sort', 'status', 'remark'],
    ],
];

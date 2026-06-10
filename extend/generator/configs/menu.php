<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 菜单(bx_menu)树形模块元数据样例（memory 子树策略）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------
//
// menu 用 memory：防环走内存 collectDescendants（无 CTE）。
// type/perms/component/icon/visible 等菜单专属字段一律走普通字段元数据，不写死。
// 注：菜单按钮约束(normalizeByType)、perms 唯一校验属菜单业务，非树形范式，本步不生成（已知 diff）。

return [
    'name'            => 'Menu',
    'plural'          => 'menus',
    'cn'              => '菜单',
    'perm'            => 'system:menu',
    'subtreeStrategy' => 'memory',
    'fields' => [
        'parent_id' => ['rule' => 'integer|egt:0'],
        'type'      => [
            'create_required' => true,
            'rule'            => 'require|in:1,2,3',
            'messages'        => [
                'require' => '请选择菜单类型',
                'in'      => '菜单类型非法（1目录/2菜单/3按钮）',
            ],
        ],
        'name'  => ['rule' => 'max:64'],
        'title' => [
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => [
                'require' => '请输入菜单标题',
                'max'     => '标题最长 64 字符',
            ],
        ],
        'path'      => ['rule' => 'max:191'],
        'component' => ['rule' => 'max:191'],
        'perms'     => ['rule' => 'max:128'],
        'icon'      => ['rule' => 'max:64'],
        'sort'      => ['rule' => 'integer|egt:0'],
        'status'    => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
        'visible' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '显示状态非法'],
        ],
    ],
];

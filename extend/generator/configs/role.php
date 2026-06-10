<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 角色(bx_role)授权链路模块元数据样例（M3-C 主标的）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 16:00:00
// +----------------------------------------------------------------------
//
// M3-C 授权链路键（缺省均不生成对应块）：
//   deleteBindingGuards 绑定拒删：count > 0 → 422（model 给 Model 名时计数不含软删行）
//   deleteCascade       删除级联：事务内清关系表 + casbin（removeAllForRole 按本行 sub 清策略，finally reload）
//   relationEndpoints   分配关系接口：GET /:id/<name> 回显 + PUT /:id/<name> 覆盖式分配 + casbinSync 整单回滚
//   protectedRows       受保护行：matchField==matchValue 且动作 ∈ denyActions → 422
//                       （预留变体：matchField '__currentUser' → admin 自我保护，本步不落地）

return [
    'name'   => 'Role',
    'plural' => 'roles',
    'cn'     => '角色',
    'perm'   => 'system:role',
    'fields' => [
        'name' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => ['require' => '请输入角色名称'],
        ],
        'code' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:64|alphaDash',
            'messages'        => [
                'require'   => '请输入角色标识',
                'alphaDash' => '角色标识只能含字母、数字、下划线和短横线',
            ],
        ],
        'sort'       => ['rule' => 'integer|egt:0'],
        'status'     => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
        'data_scope' => ['rule' => 'in:1,2,3,4,5'],
        'remark'     => ['rule' => 'max:255'],
    ],

    // 绑定拒删：仍有管理员绑定该角色 → 422
    'deleteBindingGuards' => [
        ['table' => 'bx_admin_role', 'fk' => 'role_id', 'cn' => '管理员'],
    ],

    // 删除级联：事务内清 role_menu/role_dept + removeAllForRole(code, tenant_id)，finally reload
    'deleteCascade' => [
        [
            'relationTable' => 'bx_role_menu',
            'fk'            => 'role_id',
            'casbin'        => [
                'removeAllForRole' => true,
                'subField'         => 'code',
                'domainField'      => 'tenant_id',
            ],
        ],
        ['relationTable' => 'bx_role_dept', 'fk' => 'role_id'],
    ],

    // 分配菜单：PUT roles/:id/menus（perm 以手写为准——复用 system:role:update，与 seeder 一一对应）
    'relationEndpoints' => [
        [
            'name'          => 'menus',
            'cn'            => '菜单',
            'relationTable' => 'bx_role_menu',
            'selfFk'        => 'role_id',
            'targetTable'   => 'bx_menu',
            'targetFk'      => 'menu_id',
            'perm'          => 'system:role:update',
            'casbinSync'    => [
                'enabled'     => true,
                'subField'    => 'code',
                'permSource'  => 'perms',
                'act'         => 'do',
                'domainField' => 'tenant_id',
            ],
        ],
    ],

    // 受保护行：super_admin 不可删 / 停 / 改 code / 分配菜单
    'protectedRows' => [
        [
            'matchField'  => 'code',
            'matchValue'  => 'super_admin',
            'denyActions' => ['delete', 'disable', 'changeCode', 'assign'],
            'cn'          => '超级管理员',
        ],
    ],
];

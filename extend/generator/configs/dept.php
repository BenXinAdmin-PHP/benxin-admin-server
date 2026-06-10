<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 部门(bx_dept)树形模块元数据样例（cte 子树策略）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------
//
// 树形键：tree(自动推导可省) / parentField / sortField / subtreeStrategy(memory|cte) / treeDeleteGuard。
// dept 用 cte：生成 descendantIds 递归 CTE，供防环 + ADR-9 数据权限"本部门及以下"复用。

return [
    'name'            => 'Dept',
    'plural'          => 'depts',
    'cn'              => '部门',
    'perm'            => 'system:dept',
    'subtreeStrategy' => 'cte',
    'fields' => [
        'parent_id' => ['rule' => 'integer|egt:0'],
        'name'      => [
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => [
                'require' => '请输入部门名称',
                'max'     => '部门名称最长 64 字符',
            ],
        ],
        'leader' => ['rule' => 'max:64'],
        'phone'  => ['rule' => 'max:20'],
        'email'  => [
            'rule'     => 'email|max:128',
            'messages' => ['email' => '邮箱格式不正确'],
        ],
        'sort'   => ['rule' => 'integer|egt:0'],
        'status' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
    ],
];

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 菜单(bx_menu)树形模块元数据样例（memory 子树策略）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// | @updated   2026-06-12 10:00:00
// +----------------------------------------------------------------------
//
// menu 用 memory：防环走内存 collectDescendants（无 CTE）。
// type/perms/component/icon/visible 等菜单专属字段一律走普通字段元数据，不写死。
// M3-C：deleteCascade 删除级联（事务清 bx_role_menu + casbin 按 perm 删 → 事务外 reload）；
//        perms 走可空唯一变体（unique+nullable+uniqueScope=active：非空才校验、不含 withTrashed）。
// 注：菜单按钮约束(normalizeByType)/TYPE_* 常量属菜单专有业务，非范式，永久不生成（已知 diff，PM 定）。

return [
    'name'            => 'Menu',
    'plural'          => 'menus',
    'cn'              => '菜单',
    'perm'            => 'system:menu',
    'subtreeStrategy' => 'memory',

    // 删除级联：事务内清 role_menu + 按本行 perms 清 casbin 策略（避免悬空授权），事务外 reload
    'deleteCascade' => [
        [
            'relationTable' => 'bx_role_menu',
            'fk'            => 'menu_id',
            'cn'            => '角色-菜单关联',
            'casbin'        => [
                'removeByPerm' => true,
                'permField'    => 'perms',
            ],
        ],
    ],
    'fields' => [
        'parent_id' => [
            'rule'  => 'integer|egt:0',
            'front' => ['column' => false],
        ],
        'type'      => [
            'create_required' => true,
            'rule'            => 'require|in:1,2,3',
            'messages'        => [
                'require' => '请选择菜单类型',
                'in'      => '菜单类型非法（1目录/2菜单/3按钮）',
            ],
            'front' => [
                'tsDoc'  => true,
                'column' => ['type' => 'dictTag', 'enum' => 'TYPE_OPTIONS', 'width' => 80, 'align' => 'center'],
                'form'   => ['label' => '菜单类型', 'type' => 'radio', 'enum' => 'TYPE_OPTIONS', 'defaultValue' => 2],
            ],
        ],
        'name'  => [
            'rule'  => 'max:64',
            'front' => [
                'label'  => '路由 name',
                'tsDoc'  => '路由 name（前端用）',
                'column' => false,
                'form'   => ['visible' => 'isNav', 'tip' => '前端路由记录的 name，如 SystemRole'],
            ],
        ],
        'title' => [
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => [
                'require' => '请输入菜单标题',
                'max'     => '标题最长 64 字符',
            ],
            'front' => [
                'label'  => '标题',
                'column' => ['minWidth' => 180],
            ],
        ],
        'path'      => [
            'rule'  => 'max:191',
            'front' => [
                'column' => ['minWidth' => 130, 'showOverflowTooltip' => true],
                'form'   => ['visible' => 'isNav', 'tip' => '如 /system/role'],
            ],
        ],
        'component' => [
            'rule'  => 'max:191',
            'front' => [
                'column' => ['label' => '组件', 'minWidth' => 160, 'showOverflowTooltip' => true],
                'form'   => ['label' => '组件路径', 'visible' => 'isMenu', 'tip' => 'src/views/ 下的组件路径，如 system/role/index'],
            ],
        ],
        'perms'     => [
            'rule'        => 'max:128',
            'unique'      => true,
            'nullable'    => true,
            'uniqueScope' => 'active',
            'label'       => '权限标识',
            'front'       => [
                'tsDoc'  => '权限标识，如 system:role:list（按钮类必填）',
                'column' => ['minWidth' => 170, 'showOverflowTooltip' => true],
                'form'   => ['required' => true, 'visible' => 'isButton', 'tip' => '与 Casbin enforce 同源，如 system:role:list；启用态唯一'],
            ],
        ],
        'icon'      => [
            'rule'  => 'max:64',
            'front' => [
                'column' => false,
                'form'   => ['visible' => 'isNav', 'tip' => 'Element Plus Icons 图标名'],
            ],
        ],
        'sort'      => [
            'rule'  => 'integer|egt:0',
            'front' => ['column' => ['width' => 70, 'align' => 'center']],
        ],
        'status'    => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
        'visible' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '显示状态非法'],
            'front'    => [
                'tsDoc'  => true,
                'column' => false,
                'form'   => ['type' => 'switch', 'visible' => 'isNav', 'tip' => '隐藏后不出现在侧边菜单，路由仍可达'],
            ],
        ],
    ],

    // 前端产物声明（M3-D1）：type 静态枚举 + 声明式联动钩子（visible(form) 一元判式）+ 叶子护栏
    'front' => [
        'enums' => [
            'TYPE_OPTIONS' => [
                'doc'     => '菜单类型（与后端 1目录 2菜单 3按钮 对齐；静态枚举不走字典）',
                'options' => [
                    ['label' => '目录', 'value' => 1, 'tagType' => 'warning'],
                    ['label' => '菜单', 'value' => 2, 'tagType' => 'primary'],
                    ['label' => '按钮', 'value' => 3, 'tagType' => 'info'],
                ],
            ],
        ],
        // 声明式联动：可推导的显隐钩子生成 visible(form) 一元判式；
        // type 完整规整（normalizeByType 同级业务）不复刻，由后端兜底
        'visibility' => [
            'doc' => [
                '★ 进阶范式（模块特化）：type 目录/菜单/按钮切换时字段显隐联动，',
                '隐藏字段不参与提交，后端 normalizeByType 兜底归一化。',
            ],
            'hooks' => [
                'isNav'    => ['field' => 'type', 'op' => '!==', 'value' => 3, 'cn' => '目录/菜单'],
                'isMenu'   => ['field' => 'type', 'op' => '===', 'value' => 2],
                'isButton' => ['field' => 'type', 'op' => '===', 'value' => 3],
            ],
        ],
        // 叶子类型护栏：行内「新增下级」隐藏 + 父级树过滤（按钮不可挂子级/作父级）
        'treeLeafGuard' => ['field' => 'type', 'value' => 3, 'cn' => '按钮'],
        'actionsWidth'  => 200,
        'columnOrder'   => ['title', 'type', 'path', 'component', 'perms', 'sort', 'status'],
        'formOrder'     => ['parent_id', 'type', 'title', 'name', 'path', 'component', 'perms', 'icon', 'sort', 'status', 'visible'],
    ],
];

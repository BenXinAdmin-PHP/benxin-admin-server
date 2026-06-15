<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 岗位(bx_post)模块元数据样例（保真验证用）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// | @updated   2026-06-10 18:00:00
// +----------------------------------------------------------------------
//
// 字段级属性（缺省按表结构推导）：
//   search          查询条件：none | keyword(模糊) | exact(精确)
//   create_required 新增必填
//   update_editable 更新可改（缺省 true）
//   sensitive       敏感字段（入库加密 / 列表隐藏，本步 post 无）
//   rule            校验规则覆盖（缺省按字段类型 + 必填推导）
//   messages        自定义校验消息（rule => msg）

return [
    'name'   => 'Post',
    'plural' => 'posts',
    'cn'     => '岗位',
    'perm'   => 'system:post',

    // 绑定拒删（M3-C 收尾）：岗位被管理员绑定（bx_admin_post 中间表，无软删，直接 count，
    // 与手写 PostService::delete 计数路径一致）→ 422
    'deleteBindingGuards' => [
        ['table' => 'bx_admin_post', 'fk' => 'post_id', 'cn' => '管理员'],
    ],
    'fields' => [
        'code' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:64|alphaDash',
            'messages'        => [
                'require'   => '请输入岗位编码',
                'alphaDash' => '岗位编码只能含字母、数字、下划线和短横线',
            ],
        ],
        'name' => [
            'search'          => 'keyword',
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => ['require' => '请输入岗位名称'],
        ],
        'sort'   => ['rule' => 'integer|egt:0'],
        'status' => [
            'search'   => 'exact',
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
        ],
        'remark' => ['rule' => 'max:255'],
    ],
];

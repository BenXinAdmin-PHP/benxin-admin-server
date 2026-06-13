<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 短信模板(bx_sms_template)模块元数据（M4-D D-2 纯 CRUD）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------
//
// 纯 CRUD（菜单挂「消息管理」目录，吃 M3-E menuDir 红利，不写死 System）。
// scene 唯一含软删（DB 唯一索引 uniq_scene → bx:make 自动识别 uniqueField，强唯一守卫，§5.1）。

return [
    'name'   => 'SmsTemplate',
    'plural' => 'sms-templates',
    'cn'     => '短信模板',
    'perm'   => 'system:sms:template',

    'menuDir'  => ['name' => 'Message', 'title' => '消息管理', 'path' => '/message', 'icon' => 'chat-dot-round', 'sort' => 3],
    'menuPath' => '/message/sms-template',
    'menuIcon' => 'message-box',
    'menuSort' => 1,

    'fields' => [
        'scene' => [
            'search'          => 'keyword',
            'create_required' => true,
            'label'           => '场景标识',
            'rule'            => 'require|max:32',
            'messages'        => [
                'require' => '请输入场景标识',
                'max'     => '场景标识最长 32 字符',
            ],
            'front' => ['column' => ['width' => 140]],
        ],
        'channel' => [
            'search'          => 'exact',
            'create_required' => true,
            'rule'            => 'require|in:ali,tencent',
            'messages'        => ['require' => '请选择渠道', 'in' => '渠道非法（ali/tencent）'],
            'front'           => [
                'search' => ['dict' => 'sys_sms_channel'],
                'column' => ['type' => 'dictTag', 'dict' => 'sys_sms_channel', 'width' => 100],
                'form'   => ['type' => 'select', 'dict' => 'sys_sms_channel', 'defaultValue' => 'ali'],
            ],
        ],
        'template_code' => [
            'create_required' => true,
            'rule'            => 'require|max:64',
            'messages'        => ['require' => '请输入模板ID', 'max' => '模板ID最长 64 字符'],
            'front'           => [
                'label'  => '模板ID',
                'column' => ['width' => 180],
                'form'   => ['tip' => '渠道侧审核通过后的模板 ID'],
            ],
        ],
        'sign_name' => [
            'rule'  => 'max:64',
            'front' => [
                'label'  => '签名',
                'column' => ['width' => 120],
                'form'   => ['tip' => '可空，留空则用渠道默认签名'],
            ],
        ],
        'content' => [
            'rule'  => 'max:500',
            'front' => [
                'label'  => '内容参考',
                'column' => false,
                'form'   => ['type' => 'textarea', 'tip' => '内容/参数说明，仅参考，实际以渠道模板为准'],
            ],
        ],
        'status' => [
            'rule'     => 'in:0,1',
            'messages' => ['in' => '状态非法'],
            'front'    => [
                'tsDoc'  => true,
                'search' => ['dict' => 'sys_normal_disable'],
                'column' => ['type' => 'dictTag', 'dict' => 'sys_normal_disable', 'width' => 90],
                'form'   => ['type' => 'select', 'dict' => 'sys_normal_disable', 'defaultValue' => 1],
            ],
        ],
        'remark' => [
            'rule'  => 'max:255',
            'front' => ['column' => false, 'form' => ['type' => 'textarea']],
        ],
    ],

    'front' => [
        'keywordPlaceholder' => '场景标识查询',
        'columnOrder'        => ['scene', 'channel', 'template_code', 'sign_name', 'status'],
        'formOrder'          => ['scene', 'channel', 'template_code', 'sign_name', 'content', 'status', 'remark'],
    ],
];

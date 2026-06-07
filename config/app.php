<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   应用配置 — 多应用 / 默认应用 / 多租户开关
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

return [
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用（多应用模式下，URL 无应用段时使用）
    'default_app'      => 'admin',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（common 为公共层，不对外暴露）
    'deny_app_list'    => ['common'],

    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => false,

    // ------------------------------------------------------------------
    // BenXinAdmin 自定义
    // ------------------------------------------------------------------
    // 多租户开关（ADR-1，默认单租户；置 true 后启用 tenant_id/domain 维度）
    'multi_tenant'     => env('APP_MULTI_TENANT', false),
];

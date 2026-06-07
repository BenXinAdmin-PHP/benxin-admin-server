<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   全局中间件 — 请求上下文(request_id) / 跨域
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

// 全局中间件定义文件（按数组顺序执行）
return [
    // 请求入口：生成并注入全局唯一 request_id（须排在最前，供统一响应/日志读取）
    \app\common\middleware\RequestLog::class,
    // 跨域处理（含 OPTIONS 预检）
    \app\common\middleware\Cors::class,
];

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   JWT 配置 — 双 guard 独立 secret / 双令牌 TTL（ADR-8）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

return [
    // 签发者标识（iss）
    'iss'    => env('JWT_ISS', 'BenXinAdmin'),

    // 黑/白名单所用缓存 store（须为 Valkey/Redis，TTL 精确到秒）
    'store'  => 'redis',

    // 双 guard：后台 admin 与 C 端 api 各自独立 secret（≥32 字节随机）
    'guards' => [
        'admin' => [
            'secret'      => env('JWT_ADMIN_SECRET', ''),
            'access_ttl'  => (int) env('JWT_ACCESS_TTL', 7200),    // 2h
            'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1209600), // 14d
        ],
        // api guard 本步仅预留配置，接口落地见 M5
        'api'   => [
            'secret'      => env('JWT_API_SECRET', ''),
            'access_ttl'  => (int) env('JWT_ACCESS_TTL', 7200),
            'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1209600),
        ],
    ],
];

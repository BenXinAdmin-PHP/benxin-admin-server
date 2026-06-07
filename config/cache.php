<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   缓存配置 — 默认 file，预置 Valkey(Redis 协议) 连接
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动（M0 默认 file，免依赖 ext-redis；接 Valkey 后改 CACHE_DRIVER=redis）
    'default' => env('CACHE_DRIVER', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],

        // Valkey（Redis 协议兼容，ADR-7）。启用需安装 ext-redis 并把 CACHE_DRIVER 置为 redis
        'redis' => [
            'type'     => 'redis',
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', ''),
            'select'   => (int) env('REDIS_DB', 0),
            'timeout'  => 2,
            'expire'   => 0,
            'persistent' => false,
            'prefix'   => env('REDIS_PREFIX', 'bx:'),
        ],
        // 更多的缓存连接
    ],
];

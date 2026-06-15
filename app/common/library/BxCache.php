<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   缓存助手 — 读回填/写失效（Valkey），字典/参数等通用复用
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

use think\facade\Cache;

/**
 * 通用缓存助手（M2-A 字典缓存模式抽件，字典/参数配置共用）。
 * 模式：remember 读回填 + 兜底 TTL；写操作调 forget 失效。
 * 统一走 Valkey(redis) store（config('jwt.store')），key 由调用方约定（自带业务前缀）。
 */
class BxCache
{
    /**
     * 读回填：命中直接返回；未命中执行 $producer 取值并回填（带兜底 TTL）。
     *
     * @template T
     * @param string   $key
     * @param int      $ttl      秒
     * @param callable():mixed $producer 未命中时的取值闭包
     * @return mixed
     */
    public static function remember(string $key, int $ttl, callable $producer): mixed
    {
        $store  = self::store();
        $cached = $store->get($key);
        // null 视为未命中（空数组 [] 等假值仍算命中）
        if ($cached !== null) {
            return $cached;
        }

        $value = $producer();
        // 不缓存 null，避免把"未命中"写死
        if ($value !== null) {
            $store->set($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * 失效某 key。
     */
    public static function forget(string $key): void
    {
        if ($key !== '') {
            self::store()->delete($key);
        }
    }

    /**
     * 名单/缓存专用 store（Valkey/Redis）。
     */
    public static function store()
    {
        return Cache::store((string) config('jwt.store', 'redis'));
    }
}

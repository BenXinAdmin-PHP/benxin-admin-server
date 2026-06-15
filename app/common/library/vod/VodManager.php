<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   VOD 驱动工厂 — driver(vod_tx) + 阿里 VOD 扩展位 + 测试注入
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\vod;

use app\admin\service\ConfigService;
use RuntimeException;

/**
 * VOD 驱动工厂（M-素材-C，复刻 PayManager / StorageManager）。
 * - driver('vod_tx') → TencentVodProvider，配置经 ConfigService 注入（敏感 AES）。
 * - 'vod_ali' → throw + TODO 扩展位（阿里云 VOD 留上层/后续，本步不写实现，守 §1）。
 * - fake($name, $provider)：离线测试注入 FakeProvider，BxVod 编排全覆盖无需真实凭证。
 */
class VodManager
{
    /** 已实现驱动名（写入 bx_resource.storage） */
    public const DRIVERS = ['vod_tx'];

    /** @var array<string,VodInterface> 测试注入的驱动实例 */
    protected static array $fakes = [];

    /**
     * 取 VOD 驱动实例（默认腾讯 vod_tx）。
     */
    public static function driver(string $name = 'vod_tx'): VodInterface
    {
        if (isset(self::$fakes[$name])) {
            return self::$fakes[$name];
        }

        return match ($name) {
            'vod_tx'  => new TencentVodProvider(new ConfigService(app())),
            // 阿里云 VOD 扩展位（留后续/上层，不写实现）：
            'vod_ali' => throw new RuntimeException('阿里云 VOD 驱动尚未实现（留扩展位，TODO 上层/后续按需落地）'),
            default   => throw new RuntimeException("未知 VOD 驱动：{$name}"),
        };
    }

    /**
     * 注入伪驱动（测试）；传 null 清除该驱动注入。
     */
    public static function fake(string $name, ?VodInterface $driver): void
    {
        if ($driver === null) {
            unset(self::$fakes[$name]);

            return;
        }
        self::$fakes[$name] = $driver;
    }

    /**
     * 清空所有注入。
     */
    public static function flushFakes(): void
    {
        self::$fakes = [];
    }
}

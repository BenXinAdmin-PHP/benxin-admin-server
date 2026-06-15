<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   存储管理器 — 按 bx_config 驱动配置返回存储实例 + 按 media_type 真路由
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// | @updated   2026-06-15 18:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

use app\admin\service\ConfigService;
use app\common\library\vod\VodManager;
use RuntimeException;
use think\facade\Log;

/**
 * 存储驱动工厂。云驱动 AK/SK 经 ConfigService::get 取明文（敏感 AES，复用 M2-B），不落硬编码。
 *
 * - driver()/currentName()：M2-D 文件管理沿用（storage_driver 单驱动），**本步不改其行为**。
 * - forMediaType()：M-素材-B 多路存储真路由（image→qiniu / document·archive→oss / video·audio→local）。
 *   ★守 §1 铁律：全部默认 local，配置不全一律回退 local + Log::warning，绝不让默认态挂掉。
 * - fake()：离线测试注入伪驱动（复刻 PayManager::fake），mock 验路由/调用无需真实云凭据。
 */
class StorageManager
{
    /** @var array<string,StorageInterface> 测试注入的驱动实例（按驱动名 local/oss/qiniu） */
    protected static array $fakes = [];

    /**
     * 取当前驱动实例（M2-D 既有，单驱动 storage_driver；**保持原行为不动**）。
     */
    public static function driver(?string $name = null): StorageInterface
    {
        $config = new ConfigService(app());
        $name   = $name ?: (string) $config->get('storage_driver', 'local');

        return match ($name) {
            'oss'   => new OssStorage([
                'endpoint'   => (string) $config->get('oss_endpoint', ''),
                'bucket'     => (string) $config->get('oss_bucket', ''),
                'access_key' => (string) $config->get('oss_access_key', ''),
                'secret_key' => (string) $config->get('oss_secret_key', ''),
            ]),
            'qiniu' => new QiniuStorage([
                'bucket'     => (string) $config->get('qiniu_bucket', ''),
                'domain'     => (string) $config->get('qiniu_domain', ''),
                'access_key' => (string) $config->get('qiniu_access_key', ''),
                'secret_key' => (string) $config->get('qiniu_secret_key', ''),
            ]),
            default => new LocalStorage(),
        };
    }

    /**
     * 当前驱动标识（写入 bx_file.storage）。
     */
    public static function currentName(): string
    {
        return (string) (new ConfigService(app()))->get('storage_driver', 'local');
    }

    // ================= M-素材-B：多路存储真路由（ADR-18） =================

    /**
     * 按 media_type 选驱动返回存储实例（image→qiniu / document·archive→oss / video·audio→local）。
     * 解析含「未开通回退 local」防线（见 driverNameForMediaType），故返回实例恒可用。
     */
    public static function forMediaType(string $mediaType): StorageInterface
    {
        return self::makeByName(self::driverNameForMediaType($mediaType));
    }

    /**
     * 按 media_type 解析实际驱动名（写入 bx_resource.storage）。
     *
     * - image            → storage_driver_image（local|qiniu）
     * - document/archive → storage_driver_document / storage_driver_archive（local|oss）
     * - video/audio      → storage_driver_video / storage_driver_audio（local|vod_tx；M-素材-C VOD 接入）
     * - 未知             → 恒 local 兜底
     * ★未开通回退：选了云 driver 但对应必填配置为空 → 回退 local + Log::warning（守 §1，配置不全不挂）。
     */
    public static function driverNameForMediaType(string $mediaType): string
    {
        $key = match ($mediaType) {
            'image'    => 'storage_driver_image',
            'document' => 'storage_driver_document',
            'archive'  => 'storage_driver_archive',
            'video'    => 'storage_driver_video',
            'audio'    => 'storage_driver_audio',
            default    => null, // 未知 → local
        };
        if ($key === null) {
            return 'local';
        }

        $config = new ConfigService(app());
        $driver = strtolower(trim((string) $config->get($key, 'local')));
        if ($driver === '' || $driver === 'local') {
            return 'local';
        }

        if (!self::cloudConfigReady($driver, $config)) {
            Log::warning("[StorageManager] 驱动 {$driver}（media_type={$mediaType}）配置不全，回退 local");

            return 'local';
        }

        return $driver;
    }

    /**
     * 按驱动名构造实例（测试注入优先）；云驱动 AES 配置经 ConfigService 注入。
     */
    public static function makeByName(string $driverName): StorageInterface
    {
        if (isset(self::$fakes[$driverName])) {
            return self::$fakes[$driverName];
        }

        $config = new ConfigService(app());

        return match ($driverName) {
            'oss'   => new OssStorage([
                'access_key_id'     => (string) $config->get('oss_access_key_id', ''),
                'access_key_secret' => (string) $config->get('oss_access_key_secret', ''),
                'endpoint'          => (string) $config->get('oss_endpoint', ''),
                'bucket'            => (string) $config->get('oss_bucket', ''),
                'url_expire'        => (int) $config->get('oss_url_expire', 3600),
            ]),
            'qiniu' => new QiniuStorage([
                'access_key' => (string) $config->get('qiniu_access_key', ''),
                'secret_key' => (string) $config->get('qiniu_secret_key', ''),
                'bucket'     => (string) $config->get('qiniu_bucket', ''),
                'domain'     => (string) $config->get('qiniu_domain', ''),
                'url_expire' => (int) $config->get('qiniu_url_expire', 3600),
            ]),
            // 腾讯云 VOD（M-素材-C）：客户端直传 + 凭证签发型驱动，套 VodTxStorage 适配（put throw / url 播放 / delete 删媒资）
            'vod_tx' => new VodTxStorage(VodManager::driver('vod_tx')),
            // 阿里云 VOD 扩展位（留后续/上层，不写实现，守 §1）：
            'vod_ali' => throw new RuntimeException('阿里云 VOD 驱动尚未实现（留扩展位，TODO 上层/后续按需落地）'),
            // 腾讯 COS 扩展位（留 M-素材-C+，不写实现）
            'cos'   => throw new RuntimeException('腾讯 COS 驱动尚未实现（留 M-素材-C+ 扩展位）'),
            default => new LocalStorage(),
        };
    }

    /**
     * 云驱动必填配置是否齐全（缺则视为「未开通」，路由回退 local）。
     */
    protected static function cloudConfigReady(string $driver, ConfigService $config): bool
    {
        return match ($driver) {
            'oss' => (string) $config->get('oss_access_key_id', '') !== ''
                && (string) $config->get('oss_access_key_secret', '') !== ''
                && (string) $config->get('oss_endpoint', '') !== ''
                && (string) $config->get('oss_bucket', '') !== '',
            'qiniu' => (string) $config->get('qiniu_access_key', '') !== ''
                && (string) $config->get('qiniu_secret_key', '') !== ''
                && (string) $config->get('qiniu_bucket', '') !== ''
                && (string) $config->get('qiniu_domain', '') !== '',
            // 腾讯 VOD（M-素材-C）：secret_id/secret_key/sub_app_id 必填齐全才视「已开通」
            'vod_tx' => (string) $config->get('vod_tx_secret_id', '') !== ''
                && (string) $config->get('vod_tx_secret_key', '') !== ''
                && (int) $config->get('vod_tx_sub_app_id', 0) > 0,
            default => false, // 未知云驱动一律视为未就绪 → 回退 local
        };
    }

    /**
     * 注入伪驱动（离线测试）；传 null 清除该驱动注入。
     */
    public static function fake(string $driverName, ?StorageInterface $driver): void
    {
        if ($driver === null) {
            unset(self::$fakes[$driverName]);

            return;
        }
        self::$fakes[$driverName] = $driver;
    }

    /**
     * 清空所有注入。
     */
    public static function flushFakes(): void
    {
        self::$fakes = [];
    }
}

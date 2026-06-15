<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   存储管理器 — 按 bx_config 驱动配置返回存储实例
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

use app\admin\service\ConfigService;

/**
 * 存储驱动工厂。驱动名取 bx_config `storage_driver`（默认 local）；
 * 云驱动 AK/SK 经 ConfigService::get 取明文（敏感 AES，复用 M2-B），不落硬编码。
 */
class StorageManager
{
    /**
     * 取当前驱动实例。
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

    /**
     * 多路存储路由扩展点（ADR-18，M-素材-A）：按 media_type 选驱动返回存储实例。
     *
     * ★本步（M-素材-A）一律返回本地驱动 —— 纯本地零云配置即可完整跑通素材管理
     *   （守 §1 底座可独立运行、不默认强依赖任何付费云服务）。
     * 云分支留骨架（throw 未实现 + TODO 锚点），M-素材-B/C 据此落地，
     *   **不改动既有 driver() 行为（M2-D 文件管理沿用）**。
     */
    public static function forMediaType(string $mediaType): StorageInterface
    {
        $driver = self::driverNameForMediaType($mediaType);

        return match ($driver) {
            'local' => new LocalStorage(),
            // TODO M-素材-B：image→QiniuStorage / document·archive→OssStorage（按 bx_config 开通后路由）
            // TODO M-素材-C：video·audio→阿里 VOD / 腾讯 VOD（ADR-19，客户端直传 + 转码回调）
            default => throw new \RuntimeException("存储驱动 [{$driver}] 尚未实现（media_type={$mediaType}，留 M-素材-B/C）"),
        };
    }

    /**
     * 按 media_type 解析驱动名（写入 bx_resource.storage）。
     *
     * ★本步全部本地。M-素材-B/C 在此读 bx_config（group=storage）的按类型驱动开关：
     *   image→qiniu / document·archive→oss / video·audio→alivod·tencentvod（开通才启用，缺省 local）。
     */
    public static function driverNameForMediaType(string $mediaType): string
    {
        // M-素材-A：零云配置，全部本地。
        return 'local';
    }
}

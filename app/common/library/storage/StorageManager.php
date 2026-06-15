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
}

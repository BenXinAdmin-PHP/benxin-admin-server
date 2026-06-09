<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   阿里云 OSS 存储驱动（骨架，留待 M4/按需实现）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

use RuntimeException;

/**
 * 阿里云 OSS 驱动骨架。AK/SK 经 bx_config 敏感项（is_sensitive=1，AES）配置，
 * 由 StorageManager 经 ConfigService::get 取明文注入。实现留 M4/按需。
 *
 * @param array<string,mixed> $config endpoint/bucket/access_key/secret_key 等
 */
class OssStorage implements StorageInterface
{
    public function __construct(protected array $config = [])
    {
    }

    public function put(string $tmpPath, string $saveName): string
    {
        // TODO M4: 调用 OSS SDK putObject($bucket, $saveName, file)
        throw new RuntimeException('OSS 驱动尚未实现');
    }

    public function url(string $path): string
    {
        // TODO M4: 拼 OSS 公网/CDN URL 或签名 URL
        throw new RuntimeException('OSS 驱动尚未实现');
    }

    public function delete(string $path): bool
    {
        // TODO M4: 调用 OSS SDK deleteObject
        throw new RuntimeException('OSS 驱动尚未实现');
    }
}

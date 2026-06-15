<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   七牛云存储驱动（骨架，留待 M4/按需实现）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

use RuntimeException;

/**
 * 七牛云驱动骨架。AK/SK 同 OSS 经 bx_config 敏感项配置。实现留 M4/按需。
 *
 * @param array<string,mixed> $config bucket/access_key/secret_key/domain 等
 */
class QiniuStorage implements StorageInterface
{
    public function __construct(protected array $config = [])
    {
    }

    public function put(string $tmpPath, string $saveName): string
    {
        // TODO M4: 七牛 SDK 上传
        throw new RuntimeException('七牛驱动尚未实现');
    }

    public function url(string $path): string
    {
        // TODO M4: 拼七牛域名 URL
        throw new RuntimeException('七牛驱动尚未实现');
    }

    public function delete(string $path): bool
    {
        // TODO M4: 七牛 SDK 删除
        throw new RuntimeException('七牛驱动尚未实现');
    }
}

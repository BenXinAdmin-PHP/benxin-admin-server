<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   阿里云 OSS 存储驱动（M-素材-B：文档/压缩包类，私有 bucket + 签名 URL）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// | @updated   2026-06-15 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

use OSS\OssClient;
use RuntimeException;

/**
 * 阿里云 OSS 驱动（aliyuncs/oss-sdk-php ^2.7，MIT）。
 *
 * AK/SK 经 bx_config 敏感项（is_sensitive=1，AES）配置，由 StorageManager 经
 * ConfigService::get 取明文注入（不落硬编码）。**私有 bucket**：url() 实时签发带
 * deadline 的签名 URL（不裸公网直链、不缓存落库，ADR-18/§8）。
 *
 * 期望 $config 键：access_key_id / access_key_secret / endpoint / bucket / url_expire
 */
class OssStorage implements StorageInterface
{
    public function __construct(protected array $config = [])
    {
    }

    /**
     * 上传本地临时文件到 OSS，返回 object key（= 存储内 path，供 url/delete 复用）。
     */
    public function put(string $tmpPath, string $saveName): string
    {
        $key = $this->normalizeKey($saveName);
        $this->client()->uploadFile($this->bucket(), $key, $tmpPath);

        return $key;
    }

    /**
     * 私有 bucket 签名 URL（实时签发，带 url_expire 秒有效期；过期失效，故不可缓存落库）。
     */
    public function url(string $path): string
    {
        $key = $this->normalizeKey($path);

        return (string) $this->client()->signUrl($this->bucket(), $key, $this->expire());
    }

    public function delete(string $path): bool
    {
        $key = $this->normalizeKey($path);
        $this->client()->deleteObject($this->bucket(), $key);

        return true;
    }

    // ------------------------------------------------------------------

    protected function client(): OssClient
    {
        $endpoint = (string) ($this->config['endpoint'] ?? '');
        $id       = (string) ($this->config['access_key_id'] ?? '');
        $secret   = (string) ($this->config['access_key_secret'] ?? '');
        if ($endpoint === '' || $id === '' || $secret === '') {
            throw new RuntimeException('OSS 配置不完整（endpoint/access_key_id/access_key_secret）');
        }

        // 每次新建实例（无状态、轻量）；OssClient 自身校验 endpoint。
        return new OssClient($id, $secret, $endpoint);
    }

    protected function bucket(): string
    {
        $bucket = (string) ($this->config['bucket'] ?? '');
        if ($bucket === '') {
            throw new RuntimeException('OSS bucket 未配置');
        }

        return $bucket;
    }

    protected function expire(): int
    {
        $e = (int) ($this->config['url_expire'] ?? 3600);

        return $e > 0 ? $e : 3600;
    }

    /**
     * 归一化 object key：去前导斜杠、统一正斜杠（防穿越/拼接异常）。
     */
    protected function normalizeKey(string $key): string
    {
        return ltrim(str_replace('\\', '/', $key), '/');
    }
}

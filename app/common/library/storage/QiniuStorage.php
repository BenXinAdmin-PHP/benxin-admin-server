<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   七牛云存储驱动（M-素材-B：图片类，私有空间 + 私有下载签名 URL）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// | @updated   2026-06-15 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use RuntimeException;

/**
 * 七牛云驱动（qiniu/php-sdk ^7.14，MIT）。
 *
 * AK/SK 同 OSS 经 bx_config 敏感项（AES）配置注入。**私有空间**：url() 实时签发带
 * deadline 的私有下载签名 URL（privateDownloadUrl，不裸公网直链、不缓存落库，ADR-18/§8）。
 *
 * 期望 $config 键：access_key / secret_key / bucket / domain / url_expire
 */
class QiniuStorage implements StorageInterface
{
    public function __construct(protected array $config = [])
    {
    }

    /**
     * 上传本地临时文件到七牛，返回 key（= 存储内 path，供 url/delete 复用）。
     */
    public function put(string $tmpPath, string $saveName): string
    {
        $key   = $this->normalizeKey($saveName);
        $token = $this->auth()->uploadToken($this->bucket());

        [$ret, $err] = (new UploadManager())->putFile($token, $key, $tmpPath);
        if ($err !== null) {
            throw new RuntimeException('七牛上传失败：' . $err->message());
        }

        return $key;
    }

    /**
     * 私有下载签名 URL（实时签发，带 url_expire 秒有效期；过期失效，故不可缓存落库）。
     */
    public function url(string $path): string
    {
        $key    = $this->normalizeKey($path);
        $domain = $this->domain();
        // domain 可不带协议，默认 https；拼 baseUrl 再做私有签名
        $scheme = preg_match('#^https?://#i', $domain) === 1 ? '' : 'https://';
        $base   = $scheme . $domain . '/' . $key;

        return (string) $this->auth()->privateDownloadUrl($base, $this->expire());
    }

    public function delete(string $path): bool
    {
        $key = $this->normalizeKey($path);
        $err = (new BucketManager($this->auth()))->delete($this->bucket(), $key);
        if ($err !== null) {
            throw new RuntimeException('七牛删除失败：' . $err->message());
        }

        return true;
    }

    // ------------------------------------------------------------------

    protected function auth(): Auth
    {
        $ak = (string) ($this->config['access_key'] ?? '');
        $sk = (string) ($this->config['secret_key'] ?? '');
        if ($ak === '' || $sk === '') {
            throw new RuntimeException('七牛配置不完整（access_key/secret_key）');
        }

        return new Auth($ak, $sk);
    }

    protected function bucket(): string
    {
        $bucket = (string) ($this->config['bucket'] ?? '');
        if ($bucket === '') {
            throw new RuntimeException('七牛 bucket 未配置');
        }

        return $bucket;
    }

    protected function domain(): string
    {
        $domain = rtrim((string) ($this->config['domain'] ?? ''), '/');
        if ($domain === '') {
            throw new RuntimeException('七牛 domain 未配置');
        }

        return $domain;
    }

    protected function expire(): int
    {
        $e = (int) ($this->config['url_expire'] ?? 3600);

        return $e > 0 ? $e : 3600;
    }

    /**
     * 归一化 key：去前导斜杠、统一正斜杠。
     */
    protected function normalizeKey(string $key): string
    {
        return ltrim(str_replace('\\', '/', $key), '/');
    }
}

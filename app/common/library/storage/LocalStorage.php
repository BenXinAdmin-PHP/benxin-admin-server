<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   本地存储驱动 — 存非 Web 可执行目录，后端受控下载
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

/**
 * 本地存储驱动。文件存项目根 storage/ 目录（**public 之外**，Web 不可直接访问/执行），
 * 经后端受控下载接口输出（GET /admin/v1/files/:id/raw）。
 */
class LocalStorage implements StorageInterface
{
    /**
     * 存储根目录（项目根 /storage，非 Web 目录）。
     */
    protected function root(): string
    {
        return rtrim(root_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage';
    }

    public function put(string $tmpPath, string $saveName): string
    {
        $saveName = ltrim(str_replace('\\', '/', $saveName), '/');
        $full     = $this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $saveName);

        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (!copy($tmpPath, $full)) {
            throw new \RuntimeException('文件落地失败');
        }

        return $saveName;
    }

    public function url(string $path): string
    {
        // 本地文件非公网可访问，URL 由 FileService 拼后端受控下载路由
        return '';
    }

    public function delete(string $path): bool
    {
        $full = $this->absolutePath($path);

        return is_file($full) ? unlink($full) : true;
    }

    /**
     * 相对路径 → 绝对路径（受控下载读取用）。防穿越：归一化后须仍在根目录内。
     */
    public function absolutePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $full = $this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        $real     = realpath($full);
        $rootReal = realpath($this->root());
        if ($real !== false && $rootReal !== false && !str_starts_with($real, $rootReal)) {
            throw new \RuntimeException('非法路径');
        }

        return $full;
    }

    public function exists(string $path): bool
    {
        return is_file($this->absolutePath($path));
    }
}

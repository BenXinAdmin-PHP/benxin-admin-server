<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   存储驱动接口 — 本地/OSS/七牛统一契约
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

/**
 * 存储驱动统一契约。本地驱动本步实现；OSS/七牛留骨架（M4 或按需）。
 */
interface StorageInterface
{
    /**
     * 落地文件。
     *
     * @param string $tmpPath  上传临时文件绝对路径
     * @param string $saveName 目标相对路径/云 key（如 uploads/2026/06/uuid.jpg）
     * @return string 实际存储的相对路径/key
     */
    public function put(string $tmpPath, string $saveName): string;

    /**
     * 取访问 URL（云驱动返回公网 URL；本地返回空串，走后端受控下载）。
     */
    public function url(string $path): string;

    /**
     * 删除存储中的文件（物理）。
     */
    public function delete(string $path): bool;
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 文件上传（安全校验）/ 列表（数据权限）/ 详情 / 删除 / 下载
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\storage\LocalStorage;
use app\common\library\storage\StorageManager;
use app\common\library\Uuid;
use app\common\model\Admin;
use app\common\model\File;
use think\file\UploadedFile;

/**
 * 文件服务：上传安全（finfo 真实 MIME + 扩展名白名单 + 双重校验 + uuid 重命名 +
 * 非 Web 目录）、列表（ADR-9 数据权限首示范）、详情、软删、受控下载。
 */
class FileService extends BxService
{
    /** 上传大小上限：10MB（应用层；php.ini upload_max_filesize/post_max_size 需匹配） */
    public const MAX_SIZE = 10485760;

    /**
     * 扩展名白名单 → 允许的真实 MIME（finfo）。
     * 仅允许名单内类型；php/sh/exe 等不在名单即被拒。Office 新格式 finfo 多报 application/zip，故并入。
     *
     * @var array<string,array<int,string>>
     */
    protected const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip'  => ['application/zip'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
    ];

    /**
     * 上传：安全校验 → 重命名 → 落地 → 入库（create_by/create_dept 由钩子自动填）。
     *
     * @return array<string,mixed> { id, url, original_name, size, mime, ext }
     */
    public function upload(?UploadedFile $file): array
    {
        if ($file === null) {
            throw new BusinessException('未检测到上传文件');
        }

        // 1) 大小上限
        $size = (int) $file->getSize();
        if ($size > self::MAX_SIZE) {
            throw new BusinessException('文件超过大小上限（' . (int) (self::MAX_SIZE / 1048576) . 'MB）');
        }

        // 2) 扩展名白名单（小写）
        $ext = strtolower((string) $file->getOriginalExtension());
        if ($ext === '' || !isset(self::ALLOWED[$ext])) {
            throw new BusinessException('不允许的文件类型：' . ($ext ?: '未知'));
        }

        // 3) finfo 真实 MIME + 与扩展名双重校验（防 .php 改名 .jpg）
        $mime = (string) $file->getMime();
        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            throw new BusinessException('文件内容与扩展名不匹配，已拒绝');
        }

        // 4) 重命名（uuid，禁用原名）+ 分目录
        $hash     = (string) hash_file('sha256', $file->getRealPath());
        $fileName = str_replace('-', '', Uuid::v4()) . '.' . $ext;
        $saveName = 'uploads/' . date('Y/m') . '/' . $fileName;

        // 5) 经当前驱动落地（本地存非 Web 目录）
        $storage   = StorageManager::driver();
        $storedPath = $storage->put($file->getRealPath(), $saveName);

        // 6) 入库（create_by/create_dept 自动填充）
        $record = File::create([
            'original_name' => mb_substr((string) $file->getOriginalName(), 0, 255),
            'file_name'     => $fileName,
            'path'          => $storedPath,
            'mime'          => $mime,
            'ext'           => $ext,
            'size'          => $size,
            'storage'       => StorageManager::currentName(),
            'hash'          => $hash,
            'url'           => '',
        ]);

        // 7) 访问 URL：本地走后端受控下载路由；云驱动走 storage->url
        $url = $storedPath !== '' && StorageManager::currentName() === 'local'
            ? '/admin/v1/files/' . $record->id . '/raw'
            : $storage->url($storedPath);
        $record->url = $url;
        $record->save();

        return [
            'id'            => (int) $record->id,
            'url'           => $url,
            'original_name' => $record->original_name,
            'size'          => $size,
            'mime'          => $mime,
            'ext'           => $ext,
        ];
    }

    /**
     * 列表（分页）。挂 ADR-9 数据权限：dept 维度 create_dept、本人维度 create_by。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize, Admin $acting): array
    {
        $query = File::order('id', 'desc');

        // ADR-9 在真业务表的首次示范
        $this->applyDataScope($query, $acting, 'create_dept', 'create_by');

        if (($filters['ext'] ?? '') !== '') {
            $query->where('ext', strtolower((string) $filters['ext']));
        }
        if (($filters['mime'] ?? '') !== '') {
            $query->whereLike('mime', '%' . trim((string) $filters['mime']) . '%');
        }
        if (($filters['keyword'] ?? '') !== '') {
            $query->whereLike('original_name', '%' . trim((string) $filters['keyword']) . '%');
        }
        if (($filters['start_time'] ?? '') !== '') {
            $query->where('created_at', '>=', (string) $filters['start_time']);
        }
        if (($filters['end_time'] ?? '') !== '') {
            $query->where('created_at', '<=', (string) $filters['end_time']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): File
    {
        return File::findOrFail($id);
    }

    /**
     * 删除：记录软删，物理文件保留（GC 后续）。
     */
    public function delete(int $id): void
    {
        File::findOrFail($id)->delete();
    }

    /**
     * 受控下载：返回文件内容 + mime + 原名（仅本地驱动）。
     *
     * @return array{content:string,mime:string,name:string}
     */
    public function readable(int $id): array
    {
        $record = File::findOrFail($id);
        if ((string) $record->storage !== 'local') {
            throw new BusinessException('该文件由云存储托管，请用其公网 URL 访问');
        }

        $local = new LocalStorage();
        if (!$local->exists((string) $record->path)) {
            throw new BusinessException('文件不存在或已被清理');
        }

        return [
            'content' => (string) file_get_contents($local->absolutePath((string) $record->path)),
            'mime'    => (string) $record->mime,
            'name'    => (string) $record->original_name,
        ];
    }
}

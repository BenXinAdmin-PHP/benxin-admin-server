<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 文件 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\File;

/**
 * 文件服务：标准 CRUD（生成器复刻 post 母版）。
 */
class FileService extends BxService
{
    protected const FILLABLE = ['original_name', 'file_name', 'path', 'mime', 'ext', 'size', 'storage', 'hash', 'url'];

    /**
     * 分页列表。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = File::order('id', 'asc');

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): File
    {
        return File::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): File
    {
        $data = $this->fillable($data);
        $data['tenant_id'] = File::currentTenantId();

        return File::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): File
    {
        $file = File::findOrFail($id);
        $data = $this->fillable($data);

        $file->save($data);

        return $file;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $file = File::findOrFail($id);
        $file->delete();
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }
}

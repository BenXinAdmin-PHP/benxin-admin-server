<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 字典类型 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Dict;

/**
 * 字典类型服务：标准 CRUD（生成器复刻 post 母版）。
 * type 唯一含软删（§5.1）。
 */
class DictService extends BxService
{
    protected const FILLABLE = ['name', 'type', 'status', 'remark'];

    /**
     * 分页列表（status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Dict::order('id', 'asc');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Dict
    {
        return Dict::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Dict
    {
        $data = $this->fillable($data);
        $this->assertTypeUnique((string) $data['type'], null);
        $data['tenant_id'] = Dict::currentTenantId();

        return Dict::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Dict
    {
        $dict = Dict::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('type', $data)) {
            $this->assertTypeUnique((string) $data['type'], $id);
        }

        $dict->save($data);

        return $dict;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $dict = Dict::findOrFail($id);
        $dict->delete();
    }

    public function setStatus(int $id, int $status): Dict
    {
        $dict         = Dict::findOrFail($id);
        $dict->status = $status;
        $dict->save();

        return $dict;
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

    /**
     * type 全局唯一（含 withTrashed，已删 type 不可复用，§5.1）。
     */
    protected function assertTypeUnique(string $type, ?int $exceptId): void
    {
        $query = Dict::withTrashed()->where('type', $type);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('字典类型标识已存在：' . $type);
        }
    }
}

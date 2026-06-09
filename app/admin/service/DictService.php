<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 字典类型 CRUD（删级联数据项 + 缓存失效）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Dict;
use think\facade\Db;

/**
 * 字典类型服务：标准 CRUD（复刻 M1 样板）。
 * type 唯一含 withTrashed；删除事务级联清该 type 的数据项 + 失效缓存；
 * 改 type 名时数据项 dict_type 跟随迁移，新旧缓存都清。
 */
class DictService extends BxService
{
    protected const FILLABLE = ['name', 'type', 'status', 'remark'];

    /**
     * 分页列表（keyword 模糊 name/type；status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Dict::order('id', 'desc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', "%{$keyword}%")->whereOr('type', 'like', "%{$keyword}%");
            });
        }
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
     * 更新；若 type 改名，数据项 dict_type 跟随迁移（事务），新旧缓存都清。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Dict
    {
        $dict   = Dict::findOrFail($id);
        $data   = $this->fillable($data);
        $oldType = (string) $dict->type;
        $newType = array_key_exists('type', $data) ? (string) $data['type'] : $oldType;

        if (array_key_exists('type', $data) && $newType !== $oldType) {
            $this->assertTypeUnique($newType, $id);
        }

        Db::transaction(function () use ($dict, $data, $oldType, $newType) {
            $dict->save($data);
            if ($newType !== $oldType) {
                // 数据项 dict_type 跟随迁移，避免孤儿
                Db::name('dict_data')->where('dict_type', $oldType)->update(['dict_type' => $newType]);
            }
        });

        DictDataService::clearCache($oldType);
        if ($newType !== $oldType) {
            DictDataService::clearCache($newType);
        }

        return $dict;
    }

    public function setStatus(int $id, int $status): Dict
    {
        $dict         = Dict::findOrFail($id);
        $dict->status = $status;
        $dict->save();
        DictDataService::clearCache((string) $dict->type);

        return $dict;
    }

    /**
     * 删除字典类型：事务级联软删其全部数据项 + 失效缓存。
     */
    public function delete(int $id): void
    {
        $dict = Dict::findOrFail($id);
        $type = (string) $dict->type;

        Db::transaction(function () use ($dict, $type) {
            // 级联软删该类型数据项（用模型以走软删）
            \app\common\model\DictData::where('dict_type', $type)->select()->each(function ($row) {
                $row->delete();
            });
            $dict->delete();
        });

        DictDataService::clearCache($type);
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
     * type 唯一（含 withTrashed，§5.1）。
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

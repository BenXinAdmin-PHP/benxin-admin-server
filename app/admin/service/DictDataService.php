<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 字典数据项 CRUD + 取数缓存（读回填/写失效/TTL 兜底）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Dict;
use app\common\model\DictData;
use think\facade\Cache;

/**
 * 字典数据项服务 + 字典取数缓存（★ M2 通用缓存模式，M2-B 参数配置复用）。
 * 缓存：key=dict:data:{type}（实带 bx: 前缀），只缓存启用项；任何写操作失效该 type 的 key；
 * 兜底 TTL 1 天，避免脏数据长期驻留。
 */
class DictDataService extends BxService
{
    protected const FILLABLE = ['dict_type', 'label', 'value', 'sort', 'status', 'list_class', 'is_default', 'remark'];

    /** 缓存兜底 TTL（秒）：1 天 */
    public const CACHE_TTL = 86400;

    // ------------------------------------------------------------------
    // 取数缓存（对外接口 GET /dicts/type/:type 的数据源）
    // ------------------------------------------------------------------

    /**
     * 取某字典类型的全部启用数据项（按 sort）。优先 Valkey，未命中查库回填。
     *
     * @return array<int,array<string,mixed>>
     */
    public function getByType(string $type): array
    {
        $key   = self::cacheKey($type);
        $store = self::store();

        $cached = $store->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $list = DictData::where('dict_type', $type)
            ->where('status', 1)
            ->order('sort', 'asc')->order('id', 'asc')
            ->field('label,value,list_class,is_default,sort')
            ->select()->toArray();

        $store->set($key, $list, self::CACHE_TTL);

        return $list;
    }

    /** 失效某字典类型缓存（写操作后调用）。 */
    public static function clearCache(string $type): void
    {
        if ($type !== '') {
            self::store()->delete(self::cacheKey($type));
        }
    }

    protected static function cacheKey(string $type): string
    {
        return 'dict:data:' . $type;
    }

    protected static function store()
    {
        return Cache::store((string) config('jwt.store', 'redis'));
    }

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------

    /**
     * 分页列表（必须支持 dict_type 筛选；keyword 模糊 label/value）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = DictData::order('sort', 'asc')->order('id', 'asc');

        if (($filters['dict_type'] ?? '') !== '') {
            $query->where('dict_type', (string) $filters['dict_type']);
        }
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('label', "%{$keyword}%")->whereOr('value', 'like', "%{$keyword}%");
            });
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): DictData
    {
        return DictData::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): DictData
    {
        $data = $this->fillable($data);
        $type = (string) $data['dict_type'];
        $this->assertDictTypeExists($type);
        $this->assertValueUnique($type, (string) $data['value'], null);

        $data['tenant_id'] = DictData::currentTenantId();
        $item              = DictData::create($data);

        self::clearCache($type);

        return $item;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): DictData
    {
        $item = DictData::findOrFail($id);
        $data = $this->fillable($data);

        $oldType  = (string) $item->dict_type;
        $newType  = array_key_exists('dict_type', $data) ? (string) $data['dict_type'] : $oldType;
        $newValue = array_key_exists('value', $data) ? (string) $data['value'] : (string) $item->value;

        if (array_key_exists('dict_type', $data) && $newType !== $oldType) {
            $this->assertDictTypeExists($newType);
        }
        if (array_key_exists('dict_type', $data) || array_key_exists('value', $data)) {
            $this->assertValueUnique($newType, $newValue, $id);
        }

        $item->save($data);

        // 新旧类型缓存都失效
        self::clearCache($oldType);
        if ($newType !== $oldType) {
            self::clearCache($newType);
        }

        return $item;
    }

    public function delete(int $id): void
    {
        $item = DictData::findOrFail($id);
        $type = (string) $item->dict_type;
        $item->delete();
        self::clearCache($type);
    }

    public function setStatus(int $id, int $status): DictData
    {
        $item         = DictData::findOrFail($id);
        $item->status = $status;
        $item->save();
        self::clearCache((string) $item->dict_type);

        return $item;
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

    protected function assertDictTypeExists(string $type): void
    {
        if (Dict::where('type', $type)->count() === 0) {
            throw new BusinessException('字典类型不存在：' . $type);
        }
    }

    /**
     * 同类型内 value 唯一（含 withTrashed，§5.1 软删不可复用）。
     */
    protected function assertValueUnique(string $type, string $value, ?int $exceptId): void
    {
        $query = DictData::withTrashed()->where('dict_type', $type)->where('value', $value);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('该字典类型下已存在相同的值：' . $value);
        }
    }
}

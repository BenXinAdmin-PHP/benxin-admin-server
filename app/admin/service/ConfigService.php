<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 参数配置 CRUD + 敏感值加解密 + 取数缓存
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// | @updated   2026-06-16 —— 新增 groups()：去重分组+计数（复用缓存，仅组名非敏感）
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\BxCache;
use app\common\library\ConfigCrypt;
use app\common\model\Config;

/**
 * 参数配置服务（复刻 M1 普通 CRUD 样板 + 敏感值 AES + 缓存）。
 *
 * 形态约定：
 * - 敏感项（is_sensitive=1）：value 经 AES 加密入库；HTTP 列表/详情/分组**脱敏**返回，绝不回明文/密文。
 * - 更新：提交值 == 脱敏占位（用户没改）→ 不更新该字段（保留原值）；传新值才重新加密。
 * - 内部取值 get()/getGroup() 返回**明文**（供业务模块调用，如取微信 secret）。
 * - 缓存：config:all 缓存全部**原始库值**（敏感=密文，Valkey 无明文）；写操作整体失效；兜底 TTL 1 天。
 */
class ConfigService extends BxService
{
    protected const FILLABLE  = ['name', 'group', 'key', 'value', 'is_sensitive', 'value_type', 'sort', 'remark'];
    protected const CACHE_KEY = 'config:all';
    public const CACHE_TTL    = 86400;

    // ------------------------------------------------------------------
    // 列表 / 详情（HTTP，脱敏）
    // ------------------------------------------------------------------

    /**
     * 分页列表（group/keyword 筛选）；敏感项 value 脱敏。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Config::order('sort', 'asc')->order('id', 'asc');

        if (($filters['group'] ?? '') !== '') {
            $query->where('group', (string) $filters['group']);
        }
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', "%{$keyword}%")->whereOr('key', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $rows  = $query->page($page, $pageSize)->select()->toArray();
        $list  = array_map([$this, 'toHttp'], $rows);

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 详情（敏感脱敏）。
     *
     * @return array<string,mixed>
     */
    public function detail(int $id): array
    {
        return $this->toHttp(Config::findOrFail($id)->toArray());
    }

    /**
     * 去重分组列表（含各组配置数）——供后台配置页顶栏 Tab 分类。
     *
     * 复用 rawAll() 缓存，按配置自然出现顺序去重（前端再按写死顺序排序）；
     * 仅返回组名与计数（非敏感，不涉密钥）。空组（无配置）自然不出现。
     *
     * @return array<int,array{group:string,count:int}>
     */
    public function groups(): array
    {
        $counts = [];
        foreach ($this->rawAll() as $row) {
            $group          = (string) $row['group'];
            $counts[$group] = ($counts[$group] ?? 0) + 1;
        }

        $list = [];
        foreach ($counts as $group => $count) {
            $list[] = ['group' => $group, 'count' => $count];
        }

        return $list;
    }

    /**
     * 按分组取（HTTP，脱敏）——后台配置页/前端数据源。
     *
     * @return array<int,array<string,mixed>>
     */
    public function groupForHttp(string $group): array
    {
        $rows = array_filter($this->rawAll(), static fn ($r) => (string) $r['group'] === $group);

        return array_values(array_map([$this, 'toHttp'], $rows));
    }

    // ------------------------------------------------------------------
    // 内部取值（明文，供业务模块调用）
    // ------------------------------------------------------------------

    /**
     * 按 key 取明文值（敏感自动解密）。key 跨分组应唯一；未命中返回 $default。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        foreach ($this->rawAll() as $row) {
            if ((string) $row['key'] === $key) {
                return $this->plainValue($row);
            }
        }

        return $default;
    }

    /**
     * 按分组取明文 key=>value 映射（敏感自动解密）。
     *
     * @return array<string,mixed>
     */
    public function getGroup(string $group): array
    {
        $result = [];
        foreach ($this->rawAll() as $row) {
            if ((string) $row['group'] === $group) {
                $result[(string) $row['key']] = $this->plainValue($row);
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // 写操作
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Config
    {
        $data = $this->fillable($data);
        $this->assertGroupKeyUnique((string) $data['group'], (string) $data['key'], null);

        $sensitive = (int) ($data['is_sensitive'] ?? 0) === 1;
        if ($sensitive) {
            $data['value'] = ConfigCrypt::encrypt((string) ($data['value'] ?? ''));
        }

        $data['tenant_id'] = Config::currentTenantId();
        $config            = Config::create($data);

        BxCache::forget(self::CACHE_KEY);

        return $config;
    }

    /**
     * 更新：敏感值脱敏占位不误清；切换 is_sensitive 时按需重新加/解密。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Config
    {
        $config = Config::findOrFail($id);
        $data   = $this->fillable($data);

        $wasSensitive   = (int) $config->is_sensitive === 1;
        $finalSensitive = array_key_exists('is_sensitive', $data) ? ((int) $data['is_sensitive'] === 1) : $wasSensitive;
        $currentPlain   = $wasSensitive ? ConfigCrypt::decrypt((string) $config->value) : (string) $config->value;

        if (array_key_exists('value', $data)) {
            $submitted = (string) $data['value'];
            // 提交值 == 当前脱敏占位 → 用户没改，保留原存储值
            if ($wasSensitive && $submitted === ConfigCrypt::mask($currentPlain)) {
                unset($data['value']);
                // 但若敏感性切换，仍需按新形态转换原明文
                if ($finalSensitive !== $wasSensitive) {
                    $data['value'] = $finalSensitive ? ConfigCrypt::encrypt($currentPlain) : $currentPlain;
                }
            } else {
                // 提交了新值 → 按最终敏感性入库
                $data['value'] = $finalSensitive ? ConfigCrypt::encrypt($submitted) : $submitted;
            }
        } elseif ($finalSensitive !== $wasSensitive) {
            // 未传 value 但敏感性切换 → 原明文按新形态转换
            $data['value'] = $finalSensitive ? ConfigCrypt::encrypt($currentPlain) : $currentPlain;
        }

        // (group,key) 唯一（若有变更）
        $newGroup = array_key_exists('group', $data) ? (string) $data['group'] : (string) $config->group;
        $newKey   = array_key_exists('key', $data) ? (string) $data['key'] : (string) $config->key;
        if ($newGroup !== (string) $config->group || $newKey !== (string) $config->key) {
            $this->assertGroupKeyUnique($newGroup, $newKey, $id);
        }

        $config->save($data);
        BxCache::forget(self::CACHE_KEY);

        return $config;
    }

    public function delete(int $id): void
    {
        $config = Config::findOrFail($id);
        $config->delete();
        BxCache::forget(self::CACHE_KEY);
    }

    // ------------------------------------------------------------------
    // 内部工具
    // ------------------------------------------------------------------

    /**
     * 全部配置原始库值（缓存；敏感=密文，Valkey 无明文）。
     *
     * @return array<int,array<string,mixed>>
     */
    protected function rawAll(): array
    {
        return BxCache::remember(self::CACHE_KEY, self::CACHE_TTL, static function () {
            return Config::order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        });
    }

    /**
     * 取某行明文值（敏感解密）。
     *
     * @param array<string,mixed> $row
     */
    protected function plainValue(array $row): string
    {
        $value = (string) ($row['value'] ?? '');

        return (int) ($row['is_sensitive'] ?? 0) === 1 ? ConfigCrypt::decrypt($value) : $value;
    }

    /**
     * HTTP 输出：敏感项 value 脱敏（基于明文），其余原样。
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    protected function toHttp(array $row): array
    {
        if ((int) ($row['is_sensitive'] ?? 0) === 1) {
            $row['value'] = ConfigCrypt::mask(ConfigCrypt::decrypt((string) ($row['value'] ?? '')));
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }

    /**
     * (group,key) 唯一（含 withTrashed，§5.1）。
     */
    protected function assertGroupKeyUnique(string $group, string $key, ?int $exceptId): void
    {
        $query = Config::withTrashed()->where('group', $group)->where('key', $key);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException("配置项已存在：{$group}.{$key}");
        }
    }
}

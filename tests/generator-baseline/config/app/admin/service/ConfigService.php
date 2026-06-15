<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 配置中心 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Config;

/**
 * 配置中心服务：标准 CRUD（生成器复刻 post 母版）。
 * group 唯一含软删（§5.1）。
 */
class ConfigService extends BxService
{
    protected const FILLABLE = ['name', 'group', 'key', 'value', 'remark', 'is_sensitive', 'value_type', 'sort'];

    /**
     * 分页列表。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Config::order('sort', 'asc')->order('id', 'asc');

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Config
    {
        return Config::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Config
    {
        $data = $this->fillable($data);
        $this->assertGroupUnique((string) $data['group'], null);
        $data['tenant_id'] = Config::currentTenantId();

        return Config::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Config
    {
        $config = Config::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('group', $data)) {
            $this->assertGroupUnique((string) $data['group'], $id);
        }

        $config->save($data);

        return $config;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $config = Config::findOrFail($id);
        $config->delete();
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
     * group 全局唯一（含 withTrashed，已删 group 不可复用，§5.1）。
     */
    protected function assertGroupUnique(string $group, ?int $exceptId): void
    {
        $query = Config::withTrashed()->where('group', $group);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('配置分组已存在：' . $group);
        }
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 部门 树形 CRUD（生成器复刻 dept/menu 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Admin;
use app\common\model\Dept;
use think\facade\Db;

/**
 * 部门服务：树构建 + CRUD（生成器复刻 dept/menu 树形母版）。
 */
class DeptService extends BxService
{
    protected const FILLABLE = ['parent_id', 'name', 'leader', 'phone', 'email', 'sort', 'status'];

    /**
     * 完整部门树（按 sort 升序）。
     *
     * @return array<int,array>
     */
    public function tree(): array
    {
        $list = Dept::order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        return $this->buildTree($list, 0);
    }

    /**
     * 内存建树。
     *
     * @param array<int,array> $list
     * @return array<int,array>
     */
    public function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];
        foreach ($list as $node) {
            if ((int) $node['parent_id'] === $parentId) {
                $children = $this->buildTree($list, (int) $node['id']);
                if ($children !== []) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }

        return $tree;
    }

    public function detail(int $id): Dept
    {
        return Dept::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Dept
    {
        $data = $this->fillable($data);
        $this->assertParent((int) ($data['parent_id'] ?? 0));
        $data['tenant_id'] = Dept::currentTenantId();

        return Dept::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Dept
    {
        $dept = Dept::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('parent_id', $data)) {
            $newParent = (int) $data['parent_id'];
            $this->assertParent($newParent);
            $this->assertNotCycle($id, $newParent);
        }

        $dept->save($data);

        return $dept;
    }

    /**
     * 删除部门：有子节点拒绝；有管理员绑定（bx_admin.dept_id）拒绝；否则软删。
     */
    public function delete(int $id): void
    {
        $dept = Dept::findOrFail($id);

        if (Dept::where('parent_id', $id)->count() > 0) {
            throw new BusinessException('该部门存在子节点，请先删除子节点');
        }
        if (Admin::where('dept_id', $id)->count() > 0) {
            throw new BusinessException('该部门已被管理员绑定，无法删除');
        }

        $dept->delete();
    }

    public function setStatus(int $id, int $status): Dept
    {
        $dept         = Dept::findOrFail($id);
        $dept->status = $status;
        $dept->save();

        return $dept;
    }

    /**
     * 子树 id 集合（含自身 + 全部后代），MySQL8 递归 CTE（参数化）。
     * 供数据权限“本部门及以下”使用（ADR-9）。
     *
     * @return array<int,int>
     */
    public function descendantIds(int $deptId): array
    {
        if ($deptId <= 0) {
            return [];
        }

        $prefix = config('database.connections.mysql.prefix', 'bx_');
        $table  = $prefix . 'dept';
        $sql    = "WITH RECURSIVE dept_cte AS ("
            . "SELECT id FROM {$table} WHERE id = ? AND deleted_at IS NULL "
            . "UNION ALL "
            . "SELECT d.id FROM {$table} d INNER JOIN dept_cte c ON d.parent_id = c.id WHERE d.deleted_at IS NULL"
            . ") SELECT id FROM dept_cte";

        $rows = Db::query($sql, [$deptId]);

        return array_map(static fn ($r) => (int) $r['id'], $rows);
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

    protected function assertParent(int $parentId): void
    {
        if ($parentId === 0) {
            return;
        }
        if (Dept::where('id', $parentId)->count() === 0) {
            throw new BusinessException('父级部门不存在');
        }
    }

    protected function assertNotCycle(int $id, int $newParentId): void
    {
        if ($newParentId === 0) {
            return;
        }
        if ($newParentId === $id) {
            throw new BusinessException('父级不能选择自身');
        }
        if (in_array($newParentId, $this->descendantIds($id), true)) {
            throw new BusinessException('父级不能选择自身的子节点');
        }
    }
}

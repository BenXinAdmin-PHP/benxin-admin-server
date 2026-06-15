<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 菜单 树形 CRUD（生成器复刻 dept/menu 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Menu;
use app\common\service\CasbinService;
use think\facade\Db;

/**
 * 菜单服务：树构建 + CRUD（生成器复刻 dept/menu 树形母版）。
 */
class MenuService extends BxService
{
    protected const FILLABLE = ['parent_id', 'type', 'name', 'title', 'path', 'component', 'perms', 'icon', 'sort', 'status', 'visible'];

    /**
     * 完整菜单树（按 sort 升序）。
     *
     * @return array<int,array>
     */
    public function tree(): array
    {
        $list = Menu::order('sort', 'asc')->order('id', 'asc')->select()->toArray();

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

    public function detail(int $id): Menu
    {
        return Menu::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Menu
    {
        $data = $this->fillable($data);
        $this->assertParent((int) ($data['parent_id'] ?? 0));
        $this->assertPermsUnique($data['perms'] ?? '', null);
        $data['tenant_id'] = Menu::currentTenantId();

        return Menu::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Menu
    {
        $menu = Menu::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('parent_id', $data)) {
            $newParent = (int) $data['parent_id'];
            $this->assertParent($newParent);
            $this->assertNotCycle($id, $newParent);
        }

        if (array_key_exists('perms', $data)) {
            $this->assertPermsUnique((string) $data['perms'], $id);
        }

        $menu->save($data);

        return $menu;
    }

    /**
     * 删除菜单：有子节点拒绝；级联清理 bx_role_menu 与 casbin perms。
     */
    public function delete(int $id): void
    {
        $menu = Menu::findOrFail($id);

        if (Menu::where('parent_id', $id)->count() > 0) {
            throw new BusinessException('该菜单存在子节点，请先删除子节点');
        }

        $perms = (string) $menu->perms;

        Db::transaction(function () use ($id, $menu, $perms) {
            // 清理角色-菜单关联
            Db::name('role_menu')->where('menu_id', $id)->delete();

            // 清理引用该 perm 的 casbin 授权（避免悬空策略）
            if ($perms !== '') {
                CasbinService::removePolicyByPerm($perms);
            }

            // 软删除菜单
            $menu->delete();
        });

        if ($perms !== '') {
            CasbinService::reload();
        }
    }

    public function setStatus(int $id, int $status): Menu
    {
        $menu         = Menu::findOrFail($id);
        $menu->status = $status;
        $menu->save();

        return $menu;
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
        if (Menu::where('id', $parentId)->count() === 0) {
            throw new BusinessException('父级菜单不存在');
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

        $all         = Menu::field('id,parent_id')->select()->toArray();
        $descendants = $this->collectDescendants($all, $id);
        if (in_array($newParentId, $descendants, true)) {
            throw new BusinessException('父级不能选择自身的子节点');
        }
    }

    /**
     * 收集某节点的全部子孙 id（内存遍历）。
     *
     * @param array<int,array> $all
     * @return array<int,int>
     */
    protected function collectDescendants(array $all, int $id): array
    {
        $result = [];
        foreach ($all as $node) {
            if ((int) $node['parent_id'] === $id) {
                $childId  = (int) $node['id'];
                $result[] = $childId;
                $result   = array_merge($result, $this->collectDescendants($all, $childId));
            }
        }

        return $result;
    }

    /**
     * perms 非空时全局唯一（同租户，排除自身）。
     */
    protected function assertPermsUnique(string $perms, ?int $exceptId): void
    {
        if (trim($perms) === '') {
            return;
        }
        $query = Menu::where('perms', $perms);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('权限标识 perms 已存在：' . $perms);
        }
    }
}

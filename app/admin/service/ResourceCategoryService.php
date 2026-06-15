<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 素材分类 树形 CRUD（生成器复刻 dept/menu 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Resource;
use app\common\model\ResourceCategory;

/**
 * 素材分类服务：树构建 + CRUD（生成器复刻 dept/menu 树形母版）。
 */
class ResourceCategoryService extends BxService
{
    protected const FILLABLE = ['parent_id', 'name', 'sort', 'status', 'remark'];

    /**
     * 完整素材分类树（按 sort 升序）。
     *
     * @return array<int,array>
     */
    public function tree(): array
    {
        $list = ResourceCategory::order('sort', 'asc')->order('id', 'asc')->select()->toArray();

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

    public function detail(int $id): ResourceCategory
    {
        return ResourceCategory::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): ResourceCategory
    {
        $data = $this->fillable($data);
        $this->assertParent((int) ($data['parent_id'] ?? 0));
        $data['tenant_id'] = ResourceCategory::currentTenantId();

        return ResourceCategory::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): ResourceCategory
    {
        $resourceCategory = ResourceCategory::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('parent_id', $data)) {
            $newParent = (int) $data['parent_id'];
            $this->assertParent($newParent);
            $this->assertNotCycle($id, $newParent);
        }

        $resourceCategory->save($data);

        return $resourceCategory;
    }

    /**
     * 删除素材分类：有子节点拒绝；有素材绑定（bx_resource.category_id）拒绝；否则软删。
     */
    public function delete(int $id): void
    {
        $resourceCategory = ResourceCategory::findOrFail($id);

        if (ResourceCategory::where('parent_id', $id)->count() > 0) {
            throw new BusinessException('该素材分类存在子节点，请先删除子节点');
        }
        if (Resource::where('category_id', $id)->count() > 0) {
            throw new BusinessException('该素材分类已被素材绑定，无法删除');
        }

        $resourceCategory->delete();
    }

    public function setStatus(int $id, int $status): ResourceCategory
    {
        $resourceCategory         = ResourceCategory::findOrFail($id);
        $resourceCategory->status = $status;
        $resourceCategory->save();

        return $resourceCategory;
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
        if (ResourceCategory::where('id', $parentId)->count() === 0) {
            throw new BusinessException('父级素材分类不存在');
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

        $all         = ResourceCategory::field('id,parent_id')->select()->toArray();
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
}

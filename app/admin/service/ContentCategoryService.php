<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 内容分类 树形 CRUD（生成器复刻 dept/menu 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Content;
use app\common\model\ContentCategory;

/**
 * 内容分类服务：树构建 + CRUD（生成器复刻 dept/menu 树形母版）。
 */
class ContentCategoryService extends BxService
{
    protected const FILLABLE = ['parent_id', 'name', 'sort', 'status', 'icon'];

    /**
     * 完整内容分类树（按 sort 升序）。
     *
     * @return array<int,array>
     */
    public function tree(): array
    {
        $list = ContentCategory::order('sort', 'asc')->order('id', 'asc')->select()->toArray();

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

    public function detail(int $id): ContentCategory
    {
        return ContentCategory::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): ContentCategory
    {
        $data = $this->fillable($data);
        $this->assertParent((int) ($data['parent_id'] ?? 0));
        $data['tenant_id'] = ContentCategory::currentTenantId();

        return ContentCategory::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): ContentCategory
    {
        $contentCategory = ContentCategory::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('parent_id', $data)) {
            $newParent = (int) $data['parent_id'];
            $this->assertParent($newParent);
            $this->assertNotCycle($id, $newParent);
        }

        $contentCategory->save($data);

        return $contentCategory;
    }

    /**
     * 删除内容分类：有子节点拒绝；有内容绑定（bx_content.category_id）拒绝；否则软删。
     */
    public function delete(int $id): void
    {
        $contentCategory = ContentCategory::findOrFail($id);

        if (ContentCategory::where('parent_id', $id)->count() > 0) {
            throw new BusinessException('该内容分类存在子节点，请先删除子节点');
        }
        if (Content::where('category_id', $id)->count() > 0) {
            throw new BusinessException('该内容分类已被内容绑定，无法删除');
        }

        $contentCategory->delete();
    }

    public function setStatus(int $id, int $status): ContentCategory
    {
        $contentCategory         = ContentCategory::findOrFail($id);
        $contentCategory->status = $status;
        $contentCategory->save();

        return $contentCategory;
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
        if (ContentCategory::where('id', $parentId)->count() === 0) {
            throw new BusinessException('父级内容分类不存在');
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

        $all         = ContentCategory::field('id,parent_id')->select()->toArray();
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

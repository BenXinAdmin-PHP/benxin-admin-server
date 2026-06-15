<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   素材分类 — GET|POST|PUT|DELETE /admin/v1/resource-categories[/tree|/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ResourceCategoryService;
use app\admin\validate\ResourceCategoryValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 素材分类 CRUD（生成器复刻 dept/menu 树形母版）。
 */
class ResourceCategory extends BxController
{
    /**
     * 完整素材分类树。
     * GET /admin/v1/resource-categories/tree
     */
    public function tree(): Response
    {
        return $this->success((new ResourceCategoryService($this->app))->tree());
    }

    /**
     * 详情。
     * GET /admin/v1/resource-categories/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new ResourceCategoryService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/resource-categories
     */
    public function save(): Response
    {
        validate(ResourceCategoryValidate::class)->scene('create')->check($this->request->post());

        $resourceCategory = (new ResourceCategoryService($this->app))->create($this->request->post());

        return $this->success($resourceCategory, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/resource-categories/:id
     */
    public function update(int $id): Response
    {
        validate(ResourceCategoryValidate::class)->scene('update')->check($this->request->param());

        $resourceCategory = (new ResourceCategoryService($this->app))->update($id, $this->request->param());

        return $this->success($resourceCategory, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/resource-categories/:id
     */
    public function delete(int $id): Response
    {
        (new ResourceCategoryService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/resource-categories/:id/status
     */
    public function status(int $id): Response
    {
        validate(ResourceCategoryValidate::class)->scene('status')->check($this->request->param());

        $resourceCategory = (new ResourceCategoryService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($resourceCategory, '状态更新成功');
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   内容分类 — GET|POST|PUT|DELETE /admin/v1/content-categories[/tree|/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ContentCategoryService;
use app\admin\validate\ContentCategoryValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 内容分类 CRUD（生成器复刻 dept/menu 树形母版）。
 */
class ContentCategory extends BxController
{
    /**
     * 完整内容分类树。
     * GET /admin/v1/content-categories/tree
     */
    public function tree(): Response
    {
        return $this->success((new ContentCategoryService($this->app))->tree());
    }

    /**
     * 详情。
     * GET /admin/v1/content-categories/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new ContentCategoryService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/content-categories
     */
    public function save(): Response
    {
        validate(ContentCategoryValidate::class)->scene('create')->check($this->request->post());

        $contentCategory = (new ContentCategoryService($this->app))->create($this->request->post());

        return $this->success($contentCategory, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/content-categories/:id
     */
    public function update(int $id): Response
    {
        validate(ContentCategoryValidate::class)->scene('update')->check($this->request->param());

        $contentCategory = (new ContentCategoryService($this->app))->update($id, $this->request->param());

        return $this->success($contentCategory, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/content-categories/:id
     */
    public function delete(int $id): Response
    {
        (new ContentCategoryService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/content-categories/:id/status
     */
    public function status(int $id): Response
    {
        validate(ContentCategoryValidate::class)->scene('status')->check($this->request->param());

        $contentCategory = (new ContentCategoryService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($contentCategory, '状态更新成功');
    }
}

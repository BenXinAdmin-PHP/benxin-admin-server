<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   内容 — GET|POST|PUT|DELETE /admin/v1/contents[/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ContentService;
use app\admin\validate\ContentValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 内容 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class Content extends BxController
{
    /**
     * 列表（分页 + keyword/category_id/status 筛选）。
     * GET /admin/v1/contents
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new ContentService($this->app))->list([
            'keyword'     => $this->request->param('keyword', ''),
            'category_id' => $this->request->param('category_id', ''),
            'status'      => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/contents/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new ContentService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/contents
     */
    public function save(): Response
    {
        validate(ContentValidate::class)->scene('create')->check($this->request->post());

        $content = (new ContentService($this->app))->create($this->request->post());

        return $this->success($content, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/contents/:id
     */
    public function update(int $id): Response
    {
        validate(ContentValidate::class)->scene('update')->check($this->request->param());

        $content = (new ContentService($this->app))->update($id, $this->request->param());

        return $this->success($content, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/contents/:id
     */
    public function delete(int $id): Response
    {
        (new ContentService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/contents/:id/status
     */
    public function status(int $id): Response
    {
        validate(ContentValidate::class)->scene('status')->check($this->request->param());

        $content = (new ContentService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($content, '状态更新成功');
    }
}

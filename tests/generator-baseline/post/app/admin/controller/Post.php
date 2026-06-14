<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   岗位 — GET|POST|PUT|DELETE /admin/v1/posts[/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\PostService;
use app\admin\validate\PostValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 岗位 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class Post extends BxController
{
    /**
     * 列表（分页 + keyword/status 筛选）。
     * GET /admin/v1/posts
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new PostService($this->app))->list([
            'keyword' => $this->request->param('keyword', ''),
            'status'  => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/posts/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new PostService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/posts
     */
    public function save(): Response
    {
        validate(PostValidate::class)->scene('create')->check($this->request->post());

        $post = (new PostService($this->app))->create($this->request->post());

        return $this->success($post, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/posts/:id
     */
    public function update(int $id): Response
    {
        validate(PostValidate::class)->scene('update')->check($this->request->param());

        $post = (new PostService($this->app))->update($id, $this->request->param());

        return $this->success($post, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/posts/:id
     */
    public function delete(int $id): Response
    {
        (new PostService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/posts/:id/status
     */
    public function status(int $id): Response
    {
        validate(PostValidate::class)->scene('status')->check($this->request->param());

        $post = (new PostService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($post, '状态更新成功');
    }
}

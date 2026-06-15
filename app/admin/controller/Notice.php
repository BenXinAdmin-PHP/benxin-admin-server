<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   系统公告 — GET|POST|PUT|DELETE /admin/v1/notices[/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 20:00:39
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\NoticeService;
use app\admin\validate\NoticeValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 系统公告 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class Notice extends BxController
{
    /**
     * 列表（分页 + keyword/type/status 筛选）。
     * GET /admin/v1/notices
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new NoticeService($this->app))->list([
            'keyword' => $this->request->param('keyword', ''),
            'type'    => $this->request->param('type', ''),
            'status'  => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/notices/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new NoticeService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/notices
     */
    public function save(): Response
    {
        validate(NoticeValidate::class)->scene('create')->check($this->request->post());

        $notice = (new NoticeService($this->app))->create($this->request->post());

        return $this->success($notice, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/notices/:id
     */
    public function update(int $id): Response
    {
        validate(NoticeValidate::class)->scene('update')->check($this->request->param());

        $notice = (new NoticeService($this->app))->update($id, $this->request->param());

        return $this->success($notice, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/notices/:id
     */
    public function delete(int $id): Response
    {
        (new NoticeService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/notices/:id/status
     */
    public function status(int $id): Response
    {
        validate(NoticeValidate::class)->scene('status')->check($this->request->param());

        $notice = (new NoticeService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($notice, '状态更新成功');
    }
}

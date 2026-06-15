<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   字典类型 — GET|POST|PUT|DELETE /admin/v1/dicts[/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\DictService;
use app\admin\validate\DictValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 字典类型 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class Dict extends BxController
{
    /**
     * 列表（分页 + status 筛选）。
     * GET /admin/v1/dicts
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new DictService($this->app))->list([
            'status' => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/dicts/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new DictService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/dicts
     */
    public function save(): Response
    {
        validate(DictValidate::class)->scene('create')->check($this->request->post());

        $dict = (new DictService($this->app))->create($this->request->post());

        return $this->success($dict, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/dicts/:id
     */
    public function update(int $id): Response
    {
        validate(DictValidate::class)->scene('update')->check($this->request->param());

        $dict = (new DictService($this->app))->update($id, $this->request->param());

        return $this->success($dict, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/dicts/:id
     */
    public function delete(int $id): Response
    {
        (new DictService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/dicts/:id/status
     */
    public function status(int $id): Response
    {
        validate(DictValidate::class)->scene('status')->check($this->request->param());

        $dict = (new DictService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($dict, '状态更新成功');
    }
}

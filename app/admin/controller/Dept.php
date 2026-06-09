<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   部门 — GET|POST|PUT|DELETE /admin/v1/depts[/tree|/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\DeptService;
use app\admin\validate\DeptValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 部门 CRUD（复刻 M1-C 菜单树样板）。
 */
class Dept extends BxController
{
    /**
     * 完整部门树。
     * GET /admin/v1/depts/tree
     */
    public function tree(): Response
    {
        return $this->success((new DeptService($this->app))->tree());
    }

    /**
     * 详情。
     * GET /admin/v1/depts/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new DeptService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/depts
     */
    public function save(): Response
    {
        validate(DeptValidate::class)->scene('create')->check($this->request->post());

        $dept = (new DeptService($this->app))->create($this->request->post());

        return $this->success($dept, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/depts/:id
     */
    public function update(int $id): Response
    {
        validate(DeptValidate::class)->scene('update')->check($this->request->param());

        $dept = (new DeptService($this->app))->update($id, $this->request->param());

        return $this->success($dept, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/depts/:id
     */
    public function delete(int $id): Response
    {
        (new DeptService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/depts/:id/status
     */
    public function status(int $id): Response
    {
        validate(DeptValidate::class)->scene('status')->check($this->request->param());

        $dept = (new DeptService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($dept, '状态更新成功');
    }
}

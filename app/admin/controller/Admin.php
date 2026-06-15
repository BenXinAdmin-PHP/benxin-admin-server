<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   管理员 — GET|POST|PUT|DELETE /admin/v1/admins[/:id|/:id/status|/:id/password]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\AdminService;
use app\admin\validate\AdminValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 管理员 CRUD（黄金样板）。输出绝不含 password。
 */
class Admin extends BxController
{
    /**
     * 列表（分页；keyword/dept_id/status 筛选）。
     * GET /admin/v1/admins
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new AdminService($this->app))->list([
            'keyword' => $this->request->param('keyword', ''),
            'dept_id' => $this->request->param('dept_id', ''),
            'status'  => $this->request->param('status', ''),
        ], $page, $size, $this->request->adminUser);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情（含 role_ids/post_ids）。
     * GET /admin/v1/admins/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new AdminService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/admins
     */
    public function save(): Response
    {
        validate(AdminValidate::class)->scene('create')->check($this->request->post());

        $admin = (new AdminService($this->app))->create($this->request->post());

        return $this->success(['id' => (int) $admin->id], '新增成功');
    }

    /**
     * 更新（选择性字段；不改密码）。
     * PUT /admin/v1/admins/:id
     */
    public function update(int $id): Response
    {
        validate(AdminValidate::class)->scene('update')->check($this->request->param());

        (new AdminService($this->app))->update($id, $this->request->param(), (int) $this->request->adminId);

        return $this->success(null, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/admins/:id
     */
    public function delete(int $id): Response
    {
        (new AdminService($this->app))->delete($id, (int) $this->request->adminId);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/admins/:id/status
     */
    public function status(int $id): Response
    {
        validate(AdminValidate::class)->scene('status')->check($this->request->param());

        (new AdminService($this->app))->setStatus($id, (int) $this->request->param('status'), (int) $this->request->adminId);

        return $this->success(null, '状态更新成功');
    }

    /**
     * 重置密码（管理员重置他人）。
     * PUT /admin/v1/admins/:id/password
     */
    public function password(int $id): Response
    {
        validate(AdminValidate::class)->scene('password')->check($this->request->param());

        (new AdminService($this->app))->resetPassword($id, (string) $this->request->param('password'));

        return $this->success(null, '密码已重置');
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   角色 — GET|POST|PUT|DELETE /admin/v1/roles[/:id|/:id/status|/:id/menus]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 15:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\RoleService;
use app\admin\validate\RoleValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 角色 CRUD + 分配菜单（黄金样板）。
 */
class Role extends BxController
{
    /**
     * 列表（分页 + keyword/status 筛选）。
     * GET /admin/v1/roles
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new RoleService($this->app))->list([
            'keyword' => $this->request->param('keyword', ''),
            'status'  => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情（含已分配 menu_ids）。
     * GET /admin/v1/roles/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new RoleService($this->app))->detail($id));
    }

    /**
     * 已分配菜单 id 列表（回显勾选）。
     * GET /admin/v1/roles/:id/menus
     */
    public function menus(int $id): Response
    {
        return $this->success((new RoleService($this->app))->menuIds($id));
    }

    /**
     * 新增。
     * POST /admin/v1/roles
     */
    public function save(): Response
    {
        validate(RoleValidate::class)->scene('create')->check($this->request->post());

        $role = (new RoleService($this->app))->create($this->request->post());

        return $this->success($role, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/roles/:id
     */
    public function update(int $id): Response
    {
        validate(RoleValidate::class)->scene('update')->check($this->request->param());

        $role = (new RoleService($this->app))->update($id, $this->request->param());

        return $this->success($role, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/roles/:id
     */
    public function delete(int $id): Response
    {
        (new RoleService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/roles/:id/status
     */
    public function status(int $id): Response
    {
        validate(RoleValidate::class)->scene('status')->check($this->request->param());

        $role = (new RoleService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($role, '状态更新成功');
    }

    /**
     * 分配菜单（全量覆盖式 menu_ids[]，同步 Casbin）。
     * PUT /admin/v1/roles/:id/menus
     */
    public function assignMenus(int $id): Response
    {
        validate(RoleValidate::class)->scene('assignMenus')->check($this->request->param());

        (new RoleService($this->app))->assignMenus($id, (array) $this->request->param('menu_ids', []));

        return $this->success(null, '分配成功');
    }
}

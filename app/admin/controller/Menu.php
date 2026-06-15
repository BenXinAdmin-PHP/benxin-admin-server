<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   菜单/权限 — GET|POST|PUT|DELETE /admin/v1/menus[/tree|/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\MenuService;
use app\admin\validate\MenuValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 菜单/权限 CRUD（黄金样板）。控制器只做参数编排 + 调 Service + 返回。
 * 权限点：list/create/update/delete，路由层经 CasbinAuth 显式声明。
 */
class Menu extends BxController
{
    /**
     * 完整菜单树。
     * GET /admin/v1/menus/tree
     */
    public function tree(): Response
    {
        return $this->success((new MenuService($this->app))->tree());
    }

    /**
     * 详情。
     * GET /admin/v1/menus/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new MenuService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/menus
     */
    public function save(): Response
    {
        validate(MenuValidate::class)->scene('create')->check($this->request->post());

        $menu = (new MenuService($this->app))->create($this->request->post());

        return $this->success($menu, '新增成功');
    }

    /**
     * 更新（仅更新提交字段）。
     * PUT /admin/v1/menus/:id
     */
    public function update(int $id): Response
    {
        validate(MenuValidate::class)->scene('update')->check($this->request->param());

        $menu = (new MenuService($this->app))->update($id, $this->request->param());

        return $this->success($menu, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/menus/:id
     */
    public function delete(int $id): Response
    {
        (new MenuService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/menus/:id/status
     */
    public function status(int $id): Response
    {
        validate(MenuValidate::class)->scene('status')->check($this->request->param());

        $menu = (new MenuService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($menu, '状态更新成功');
    }
}

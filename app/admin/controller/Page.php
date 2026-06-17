<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   页面 — GET|POST|PUT|DELETE /admin/v1/pages[/:id]（通用页面搭建，供 M6-C）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\validate\PageValidate;
use app\common\base\BxController;
use app\common\service\PageService;
use think\Response;

/**
 * 页面 CRUD（手写，非标准 JSON blocks 模型，ADR-21；不入生成器基线）。
 * 详情返回原始 blocks JSON（供 M6-C 搭建器编辑）；渲染解析在 api 端 renderBySlug。
 */
class Page extends BxController
{
    /**
     * 列表（分页 + keyword/status 筛选）。
     * GET /admin/v1/pages
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new PageService($this->app))->list([
            'keyword' => $this->request->param('keyword', ''),
            'status'  => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情（含原始 blocks JSON）。
     * GET /admin/v1/pages/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new PageService($this->app))->detail($id));
    }

    /**
     * 新建。
     * POST /admin/v1/pages
     */
    public function save(): Response
    {
        validate(PageValidate::class)->scene('create')->check($this->request->post());

        $page = (new PageService($this->app))->create($this->request->post());

        return $this->success($page, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/pages/:id
     */
    public function update(int $id): Response
    {
        validate(PageValidate::class)->scene('update')->check($this->request->param());

        $page = (new PageService($this->app))->update($id, $this->request->param());

        return $this->success($page, '更新成功');
    }

    /**
     * 删除（软删）。
     * DELETE /admin/v1/pages/:id
     */
    public function delete(int $id): Response
    {
        (new PageService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }
}

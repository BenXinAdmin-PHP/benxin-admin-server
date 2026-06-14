<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   配置中心 — GET|POST|PUT|DELETE /admin/v1/configs[/:id]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ConfigService;
use app\admin\validate\ConfigValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 配置中心 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class Config extends BxController
{
    /**
     * 列表（分页）。
     * GET /admin/v1/configs
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new ConfigService($this->app))->list([

        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/configs/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new ConfigService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/configs
     */
    public function save(): Response
    {
        validate(ConfigValidate::class)->scene('create')->check($this->request->post());

        $config = (new ConfigService($this->app))->create($this->request->post());

        return $this->success($config, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/configs/:id
     */
    public function update(int $id): Response
    {
        validate(ConfigValidate::class)->scene('update')->check($this->request->param());

        $config = (new ConfigService($this->app))->update($id, $this->request->param());

        return $this->success($config, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/configs/:id
     */
    public function delete(int $id): Response
    {
        (new ConfigService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }
}

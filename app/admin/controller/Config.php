<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   参数配置 — GET|POST|PUT|DELETE /admin/v1/configs[/:id|/group/:group]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ConfigService;
use app\admin\validate\ConfigValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 参数配置 CRUD（复刻样板）。敏感项一律脱敏回显，内部明文取值走 ConfigService::get。
 */
class Config extends BxController
{
    /**
     * 列表（分页 + group/keyword 筛选；敏感脱敏）。
     * GET /admin/v1/configs
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new ConfigService($this->app))->list([
            'group'   => $this->request->param('group', ''),
            'keyword' => $this->request->param('keyword', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 按分组取（敏感脱敏）。
     * GET /admin/v1/configs/group/:group
     */
    public function group(string $group): Response
    {
        return $this->success((new ConfigService($this->app))->groupForHttp($group));
    }

    /**
     * 详情（敏感脱敏）。
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

        return $this->success(['id' => (int) $config->id], '新增成功');
    }

    /**
     * 更新（敏感脱敏占位不误清）。
     * PUT /admin/v1/configs/:id
     */
    public function update(int $id): Response
    {
        validate(ConfigValidate::class)->scene('update')->check($this->request->param());

        (new ConfigService($this->app))->update($id, $this->request->param());

        return $this->success(null, '更新成功');
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

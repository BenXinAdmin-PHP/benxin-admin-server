<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   字典类型 — GET|POST|PUT|DELETE /admin/v1/dicts[/:id|/:id/status|/type/:type]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\DictDataService;
use app\admin\service\DictService;
use app\admin\validate\DictValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 字典类型 CRUD + 对外取数（复刻 M1 普通 CRUD 样板）。
 */
class Dict extends BxController
{
    /**
     * 列表（分页 + keyword/status 筛选）。
     * GET /admin/v1/dicts
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new DictService($this->app))->list([
            'keyword' => $this->request->param('keyword', ''),
            'status'  => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 取某字典类型的全部启用数据项（缓存读回填）。前端下拉/标签数据源。
     * GET /admin/v1/dicts/type/:type
     */
    public function dataByType(string $type): Response
    {
        return $this->success((new DictDataService($this->app))->getByType($type));
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
     * 删除（级联清数据项 + 缓存）。
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

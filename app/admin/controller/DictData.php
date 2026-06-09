<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   字典数据项 — GET|POST|PUT|DELETE /admin/v1/dict-data[/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\DictDataService;
use app\admin\validate\DictDataValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 字典数据项 CRUD（复刻样板）。列表支持 dict_type 筛选；写操作失效缓存。
 */
class DictData extends BxController
{
    /**
     * 列表（分页 + dict_type/keyword/status 筛选）。
     * GET /admin/v1/dict-data
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new DictDataService($this->app))->list([
            'dict_type' => $this->request->param('dict_type', ''),
            'keyword'   => $this->request->param('keyword', ''),
            'status'    => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/dict-data/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new DictDataService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/dict-data
     */
    public function save(): Response
    {
        validate(DictDataValidate::class)->scene('create')->check($this->request->post());

        $item = (new DictDataService($this->app))->create($this->request->post());

        return $this->success($item, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/dict-data/:id
     */
    public function update(int $id): Response
    {
        validate(DictDataValidate::class)->scene('update')->check($this->request->param());

        $item = (new DictDataService($this->app))->update($id, $this->request->param());

        return $this->success($item, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/dict-data/:id
     */
    public function delete(int $id): Response
    {
        (new DictDataService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/dict-data/:id/status
     */
    public function status(int $id): Response
    {
        validate(DictDataValidate::class)->scene('status')->check($this->request->param());

        $item = (new DictDataService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($item, '状态更新成功');
    }
}

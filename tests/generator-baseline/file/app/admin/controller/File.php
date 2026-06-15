<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   文件 — GET|POST|PUT|DELETE /admin/v1/files[/:id]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\FileService;
use app\admin\validate\FileValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 文件 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class File extends BxController
{
    /**
     * 列表（分页）。
     * GET /admin/v1/files
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new FileService($this->app))->list([

        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/files/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new FileService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/files
     */
    public function save(): Response
    {
        validate(FileValidate::class)->scene('create')->check($this->request->post());

        $file = (new FileService($this->app))->create($this->request->post());

        return $this->success($file, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/files/:id
     */
    public function update(int $id): Response
    {
        validate(FileValidate::class)->scene('update')->check($this->request->param());

        $file = (new FileService($this->app))->update($id, $this->request->param());

        return $this->success($file, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/files/:id
     */
    public function delete(int $id): Response
    {
        (new FileService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }
}

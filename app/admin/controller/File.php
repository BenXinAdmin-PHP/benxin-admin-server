<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   文件 — POST upload / GET files[/:id|/:id/raw] / DELETE /admin/v1/files/:id
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\FileService;
use app\common\base\BxController;
use think\Response;

/**
 * 文件管理：上传（安全校验）、列表（数据权限）、详情、软删、受控下载。
 */
class File extends BxController
{
    /**
     * 上传（multipart，字段名 file）。
     * POST /admin/v1/files/upload
     */
    public function upload(): Response
    {
        $data = (new FileService($this->app))->upload($this->request->file('file'));

        return $this->success($data, '上传成功');
    }

    /**
     * 列表（分页 + ext/mime/keyword/时间；挂 ADR-9 数据权限）。
     * GET /admin/v1/files
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new FileService($this->app))->list([
            'ext'        => $this->request->param('ext', ''),
            'mime'       => $this->request->param('mime', ''),
            'keyword'    => $this->request->param('keyword', ''),
            'start_time' => $this->request->param('start_time', ''),
            'end_time'   => $this->request->param('end_time', ''),
        ], $page, $size, $this->request->adminUser);

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
     * 受控下载（后端输出；本地驱动文件不可被当脚本执行/直链访问）。
     * GET /admin/v1/files/:id/raw
     */
    public function raw(int $id): Response
    {
        $info = (new FileService($this->app))->readable($id);

        return Response::create($info['content'])
            ->contentType($info['mime'])
            ->header(['Content-Disposition' => 'inline; filename="' . rawurlencode($info['name']) . '"']);
    }

    /**
     * 删除（软删记录，物理文件保留）。
     * DELETE /admin/v1/files/:id
     */
    public function delete(int $id): Response
    {
        (new FileService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }
}

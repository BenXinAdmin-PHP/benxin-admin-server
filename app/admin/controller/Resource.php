<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   素材 — GET|POST|PUT|DELETE /admin/v1/resources[/:id|/upload|/batch|/:id/raw]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// | @updated   2026-06-15 15:06:31
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ResourceService;
use app\admin\validate\ResourceValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 素材管理。
 * 【生成器产出】index / read / save / update / delete（纯 CRUD 复刻 post 母版）。
 * 【手工槽（M-素材-A）】upload（本地全类型上传）/ raw（受控取流，本地音视频播放）/
 *   batchDelete（批量删，事务软删 + 物理删容错）。
 */
class Resource extends BxController
{
    /**
     * 上传（multipart，字段名 file；可选 category_id 归类）。
     * POST /admin/v1/resources/upload
     */
    public function upload(): Response
    {
        $data = (new ResourceService($this->app))->upload(
            $this->request->file('file'),
            (int) $this->request->param('category_id', 0)
        );

        return $this->success($data, '上传成功');
    }

    /**
     * 受控取流（按 storage 分流）：本地→后端 inline 输出（音视频播放靠它）；
     * 云(oss/qiniu)→302 重定向到实时签名 URL（浏览器直连云，不经后端扛流量，ADR-18）。
     * GET /admin/v1/resources/:id/raw
     */
    public function raw(int $id): Response
    {
        $target = (new ResourceService($this->app))->rawTarget($id);

        if (($target['type'] ?? '') === 'redirect') {
            return redirect((string) $target['url']);
        }

        return Response::create($target['content'])
            ->contentType($target['mime'])
            ->header(['Content-Disposition' => 'inline; filename="' . rawurlencode($target['name']) . '"']);
    }

    /**
     * 批量删（body: ids[]）：事务软删记录 + 同步物理删（部分物理删失败仅记日志不回滚）。
     * DELETE /admin/v1/resources/batch
     */
    public function batchDelete(): Response
    {
        $result = (new ResourceService($this->app))->batchDelete(
            (array) $this->request->param('ids', [])
        );

        return $this->success($result, '批量删除完成');
    }

    /**
     * 列表（分页 + keyword/category_id/media_type 筛选）。
     * GET /admin/v1/resources
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new ResourceService($this->app))->list([
            'keyword'     => $this->request->param('keyword', ''),
            'category_id' => $this->request->param('category_id', ''),
            'media_type'  => $this->request->param('media_type', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/resources/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new ResourceService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/resources
     */
    public function save(): Response
    {
        validate(ResourceValidate::class)->scene('create')->check($this->request->post());

        $resource = (new ResourceService($this->app))->create($this->request->post());

        return $this->success($resource, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/resources/:id
     */
    public function update(int $id): Response
    {
        validate(ResourceValidate::class)->scene('update')->check($this->request->param());

        $resource = (new ResourceService($this->app))->update($id, $this->request->param());

        return $this->success($resource, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/resources/:id
     */
    public function delete(int $id): Response
    {
        (new ResourceService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }
}

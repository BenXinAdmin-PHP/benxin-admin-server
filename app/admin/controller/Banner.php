<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   广告位 — GET|POST|PUT|DELETE /admin/v1/banners[/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// | @updated   2026-06-12 14:45:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\BannerService;
use app\admin\validate\BannerValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 广告位 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class Banner extends BxController
{
    /**
     * 列表（分页 + keyword/status/生效区间筛选）。
     * GET /admin/v1/banners
     *
     * effective 为生成后手工补的区间参数（[起, 止] 日期对，M4-A 吃狗粮缺口：
     * 生成器 search 暂不支持 daterange，回炉候选）。
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new BannerService($this->app))->list([
            'keyword'   => $this->request->param('keyword', ''),
            'status'    => $this->request->param('status', ''),
            'effective' => $this->request->param('effective/a', []),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/banners/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new BannerService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/banners
     */
    public function save(): Response
    {
        validate(BannerValidate::class)->scene('create')->check($this->request->post());

        $banner = (new BannerService($this->app))->create($this->request->post());

        return $this->success($banner, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/banners/:id
     */
    public function update(int $id): Response
    {
        validate(BannerValidate::class)->scene('update')->check($this->request->param());

        $banner = (new BannerService($this->app))->update($id, $this->request->param());

        return $this->success($banner, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/banners/:id
     */
    public function delete(int $id): Response
    {
        (new BannerService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/banners/:id/status
     */
    public function status(int $id): Response
    {
        validate(BannerValidate::class)->scene('status')->check($this->request->param());

        $banner = (new BannerService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($banner, '状态更新成功');
    }
}

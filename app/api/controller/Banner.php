<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   前台广告位 — GET /api/v1/banners（启用 + 生效区间）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\model\Banner as BannerModel;
use think\Response;

/**
 * 前台广告位（C 端只读，懒登录不强制）。仅返回启用（status=1）且处于生效区间
 * （start_at≤now≤end_at，空值视为不限）的广告位，按 sort 升序。
 * 字段精简：不外露 create_by/tenant_id 等内部字段。
 * 后台广告位 CRUD 见 admin/controller/Banner（bx:make 生成）。
 */
class Banner extends BxController
{
    private const STATUS_ENABLED = 1;

    /** 字段白名单：仅前台展示所需，不含内部归属字段 */
    private const FIELDS = 'id,title,image,link,position,sort';

    /**
     * 启用且生效中的广告位列表（按 position 过滤，可选）。GET /api/v1/banners?position=home_top
     * 无分页（广告位数量小、整组消费）；按 sort 升序。
     */
    public function index(): Response
    {
        $now = date('Y-m-d H:i:s');

        $query = BannerModel::field(self::FIELDS)
            ->where('status', self::STATUS_ENABLED)
            // 生效开始：未设（null）或已到
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->whereOr('start_at', '<=', $now);
            })
            // 生效结束：未设（null）或未过
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->whereOr('end_at', '>=', $now);
            });

        $position = trim((string) $this->request->param('position', ''));
        if ($position !== '') {
            $query->where('position', $position);
        }

        $list = $query->order('sort', 'asc')->order('id', 'desc')->select()->toArray();

        return $this->success($list);
    }
}

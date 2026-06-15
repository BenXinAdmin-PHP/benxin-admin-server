<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 广告位 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// | @updated   2026-06-12 14:45:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Banner;

/**
 * 广告位服务：标准 CRUD（生成器复刻 post 母版）。
 */
class BannerService extends BxService
{
    protected const FILLABLE = ['title', 'image', 'link', 'position', 'sort', 'status', 'start_at', 'end_at'];

    /**
     * 分页列表（keyword: title/position；status 精确；effective 生效区间）。
     *
     * effective 为生成后手工补的区间条件（M4-A 吃狗粮缺口：生成器 search 暂不支持
     * daterange，回炉候选）：[起, 止] 日期对 → 命中「与所选区间有交集」的广告——
     * start_at 为空或 <= 区间末，且 end_at 为空或 >= 区间起（空值=立即生效/长期有效）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Banner::order('sort', 'asc')->order('id', 'asc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', "%{$keyword}%")->whereOr('position', 'like', "%{$keyword}%");
            });
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }
        $effective = (array) ($filters['effective'] ?? []);
        if (count($effective) === 2 && $effective[0] !== '' && $effective[1] !== '') {
            $rangeStart = (string) $effective[0] . ' 00:00:00';
            $rangeEnd   = (string) $effective[1] . ' 23:59:59';
            $query->where(function ($q) use ($rangeEnd) {
                $q->whereNull('start_at')->whereOr('start_at', '<=', $rangeEnd);
            })->where(function ($q) use ($rangeStart) {
                $q->whereNull('end_at')->whereOr('end_at', '>=', $rangeStart);
            });
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Banner
    {
        return Banner::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Banner
    {
        $data = $this->fillable($data);
        $data['tenant_id'] = Banner::currentTenantId();

        return Banner::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Banner
    {
        $banner = Banner::findOrFail($id);
        $data = $this->fillable($data);

        $banner->save($data);

        return $banner;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();
    }

    public function setStatus(int $id, int $status): Banner
    {
        $banner         = Banner::findOrFail($id);
        $banner->status = $status;
        $banner->save();

        return $banner;
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }
}

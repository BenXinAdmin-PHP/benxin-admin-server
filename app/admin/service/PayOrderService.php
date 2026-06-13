<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 支付订单后台只读查询（列表/详情/退款记录）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 11:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\model\PayOrder;
use app\common\model\PayRefund;

/**
 * 支付订单后台服务（只读 + 退款记录）。退款写操作走 BxPay::refund（核心收口），本类不写订单。
 * 「只读 + 退款」特殊形态不套 bx:make（无新增/编辑），手写；记为未来「只读模块」吃狗粮候选。
 */
class PayOrderService extends BxService
{
    /**
     * 分页列表（order_no/out_trade_no/channel/status/biz_type + 时间范围）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = PayOrder::order('id', 'desc');

        foreach (['order_no', 'out_trade_no', 'channel', 'biz_type'] as $exact) {
            if (($filters[$exact] ?? '') !== '') {
                $query->where($exact, (string) $filters[$exact]);
            }
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (($filters['keyword'] ?? '') !== '') {
            $kw = '%' . trim((string) $filters['keyword']) . '%';
            $query->where(function ($q) use ($kw) {
                $q->whereLike('order_no', $kw)
                    ->whereOr('out_trade_no', $kw)
                    ->whereOr('subject', $kw)
                    ->whereOr('transaction_id', $kw);
            });
        }
        if (($filters['start_time'] ?? '') !== '') {
            $query->where('created_at', '>=', (string) $filters['start_time']);
        }
        if (($filters['end_time'] ?? '') !== '') {
            $query->where('created_at', '<=', (string) $filters['end_time']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 详情（含退款记录）。
     *
     * @return array<string,mixed>
     */
    public function detail(int $id): array
    {
        $order          = PayOrder::findOrFail($id)->toArray();
        $order['refunds'] = PayRefund::where('pay_order_id', $id)->order('id', 'desc')->select()->toArray();

        return $order;
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   支付订单管理 — GET /admin/v1/pay-orders[/:id], POST /:id/refund
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 11:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\PayOrderService;
use app\common\base\BxController;
use app\common\exception\BusinessException;
use app\common\service\BxPay;
use think\Response;

/**
 * 支付订单后台管理（只读 + 退款）。
 * - 列表/详情只读，挂 system:pay:list。
 * - 退款为后台敏感操作（§8）：挂 system:pay:refund + 二次确认（confirm=1）+ 审计（操作日志中间件）。
 */
class Pay extends BxController
{
    /**
     * 订单列表。GET /admin/v1/pay-orders
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new PayOrderService($this->app))->list([
            'keyword'      => $this->request->param('keyword', ''),
            'order_no'     => $this->request->param('order_no', ''),
            'out_trade_no' => $this->request->param('out_trade_no', ''),
            'channel'      => $this->request->param('channel', ''),
            'status'       => $this->request->param('status', ''),
            'biz_type'     => $this->request->param('biz_type', ''),
            'start_time'   => $this->request->param('start_time', ''),
            'end_time'     => $this->request->param('end_time', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 订单详情（含退款记录）。GET /admin/v1/pay-orders/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new PayOrderService($this->app))->detail($id));
    }

    /**
     * 退款（敏感操作，需二次确认 confirm=1）。POST /admin/v1/pay-orders/:id/refund
     * body：amount（分，必填）/ reason（可空）/ confirm（=1 二次确认）。
     */
    public function refund(int $id): Response
    {
        if ((string) $this->request->param('confirm', '') !== '1') {
            throw new BusinessException('退款为敏感操作，需二次确认（confirm=1）');
        }
        $amount = (int) $this->request->param('amount', 0);
        if ($amount <= 0) {
            throw new BusinessException('退款金额必须大于 0（单位：分）');
        }
        $reason = (string) $this->request->param('reason', '');

        $order  = (new PayOrderService($this->app))->detail($id);
        $refund = (new BxPay($this->app))->refund((string) $order['order_no'], $amount, $reason);

        return $this->success([
            'refund_no'     => $refund->refund_no,
            'out_refund_no' => $refund->out_refund_no,
            'amount'        => $refund->amount,
            'status'        => $refund->status,
        ], '退款已发起');
    }
}

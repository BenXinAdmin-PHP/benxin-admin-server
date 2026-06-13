<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   BxPay 服务 — 支付核心收口（下单/查单/状态机/幂等/金额/event）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service;

use app\common\base\BxService;
use app\common\exception\PayException;
use app\common\library\ErrorCode;
use app\common\library\pay\PayManager;
use app\common\model\PayOrder;

/**
 * 支付核心服务（M4-C，复刻「lcobucci→BxJwt」「casbin→CasbinService」自建收口层）。
 *
 * 职责收口：下单/查单/退款/回调处理；**订单状态机、幂等、金额校验、event 触发集中于此**。
 * 所有订单状态变更走唯一入口 transitionTo()（非法迁移拒绝 + 异常）。
 *
 * 开源边界（§1）：biz_type/biz_id 仅透传，底座不理解其含义；支付/退款成功 fire event 交上层。
 *
 * 本类按批次成长：C-1 下单/查单；C-2 回调/退款/event。
 */
class BxPay extends BxService
{
    /**
     * 统一下单：建单 + 调渠道 → 返回 { order, prepay }。
     * 供上层业务 service 调用（透传 biz_type/biz_id），不暴露通用 C 端 HTTP 下单接口。
     *
     * @param array<string,mixed> $params
     *   必填：channel(wechat/alipay) / trade_type / subject / amount(分)
     *   可选：openid(jsapi) / attach / user_id / biz_type / biz_id / expire_minutes / out_trade_no
     * @return array{order:PayOrder,prepay:array<string,mixed>}
     */
    public function prepay(array $params): array
    {
        $channel   = (string) ($params['channel'] ?? '');
        $tradeType = (string) ($params['trade_type'] ?? '');
        $amount    = (int) ($params['amount'] ?? 0);

        if (!in_array($channel, PayManager::CHANNELS, true)) {
            throw PayException::channel(ErrorCode::PAY_PREPAY_FAILED, "不支持的支付渠道：{$channel}");
        }
        if ($tradeType === '') {
            throw PayException::channel(ErrorCode::PAY_PREPAY_FAILED, '缺少 trade_type');
        }
        if ($amount <= 0) {
            throw PayException::channel(ErrorCode::PAY_PREPAY_FAILED, '支付金额必须大于 0（单位：分）');
        }

        $order = new PayOrder();
        $order->order_no     = $this->generateNo('PO');
        $order->out_trade_no = (string) ($params['out_trade_no'] ?? '') !== '' ? (string) $params['out_trade_no'] : $this->generateNo('OT');
        $order->channel      = $channel;
        $order->trade_type   = $tradeType;
        $order->subject      = mb_substr((string) ($params['subject'] ?? ''), 0, 255);
        $order->amount       = $amount;
        $order->status       = PayOrder::STATUS_PENDING;
        $order->openid       = (string) ($params['openid'] ?? '');
        $order->attach       = mb_substr((string) ($params['attach'] ?? ''), 0, 255);
        $order->user_id      = (int) ($params['user_id'] ?? 0);
        $order->biz_type     = mb_substr((string) ($params['biz_type'] ?? ''), 0, 32);
        $order->biz_id       = mb_substr((string) ($params['biz_id'] ?? ''), 0, 64);
        $order->tenant_id    = PayOrder::currentTenantId();
        $minutes             = (int) ($params['expire_minutes'] ?? 0);
        if ($minutes > 0) {
            $order->expire_at = date('Y-m-d H:i:s', time() + $minutes * 60);
        }
        $order->save();

        // 调渠道下单（失败抛 120002；订单留待关单/超时清理）
        $prepay = PayManager::channel($channel)->prepay($order);

        return ['order' => $order, 'prepay' => $prepay];
    }

    /**
     * 查单（内部单号或对外交易号）→ 渠道原始结果。
     *
     * @return array<string,mixed>
     */
    public function query(string $orderNoOrOut): array
    {
        $order = $this->findOrder($orderNoOrOut);

        return PayManager::channel($order->channel)->query($order->out_trade_no);
    }

    /**
     * 关单（待支付订单主动关闭）。
     */
    public function close(string $orderNoOrOut): void
    {
        $order = $this->findOrder($orderNoOrOut);
        PayManager::channel($order->channel)->close($order->out_trade_no);
        $this->transitionTo($order, PayOrder::STATUS_CLOSED);
    }

    // ------------------------------------------------------------------
    // 内部：订单定位 / 状态机 / 单号
    // ------------------------------------------------------------------

    /**
     * 按 order_no 或 out_trade_no 定位订单；不存在抛 120003。
     */
    public function findOrder(string $orderNoOrOut): PayOrder
    {
        $order = PayOrder::where('order_no', $orderNoOrOut)
            ->whereOr('out_trade_no', $orderNoOrOut)
            ->find();
        if ($order === null) {
            throw PayException::orderNotFound($orderNoOrOut);
        }

        return $order;
    }

    /**
     * ★状态机唯一入口：仅允许合法迁移，否则拒绝（120004）。
     * 相同目标状态视为幂等无操作（由调用方幂等层兜底，这里直接返回）。
     *
     * @param array<string,mixed> $extra 同事务一并写入的字段（transaction_id/paid_at/notify_data...）
     */
    public function transitionTo(PayOrder $order, int $to, array $extra = []): void
    {
        $from = (int) $order->status;
        if ($from === $to) {
            if ($extra !== []) {
                $order->save($extra);
            }

            return;
        }
        if (!PayOrder::canTransit($from, $to)) {
            throw PayException::illegalTransit($from, $to);
        }

        $order->save(['status' => $to] + $extra);
    }

    /**
     * 生成全局唯一单号：前缀 + 时间 + 随机；冲突极低，落库唯一索引兜底。
     */
    public function generateNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . substr((string) microtime(true), -4) . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}

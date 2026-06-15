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
use app\common\event\PaySuccessEvent;
use app\common\event\RefundSuccessEvent;
use app\common\exception\PayException;
use app\common\library\ErrorCode;
use app\common\library\pay\NotifyResult;
use app\common\library\pay\PayManager;
use app\common\model\PayNotifyLog;
use app\common\model\PayOrder;
use app\common\model\PayRefund;
use think\facade\Db;
use think\facade\Event;
use think\Response;

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
    // 回调处理（★核心：验签 → 幂等 → 金额二次校验 → 状态机 → 更新 → event → ACK）
    // ------------------------------------------------------------------

    /**
     * 处理渠道异步回调，返回渠道要求的 ACK（微信 {code:SUCCESS} / 支付宝 success）。
     * 安全四件套全程收口于此：
     *  1) 验签（Provider）失败 → 记审计(verified=0) + ackFail（不泄露细节）。
     *  2) 幂等：notify_log 唯一键去重，已处理重复回调直接 ACK 不重复处理。
     *  3) 金额二次校验：回调金额≠订单金额 → 拒绝（120006）+ 审计。
     *  4) 状态机合法迁移：非法迁移拒绝（120004）+ 审计。
     * 全程把回调原文落 notify_log 审计。
     *
     * @param array<string,mixed> $headers
     */
    public function handleNotify(string $channel, string $eventType, array $headers, string $body): Response
    {
        $provider = PayManager::channel($channel);
        $result   = $provider->verifyNotify($headers, $body, $eventType);

        // 1) 验签失败 → 审计 + 拒绝（idem_no 用 body 摘要，确保审计行可落、相同伪报文去重）
        if (!$result->verified) {
            $this->writeNotifyLog($channel, $eventType, 'INVALID:' . substr(sha1($body), 0, 40), '', '', $body, 0, 0, '验签失败');

            return $provider->ackFail('verify failed');
        }

        $idemNo = $result->idemNo();

        // 2) 幂等锚点：find-or-create；已 processed 直接 ACK
        $log = PayNotifyLog::where('channel', $channel)
            ->where('event_type', $eventType)
            ->where('idem_no', $idemNo)
            ->find();
        if ($log !== null && (int) $log->processed === 1) {
            return $provider->ackSuccess();
        }
        if ($log === null) {
            $log = $this->writeNotifyLog($channel, $eventType, $idemNo, $result->outTradeNo, $result->transactionId, $body, 1, 0, '处理中');
        }

        // 3+4) 金额二次校验 + 状态机迁移 + 更新（applyXxxNotify 内事务）
        try {
            $changed = $eventType === PayNotifyLog::EVENT_REFUND
                ? $this->applyRefundNotify($result)
                : $this->applyPayNotify($result);
        } catch (PayException $e) {
            $log->save(['processed' => 0, 'result' => mb_substr($e->getMessage(), 0, 255)]);

            return $provider->ackFail($e->getMessage());
        }

        $log->save(['processed' => 1, 'result' => $changed ? 'ok' : 'ok(idempotent)']);

        return $provider->ackSuccess();
    }

    /**
     * 应用支付成功回调：金额二次校验 + 状态机 + 更新 + fire PaySuccessEvent。
     * 返回是否发生了实际状态变更（已支付的重复回调返回 false，不重复 fire）。
     */
    protected function applyPayNotify(NotifyResult $result): bool
    {
        $order = $this->findOrder($result->outTradeNo);

        // 渠道侧未成功（如关闭/失败通知）：仅待支付可转失败，其他状态忽略
        if (!$result->tradeSuccess) {
            if ((int) $order->status === PayOrder::STATUS_PENDING) {
                $this->transitionTo($order, PayOrder::STATUS_FAILED, ['notify_data' => $this->rawJson($result)]);
            }

            return false;
        }

        // 金额二次校验（防篡改，120006）
        if ($result->amount !== (int) $order->amount) {
            throw PayException::amountMismatch((int) $order->amount, $result->amount);
        }

        // 幂等：已支付的重复回调直接成功，不再 fire
        if ((int) $order->status === PayOrder::STATUS_PAID) {
            return false;
        }

        Db::transaction(function () use ($order, $result) {
            $this->transitionTo($order, PayOrder::STATUS_PAID, [
                'transaction_id' => $result->transactionId,
                'paid_at'        => date('Y-m-d H:i:s'),
                'notify_data'    => $this->rawJson($result),
            ]);
        });

        Event::trigger(new PaySuccessEvent(
            orderId: (int) $order->id,
            orderNo: (string) $order->order_no,
            outTradeNo: (string) $order->out_trade_no,
            channel: (string) $order->channel,
            tradeType: (string) $order->trade_type,
            amount: (int) $order->amount,
            transactionId: (string) $order->transaction_id,
            userId: (int) $order->user_id,
            bizType: (string) $order->biz_type,
            bizId: (string) $order->biz_id,
            attach: (string) $order->attach,
        ));

        return true;
    }

    /**
     * 应用退款回调：定位退款单 + 渠道确认 → confirmRefund（更新 + fire RefundSuccessEvent）。
     */
    protected function applyRefundNotify(NotifyResult $result): bool
    {
        $refund = PayRefund::where('out_refund_no', $result->outRefundNo)->find();
        if ($refund === null) {
            throw PayException::orderNotFound('退款单 ' . $result->outRefundNo);
        }
        if ((int) $refund->status === PayRefund::STATUS_SUCCESS) {
            return false; // 幂等
        }
        if (!$result->tradeSuccess) {
            $refund->save(['status' => PayRefund::STATUS_FAILED, 'notify_data' => $this->rawJson($result)]);

            return true;
        }

        $order = PayOrder::findOrFail($refund->pay_order_id);
        $this->confirmRefund($order, $refund, $this->rawJson($result));

        return true;
    }

    // ------------------------------------------------------------------
    // 退款（后台敏感操作；权限/二次确认在控制器层把关）
    // ------------------------------------------------------------------

    /**
     * 发起退款：校验（已支付/部分退款 + 退款额≤可退余额 120008）→ 建退款单 → 调渠道。
     * 支付宝同步返回成功即 confirm；微信异步等退款回调 confirm。
     */
    public function refund(string $orderNoOrOut, int $amount, string $reason = ''): PayRefund
    {
        $order = $this->findOrder($orderNoOrOut);

        if (!in_array((int) $order->status, [PayOrder::STATUS_PAID, PayOrder::STATUS_PART_REFUNDED], true)) {
            throw PayException::channel(ErrorCode::PAY_REFUND_FAILED, '订单当前状态不可退款');
        }
        if ($amount <= 0) {
            throw PayException::channel(ErrorCode::PAY_REFUND_FAILED, '退款金额必须大于 0（单位：分）');
        }
        $refundable = (int) $order->amount - (int) $order->refunded_amount;
        if ($amount > $refundable) {
            throw new PayException("退款金额 {$amount} 分超过可退余额 {$refundable} 分", ErrorCode::PAY_REFUND_OVERFLOW);
        }

        $refund                = new PayRefund();
        $refund->pay_order_id  = (int) $order->id;
        $refund->refund_no     = $this->generateNo('RF');
        $refund->out_refund_no = $this->generateNo('OR');
        $refund->channel       = (string) $order->channel;
        $refund->amount        = $amount;
        $refund->reason        = mb_substr($reason, 0, 255);
        $refund->status        = PayRefund::STATUS_REFUNDING;
        $refund->tenant_id     = PayOrder::currentTenantId();
        $refund->save();

        try {
            $result = PayManager::channel($order->channel)->refund($order, $refund);
        } catch (PayException $e) {
            // 渠道调用失败：退款单置失败，不留「退款中」悬挂
            $refund->save(['status' => PayRefund::STATUS_FAILED, 'notify_data' => mb_substr($e->getMessage(), 0, 255)]);
            throw $e;
        }

        // 渠道同步成功（支付宝多为同步）→ 立即确认；微信异步 → 等退款回调
        if ($this->isSyncRefundSuccess((string) $order->channel, $result)) {
            $this->confirmRefund($order, $refund, json_encode($result, JSON_UNESCAPED_UNICODE) ?: '');
        }

        return $refund;
    }

    /**
     * 确认退款成功（同步退款 / 退款回调共用）：事务内更新退款单 + 订单累计退款额与状态，
     * 提交后 fire RefundSuccessEvent。
     */
    protected function confirmRefund(PayOrder $order, PayRefund $refund, string $notifyData): void
    {
        Db::transaction(function () use ($order, $refund, $notifyData) {
            $refund->save([
                'status'      => PayRefund::STATUS_SUCCESS,
                'refunded_at' => date('Y-m-d H:i:s'),
                'notify_data' => $notifyData,
            ]);

            $newRefunded = (int) $order->refunded_amount + (int) $refund->amount;
            $target      = $newRefunded >= (int) $order->amount ? PayOrder::STATUS_REFUNDED : PayOrder::STATUS_PART_REFUNDED;
            $this->transitionTo($order, $target, ['refunded_amount' => $newRefunded]);
        });

        $fully = (int) $order->refunded_amount >= (int) $order->amount;
        Event::trigger(new RefundSuccessEvent(
            refundId: (int) $refund->id,
            refundNo: (string) $refund->refund_no,
            outRefundNo: (string) $refund->out_refund_no,
            orderId: (int) $order->id,
            orderNo: (string) $order->order_no,
            outTradeNo: (string) $order->out_trade_no,
            channel: (string) $order->channel,
            refundAmount: (int) $refund->amount,
            totalRefunded: (int) $order->refunded_amount,
            orderAmount: (int) $order->amount,
            fullyRefunded: $fully,
            bizType: (string) $order->biz_type,
            bizId: (string) $order->biz_id,
        ));
    }

    /**
     * 渠道退款是否同步成功（支付宝 code=10000；微信 status=SUCCESS）。
     *
     * @param array<string,mixed> $result
     */
    protected function isSyncRefundSuccess(string $channel, array $result): bool
    {
        if ($channel === 'alipay') {
            return (string) ($result['code'] ?? '') === '10000';
        }

        return (string) ($result['status'] ?? '') === 'SUCCESS';
    }

    // ------------------------------------------------------------------
    // 内部：订单定位 / 状态机 / 单号 / 审计
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

    /**
     * 写回调审计行（幂等锚点 / 验签失败留痕）。raw_body 经 LogSanitizer 思路落原文审计。
     */
    protected function writeNotifyLog(
        string $channel,
        string $eventType,
        string $idemNo,
        string $outTradeNo,
        string $transactionId,
        string $body,
        int $verified,
        int $processed,
        string $result,
    ): PayNotifyLog {
        return PayNotifyLog::create([
            'channel'        => $channel,
            'event_type'     => $eventType,
            'idem_no'        => $idemNo,
            'out_trade_no'   => $outTradeNo,
            'transaction_id' => $transactionId,
            'raw_body'       => mb_substr($body, 0, 60000),
            'verified'       => $verified,
            'processed'      => $processed,
            'result'         => mb_substr($result, 0, 255),
        ]);
    }

    /**
     * 回调原文转 JSON（落 notify_data 审计；失败兜底空串）。
     */
    protected function rawJson(NotifyResult $result): string
    {
        return json_encode($result->raw, JSON_UNESCAPED_UNICODE) ?: '';
    }
}

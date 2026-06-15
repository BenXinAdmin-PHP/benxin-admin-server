<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   支付回调解析结果 DTO — 验签结果 + 标准化字段
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\pay;

/**
 * 回调验签 + 解析的标准化结果（渠道差异收敛于 Provider，BxPay 只认本 DTO）。
 * - verified：验签是否通过（false → BxPay 记 notify_log 后拒绝 120005）。
 * - 金额统一为整型分；渠道原始报文存 raw（落 notify_log 审计）。
 */
class NotifyResult
{
    /**
     * @param bool                 $verified      验签是否通过
     * @param string               $eventType     pay / refund
     * @param string               $outTradeNo    对外交易号
     * @param string               $transactionId 渠道交易号（可空）
     * @param int                  $amount        金额（分）；pay=订单支付额，refund=本次退款额
     * @param bool                 $tradeSuccess  渠道侧该笔是否成功（退款回调可能是失败/关闭）
     * @param string               $outRefundNo   退款回调：对外退款号
     * @param string               $refundId      退款回调：渠道退款号
     * @param array<string,mixed>  $raw           渠道原始报文（审计/排查）
     */
    public function __construct(
        public bool $verified,
        public string $eventType,
        public string $outTradeNo,
        public string $transactionId = '',
        public int $amount = 0,
        public bool $tradeSuccess = true,
        public string $outRefundNo = '',
        public string $refundId = '',
        public array $raw = [],
    ) {
    }

    /**
     * 幂等标识：支付回调取 out_trade_no（一单一付）；退款回调取 out_refund_no（一单可多退）。
     */
    public function idemNo(): string
    {
        return $this->eventType === 'refund' ? $this->outRefundNo : $this->outTradeNo;
    }
}

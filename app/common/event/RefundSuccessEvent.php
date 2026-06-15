<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   事件 — 退款成功（业务解耦，开源边界命脉）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\event;

/**
 * 退款成功事件（★开源边界 §1）。
 *
 * 退款渠道确认（退款回调/查询）成功、退款记录与订单状态更新后，BxPay fire 本事件。
 * 上层闭源业务监听本事件处理后续（如关闭课程权限、回滚库存），
 * 用 biz_type/biz_id 路由定位上层单据。底座只透传，不理解。
 *
 * 载荷不含任何敏感密钥（§8）。
 */
class RefundSuccessEvent
{
    public function __construct(
        public int $refundId,
        public string $refundNo,
        public string $outRefundNo,
        public int $orderId,
        public string $orderNo,
        public string $outTradeNo,
        public string $channel,
        public int $refundAmount,       // 本次退款金额（分）
        public int $totalRefunded,      // 订单累计已退（分）
        public int $orderAmount,        // 订单总额（分）
        public bool $fullyRefunded,     // 是否已全额退完
        public string $bizType,
        public string $bizId,
    ) {
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   事件 — 支付成功（业务解耦，开源边界命脉）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\event;

/**
 * 支付成功事件（★开源边界 §1）。
 *
 * 回调验签/幂等/金额/状态机全部通过、订单更新为已支付后，BxPay fire 本事件。
 * **底座不写具体 listener**——上层闭源业务在自己项目 app/event.php 注册监听，
 * 用 biz_type 路由到自己的业务（如 biz_type='course_order' → 开通课程），
 * 用 biz_id 定位上层单据。底座只透传 biz_type/biz_id，不理解其含义。
 *
 * 载荷不含任何敏感密钥（§8）。
 */
class PaySuccessEvent
{
    public function __construct(
        public int $orderId,
        public string $orderNo,
        public string $outTradeNo,
        public string $channel,
        public string $tradeType,
        public int $amount,            // 金额（分）
        public string $transactionId,  // 渠道交易号
        public int $userId,
        public string $bizType,        // 上层业务类型（解耦）
        public string $bizId,          // 上层业务单据id（解耦）
        public string $attach = '',
    ) {
    }
}

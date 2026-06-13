<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   示例监听 — 支付/退款成功（空示例，演示上层如何解耦接入）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\listener;

use app\common\event\PaySuccessEvent;
use app\common\event\RefundSuccessEvent;
use think\facade\Log;

/**
 * ★业务解耦示例监听（开源边界 §1）——**底座只演示，不含具体业务**。
 *
 * 上层闭源项目接入方式（二选一）：
 *  1) 在自己项目的 app/event.php 注册自己的 listener：
 *       'listen' => [
 *           PaySuccessEvent::class    => [\app\xxx\listener\CourseGrantListener::class],
 *           RefundSuccessEvent::class => [\app\xxx\listener\CourseRevokeListener::class],
 *       ]
 *  2) 在 listener 内用 biz_type 路由、biz_id 定位上层单据：
 *       match ($event->bizType) {
 *           'course_order' => $this->grantCourse($event->bizId),
 *           'vip_order'    => $this->openVip($event->bizId),
 *           default        => null,   // 非本业务，忽略
 *       };
 *
 * 本示例监听已在底座 app/event.php 注册，仅记一条 debug 日志证明事件链路联通，
 * **不做任何业务动作**。上层项目应替换为自己的 listener，或在自己仓库另行注册。
 */
class PayBizExampleListener
{
    /**
     * 监听支付成功（TP 事件回调；类绑定到具体事件由 event.php 决定）。
     */
    public function onPaySuccess(PaySuccessEvent $event): void
    {
        // 底座零业务：仅留痕，证明 fire→listener 链路联通。
        Log::debug(sprintf(
            '[PayBizExample] 支付成功事件已派发：order_no=%s biz_type=%s biz_id=%s amount=%d（上层应在此路由到自己的业务）',
            $event->orderNo,
            $event->bizType,
            $event->bizId,
            $event->amount,
        ));
    }

    /**
     * 监听退款成功。
     */
    public function onRefundSuccess(RefundSuccessEvent $event): void
    {
        Log::debug(sprintf(
            '[PayBizExample] 退款成功事件已派发：refund_no=%s biz_type=%s biz_id=%s amount=%d 全额=%s',
            $event->refundNo,
            $event->bizType,
            $event->bizId,
            $event->refundAmount,
            $event->fullyRefunded ? 'yes' : 'no',
        ));
    }
}

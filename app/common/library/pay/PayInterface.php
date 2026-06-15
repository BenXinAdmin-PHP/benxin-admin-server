<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   支付渠道抽象 — 多渠道统一契约（下单/查单/退款/回调）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\pay;

use app\common\model\PayOrder;
use app\common\model\PayRefund;
use think\Response;

/**
 * 支付渠道统一契约（M4-C）。WechatPayProvider / AlipayProvider 各自实现，
 * 内部基于 yansongda/pay v3；BxPay 经 PayManager 拿 Provider，只面向本接口编程。
 *
 * 设计：可注入（PayManager::fake）便于离线测试——BxPay 编排（状态机/幂等/金额/event）
 * 用 FakeProvider 全覆盖，渠道真实下单/验签为「需真实商户号」边界。
 */
interface PayInterface
{
    /**
     * 统一下单 → 返回前端调起参数（渠道差异：jsapi 调起串 / native code_url / wap·page 跳转 URL）。
     *
     * @return array<string,mixed>
     */
    public function prepay(PayOrder $order): array;

    /**
     * 查单（按对外交易号）→ 渠道原始结果。
     *
     * @return array<string,mixed>
     */
    public function query(string $outTradeNo): array;

    /**
     * 发起退款 → 渠道原始结果。
     *
     * @return array<string,mixed>
     */
    public function refund(PayOrder $order, PayRefund $refund): array;

    /**
     * 退款查询（支付宝同步多用）→ 渠道原始结果。
     *
     * @return array<string,mixed>
     */
    public function refundQuery(PayOrder $order, PayRefund $refund): array;

    /**
     * 关单。
     */
    public function close(string $outTradeNo): void;

    /**
     * 验签 + 解析回调（渠道差异收敛为 NotifyResult）。
     *
     * @param array<string,mixed> $headers 回调请求头（微信 v3 验签需）
     * @param string              $body    回调原文
     * @param string              $eventType pay / refund
     */
    public function verifyNotify(array $headers, string $body, string $eventType): NotifyResult;

    /**
     * 渠道 ACK（成功）：微信 v3 {code:SUCCESS}；支付宝 success。
     */
    public function ackSuccess(): Response;

    /**
     * 渠道 ACK（失败）：触发渠道重试。
     */
    public function ackFail(string $msg = ''): Response;
}

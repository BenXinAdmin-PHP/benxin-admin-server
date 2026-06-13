<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   微信支付渠道 — yansongda/pay v3 封装（jsapi/native + 退款 + 回调验签）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\pay;

use app\admin\service\ConfigService;
use app\common\exception\PayException;
use app\common\library\ErrorCode;
use app\common\model\PayOrder;
use app\common\model\PayRefund;
use think\Response;
use Throwable;
use Yansongda\Pay\Pay;

/**
 * 微信支付渠道（M4-C）。
 * - 商户参数取 bx_config group=pay（wxpay_ 前缀，敏感 AES 解密）；**appid 复用 wechat 组**（mp/mini）。
 * - 私钥 pem 内容 AES 入库，运行时解密以字符串注入 yansongda（v3 支持私钥/证书字符串）。
 * - 本阶段实现 jsapi（公众号/小程序）+ native（扫码）；h5/app 留扩展位。
 * - 下单 order 数组构造（buildPrepayOrder/buildRefundOrder）为纯方法，离线可断言；
 *   真实 HTTP 调起/验签为「需真实商户号」边界。
 */
class WechatPayProvider implements PayInterface
{
    /** trade_type → yansongda 快捷方法 */
    protected const TRADE_SHORTCUT = [
        'jsapi'  => 'mp',
        'native' => 'scan',
    ];

    public function __construct(protected ConfigService $config)
    {
    }

    // ------------------------------------------------------------------
    // 下单 / 查单
    // ------------------------------------------------------------------

    public function prepay(PayOrder $order): array
    {
        $shortcut = self::TRADE_SHORTCUT[$order->trade_type] ?? null;
        if ($shortcut === null) {
            throw PayException::channel(ErrorCode::PAY_PREPAY_FAILED, "微信支付暂不支持的 trade_type：{$order->trade_type}");
        }

        $payOrder = $this->buildPrepayOrder($order);
        try {
            $result = Pay::wechat($this->channelConfig())->{$shortcut}($payOrder);

            return $result instanceof \Yansongda\Supports\Collection ? $result->toArray() : (array) $result;
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_PREPAY_FAILED);
        }
    }

    public function query(string $outTradeNo): array
    {
        try {
            return Pay::wechat($this->channelConfig())->query(['out_trade_no' => $outTradeNo])->toArray();
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_CHANNEL_ERROR);
        }
    }

    // ------------------------------------------------------------------
    // 退款
    // ------------------------------------------------------------------

    public function refund(PayOrder $order, PayRefund $refund): array
    {
        try {
            return Pay::wechat($this->channelConfig())->refund($this->buildRefundOrder($order, $refund))->toArray();
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_REFUND_FAILED);
        }
    }

    public function refundQuery(PayOrder $order, PayRefund $refund): array
    {
        try {
            return Pay::wechat($this->channelConfig())->query([
                '_action'       => 'refund',
                'out_refund_no' => $refund->out_refund_no,
            ])->toArray();
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_CHANNEL_ERROR);
        }
    }

    public function close(string $outTradeNo): void
    {
        try {
            Pay::wechat($this->channelConfig())->close(['out_trade_no' => $outTradeNo]);
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_CHANNEL_ERROR);
        }
    }

    // ------------------------------------------------------------------
    // 回调验签 + ACK
    // ------------------------------------------------------------------

    public function verifyNotify(array $headers, string $body, string $eventType): NotifyResult
    {
        try {
            $params = $this->normalizeHeaders($headers);
            // yansongda v3 callback：传 headers + body 完成 v3 验签（平台证书 / APIv3 key）
            $data = Pay::wechat($this->channelConfig())->callback($params + ['body' => $body])->toArray();

            return $this->parseNotify($data, $eventType, $body);
        } catch (Throwable) {
            // 验签失败不泄露细节，标记未验签交 BxPay 记审计 + 拒绝
            return new NotifyResult(false, $eventType, '', raw: ['body' => $body]);
        }
    }

    /**
     * 解析微信回调报文（验签后）为标准 NotifyResult。微信金额单位为分。
     *
     * @param array<string,mixed> $data
     */
    public function parseNotify(array $data, string $eventType, string $body = ''): NotifyResult
    {
        if ($eventType === 'refund') {
            $amount = (int) ($data['amount']['refund'] ?? $data['amount']['payer_refund'] ?? 0);

            return new NotifyResult(
                verified: true,
                eventType: 'refund',
                outTradeNo: (string) ($data['out_trade_no'] ?? ''),
                transactionId: (string) ($data['transaction_id'] ?? ''),
                amount: $amount,
                tradeSuccess: ($data['refund_status'] ?? '') === 'SUCCESS',
                outRefundNo: (string) ($data['out_refund_no'] ?? ''),
                refundId: (string) ($data['refund_id'] ?? ''),
                raw: $data,
            );
        }

        return new NotifyResult(
            verified: true,
            eventType: 'pay',
            outTradeNo: (string) ($data['out_trade_no'] ?? ''),
            transactionId: (string) ($data['transaction_id'] ?? ''),
            amount: (int) ($data['amount']['total'] ?? 0),
            tradeSuccess: ($data['trade_state'] ?? 'SUCCESS') === 'SUCCESS',
            raw: $data,
        );
    }

    public function ackSuccess(): Response
    {
        return Response::create(['code' => 'SUCCESS', 'message' => '成功'], 'json', 200);
    }

    public function ackFail(string $msg = ''): Response
    {
        return Response::create(['code' => 'FAIL', 'message' => $msg !== '' ? $msg : '失败'], 'json', 200)
            ->code(500);
    }

    // ------------------------------------------------------------------
    // order 数组构造（纯方法，离线断言）
    // ------------------------------------------------------------------

    /**
     * 构造下单 order 数组（v3：金额 amount.total 单位分；jsapi 需 payer.openid）。
     *
     * @return array<string,mixed>
     */
    public function buildPrepayOrder(PayOrder $order): array
    {
        $payOrder = [
            'out_trade_no' => $order->out_trade_no,
            'description'  => $order->subject !== '' ? $order->subject : $order->out_trade_no,
            'amount'       => ['total' => (int) $order->amount, 'currency' => 'CNY'],
        ];
        if ($order->trade_type === 'jsapi') {
            if ((string) $order->openid === '') {
                throw PayException::channel(ErrorCode::PAY_PREPAY_FAILED, '微信 jsapi 下单缺少 openid');
            }
            $payOrder['payer'] = ['openid' => $order->openid];
        }
        if ((string) $order->attach !== '') {
            $payOrder['attach'] = $order->attach;
        }
        $notifyUrl = trim((string) $this->config->get('wxpay_notify_url', ''));
        if ($notifyUrl !== '') {
            $payOrder['notify_url'] = $notifyUrl;
        }

        return $payOrder;
    }

    /**
     * 构造退款 order 数组（v3：amount.refund/total 单位分）。
     *
     * @return array<string,mixed>
     */
    public function buildRefundOrder(PayOrder $order, PayRefund $refund): array
    {
        return [
            'out_trade_no'  => $order->out_trade_no,
            'out_refund_no' => $refund->out_refund_no,
            'amount'        => [
                'refund'   => (int) $refund->amount,
                'total'    => (int) $order->amount,
                'currency' => 'CNY',
            ],
            'reason' => $refund->reason !== '' ? $refund->reason : '用户退款',
        ];
    }

    // ------------------------------------------------------------------
    // 配置注入
    // ------------------------------------------------------------------

    /**
     * 组装 yansongda 微信配置（敏感项 AES 解密；私钥以字符串注入，不落文件）。
     * appid 复用 wechat 组（优先 mp_app_id，回落 mini_app_id）。
     *
     * @return array<string,mixed>
     */
    public function channelConfig(): array
    {
        $mchId      = trim((string) $this->config->get('wxpay_mch_id', ''));
        $apiV3Key   = trim((string) $this->config->get('wxpay_api_v3_key', ''));
        $certSerial = trim((string) $this->config->get('wxpay_cert_serial', ''));
        $privateKey = trim((string) $this->config->get('wxpay_private_key', ''));
        $appId      = trim((string) $this->config->get('mp_app_id', '')) ?: trim((string) $this->config->get('mini_app_id', ''));

        foreach (['wxpay_mch_id' => $mchId, 'wxpay_api_v3_key' => $apiV3Key, 'wxpay_private_key' => $privateKey] as $k => $v) {
            if ($v === '') {
                throw PayException::configMissing($k);
            }
        }
        if ($appId === '') {
            throw PayException::configMissing('mp_app_id/mini_app_id（wechat 组）');
        }

        return [
            'pay' => [
                'wechat' => [
                    'default' => [
                        'mp_app_id'       => $appId,
                        'mch_id'          => $mchId,
                        'mch_secret_key'  => $apiV3Key,
                        'mch_secret_cert' => $privateKey,
                        'mch_public_cert_path' => $certSerial !== '' ? [$certSerial => ''] : null,
                        'mode'            => Pay::MODE_NORMAL,
                    ],
                ],
            ],
            // 关闭 yansongda 内置日志（避免私钥/报文落第三方日志，§8）
            'logger' => ['enable' => false],
            'http'   => ['timeout' => 10.0],
        ];
    }

    /**
     * 头规整为小写键（微信 v3 验签需 Wechatpay-Signature 等）。
     *
     * @param array<string,mixed> $headers
     * @return array<string,mixed>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[strtolower((string) $k)] = is_array($v) ? ($v[0] ?? '') : $v;
        }

        return $out;
    }

    /**
     * 渠道异常包装为 PayException（透传渠道码、不泄露敏感堆栈）。
     */
    protected function wrap(Throwable $e, int $bizCode): PayException
    {
        return PayException::channel($bizCode, '微信支付渠道错误：' . $e->getMessage(), (string) $e->getCode());
    }
}

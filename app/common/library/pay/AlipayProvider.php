<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   支付宝渠道 — yansongda/pay v3 封装（wap/page + 退款 + 回调验签）
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
 * 支付宝渠道（M4-C）。
 * - 参数取 bx_config group=pay（alipay_ 前缀，私钥敏感 AES 解密以字符串注入）。
 * - 本阶段实现 wap（手机网站）+ page（电脑网站）；app 留扩展位。
 * - ★金额单位差异：支付宝 total_amount 单位为**元**（两位小数字符串），
 *   底座统一整型分 → 构造时 fenToYuan 换算，回调 yuanToFen 还原（与微信「分」统一对账）。
 */
class AlipayProvider implements PayInterface
{
    /** 底座 trade_type → yansongda 支付宝快捷方法 */
    protected const TRADE_SHORTCUT = [
        'wap'  => 'wap',
        'page' => 'web',
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
            throw PayException::channel(ErrorCode::PAY_PREPAY_FAILED, "支付宝暂不支持的 trade_type：{$order->trade_type}");
        }

        try {
            $result = Pay::alipay($this->channelConfig())->{$shortcut}($this->buildPrepayOrder($order));
            // wap/web 返回的是跳转 HTML/Response，统一取其字符串供前端跳转
            if ($result instanceof \Psr\Http\Message\ResponseInterface) {
                return ['type' => 'redirect', 'body' => (string) $result->getBody()];
            }

            return $result instanceof \Yansongda\Supports\Collection ? $result->toArray() : (array) $result;
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_PREPAY_FAILED);
        }
    }

    public function query(string $outTradeNo): array
    {
        try {
            return Pay::alipay($this->channelConfig())->query(['out_trade_no' => $outTradeNo])->toArray();
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
            return Pay::alipay($this->channelConfig())->refund($this->buildRefundOrder($order, $refund))->toArray();
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_REFUND_FAILED);
        }
    }

    public function refundQuery(PayOrder $order, PayRefund $refund): array
    {
        try {
            return Pay::alipay($this->channelConfig())->query([
                '_action'       => 'refund_query',
                'out_trade_no'  => $order->out_trade_no,
                'out_request_no' => $refund->out_refund_no,
            ])->toArray();
        } catch (Throwable $e) {
            throw $this->wrap($e, ErrorCode::PAY_CHANNEL_ERROR);
        }
    }

    public function close(string $outTradeNo): void
    {
        try {
            Pay::alipay($this->channelConfig())->close(['out_trade_no' => $outTradeNo]);
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
            // 支付宝回调为 form 编码 body；yansongda callback 接收 array
            parse_str($body, $params);
            $data = Pay::alipay($this->channelConfig())->callback($params)->toArray();

            return $this->parseNotify($data, $eventType, $body);
        } catch (Throwable) {
            return new NotifyResult(false, $eventType, '', raw: ['body' => $body]);
        }
    }

    /**
     * 解析支付宝回调（验签后）为标准 NotifyResult。金额由元换算为分。
     *
     * @param array<string,mixed> $data
     */
    public function parseNotify(array $data, string $eventType, string $body = ''): NotifyResult
    {
        // 支付宝支付与退款共用 notify（以 refund_fee/trade_status 区分）
        $isRefund = $eventType === 'refund' || isset($data['refund_fee']);
        if ($isRefund) {
            return new NotifyResult(
                verified: true,
                eventType: 'refund',
                outTradeNo: (string) ($data['out_trade_no'] ?? ''),
                transactionId: (string) ($data['trade_no'] ?? ''),
                amount: self::yuanToFen((string) ($data['refund_fee'] ?? '0')),
                tradeSuccess: true,
                outRefundNo: (string) ($data['out_biz_no'] ?? ''),
                refundId: (string) ($data['trade_no'] ?? ''),
                raw: $data,
            );
        }

        $tradeStatus = (string) ($data['trade_status'] ?? '');

        return new NotifyResult(
            verified: true,
            eventType: 'pay',
            outTradeNo: (string) ($data['out_trade_no'] ?? ''),
            transactionId: (string) ($data['trade_no'] ?? ''),
            amount: self::yuanToFen((string) ($data['total_amount'] ?? '0')),
            tradeSuccess: in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true),
            raw: $data,
        );
    }

    public function ackSuccess(): Response
    {
        // 支付宝要求纯文本 success
        return Response::create('success', 'html', 200);
    }

    public function ackFail(string $msg = ''): Response
    {
        return Response::create('fail', 'html', 200);
    }

    // ------------------------------------------------------------------
    // order 数组构造（纯方法，离线断言）
    // ------------------------------------------------------------------

    /**
     * 构造下单 order（支付宝 total_amount 单位元，两位小数字符串）。
     *
     * @return array<string,mixed>
     */
    public function buildPrepayOrder(PayOrder $order): array
    {
        $payOrder = [
            'out_trade_no' => $order->out_trade_no,
            'total_amount' => self::fenToYuan((int) $order->amount),
            'subject'      => $order->subject !== '' ? $order->subject : $order->out_trade_no,
        ];
        if ((string) $order->attach !== '') {
            $payOrder['passback_params'] = rawurlencode($order->attach);
        }
        $notifyUrl = trim((string) $this->config->get('alipay_notify_url', ''));
        if ($notifyUrl !== '') {
            $payOrder['_notify_url'] = $notifyUrl;
        }

        return $payOrder;
    }

    /**
     * 构造退款 order（refund_amount 单位元）。
     *
     * @return array<string,mixed>
     */
    public function buildRefundOrder(PayOrder $order, PayRefund $refund): array
    {
        return [
            'out_trade_no'   => $order->out_trade_no,
            'refund_amount'  => self::fenToYuan((int) $refund->amount),
            'out_request_no' => $refund->out_refund_no,
            'refund_reason'  => $refund->reason !== '' ? $refund->reason : '用户退款',
        ];
    }

    // ------------------------------------------------------------------
    // 金额换算（分 ↔ 元）
    // ------------------------------------------------------------------

    /**
     * 分 → 元（两位小数字符串，避免浮点精度）。
     */
    public static function fenToYuan(int $fen): string
    {
        return number_format($fen / 100, 2, '.', '');
    }

    /**
     * 元 → 分（四舍五入，避免浮点误差）。
     */
    public static function yuanToFen(string $yuan): int
    {
        return (int) round(((float) $yuan) * 100);
    }

    // ------------------------------------------------------------------
    // 配置注入
    // ------------------------------------------------------------------

    /**
     * 组装 yansongda 支付宝配置（私钥敏感 AES 解密以字符串注入，不落文件）。
     *
     * @return array<string,mixed>
     */
    public function channelConfig(): array
    {
        $appId      = trim((string) $this->config->get('alipay_app_id', ''));
        $privateKey = trim((string) $this->config->get('alipay_private_key', ''));
        $publicKey  = trim((string) $this->config->get('alipay_public_key', ''));

        foreach (['alipay_app_id' => $appId, 'alipay_private_key' => $privateKey, 'alipay_public_key' => $publicKey] as $k => $v) {
            if ($v === '') {
                throw PayException::configMissing($k);
            }
        }

        return [
            'pay' => [
                'alipay' => [
                    'default' => [
                        'app_id'                  => $appId,
                        'app_secret_cert'         => $privateKey,
                        'alipay_public_cert_path' => $publicKey,
                        'mode'                    => Pay::MODE_NORMAL,
                    ],
                ],
            ],
            'logger' => ['enable' => false],
            'http'   => ['timeout' => 10.0],
        ];
    }

    /**
     * 渠道异常包装为 PayException。
     */
    protected function wrap(Throwable $e, int $bizCode): PayException
    {
        return PayException::channel($bizCode, '支付宝渠道错误：' . $e->getMessage(), (string) $e->getCode());
    }
}

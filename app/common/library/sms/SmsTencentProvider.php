<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   腾讯云短信渠道 — SendSms + 自建 TC3-HMAC-SHA256 签名
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\sms;

use app\common\exception\SmsException;
use app\common\library\ErrorCode;

/**
 * 腾讯云短信（sms 2021-01-11 SendSms）。
 * 自建 TC3-HMAC-SHA256 签名（CanonicalRequest → StringToSign → 派生签名密钥 → Signature），
 * 算法对照腾讯云官方签名方法 v3 文档样例离线逐字校验（CanonicalRequest/StringToSign + HMAC 链）。
 * SecretId/SecretKey 由 MessageManager 经 ConfigService 解密注入，不硬编码。
 */
class SmsTencentProvider implements SmsChannelInterface
{
    public const HOST     = 'sms.tencentcloudapi.com';
    public const SERVICE  = 'sms';
    public const ENDPOINT = 'https://sms.tencentcloudapi.com/';
    public const VERSION  = '2021-01-11';
    public const REGION   = 'ap-guangzhou';
    public const ALGO     = 'TC3-HMAC-SHA256';

    public function __construct(
        protected string $secretId,
        protected string $secretKey,
        protected string $sdkAppId,
        protected string $defaultSignName,
        protected SmsHttpClientInterface $http,
    ) {
    }

    public function name(): string
    {
        return 'tencent';
    }

    public function send(string $mobile, string $templateCode, array $params, ?string $signName = null): SmsResult
    {
        $payload = json_encode([
            'PhoneNumberSet'   => [$this->normalizeMobile($mobile)],
            'SmsSdkAppId'      => $this->sdkAppId,
            'SignName'         => $signName ?? $this->defaultSignName,
            'TemplateId'       => $templateCode,
            'TemplateParamSet' => array_values(array_map('strval', $params)),
        ], JSON_UNESCAPED_UNICODE) ?: '{}';

        $timestamp = time();
        $headers   = $this->buildHeaders($timestamp, $payload, 'SendSms');
        $resp      = $this->http->postJson(self::ENDPOINT, $payload, $headers);

        $response = $resp['Response'] ?? [];
        if (isset($response['Error'])) {
            $err = $response['Error'];
            throw SmsException::channel(
                ErrorCode::SMS_SEND_FAILED,
                '腾讯云短信发送失败：' . (string) ($err['Message'] ?? ''),
                (string) ($err['Code'] ?? ''),
            );
        }
        $status = $response['SendStatusSet'][0] ?? [];
        if ((string) ($status['Code'] ?? '') !== 'Ok') {
            throw SmsException::channel(
                ErrorCode::SMS_SEND_FAILED,
                '腾讯云短信发送失败：' . (string) ($status['Message'] ?? ''),
                (string) ($status['Code'] ?? ''),
            );
        }

        return new SmsResult(true, (string) ($response['RequestId'] ?? ''), 'Ok', (string) ($status['Message'] ?? ''));
    }

    /**
     * 构造含 TC3 签名 Authorization 的请求头。
     *
     * @return array<string,string>
     */
    public function buildHeaders(int $timestamp, string $payload, string $action): array
    {
        $date             = gmdate('Y-m-d', $timestamp);
        $canonicalRequest = self::canonicalRequest(self::HOST, $action, $payload);
        $stringToSign     = self::stringToSign($timestamp, $date, self::SERVICE, $canonicalRequest);
        $signingKey       = self::signingKey($this->secretKey, $date, self::SERVICE);
        $signature        = self::sign($signingKey, $stringToSign);

        $authorization = sprintf(
            '%s Credential=%s/%s/%s/tc3_request, SignedHeaders=content-type;host;x-tc-action, Signature=%s',
            self::ALGO,
            $this->secretId,
            $date,
            self::SERVICE,
            $signature,
        );

        return [
            'Authorization' => $authorization,
            'Content-Type'  => 'application/json; charset=utf-8',
            'Host'          => self::HOST,
            'X-TC-Action'   => $action,
            'X-TC-Timestamp' => (string) $timestamp,
            'X-TC-Version'  => self::VERSION,
            'X-TC-Region'   => self::REGION,
        ];
    }

    // ------------------------------------------------------------------
    // TC3 签名纯方法（离线对照官方样例）
    // ------------------------------------------------------------------

    /**
     * CanonicalRequest（签名头固定 content-type;host;x-tc-action）。
     */
    public static function canonicalRequest(string $host, string $action, string $payload): string
    {
        $contentType   = 'application/json; charset=utf-8';
        $signedHeaders = 'content-type;host;x-tc-action';
        $canonHeaders  = "content-type:{$contentType}\nhost:{$host}\nx-tc-action:" . strtolower($action) . "\n";

        return implode("\n", [
            'POST',
            '/',
            '',
            $canonHeaders,
            $signedHeaders,
            hash('sha256', $payload),
        ]);
    }

    /**
     * StringToSign。
     */
    public static function stringToSign(int $timestamp, string $date, string $service, string $canonicalRequest): string
    {
        return implode("\n", [
            self::ALGO,
            (string) $timestamp,
            "{$date}/{$service}/tc3_request",
            hash('sha256', $canonicalRequest),
        ]);
    }

    /**
     * 派生签名密钥（返回原始字节）：
     * SecretDate=HMAC("TC3".key, date) → SecretService=HMAC(SecretDate, service) → HMAC(SecretService, "tc3_request")。
     */
    public static function signingKey(string $secretKey, string $date, string $service): string
    {
        $secretDate    = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);

        return hash_hmac('sha256', 'tc3_request', $secretService, true);
    }

    /**
     * 最终签名（hex）。
     */
    public static function sign(string $signingKeyRaw, string $stringToSign): string
    {
        return hash_hmac('sha256', $stringToSign, $signingKeyRaw);
    }

    /**
     * 手机号规整为 E.164（默认中国 +86；已带 + 原样）。
     */
    protected function normalizeMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        if (str_starts_with($mobile, '+')) {
            return $mobile;
        }

        return '+86' . $mobile;
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   阿里云短信渠道 — SendSms + 自建 RPC 签名（HMAC-SHA1）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\sms;

use app\common\exception\SmsException;
use app\common\library\ErrorCode;

/**
 * 阿里云短信（Dysmsapi 2017-05-25 SendSms）。
 * 自建 RPC 风格签名（参数排序 + 特殊字符 percentEncode + HMAC-SHA1 + Base64），
 * 算法对照阿里云官方 RPC 签名文档样例（DescribeDedicatedHosts 向量）离线逐字校验。
 * AK/SK 由 MessageManager 经 ConfigService 解密注入，不硬编码。
 */
class SmsAliProvider implements SmsChannelInterface
{
    public const ENDPOINT = 'https://dysmsapi.aliyuncs.com/';

    public function __construct(
        protected string $accessKeyId,
        protected string $accessKeySecret,
        protected string $defaultSignName,
        protected SmsHttpClientInterface $http,
    ) {
    }

    public function name(): string
    {
        return 'ali';
    }

    public function send(string $mobile, string $templateCode, array $params, ?string $signName = null): SmsResult
    {
        $query = $this->buildSendParams($mobile, $templateCode, $params, $signName);
        $resp  = $this->http->get(self::ENDPOINT, $query);

        $code = (string) ($resp['Code'] ?? '');
        if ($code !== 'OK') {
            throw SmsException::channel(
                ErrorCode::SMS_SEND_FAILED,
                '阿里云短信发送失败：' . (string) ($resp['Message'] ?? $code),
                $code,
            );
        }

        return new SmsResult(true, (string) ($resp['BizId'] ?? ''), $code, (string) ($resp['Message'] ?? ''));
    }

    /**
     * 构造已签名的 SendSms 请求参数（公共参数 + 业务参数 + Signature）。
     *
     * @param array<string,string> $params
     * @return array<string,string>
     */
    public function buildSendParams(string $mobile, string $templateCode, array $params, ?string $signName): array
    {
        $public = [
            'AccessKeyId'      => $this->accessKeyId,
            'Action'           => 'SendSms',
            'Format'           => 'JSON',
            'RegionId'         => 'cn-hangzhou',
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => bin2hex(random_bytes(16)),
            'SignatureVersion' => '1.0',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'Version'          => '2017-05-25',
            'PhoneNumbers'     => $mobile,
            'SignName'         => $signName ?? $this->defaultSignName,
            'TemplateCode'     => $templateCode,
            'TemplateParam'    => json_encode($params, JSON_UNESCAPED_UNICODE) ?: '{}',
        ];

        $public['Signature'] = self::rpcSignature($public, $this->accessKeySecret);

        return $public;
    }

    /**
     * RPC 签名（纯方法，离线对照官方样例）：
     * stringToSign = METHOD&percentEncode('/')&percentEncode(canonicalizedQuery)
     * signature    = base64(HMAC-SHA1(secret.'&', stringToSign))
     *
     * @param array<string,string> $params 不含 Signature
     */
    public static function rpcSignature(array $params, string $secret, string $method = 'GET'): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = self::percentEncode((string) $k) . '=' . self::percentEncode((string) $v);
        }
        $canonical    = implode('&', $pairs);
        $stringToSign = $method . '&' . self::percentEncode('/') . '&' . self::percentEncode($canonical);

        return base64_encode(hash_hmac('sha1', $stringToSign, $secret . '&', true));
    }

    /**
     * 阿里云 percentEncode：rawurlencode 后 + → %20、* → %2A、%7E → ~。
     */
    public static function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
}

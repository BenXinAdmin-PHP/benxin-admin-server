<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信 HTTP 客户端 curl 实现 — 强制 HTTPS + 超时 + JSON 解码
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\sms;

use app\common\exception\SmsException;
use app\common\library\ErrorCode;

/**
 * 短信渠道 HTTP 客户端默认实现（curl）。仅 HTTPS + 证书校验 + 超时；JSON 解码。
 * 传输层失败 → SmsException(130099)。
 */
class SmsCurlHttpClient implements SmsHttpClientInterface
{
    protected const CONNECT_TIMEOUT = 5;
    protected const TIMEOUT         = 10;

    public function get(string $url, array $query = []): array
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->exec('GET', $url, null, []);
    }

    public function postJson(string $url, string $body, array $headers = []): array
    {
        $hdr = ['Content-Type: application/json; charset=utf-8'];
        foreach ($headers as $k => $v) {
            $hdr[] = $k . ': ' . $v;
        }

        return $this->exec('POST', $url, $body, $hdr);
    }

    /**
     * @param array<int,string> $headers
     * @return array<string,mixed>
     */
    protected function exec(string $method, string $url, ?string $body, array $headers): array
    {
        if (!str_starts_with($url, 'https://')) {
            throw SmsException::channel(ErrorCode::SMS_CHANNEL_ERROR, '仅允许 HTTPS 调用短信接口');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $resp  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $errno !== 0) {
            throw SmsException::channel(ErrorCode::SMS_CHANNEL_ERROR, "短信接口请求失败：curl[{$errno}] {$error}");
        }

        $decoded = json_decode((string) $resp, true);
        if (!is_array($decoded)) {
            throw SmsException::channel(ErrorCode::SMS_CHANNEL_ERROR, '短信接口响应非合法 JSON');
        }

        return $decoded;
    }
}

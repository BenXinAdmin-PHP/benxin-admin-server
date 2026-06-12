<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   微信 HTTP 客户端 curl 实现 — 强制 HTTPS + 超时 + JSON 解码
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

use app\common\exception\WechatException;

/**
 * 微信 API HTTP 客户端默认实现（curl）。
 * - 仅允许 HTTPS（§8 安全基线），开启证书校验。
 * - 连接超时 5s / 总超时 10s。
 * - 响应统一按 JSON 解码（微信部分接口 Content-Type 为 text/plain，不依赖响应头）。
 * - 传输层失败/非 JSON 响应 → WechatException::transport（140099）。
 */
class CurlHttpClient implements HttpClientInterface
{
    protected const CONNECT_TIMEOUT = 5;
    protected const TIMEOUT         = 10;

    public function get(string $url, array $query = []): array
    {
        return $this->request('GET', $this->buildUrl($url, $query));
    }

    public function postJson(string $url, array $body = [], array $query = []): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw WechatException::transport('请求体 JSON 编码失败');
        }

        return $this->request('POST', $this->buildUrl($url, $query), $payload);
    }

    /**
     * 执行请求并解码 JSON。
     *
     * @return array<string,mixed>
     */
    protected function request(string $method, string $url, ?string $jsonPayload = null): array
    {
        if (!str_starts_with($url, 'https://')) {
            throw WechatException::transport('仅允许 HTTPS 调用微信接口');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload ?? '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        }

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            throw WechatException::transport("curl[{$errno}] {$error}");
        }
        if ($http >= 400) {
            throw WechatException::transport("HTTP {$http}");
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw WechatException::transport('响应非合法 JSON');
        }

        return $decoded;
    }

    /**
     * 拼接 query（RFC3986 编码，参数化不拼裸串）。
     *
     * @param array<string,mixed> $query
     */
    protected function buildUrl(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}

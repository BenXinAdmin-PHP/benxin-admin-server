<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信 HTTP 客户端接口 — 可注入（生产 curl / 测试 mock）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\sms;

/**
 * 短信渠道 HTTP 客户端抽象（M4-D）。阿里云 SendSms 走 GET（RPC 风格 query 签名），
 * 腾讯云走 POST JSON（TC3 签名于 header）。注入 mock 离线测试，不依赖真实 AK/SK。
 */
interface SmsHttpClientInterface
{
    /**
     * GET 请求（阿里云 RPC）。
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed> JSON 解码后的响应
     */
    public function get(string $url, array $query = []): array;

    /**
     * POST JSON 请求（腾讯云）。
     *
     * @param array<string,string> $headers
     * @return array<string,mixed> JSON 解码后的响应
     */
    public function postJson(string $url, string $body, array $headers = []): array;
}

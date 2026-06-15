<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   微信 HTTP 客户端接口 — 可注入（生产 curl / 测试 mock）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

/**
 * 微信 API HTTP 客户端抽象（M4-B）。
 * BxWechat 各能力经本接口调微信，便于离线测试时注入 mock 响应
 * （WechatManager::setHttpClient），不依赖真实 appid/secret。
 * 实现约定：仅允许 HTTPS；响应体按 JSON 解码为数组返回；
 * 传输层失败（网络/超时/非 JSON）抛 WechatException::transport（140099）。
 */
interface HttpClientInterface
{
    /**
     * GET 请求。
     *
     * @param string              $url   完整 HTTPS 地址（不含 query）
     * @param array<string,mixed> $query query 参数
     * @return array<string,mixed> JSON 解码后的响应
     */
    public function get(string $url, array $query = []): array;

    /**
     * POST JSON 请求（本阶段微信能力全为 GET，留给后续模板消息/支付回调等）。
     *
     * @param string              $url
     * @param array<string,mixed> $body  JSON 请求体
     * @param array<string,mixed> $query query 参数
     * @return array<string,mixed>
     */
    public function postJson(string $url, array $body = [], array $query = []): array;
}

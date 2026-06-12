<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   公众号账号服务 — jsapi_ticket 中心化缓存（JSSDK 签名/oauth 随 B-2）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

use app\common\exception\WechatException;
use app\common\library\ErrorCode;

/**
 * 公众号（mp）账号服务：
 * - jsapi_ticket 同 access_token 套路缓存（wechat:ticket:jsapi:{appid}，TTL−提前量 + 防并发锁）。
 * - JSSDK 签名 / 网页 oauth 见 B-2。
 */
class MpAccount extends WechatAccount
{
    protected function type(): string
    {
        return 'mp';
    }

    /**
     * 取 jsapi_ticket：优先 Valkey 缓存，miss 走防并发锁刷新；
     * 刷新经 callWithToken（内含 access_token 失效自动重刷重试）。
     */
    public function jsapiTicket(): string
    {
        return $this->rememberWithLock(
            "wechat:ticket:jsapi:{$this->appId}",
            "wechat:lock:ticket:jsapi:{$this->appId}",
            fn (): array => $this->fetchJsapiTicket(),
        );
    }

    /**
     * 调微信 cgi-bin/ticket/getticket 刷新 jsapi_ticket。
     *
     * @return array{value:string,ttl:int}
     */
    protected function fetchJsapiTicket(): array
    {
        $resp = $this->callWithToken('/cgi-bin/ticket/getticket', ['type' => 'jsapi'], ErrorCode::WECHAT_TICKET_FAILED);

        $ticket = (string) ($resp['ticket'] ?? '');
        if ($ticket === '') {
            throw new WechatException('微信未返回 jsapi_ticket', ErrorCode::WECHAT_TICKET_FAILED);
        }

        return ['value' => $ticket, 'ttl' => $this->cacheTtl((int) ($resp['expires_in'] ?? 7200))];
    }
}

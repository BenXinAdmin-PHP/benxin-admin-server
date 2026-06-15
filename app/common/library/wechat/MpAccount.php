<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   公众号账号服务 — jsapi_ticket 缓存 + JSSDK 签名 + 网页 oauth
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// | @updated   2026-06-12 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

use app\common\exception\WechatException;
use app\common\library\ErrorCode;

/**
 * 公众号（mp）账号服务：
 * - jsapi_ticket 同 access_token 套路缓存（wechat:ticket:jsapi:{appid}，TTL−提前量 + 防并发锁）。
 * - JSSDK 签名：jsapi_ticket + noncestr + timestamp + url → sha1（算法对照微信官方校验工具）。
 * - 网页 oauth：授权 URL 生成 / 回调 code 换 openid（网页授权 token，区别于全局 access_token）/
 *   可选拉用户信息（scope=snsapi_userinfo）。网页授权 token 敏感：不缓存、不日志明文。
 */
class MpAccount extends WechatAccount
{
    public const OAUTH_AUTHORIZE_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    public const SCOPE_BASE     = 'snsapi_base';
    public const SCOPE_USERINFO = 'snsapi_userinfo';
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

    // ------------------------------------------------------------------
    // JSSDK 签名
    // ------------------------------------------------------------------

    /**
     * JSSDK 配置签名：返回 {appId, timestamp, nonceStr, signature} 四元组。
     * url 为当前网页完整 URL（去 # 锚点部分参与签名）。
     *
     * @return array{appId:string,timestamp:int,nonceStr:string,signature:string}
     */
    public function jssdkSign(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw WechatException::signFailed('JSSDK 签名缺少 url 参数');
        }
        // 锚点不参与签名（微信约定）
        $url = explode('#', $url, 2)[0];

        $nonceStr  = bin2hex(random_bytes(8));
        $timestamp = time();

        return [
            'appId'     => $this->appId,
            'timestamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'signature' => self::signature($this->jsapiTicket(), $nonceStr, $timestamp, $url),
        ];
    }

    /**
     * JSSDK 签名算法（纯函数，离线可对照微信官方校验工具）：
     * sha1("jsapi_ticket=..&noncestr=..&timestamp=..&url=..")，key 按 ASCII 序、value 不编码。
     */
    public static function signature(string $ticket, string $nonceStr, int $timestamp, string $url): string
    {
        return sha1("jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}");
    }

    // ------------------------------------------------------------------
    // 网页 oauth（M5 登录流消费；state 防 CSRF 校验由登录流承担）
    // ------------------------------------------------------------------

    /**
     * 生成网页授权 URL（open.weixin.qq.com/connect/oauth2/authorize#wechat_redirect）。
     *
     * @param string $redirectUri 回调地址（本方法负责 urlencode）
     * @param string $scope       snsapi_base（静默拿 openid）/ snsapi_userinfo（需用户确认，可拉资料）
     * @param string $state       透传状态码（防 CSRF，回调时校验）
     */
    public function oauthUrl(string $redirectUri, string $scope = self::SCOPE_BASE, string $state = ''): string
    {
        if ($redirectUri === '') {
            throw new WechatException('oauth 授权缺少 redirect_uri', ErrorCode::WECHAT_OAUTH_FAILED);
        }
        if (!in_array($scope, [self::SCOPE_BASE, self::SCOPE_USERINFO], true)) {
            throw new WechatException("oauth 不支持的 scope：{$scope}", ErrorCode::WECHAT_OAUTH_FAILED);
        }

        // 微信要求参数顺序固定：appid → redirect_uri → response_type → scope → state
        return self::OAUTH_AUTHORIZE_URL
            . '?appid=' . $this->appId
            . '&redirect_uri=' . rawurlencode($redirectUri)
            . '&response_type=code'
            . '&scope=' . $scope
            . '&state=' . rawurlencode($state)
            . '#wechat_redirect';
    }

    /**
     * 回调 code 换网页授权 access_token + openid（sns/oauth2/access_token，
     * 网页授权专用 token，区别于全局 access_token）。
     *
     * @return array<string,mixed> {access_token, expires_in, refresh_token, openid, scope, unionid?}
     */
    public function oauthAccessToken(string $code): array
    {
        if ($code === '') {
            throw new WechatException('oauth 回调缺少 code', ErrorCode::WECHAT_OAUTH_FAILED);
        }

        return $this->apiGet('/sns/oauth2/access_token', [
            'appid'      => $this->appId,
            'secret'     => $this->appSecret,
            'code'       => $code,
            'grant_type' => 'authorization_code',
        ], ErrorCode::WECHAT_OAUTH_FAILED);
    }

    /**
     * 拉取用户信息（仅 scope=snsapi_userinfo 时可用；入参为网页授权 token）。
     *
     * @return array<string,mixed> {openid, nickname, headimgurl, unionid?, ...}
     */
    public function oauthUserInfo(string $oauthAccessToken, string $openid, string $lang = 'zh_CN'): array
    {
        if ($oauthAccessToken === '' || $openid === '') {
            throw new WechatException('拉取用户信息缺少网页授权 token 或 openid', ErrorCode::WECHAT_OAUTH_FAILED);
        }

        return $this->apiGet('/sns/userinfo', [
            'access_token' => $oauthAccessToken,
            'openid'       => $openid,
            'lang'         => $lang,
        ], ErrorCode::WECHAT_OAUTH_FAILED);
    }
}

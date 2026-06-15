<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   小程序账号服务 — code2session 换 openid/session_key/unionid
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// | @updated   2026-06-13 22:50:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

use app\common\exception\WechatException;
use app\common\library\ErrorCode;

/**
 * 小程序（mini）账号服务：access_token 中心化能力继承基类；
 * code2session 供 M5 懒登录消费（登录即注册，微信 + 手机号缺一不可）。
 */
class MiniAccount extends WechatAccount
{
    protected function type(): string
    {
        return 'mini';
    }

    /**
     * 小程序登录凭证校验（sns/jscode2session）：js_code 换 openid/session_key/unionid。
     * ★session_key 敏感（§8）：本方法不缓存、不写日志（LogSanitizer 已拉黑），
     * 仅返回给调用方按需透传（M5 登录流消费后即弃或自行安全存储）。
     *
     * @return array{openid:string,session_key:string,unionid:?string}
     */
    public function code2session(string $jsCode): array
    {
        if ($jsCode === '') {
            throw new WechatException('code2session 缺少 js_code', ErrorCode::WECHAT_CODE2SESSION_FAILED);
        }

        $resp = $this->apiGet('/sns/jscode2session', [
            'appid'      => $this->appId,
            'secret'     => $this->appSecret,
            'js_code'    => $jsCode,
            'grant_type' => 'authorization_code',
        ], ErrorCode::WECHAT_CODE2SESSION_FAILED);

        $openid = (string) ($resp['openid'] ?? '');
        if ($openid === '') {
            throw new WechatException('微信未返回 openid', ErrorCode::WECHAT_CODE2SESSION_FAILED);
        }

        return [
            'openid'      => $openid,
            'session_key' => (string) ($resp['session_key'] ?? ''),
            'unionid'     => isset($resp['unionid']) ? (string) $resp['unionid'] : null,
        ];
    }

    /**
     * 新版手机号快速验证（wxa/business/getuserphonenumber）：phone_code 换手机号。
     * ★免 session_key（新版机制）：经 callWithTokenPost 自动带全局 access_token + 失效重试，
     * 不取/不缓存/不日志 session_key（延续 §8 红线）。供 M5-B 小程序登录流消费。
     *
     * @param string $phoneCode 前端 getPhoneNumber 回调拿到的一次性 code（动态令牌，非 openid）
     * @return string 纯手机号 purePhoneNumber（国内 11 位，不含区号）
     *
     * @throws WechatException 缺 code/未返回手机号/接口报错 → 140099（透传 errcode/errmsg）
     */
    public function getPhoneNumber(string $phoneCode): string
    {
        if ($phoneCode === '') {
            throw new WechatException('getPhoneNumber 缺少 code', ErrorCode::WECHAT_API_ERROR);
        }

        $resp  = $this->callWithTokenPost('/wxa/business/getuserphonenumber', ['code' => $phoneCode], ErrorCode::WECHAT_API_ERROR);
        $phone = (string) ($resp['phone_info']['purePhoneNumber'] ?? '');
        if ($phone === '') {
            throw new WechatException('微信未返回手机号', ErrorCode::WECHAT_API_ERROR);
        }

        return $phone;
    }
}

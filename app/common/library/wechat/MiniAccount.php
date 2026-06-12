<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   小程序账号服务 — access_token 继承基类（code2session 随 B-2）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

/**
 * 小程序（mini）账号服务：access_token 中心化能力继承基类；
 * code2session（换 openid/session_key/unionid）见 B-2，供 M5 懒登录消费。
 */
class MiniAccount extends WechatAccount
{
    protected function type(): string
    {
        return 'mini';
    }
}

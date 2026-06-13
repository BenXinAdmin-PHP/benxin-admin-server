<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信渠道抽象 — 统一发送契约（阿里/腾讯）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\sms;

/**
 * 短信渠道统一契约（M4-D）。SmsAliProvider / SmsTencentProvider 各自实现，
 * 内部自建签名（阿里 RPC HMAC-SHA1 / 腾讯 TC3-HMAC-SHA256）。
 */
interface SmsChannelInterface
{
    /**
     * 发送短信。
     *
     * @param string                $mobile       手机号（明文，发送用；落库前脱敏）
     * @param string                $templateCode 渠道侧模板ID
     * @param array<string,string>  $params       模板参数（如 ['code' => '123456']）
     * @param string|null           $signName     签名名（null 取渠道默认签名）
     */
    public function send(string $mobile, string $templateCode, array $params, ?string $signName = null): SmsResult;

    /**
     * 渠道标识（ali / tencent）。
     */
    public function name(): string;
}

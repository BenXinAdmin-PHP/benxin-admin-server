<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信能力工厂 — channel(ali/tencent) + 配置注入（复刻 WechatManager）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\sms;

use app\admin\service\ConfigService;
use app\common\exception\SmsException;

/**
 * 短信能力入口（M4-D，复刻 WechatManager）。
 * - channel(null) 缺省取 bx_config sms_channel；配置缺失抛 130001。
 * - 配置经 ConfigService 注入（AK/SK 敏感 AES 自动解密，复用 M2-B）。
 * - HTTP 客户端可注入（setHttpClient），离线测试 mock 渠道响应。
 */
class MessageManager
{
    public const CHANNELS = ['ali', 'tencent'];

    protected static ?SmsHttpClientInterface $http = null;

    /**
     * 取短信渠道（缺省取配置 sms_channel）。
     */
    public static function channel(?string $channel = null): SmsChannelInterface
    {
        $config  = self::configService();
        $channel = $channel ?? trim((string) $config->get('sms_channel', ''));
        if ($channel === '') {
            throw SmsException::configMissing('sms_channel（未配置启用渠道）');
        }
        if (!in_array($channel, self::CHANNELS, true)) {
            throw SmsException::channel(\app\common\library\ErrorCode::SMS_CHANNEL_ERROR, "未知短信渠道：{$channel}");
        }

        return $channel === 'tencent' ? self::tencent($config) : self::ali($config);
    }

    /**
     * 阿里云渠道。
     */
    public static function ali(?ConfigService $config = null): SmsAliProvider
    {
        $config = $config ?? self::configService();

        return new SmsAliProvider(
            self::require($config, 'ali_access_key_id'),
            self::require($config, 'ali_access_key_secret'),
            self::require($config, 'ali_sign_name'),
            self::httpClient(),
        );
    }

    /**
     * 腾讯云渠道。
     */
    public static function tencent(?ConfigService $config = null): SmsTencentProvider
    {
        $config = $config ?? self::configService();

        return new SmsTencentProvider(
            self::require($config, 'tencent_secret_id'),
            self::require($config, 'tencent_secret_key'),
            self::require($config, 'tencent_sdk_app_id'),
            self::require($config, 'tencent_sign_name'),
            self::httpClient(),
        );
    }

    /**
     * 注入 HTTP 客户端（离线测试）；传 null 恢复默认 curl。
     */
    public static function setHttpClient(?SmsHttpClientInterface $client): void
    {
        self::$http = $client;
    }

    protected static function httpClient(): SmsHttpClientInterface
    {
        return self::$http ??= new SmsCurlHttpClient();
    }

    /**
     * 取必填配置（敏感项自动解密）；缺失抛 130001。
     */
    protected static function require(ConfigService $config, string $key): string
    {
        $value = trim((string) $config->get($key, ''));
        if ($value === '') {
            throw SmsException::configMissing($key);
        }

        return $value;
    }

    protected static function configService(): ConfigService
    {
        return new ConfigService(app());
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   支付渠道工厂 — channel(wechat/alipay) + 测试注入（复刻 StorageManager）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\pay;

use app\admin\service\ConfigService;
use app\common\exception\PayException;
use app\common\library\ErrorCode;

/**
 * 支付渠道工厂（复刻 StorageManager::driver()）。
 * - channel('wechat'|'alipay') → 对应 Provider，配置经 ConfigService 注入。
 * - fake($channel, $provider)：离线测试注入 FakeProvider，BxPay 编排全覆盖无需真实商户号。
 */
class PayManager
{
    public const CHANNELS = ['wechat', 'alipay'];

    /** @var array<string,PayInterface> 测试注入的渠道实例 */
    protected static array $fakes = [];

    /**
     * 取渠道 Provider。
     */
    public static function channel(string $channel): PayInterface
    {
        if (isset(self::$fakes[$channel])) {
            return self::$fakes[$channel];
        }

        $config = new ConfigService(app());

        return match ($channel) {
            'wechat' => new WechatPayProvider($config),
            'alipay' => new AlipayProvider($config),
            default  => throw PayException::channel(ErrorCode::PAY_CHANNEL_ERROR, "未知支付渠道：{$channel}"),
        };
    }

    /**
     * 注入伪渠道（测试）；传 null 清除该渠道注入。
     */
    public static function fake(string $channel, ?PayInterface $provider): void
    {
        if ($provider === null) {
            unset(self::$fakes[$channel]);

            return;
        }
        self::$fakes[$channel] = $provider;
    }

    /**
     * 清空所有注入。
     */
    public static function flushFakes(): void
    {
        self::$fakes = [];
    }
}

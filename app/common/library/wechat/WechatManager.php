<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   微信能力工厂 — 多账号入口（mp/mini/work）+ 配置注入
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

use app\admin\service\ConfigService;
use app\common\exception\WechatException;

/**
 * BxWechat 对外入口（M4-B）：按账号类型出实例，配置从 bx_config（group=wechat，
 * 类型前缀 key）经 ConfigService 注入（敏感项自动 AES 解密，复用 M2-B）。
 * - 配置缺失抛 WechatException(140001)，不静默。
 * - HTTP 客户端可注入（setHttpClient），离线测试 mock 微信响应。
 * - 实例按类型缓存于进程内（fpm 单请求生命周期），配置变更后新请求自然生效；
 *   常驻进程下改配置需调 flush()。
 */
class WechatManager
{
    /** @var array<string,object> */
    protected static array $instances = [];

    protected static ?HttpClientInterface $http = null;

    /**
     * 公众号账号（mp_app_id / mp_app_secret）。
     */
    public static function mp(): MpAccount
    {
        /** @var MpAccount */
        return self::$instances['mp'] ??= new MpAccount(
            self::requireConfig('mp_app_id'),
            self::requireConfig('mp_app_secret'),
            self::httpClient(),
        );
    }

    /**
     * 小程序账号（mini_app_id / mini_app_secret）。
     */
    public static function mini(): MiniAccount
    {
        /** @var MiniAccount */
        return self::$instances['mini'] ??= new MiniAccount(
            self::requireConfig('mini_app_id'),
            self::requireConfig('mini_app_secret'),
            self::httpClient(),
        );
    }

    /**
     * 企业微信账号（work_corp_id / work_secret，agent_id 可空）——预留。
     */
    public static function work(): WorkAccount
    {
        /** @var WorkAccount */
        return self::$instances['work'] ??= new WorkAccount(
            self::requireConfig('work_corp_id'),
            (string) self::configService()->get('work_agent_id', ''),
            self::requireConfig('work_secret'),
            self::httpClient(),
        );
    }

    /**
     * 注入 HTTP 客户端（离线测试 mock 用）；传 null 恢复默认 curl。
     */
    public static function setHttpClient(?HttpClientInterface $client): void
    {
        self::$http      = $client;
        self::$instances = [];
    }

    /**
     * 清空实例缓存（配置变更后/测试用）。
     */
    public static function flush(): void
    {
        self::$instances = [];
    }

    protected static function httpClient(): HttpClientInterface
    {
        return self::$http ??= new CurlHttpClient();
    }

    /**
     * 取必填配置（敏感项 ConfigService 自动解密）；缺失/空值 → 140001。
     */
    protected static function requireConfig(string $key): string
    {
        $value = trim((string) self::configService()->get($key, ''));
        if ($value === '') {
            throw WechatException::configMissing($key);
        }

        return $value;
    }

    protected static function configService(): ConfigService
    {
        return new ConfigService(app());
    }
}

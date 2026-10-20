<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   错误码字典 — 业务码风格 A（ARCHITECTURE §6.2）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-20 11:00:00（B-增强-② 启用 16xxxx 页面/搭建器段，新增 160001 slug 保留词）
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

/**
 * 统一错误码段：
 *   0        成功
 *   400xxx   请求/参数错误
 *   401xxx   认证
 *   403xxx   权限不足
 *   404xxx   资源不存在
 *   422xxx   业务校验失败
 *   429xxx   触发限流
 *   500xxx   服务端错误
 * 业务段（各模块分配）：内容 11xxxx、支付 12xxxx、消息 13xxxx、微信 14xxxx、C端登录 15xxxx、页面/搭建器 16xxxx
 */
class ErrorCode
{
    // 成功
    public const SUCCESS = 0;

    // 400xxx 请求/参数
    public const PARAM_ERROR = 400000;

    // 401xxx 认证
    public const UNAUTHORIZED    = 401001; // 未登录
    public const LOGIN_FAIL      = 401002; // 账号或密码错误（防枚举统一文案）
    public const TOKEN_EXPIRED   = 401003; // token 过期
    public const REFRESH_INVALID = 401004; // 刷新令牌失效

    // 403xxx 权限
    public const FORBIDDEN = 403000;

    // 404xxx 不存在
    public const NOT_FOUND = 404000;

    // 422xxx 业务校验
    public const VALIDATE_FAIL = 422000;
    // 素材管理（M-素材-A，ADR-18，§6.2 固化）：白名单外文件类型
    public const RESOURCE_UNSUPPORTED_TYPE = 422100; // 不支持的文件类型
    // 素材 VOD（M-素材-C，ADR-19）：VOD 未开通/配置不全（上传凭证签发拒绝）
    public const RESOURCE_VOD_NOT_READY = 422101; // VOD 未开通或配置不全

    // 429xxx 限流
    public const TOO_MANY_REQUESTS = 429000;

    // 500xxx 服务端
    public const SERVER_ERROR = 500000;

    // 12xxxx 支付业务段（M4-C，§6.2）
    public const PAY_CONFIG_MISSING   = 120001; // 支付配置缺失
    public const PAY_PREPAY_FAILED    = 120002; // 下单失败
    public const PAY_ORDER_NOT_FOUND  = 120003; // 订单不存在
    public const PAY_ILLEGAL_TRANSIT  = 120004; // 订单状态非法迁移
    public const PAY_NOTIFY_VERIFY    = 120005; // 回调验签失败
    public const PAY_AMOUNT_MISMATCH  = 120006; // 金额不一致（防篡改）
    public const PAY_REFUND_FAILED    = 120007; // 退款失败
    public const PAY_REFUND_OVERFLOW  = 120008; // 退款金额超可退余额
    public const PAY_CHANNEL_ERROR    = 120099; // 渠道通用错误（透传）

    // 13xxxx 消息/短信业务段（M4-D，§6.2）
    public const SMS_CONFIG_MISSING = 130001; // 短信配置缺失
    public const SMS_SEND_FAILED    = 130002; // 短信发送失败
    public const SMS_CODE_TOO_OFTEN = 130003; // 验证码发送过频（限流）
    public const SMS_CODE_WRONG     = 130004; // 验证码错误
    public const SMS_CODE_EXPIRED   = 130005; // 验证码不存在/已过期
    public const SMS_CODE_LOCKED    = 130006; // 验证码错误次数超限锁定
    public const SMS_CHANNEL_ERROR  = 130099; // 渠道通用错误（透传）

    // 15xxxx C 端用户/登录业务段（M5-B，§6.2）
    // 微信错误复用 14xxxx（140005 code2session/140006 oauth/140099 通用）、
    // 短信验证码错误复用 13xxxx（130004 错/130005 过期/130006 锁定），此段不重复造码。
    public const LOGIN_NEED_MOBILE = 150001; // 新用户需补充手机号（引导授权/填写）
    public const LOGIN_DISABLED    = 150002; // 账号已被禁用或注销（status=0 或命中软删行）
    public const LOGIN_FAILED      = 150099; // 登录失败通用

    // 14xxxx 微信业务段（M4-B，§6.2）
    public const WECHAT_CONFIG_MISSING      = 140001; // 微信配置缺失/未配置
    public const WECHAT_TOKEN_FAILED        = 140002; // access_token 获取失败
    public const WECHAT_TICKET_FAILED       = 140003; // jsapi_ticket 获取失败
    public const WECHAT_JSSDK_SIGN_FAILED   = 140004; // JSSDK 签名失败（缺 url 等）
    public const WECHAT_CODE2SESSION_FAILED = 140005; // 小程序 code2session 失败
    public const WECHAT_OAUTH_FAILED        = 140006; // 公众号 oauth 失败
    public const WECHAT_API_ERROR           = 140099; // 微信接口通用错误（透传 errcode/errmsg）

    // 16xxxx 页面/搭建器业务段（B-增强-②，ADR-21 预留段正式启用，§6.2）
    public const PAGE_RESERVED_SLUG = 160001; // slug 命中系统保留词（与 site 静态路由冲突，强校验拦截）

    /**
     * 错误码 → 默认提示语映射。
     *
     * @var array<int,string>
     */
    protected const MESSAGES = [
        self::SUCCESS           => 'success',
        self::PARAM_ERROR       => '请求参数错误',
        self::UNAUTHORIZED      => '未登录或登录已失效',
        self::LOGIN_FAIL        => '账号或密码错误',
        self::TOKEN_EXPIRED     => '登录已过期，请重新登录',
        self::REFRESH_INVALID   => '刷新令牌已失效，请重新登录',
        self::FORBIDDEN         => '没有访问权限',
        self::NOT_FOUND         => '请求的资源不存在',
        self::VALIDATE_FAIL     => '数据校验失败',
        self::RESOURCE_UNSUPPORTED_TYPE => '不支持的文件类型',
        self::RESOURCE_VOD_NOT_READY    => 'VOD 点播未开通或配置不全，请在后台参数配置中完善',
        self::TOO_MANY_REQUESTS => '请求过于频繁，请稍后再试',
        self::SERVER_ERROR      => '服务器开小差了，请稍后再试',

        self::PAY_CONFIG_MISSING  => '支付配置缺失，请先在后台参数配置中完善',
        self::PAY_PREPAY_FAILED   => '下单失败',
        self::PAY_ORDER_NOT_FOUND => '支付订单不存在',
        self::PAY_ILLEGAL_TRANSIT => '订单状态非法迁移',
        self::PAY_NOTIFY_VERIFY   => '回调验签失败',
        self::PAY_AMOUNT_MISMATCH => '支付金额不一致',
        self::PAY_REFUND_FAILED   => '退款失败',
        self::PAY_REFUND_OVERFLOW => '退款金额超过可退余额',
        self::PAY_CHANNEL_ERROR   => '支付渠道调用失败',

        self::SMS_CONFIG_MISSING => '短信配置缺失，请先在后台参数配置中完善',
        self::SMS_SEND_FAILED    => '短信发送失败',
        self::SMS_CODE_TOO_OFTEN => '验证码获取过于频繁，请稍后再试',
        self::SMS_CODE_WRONG     => '验证码错误',
        self::SMS_CODE_EXPIRED   => '验证码不存在或已过期，请重新获取',
        self::SMS_CODE_LOCKED    => '验证码错误次数过多，请重新获取',
        self::SMS_CHANNEL_ERROR  => '短信渠道调用失败',

        self::LOGIN_NEED_MOBILE => '请补充手机号完成登录',
        self::LOGIN_DISABLED    => '账号已被禁用或注销',
        self::LOGIN_FAILED      => '登录失败，请稍后再试',

        self::WECHAT_CONFIG_MISSING      => '微信配置缺失，请先在后台参数配置中完善',
        self::WECHAT_TOKEN_FAILED        => '微信 access_token 获取失败',
        self::WECHAT_TICKET_FAILED       => '微信 jsapi_ticket 获取失败',
        self::WECHAT_JSSDK_SIGN_FAILED   => 'JSSDK 签名失败',
        self::WECHAT_CODE2SESSION_FAILED => '小程序登录凭证校验失败',
        self::WECHAT_OAUTH_FAILED        => '公众号网页授权失败',
        self::WECHAT_API_ERROR           => '微信接口调用失败',

        self::PAGE_RESERVED_SLUG => '该 slug 为系统保留字（与官网路由冲突），请更换',
    ];

    /**
     * 取错误码默认提示语。
     */
    public static function message(int $code): string
    {
        return self::MESSAGES[$code] ?? '未知错误';
    }
}

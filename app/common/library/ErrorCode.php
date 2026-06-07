<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   错误码字典 — 业务码风格 A（ARCHITECTURE §6.2）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
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
 * 业务段（各模块分配）：内容 11xxxx、支付 12xxxx、消息 13xxxx、微信 14xxxx
 */
class ErrorCode
{
    // 成功
    public const SUCCESS = 0;

    // 400xxx 请求/参数
    public const PARAM_ERROR = 400000;

    // 401xxx 认证
    public const UNAUTHORIZED   = 401001; // 未登录
    public const TOKEN_EXPIRED  = 401003; // token 过期
    public const REFRESH_INVALID = 401004; // 刷新令牌失效

    // 403xxx 权限
    public const FORBIDDEN = 403000;

    // 404xxx 不存在
    public const NOT_FOUND = 404000;

    // 422xxx 业务校验
    public const VALIDATE_FAIL = 422000;

    // 429xxx 限流
    public const TOO_MANY_REQUESTS = 429000;

    // 500xxx 服务端
    public const SERVER_ERROR = 500000;

    /**
     * 错误码 → 默认提示语映射。
     *
     * @var array<int,string>
     */
    protected const MESSAGES = [
        self::SUCCESS           => 'success',
        self::PARAM_ERROR       => '请求参数错误',
        self::UNAUTHORIZED      => '未登录或登录已失效',
        self::TOKEN_EXPIRED     => '登录已过期，请重新登录',
        self::REFRESH_INVALID   => '刷新令牌已失效，请重新登录',
        self::FORBIDDEN         => '没有访问权限',
        self::NOT_FOUND         => '请求的资源不存在',
        self::VALIDATE_FAIL     => '数据校验失败',
        self::TOO_MANY_REQUESTS => '请求过于频繁，请稍后再试',
        self::SERVER_ERROR      => '服务器开小差了，请稍后再试',
    ];

    /**
     * 取错误码默认提示语。
     */
    public static function message(int $code): string
    {
        return self::MESSAGES[$code] ?? '未知错误';
    }
}

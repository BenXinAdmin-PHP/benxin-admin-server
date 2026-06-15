<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   认证异常 — 携带业务错误码，统一映射 HTTP 401
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\exception;

use app\common\library\ErrorCode;
use RuntimeException;

/**
 * 认证类异常：由 BxJwt / JwtAuth 抛出，统一异常处理器收敛为
 * 业务码风格 A 信封 + HTTP 401（便于前端拦截器统一跳登录）。
 */
class AuthException extends RuntimeException
{
    /** 业务错误码（401xxx） */
    public int $bizCode;

    public function __construct(int $bizCode, string $message = '')
    {
        $this->bizCode = $bizCode;
        parent::__construct($message !== '' ? $message : ErrorCode::message($bizCode));
    }

    /** 未登录 / token 缺失或无效 → 401001 */
    public static function unauthorized(string $message = ''): self
    {
        return new self(ErrorCode::UNAUTHORIZED, $message);
    }

    /** access token 过期 → 401003 */
    public static function expired(string $message = ''): self
    {
        return new self(ErrorCode::TOKEN_EXPIRED, $message);
    }

    /** refresh 失效（白名单缺失/过期）→ 401004 */
    public static function refreshInvalid(string $message = ''): self
    {
        return new self(ErrorCode::REFRESH_INVALID, $message);
    }
}

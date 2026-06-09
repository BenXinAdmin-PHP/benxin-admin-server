<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   业务异常 — Service 层业务校验失败，统一映射 422xxx
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\exception;

use app\common\library\ErrorCode;
use RuntimeException;

/**
 * 业务异常：Service 层在业务规则不满足时抛出（如用户名已存在、删除超管、
 * 菜单存在子节点等），由全局 Handle 收敛为 422xxx 统一信封（HTTP 200）。
 * 与 AuthException（401）/ ValidateException（入参校验）分工：本异常承载
 * “参数合法但业务不允许”的场景。
 */
class BusinessException extends RuntimeException
{
    /** 业务错误码（默认 422000） */
    public int $bizCode;

    public function __construct(string $message, int $bizCode = ErrorCode::VALIDATE_FAIL)
    {
        $this->bizCode = $bizCode;
        parent::__construct($message !== '' ? $message : ErrorCode::message($bizCode));
    }
}

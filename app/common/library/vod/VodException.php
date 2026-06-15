<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   VOD 异常 — 点播接入业务异常（复用 422xxx 信封）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\vod;

use app\common\exception\BusinessException;
use app\common\library\ErrorCode;

/**
 * VOD 点播接入异常（M-素材-C，ADR-19）。继承 BusinessException → 统一 422xxx 信封。
 * 与 M4-C PayException 同位：渠道/配置/凭证类失败收敛为可读业务异常，不泄露细节。
 */
class VodException extends BusinessException
{
    /**
     * VOD 未开通/配置不全（上传凭证签发拒绝，422101）。
     */
    public static function notReady(string $message = ''): self
    {
        return new self($message !== '' ? $message : ErrorCode::message(ErrorCode::RESOURCE_VOD_NOT_READY), ErrorCode::RESOURCE_VOD_NOT_READY);
    }

    /**
     * VOD 通用业务失败（默认 422000）。
     */
    public static function fail(string $message): self
    {
        return new self($message);
    }
}

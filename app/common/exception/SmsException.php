<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信异常 — 短信渠道/验证码服务错误，统一映射 13xxxx
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\exception;

use app\common\library\ErrorCode;

/**
 * 短信业务异常（M4-D）：短信渠道（阿里/腾讯）+ 验证码服务出错抛出，
 * 继承 BusinessException 复用全局 Handle（HTTP 200 + code=13xxxx）。
 * 渠道原始错误码经 channelCode 记录，敏感细节（AK/SK/验证码）不透出。
 */
class SmsException extends BusinessException
{
    /** 渠道原始错误码（非渠道报错为 null） */
    public ?string $channelCode = null;

    public function __construct(string $message = '', int $bizCode = ErrorCode::SMS_CHANNEL_ERROR, ?string $channelCode = null)
    {
        $this->channelCode = $channelCode;
        parent::__construct($message, $bizCode);
    }

    /**
     * 短信配置缺失（130001）。
     */
    public static function configMissing(string $what): self
    {
        return new self("短信配置缺失：{$what}，请先在后台参数配置（sms 分组）中完善", ErrorCode::SMS_CONFIG_MISSING);
    }

    /**
     * 渠道返回失败（透传渠道码，业务码按场景给定）。
     */
    public static function channel(int $bizCode, string $message, ?string $channelCode = null): self
    {
        return new self($message, $bizCode, $channelCode);
    }
}

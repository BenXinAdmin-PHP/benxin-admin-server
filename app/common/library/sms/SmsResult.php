<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信发送结果 DTO — 成功/失败 + 渠道响应摘要
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\sms;

/**
 * 短信发送标准化结果（渠道差异收敛；SmsCodeService 据此记 bx_sms_log）。
 */
class SmsResult
{
    /**
     * @param bool   $success     是否成功
     * @param string $bizId       渠道回执/请求 ID
     * @param string $code        渠道返回码（成功如 OK / Ok）
     * @param string $message     渠道返回信息（脱敏后落库）
     */
    public function __construct(
        public bool $success,
        public string $bizId = '',
        public string $code = '',
        public string $message = '',
    ) {
    }
}

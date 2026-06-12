<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   微信异常 — BxWechat 能力层错误，统一映射 14xxxx
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\exception;

use app\common\library\ErrorCode;

/**
 * 微信业务异常（M4-B）：BxWechat 能力层（token/ticket/code2session/oauth/JSSDK）
 * 出错时抛出，继承 BusinessException 复用全局 Handle 渲染（HTTP 200 + code=14xxxx）。
 * - errmsg 透出到 message（便于排查），微信原始 errcode 记录在 $errcode 属性。
 * - 配置缺失（140001）不静默，调用方必须显式处理或让其冒泡。
 */
class WechatException extends BusinessException
{
    /** 微信原始 errcode（非微信接口报错时为 null） */
    public ?int $errcode = null;

    public function __construct(string $message = '', int $bizCode = ErrorCode::WECHAT_API_ERROR, ?int $errcode = null)
    {
        $this->errcode = $errcode;
        parent::__construct($message, $bizCode);
    }

    /**
     * 配置缺失（140001）：$what 为缺失的配置 key（如 mp_app_id）。
     */
    public static function configMissing(string $what): self
    {
        return new self("微信配置缺失：{$what}，请先在后台参数配置（wechat 分组）中完善", ErrorCode::WECHAT_CONFIG_MISSING);
    }

    /**
     * JSSDK 签名失败（140004，缺 url 等本地校验类错误）。
     */
    public static function signFailed(string $msg): self
    {
        return new self($msg, ErrorCode::WECHAT_JSSDK_SIGN_FAILED);
    }

    /**
     * 微信接口返回 errcode≠0：errmsg 透出、errcode 记录，业务码按调用场景给定。
     */
    public static function fromApi(int $bizCode, int $errcode, string $errmsg): self
    {
        $message = sprintf('%s[%d]：%s', ErrorCode::message($bizCode), $errcode, $errmsg !== '' ? $errmsg : 'unknown');

        return new self($message, $bizCode, $errcode);
    }

    /**
     * HTTP 传输层失败（网络/超时/非 JSON 响应），归 140099，errcode 记 -1。
     */
    public static function transport(string $msg): self
    {
        return new self('微信接口请求失败：' . $msg, ErrorCode::WECHAT_API_ERROR, -1);
    }
}

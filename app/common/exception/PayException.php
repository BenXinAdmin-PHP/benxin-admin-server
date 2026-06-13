<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   支付异常 — BxPay 框架层错误，统一映射 12xxxx
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\exception;

use app\common\library\ErrorCode;

/**
 * 支付业务异常（M4-C）：BxPay/Provider 层（下单/查单/退款/回调/状态机）出错抛出，
 * 继承 BusinessException 复用全局 Handle（HTTP 200 + code=12xxxx）。
 * 渠道原始错误经 channelCode/channelMsg 记录，不泄露敏感细节。
 */
class PayException extends BusinessException
{
    /** 渠道原始错误码（非渠道报错为 null） */
    public ?string $channelCode = null;

    public function __construct(string $message = '', int $bizCode = ErrorCode::PAY_CHANNEL_ERROR, ?string $channelCode = null)
    {
        $this->channelCode = $channelCode;
        parent::__construct($message, $bizCode);
    }

    /**
     * 支付配置缺失（120001）。$what 为缺失的配置 key。
     */
    public static function configMissing(string $what): self
    {
        return new self("支付配置缺失：{$what}，请先在后台参数配置（pay/wechat 分组）中完善", ErrorCode::PAY_CONFIG_MISSING);
    }

    /**
     * 订单不存在（120003）。
     */
    public static function orderNotFound(string $hint = ''): self
    {
        return new self($hint !== '' ? "支付订单不存在：{$hint}" : '支付订单不存在', ErrorCode::PAY_ORDER_NOT_FOUND);
    }

    /**
     * 状态非法迁移（120004）。
     */
    public static function illegalTransit(int $from, int $to): self
    {
        return new self("订单状态非法迁移：{$from} → {$to}", ErrorCode::PAY_ILLEGAL_TRANSIT);
    }

    /**
     * 金额不一致（120006，防篡改）。
     */
    public static function amountMismatch(int $expect, int $actual): self
    {
        return new self("回调金额与订单不符（订单 {$expect} 分 / 回调 {$actual} 分）", ErrorCode::PAY_AMOUNT_MISMATCH);
    }

    /**
     * 渠道调用失败（透传渠道码，业务码按场景给定）。
     */
    public static function channel(int $bizCode, string $message, ?string $channelCode = null): self
    {
        return new self($message, $bizCode, $channelCode);
    }
}

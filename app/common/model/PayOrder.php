<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 支付订单 bx_pay_order（订单状态机常量收口）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 支付订单模型。状态机常量与「合法迁移表」在此定义，BxPay 收口所有状态变更。
 */
class PayOrder extends BxModel
{
    protected $name = 'pay_order';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'              => 'integer',
        'tenant_id'       => 'integer',
        'amount'          => 'integer',
        'status'          => 'integer',
        'refunded_amount' => 'integer',
        'user_id'         => 'integer',
        'create_by'       => 'integer',
    ];

    // ---- 订单状态机（§四）----
    public const STATUS_PENDING       = 0; // 待支付
    public const STATUS_PAID          = 1; // 已支付
    public const STATUS_REFUNDED      = 2; // 已退款（全额）
    public const STATUS_PART_REFUNDED = 3; // 部分退款
    public const STATUS_CLOSED        = 4; // 已关闭
    public const STATUS_FAILED        = 5; // 支付失败

    /**
     * 合法迁移表：当前状态 => 允许迁入的状态集合。
     * 待支付 → 已支付 / 已关闭 / 支付失败；已支付 → 已退款 / 部分退款；
     * 部分退款 → 已退款（继续退至全额）/ 部分退款（多次部分退）。
     *
     * @var array<int,array<int,int>>
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING       => [self::STATUS_PAID, self::STATUS_CLOSED, self::STATUS_FAILED],
        self::STATUS_PAID          => [self::STATUS_REFUNDED, self::STATUS_PART_REFUNDED],
        self::STATUS_PART_REFUNDED => [self::STATUS_REFUNDED, self::STATUS_PART_REFUNDED],
        self::STATUS_REFUNDED      => [],
        self::STATUS_CLOSED        => [],
        self::STATUS_FAILED        => [],
    ];

    /**
     * 是否允许从 $from 迁移到 $to（相同状态视为幂等允许，由上层幂等层先拦）。
     */
    public static function canTransit(int $from, int $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}

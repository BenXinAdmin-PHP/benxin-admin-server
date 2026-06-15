<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 退款记录 bx_pay_refund
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 退款记录模型。
 */
class PayRefund extends BxModel
{
    protected $name = 'pay_refund';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'           => 'integer',
        'tenant_id'    => 'integer',
        'pay_order_id' => 'integer',
        'amount'       => 'integer',
        'status'       => 'integer',
        'create_by'    => 'integer',
    ];

    // ---- 退款状态 ----
    public const STATUS_REFUNDING = 0; // 退款中
    public const STATUS_SUCCESS   = 1; // 成功
    public const STATUS_FAILED    = 2; // 失败
}

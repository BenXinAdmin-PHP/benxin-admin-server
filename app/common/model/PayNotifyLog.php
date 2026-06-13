<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 支付回调审计/幂等 bx_pay_notify_log（只增不改不软删）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 回调审计/幂等模型。只增不改不软删：直接继承 think\Model，
 * 仅写 created_at（关闭 updated_at），不挂软删除与租户全局作用域（类比 OperLog）。
 */
class PayNotifyLog extends Model
{
    protected $name = 'pay_notify_log';

    protected $createTime        = 'created_at';
    protected $updateTime        = false;
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'        => 'integer',
        'verified'  => 'integer',
        'processed' => 'integer',
    ];

    public const EVENT_PAY    = 'pay';
    public const EVENT_REFUND = 'refund';
}

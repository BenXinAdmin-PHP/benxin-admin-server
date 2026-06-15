<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 短信日志 bx_sms_log（只增不改不软删）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 短信日志模型。只增不改不软删：直接继承 think\Model，仅写 created_at，
 * 不挂软删除与租户全局作用域（类比 OperLog）。手机号/参数脱敏后入库。
 */
class SmsLog extends Model
{
    protected $name = 'sms_log';

    protected $hidden = ['tenant_id'];

    protected $createTime        = 'created_at';
    protected $updateTime        = false;
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'status'    => 'integer',
    ];
}

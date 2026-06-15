<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — VOD 转码回调审计/幂等 bx_resource_vod_notify_log（只增不改不软删）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * VOD 转码回调审计/幂等模型（M-素材-C，复刻 M4-C PayNotifyLog 范式）。
 * 只增不改不软删：直接继承 think\Model，仅写 created_at（关闭 updated_at），
 * 不挂软删除与租户全局作用域（类比 oper_log / pay_notify_log）。
 *
 * PM 倾向独立表（VOD 事件与支付语义不同，不混表更清晰）。
 * 幂等唯一键：(event_type, vod_media_id, idem_no)，重复回调命中即直接 ACK。
 */
class ResourceVodNotifyLog extends Model
{
    protected $name = 'resource_vod_notify_log';

    protected $createTime        = 'created_at';
    protected $updateTime        = false;
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'        => 'integer',
        'verified'  => 'integer',
        'processed' => 'integer',
    ];

    /** 归一化事件类型 */
    public const EVENT_TRANSCODE = 'transcode'; // 转码状态变更
    public const EVENT_OTHER     = 'other';     // 其他事件（仅审计 ACK）
    public const EVENT_INVALID   = 'invalid';   // 验签失败留痕
}

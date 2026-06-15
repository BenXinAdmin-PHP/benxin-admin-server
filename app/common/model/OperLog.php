<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 操作日志 bx_oper_log（只增不改不软删）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 操作日志模型。日志只增不改不软删：直接继承 think\Model，
 * 仅写 created_at（关闭 updated_at），不挂软删除与租户全局作用域。
 */
class OperLog extends Model
{
    protected $name = 'oper_log';

    protected $hidden = ['tenant_id'];

    protected $createTime        = 'created_at';
    protected $updateTime        = false;
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'            => 'integer',
        'tenant_id'     => 'integer',
        'admin_id'      => 'integer',
        'response_code' => 'integer',
        'http_status'   => 'integer',
        'duration_ms'   => 'integer',
    ];
}

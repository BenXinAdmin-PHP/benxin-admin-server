<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 登录日志 bx_login_log（只增不改不软删）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 登录日志模型。只增不改不软删：仅写 created_at，不挂软删/租户作用域。
 */
class LoginLog extends Model
{
    protected $name = 'login_log';

    protected $createTime        = 'created_at';
    protected $updateTime        = false;
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'admin_id'  => 'integer',
        'status'    => 'integer',
    ];
}

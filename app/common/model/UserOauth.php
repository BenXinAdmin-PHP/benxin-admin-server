<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — C 端第三方账号关联 bx_user_oauth
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * C 端第三方账号关联模型（ADR-16）。不软删、不挂租户作用域（关联随 user 生命周期，
 * 直接继承 think\Model）；写 created_at/updated_at 时间戳。
 */
class UserOauth extends Model
{
    protected $name = 'user_oauth';

    protected $createTime        = 'created_at';
    protected $updateTime        = 'updated_at';
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'      => 'integer',
        'user_id' => 'integer',
    ];
}

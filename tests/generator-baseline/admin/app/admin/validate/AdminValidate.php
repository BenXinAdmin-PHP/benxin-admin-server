<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 管理员（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 管理员入参校验。username 唯一（含软删）校验在 AdminService。
 */
class AdminValidate extends BxValidate
{
    protected $rule = [
        'username'      => 'max:64',
        'password'      => 'max:255',
        'nickname'      => 'max:64',
        'avatar'        => 'max:255',
        'mobile'        => 'max:20',
        'email'         => 'max:128',
        'dept_id'       => 'integer',
        'status'        => 'integer',
        'last_login_at' => 'date',
        'last_login_ip' => 'max:64',
        'remark'        => 'max:255',
    ];

    protected $message = [];

    public function sceneCreate(): static
    {
        return $this->only(['username', 'password', 'nickname', 'avatar', 'mobile', 'email', 'dept_id', 'status', 'last_login_at', 'last_login_ip', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['username', 'password', 'nickname', 'avatar', 'mobile', 'email', 'dept_id', 'status', 'last_login_at', 'last_login_ip', 'remark']);
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

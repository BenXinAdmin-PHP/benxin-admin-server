<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 管理员（create/update/status/password 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 管理员入参校验。唯一性、超管护栏等业务规则在 AdminService。
 */
class AdminValidate extends BxValidate
{
    protected $rule = [
        'username' => 'require|length:2,64|alphaDash',
        'password' => 'require|length:6,64',
        'nickname' => 'max:64',
        'avatar'   => 'max:255',
        'mobile'   => 'max:20',
        'email'    => 'email|max:128',
        'dept_id'  => 'integer|egt:0',
        'status'   => 'in:0,1',
        'remark'   => 'max:255',
        'role_ids' => 'array',
        'post_ids' => 'array',
    ];

    protected $message = [
        'username.require'   => '请输入登录账号',
        'username.length'    => '账号长度 2~64',
        'username.alphaDash' => '账号只能含字母、数字、下划线和短横线',
        'password.require'   => '请输入密码',
        'password.length'    => '密码长度 6~64',
        'email.email'        => '邮箱格式不正确',
    ];

    /** 新增：账号 + 密码必填 */
    public function sceneCreate(): static
    {
        return $this->only(['username', 'password', 'nickname', 'avatar', 'mobile', 'email', 'dept_id', 'status', 'remark', 'role_ids', 'post_ids']);
    }

    /** 更新：不改密码（走专用接口）；账号选择性、去除必填 */
    public function sceneUpdate(): static
    {
        return $this->only(['username', 'nickname', 'avatar', 'mobile', 'email', 'dept_id', 'status', 'remark', 'role_ids', 'post_ids'])
            ->remove('username', 'require');
    }

    /** 状态切换 */
    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }

    /** 重置密码 */
    public function scenePassword(): static
    {
        return $this->only(['password']);
    }
}

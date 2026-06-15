<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 后台认证（登录/刷新/自助改密入参）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// | @updated   2026-06-09 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 后台认证入参校验。校验失败抛 ValidateException → 统一 422xxx。
 */
class AuthValidate extends BxValidate
{
    protected $rule = [
        'username'      => 'require|length:1,64',
        'password'      => 'require|length:1,255',
        'refresh_token' => 'require',
        'old_password'  => 'require',
        'new_password'  => 'require|length:6,64',
    ];

    protected $message = [
        'username.require'      => '请输入账号',
        'username.length'       => '账号长度不合法',
        'password.require'      => '请输入密码',
        'password.length'       => '密码长度不合法',
        'refresh_token.require' => '缺少 refresh_token',
        'old_password.require'  => '请输入原密码',
        'new_password.require'  => '请输入新密码',
        'new_password.length'   => '新密码长度 6~64',
    ];

    protected $scene = [
        // 登录：账号 + 密码
        'login'    => ['username', 'password'],
        // 刷新：refresh_token
        'refresh'  => ['refresh_token'],
        // 自助改密：原密码 + 新密码
        'changePwd' => ['old_password', 'new_password'],
    ];
}

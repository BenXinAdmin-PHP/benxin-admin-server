<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 后台认证（登录/刷新入参）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
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
    ];

    protected $message = [
        'username.require'      => '请输入账号',
        'username.length'       => '账号长度不合法',
        'password.require'      => '请输入密码',
        'password.length'       => '密码长度不合法',
        'refresh_token.require' => '缺少 refresh_token',
    ];

    protected $scene = [
        // 登录：账号 + 密码
        'login'   => ['username', 'password'],
        // 刷新：refresh_token
        'refresh' => ['refresh_token'],
    ];
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — C 端认证（刷新入参）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\validate;

use app\common\base\BxValidate;

/**
 * C 端认证入参校验。校验失败抛 ValidateException → 统一 422xxx。
 * 登录场景（手机号/微信 code）留 M5-B。
 */
class AuthValidate extends BxValidate
{
    protected $rule = [
        'refresh_token' => 'require',
    ];

    protected $message = [
        'refresh_token.require' => '缺少 refresh_token',
    ];

    protected $scene = [
        // 刷新：refresh_token
        'refresh' => ['refresh_token'],
    ];
}

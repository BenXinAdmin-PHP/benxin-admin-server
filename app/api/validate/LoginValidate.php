<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — C 端登录入参（小程序 / H5 公众号）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 22:50:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\validate;

use app\common\base\BxValidate;

/**
 * C 端登录入参校验（格式层）。条件必填（新用户才需 mobile/sms_code/phone_code）
 * 由 Controller 按「老用户静默 / 新用户补凭据」编排判定（D1），本校验器仅做格式校验：
 * - 非 require 规则对空值放行（老用户路径可不带 mobile/sms_code/phone_code）。
 */
class LoginValidate extends BxValidate
{
    protected $rule = [
        'code'       => 'require|max:512',   // wx.login / oauth 一次性 code
        'mobile'     => 'mobile',             // 存在则须为合法手机号（H5 用户填写）
        'sms_code'   => 'length:4,8',         // 存在则长度合法（H5 短信验证码）
        'phone_code' => 'max:512',            // 存在则长度受限（小程序 getPhoneNumber code）
    ];

    protected $message = [
        'code.require'  => '缺少登录 code',
        'code.max'      => 'code 非法',
        'mobile.mobile' => '手机号格式不正确',
        'sms_code.length' => '验证码格式不正确',
    ];

    protected $scene = [
        // 小程序：code 必填 + phone_code 可选（新用户带）
        'mini' => ['code', 'phone_code'],
        // H5 公众号：code 必填 + mobile/sms_code 可选（新用户带）
        'h5'   => ['code', 'mobile', 'sms_code'],
    ];
}

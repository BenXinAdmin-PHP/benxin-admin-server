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
 * C 端登录入参校验（格式层）。小程序 phone_code 条件必填；H5 首次绑定单独校验。
 */
class LoginValidate extends BxValidate
{
    protected $rule = [
        'code'       => 'require|max:512',   // wx.login / oauth 一次性 code
        'state'      => 'require|length:36', // H5 oauth 回调 state（UUID）
        'challenge_token' => 'require|length:36', // H5 首次绑定的短期凭据
        'mobile'     => 'require|mobile',     // H5 首次绑定手机号
        'sms_code'   => 'require|length:4,8', // H5 短信验证码
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
        'h5'   => ['code', 'state'],
        // H5 新用户：OAuth code 已消费，仅凭短期 challenge + 短信码完成绑定
        'h5Complete' => ['challenge_token', 'mobile', 'sms_code'],
    ];
}

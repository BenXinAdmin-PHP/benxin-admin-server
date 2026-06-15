<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信能力 — POST /api/v1/sms/code（发送验证码）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\exception\BusinessException;
use app\common\service\SmsCodeService;
use think\Response;

/**
 * 短信验证码下发（api，懒登录不强制）。接口级严格限流（防轰炸）见路由；
 * 业务多维限流（手机号间隔/天上限 + IP 天上限 + 防爆破）在 SmsCodeService。
 */
class Sms extends BxController
{
    /** 允许的发送场景白名单（防滥用任意 scene） */
    protected const ALLOWED_SCENES = ['login', 'bind', 'reset'];

    /**
     * 发送验证码。POST /api/v1/sms/code { mobile, scene }
     */
    public function code(): Response
    {
        $mobile = trim((string) $this->request->param('mobile', ''));
        $scene  = trim((string) $this->request->param('scene', 'login'));

        if ($mobile === '') {
            throw new BusinessException('请输入手机号');
        }
        if (!in_array($scene, self::ALLOWED_SCENES, true)) {
            throw new BusinessException('不支持的验证码场景');
        }

        $masked = (new SmsCodeService($this->app))->send($mobile, $scene, $this->request->ip());

        return $this->success(['mobile' => $masked], '验证码已发送');
    }
}

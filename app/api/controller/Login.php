<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   C 端登录 — POST /api/v1/login/mini|h5（登录即注册）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 22:50:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\LoginValidate;
use app\common\base\BxController;
use app\common\exception\WechatException;
use app\common\library\BxJwt;
use app\common\library\ErrorCode;
use app\common\library\wechat\WechatManager;
use app\common\model\User;
use app\common\service\SmsCodeService;
use app\common\service\UserAuthService;
use think\Response;

/**
 * C 端双端登录闭环（M5-B，ADR-17，懒登录入口）。两端均免登录、接口级限流（路由声明）。
 *
 * 统一编排（D1，两端对称）：先静默授权拿 openid → 已注册即老用户静默登录（免补凭据）；
 * 未注册返回 150001 引导前端补手机号凭据，前端补齐后重试。
 * 登录成功统一 BxJwt::issueForApi 签发 api 双令牌（响应只回令牌，user 详情留 M5-C）。
 *
 * 安全（§8）：code/phone_code/sms_code 一次性凭据不落日志明文（LogSanitizer 黑名单）；
 * session_key 不涉及（getPhoneNumber 新版免 session_key）；验证码消费即删（SmsCodeService）。
 */
class Login extends BxController
{
    /**
     * 小程序登录。POST /api/v1/login/mini { code, phone_code? }
     * code → code2session 拿 openid；新用户用 phone_code 换手机号（免 session_key）。
     */
    public function mini(): Response
    {
        validate(LoginValidate::class)->scene('mini')->check($this->request->post());

        // 1) code2session 换 openid(+unionid)；失败透传 140005
        $session = WechatManager::mini()->code2session((string) $this->request->post('code', ''));
        $openid  = (string) $session['openid'];
        $unionid = $session['unionid']; // ?string

        $service = new UserAuthService($this->app);

        // 2) 未注册（无 mini openid 关联）才需补手机号
        $mobile = null;
        if (!$service->isRegistered('mini', $openid)) {
            $phoneCode = (string) $this->request->post('phone_code', '');
            if ($phoneCode === '') {
                // 引导前端发起 getPhoneNumber 授权后重试
                return $this->fail(ErrorCode::LOGIN_NEED_MOBILE);
            }
            // 新版手机号快速验证：phone_code 换纯手机号；失败透传 140099
            $mobile = WechatManager::mini()->getPhoneNumber($phoneCode);
        }

        $user = $service->loginOrRegister('mini', $openid, $mobile, $unionid);

        return $this->issue($user);
    }

    /**
     * H5 公众号登录。POST /api/v1/login/h5 { code, mobile?, sms_code? }
     * code → oauth 拿 openid；新用户用短信验证码校验拿手机号。
     * D5：后端靠 oauth 天然约束微信环境（非微信拿不到 code → 140006），UA 判断留 M5-C。
     */
    public function h5(): Response
    {
        validate(LoginValidate::class)->scene('h5')->check($this->request->post());

        // 1) oauth code 换网页授权 token + openid(+unionid)；失败透传 140006
        $oauth   = WechatManager::mp()->oauthAccessToken((string) $this->request->post('code', ''));
        $openid  = (string) ($oauth['openid'] ?? '');
        if ($openid === '') {
            throw new WechatException('公众号 oauth 未返回 openid', ErrorCode::WECHAT_OAUTH_FAILED);
        }
        $unionid = isset($oauth['unionid']) ? (string) $oauth['unionid'] : null;

        $service = new UserAuthService($this->app);

        // 2) 未注册（无 mp openid 关联）才需手机号 + 短信验证码
        $mobile = null;
        if (!$service->isRegistered('mp', $openid)) {
            $mobile  = trim((string) $this->request->post('mobile', ''));
            $smsCode = trim((string) $this->request->post('sms_code', ''));
            if ($mobile === '' || $smsCode === '') {
                return $this->fail(ErrorCode::LOGIN_NEED_MOBILE);
            }
            // 短信验证码校验（场景 login，消费即删）；失败透传 130004/130005/130006
            (new SmsCodeService($this->app))->verify($mobile, 'login', $smsCode);
        }

        $user = $service->loginOrRegister('mp', $openid, $mobile, $unionid);

        return $this->issue($user);
    }

    /**
     * 登录成功统一响应：签发 api 双令牌（与 M5-A issueForApi / refresh / logout 闭环一致）。
     */
    private function issue(User $user): Response
    {
        $tokens = BxJwt::issueForApi((int) $user->id, (int) $user->tenant_id);

        return $this->success($tokens, '登录成功');
    }
}

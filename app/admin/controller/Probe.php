<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   调试探针 — _perm/_wechat/_pay/_sms_probe，仅调试态注册
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 10:00:00
// | @updated   2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ConfigService;
use app\common\base\BxController;
use app\common\exception\BusinessException;
use app\common\exception\PayException;
use app\common\exception\SmsException;
use app\common\exception\WechatException;
use app\common\library\BxJwt;
use app\common\library\pay\AlipayProvider;
use app\common\library\pay\WechatPayProvider;
use app\common\library\sms\SmsAliProvider;
use app\common\library\sms\SmsTencentProvider;
use app\common\library\wechat\WechatManager;
use app\common\model\PayOrder;
use app\common\model\User;
use think\Response;

/**
 * 调试探针集合：路由仅在 APP_DEBUG=true 时注册，生产不暴露。
 * - index：RBAC 放行/拒绝验证（M1-B，挂 JwtAuth + CasbinAuth:system:admin:list）。
 * - wechat：微信能力就绪态（M4-B，挂 JwtAuth）。
 */
class Probe extends BxController
{
    public function index(): Response
    {
        return $this->success(['ok' => true], 'permitted');
    }

    /**
     * 微信能力探针：配置就绪态 + token 获取结果 + JSSDK 签名样例 + oauth URL 样例。
     * 无配置/占位假串时各项以 {ok:false, code:140001/140002...} 呈现，整体不报错。
     */
    public function wechat(): Response
    {
        $group = (new ConfigService($this->app))->getGroup('wechat');
        $ready = [];
        foreach (['mp_app_id', 'mp_app_secret', 'mp_token', 'mp_aes_key', 'mini_app_id', 'mini_app_secret', 'work_corp_id', 'work_agent_id', 'work_secret'] as $key) {
            $ready[$key] = trim((string) ($group[$key] ?? '')) !== '';
        }

        // 单项探测：微信异常收敛为 {ok:false, code, errcode, msg}，不冒泡
        $attempt = static function (callable $fn): array {
            try {
                return ['ok' => true] + $fn();
            } catch (WechatException $e) {
                return ['ok' => false, 'code' => $e->bizCode, 'errcode' => $e->errcode, 'msg' => $e->getMessage()];
            }
        };

        return $this->success([
            'config_ready'      => $ready,
            'mp_access_token'   => $attempt(static function (): array {
                $token = WechatManager::mp()->accessToken();
                // 不回完整 token（仅样例首 8 位 + 长度）
                return ['sample' => substr($token, 0, 8) . '...(' . strlen($token) . ')'];
            }),
            'mini_access_token' => $attempt(static function (): array {
                $token = WechatManager::mini()->accessToken();
                return ['sample' => substr($token, 0, 8) . '...(' . strlen($token) . ')'];
            }),
            'jssdk_sample'      => $attempt(static fn (): array => [
                'sign' => WechatManager::mp()->jssdkSign('https://example.com/demo?from=probe'),
            ]),
            'oauth_url_sample'  => $attempt(static fn (): array => [
                'url' => WechatManager::mp()->oauthUrl('https://example.com/oauth/callback', 'snsapi_base', 'probe-state'),
            ]),
        ]);
    }

    /**
     * 支付能力探针（M4-C）：配置就绪态 + 下单参数构造样例 + 状态机迁移样例。
     * 无配置/占位假串时以 {ok:false, code:120001} 呈现，整体不报错。
     * 不触发真实渠道 HTTP（下单/验签为「需真实商户号」边界），仅离线可验项。
     */
    public function pay(): Response
    {
        $config = new ConfigService($this->app);
        $group  = $config->getGroup('pay');
        $ready  = [];
        foreach (['wxpay_mch_id', 'wxpay_api_v3_key', 'wxpay_cert_serial', 'wxpay_private_key', 'wxpay_notify_url', 'alipay_app_id', 'alipay_private_key', 'alipay_public_key', 'alipay_notify_url'] as $key) {
            $ready[$key] = trim((string) ($group[$key] ?? '')) !== '';
        }

        $attempt = static function (callable $fn): array {
            try {
                return ['ok' => true] + $fn();
            } catch (PayException $e) {
                return ['ok' => false, 'code' => $e->bizCode, 'msg' => $e->getMessage()];
            }
        };

        // 离线样例订单（不落库）
        $sample              = new PayOrder();
        $sample->out_trade_no = 'OT_PROBE_SAMPLE';
        $sample->subject      = '探针样例';
        $sample->amount       = 1;
        $sample->trade_type   = 'jsapi';
        $sample->openid       = 'OPENID_PROBE';

        $sampleAli              = new PayOrder();
        $sampleAli->out_trade_no = 'OT_PROBE_SAMPLE';
        $sampleAli->subject      = '探针样例';
        $sampleAli->amount       = 1234;
        $sampleAli->trade_type   = 'page';

        return $this->success([
            'config_ready'      => $ready,
            'wechat_prepay_args' => $attempt(static fn (): array => [
                'order' => (new WechatPayProvider($config))->buildPrepayOrder($sample),
            ]),
            'alipay_prepay_args' => $attempt(static fn (): array => [
                'order' => (new AlipayProvider($config))->buildPrepayOrder($sampleAli),
            ]),
            'state_machine'     => [
                'pending→paid'      => PayOrder::canTransit(PayOrder::STATUS_PENDING, PayOrder::STATUS_PAID),
                'paid→part_refund'  => PayOrder::canTransit(PayOrder::STATUS_PAID, PayOrder::STATUS_PART_REFUNDED),
                'refunded→paid(非法)' => PayOrder::canTransit(PayOrder::STATUS_REFUNDED, PayOrder::STATUS_PAID),
            ],
        ]);
    }

    /**
     * 短信能力探针（M4-D）：配置就绪态 + 双渠道签名构造样例（离线，不触发真实发送）。
     * 无配置/占位假串时各项以 {ok:false, code:130001} 呈现，整体不报错。
     */
    public function sms(): Response
    {
        $config = new ConfigService($this->app);
        $group  = $config->getGroup('sms');
        $ready  = [];
        foreach (['sms_channel', 'ali_access_key_id', 'ali_access_key_secret', 'ali_sign_name', 'tencent_secret_id', 'tencent_secret_key', 'tencent_sdk_app_id', 'tencent_sign_name'] as $key) {
            $ready[$key] = trim((string) ($group[$key] ?? '')) !== '';
        }

        $attempt = static function (callable $fn): array {
            try {
                return ['ok' => true] + $fn();
            } catch (SmsException $e) {
                return ['ok' => false, 'code' => $e->bizCode, 'msg' => $e->getMessage()];
            }
        };

        return $this->success([
            'config_ready'    => $ready,
            'current_channel' => trim((string) ($group['sms_channel'] ?? '')),
            // 阿里 RPC 签名样例（固定参数 → 可对照算法；不含真实密钥）
            'ali_rpc_sign_demo' => [
                'signature' => SmsAliProvider::rpcSignature(
                    ['AccessKeyId' => 'demo', 'Action' => 'SendSms', 'Format' => 'JSON'],
                    'demo-secret',
                ),
            ],
            // 腾讯 TC3 StringToSign 样例（结构展示，不含真实密钥）
            'tencent_tc3_demo' => [
                'string_to_sign' => SmsTencentProvider::stringToSign(
                    1551113065,
                    '2019-02-25',
                    'sms',
                    SmsTencentProvider::canonicalRequest('sms.tencentcloudapi.com', 'SendSms', '{}'),
                ),
            ],
        ]);
    }

    /**
     * C 端登录令牌签发探针（M5-A）：不依赖真实微信，直接为指定 user_id 经
     * BxJwt::issueForApi 签发 api guard 双令牌，用于验证 api JwtAuth 放行 / refresh /
     * logout 黑白名单闭环（真实登录留 M5-B）。
     * POST /admin/v1/_api_login_probe { user_id }（仅 APP_DEBUG + 后台 JwtAuth）。
     */
    public function apiLogin(): Response
    {
        $userId = (int) $this->request->param('user_id', 0);
        if ($userId <= 0) {
            throw new BusinessException('请提供 user_id');
        }

        // 校验目标用户存在且启用（与 api JwtAuth/refresh 的取数口径一致）
        $user = User::where('id', $userId)->where('status', 1)->find();
        if ($user === null) {
            throw new BusinessException('C 端用户不存在或已停用');
        }

        return $this->success(
            BxJwt::issueForApi((int) $user->id, (int) $user->tenant_id),
            'api 令牌已签发',
        );
    }
}

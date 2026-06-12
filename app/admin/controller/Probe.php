<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   调试探针 — _perm_probe（M1-B）/ _wechat_probe（M4-B），仅调试态注册
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 10:00:00
// | @updated   2026-06-12 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\ConfigService;
use app\common\base\BxController;
use app\common\exception\WechatException;
use app\common\library\wechat\WechatManager;
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
}

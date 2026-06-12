<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   微信能力接口 — GET /api/v1/wechat/jssdk
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\exception\WechatException;
use app\common\library\wechat\WechatManager;
use think\Response;

/**
 * 微信能力对外接口（最小暴露，M4-B）：
 * - jssdk：H5 公众号 JSSDK 签名（懒登录不强制，ADR-3）。
 * - code2session / oauth 换 openid 不单独暴露——它们是 M5 登录流中间步骤，
 *   由 M5 登录接口经 WechatManager 服务方法调用。
 */
class Wechat extends BxController
{
    /**
     * JSSDK 签名：?url=<当前页完整 URL（encode 后传入）> → {appId, timestamp, nonceStr, signature}。
     */
    public function jssdk(): Response
    {
        $url = trim((string) $this->request->param('url', ''));
        if ($url === '') {
            throw WechatException::signFailed('JSSDK 签名缺少 url 参数');
        }

        return $this->success(WechatManager::mp()->jssdkSign($url));
    }
}

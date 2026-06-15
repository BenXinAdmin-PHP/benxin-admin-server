<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   支付回调 — POST /api/v1/pay/notify/{channel}
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 11:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\library\pay\PayManager;
use app\common\model\PayNotifyLog;
use app\common\service\BxPay;
use think\Response;

/**
 * 支付渠道异步回调入口（M4-C，公开接口、不挂鉴权——渠道服务器回调）。
 * 安全四件套（验签/幂等/金额二次校验/状态机）全部收口于 BxPay::handleNotify，
 * 本控制器只负责取原文 + 头并转交，返回渠道要求的 ACK 原样输出。
 */
class Pay extends BxController
{
    /**
     * 支付/退款异步通知。{channel}=wechat|alipay。
     * 渠道通过 query ?type=refund 或报文自身区分支付/退款（微信退款走独立回调地址时由 type 指定）。
     */
    public function notify(string $channel): Response
    {
        if (!in_array($channel, PayManager::CHANNELS, true)) {
            // 未知渠道：返回失败 ACK，不暴露细节
            return Response::create('fail', 'html', 200);
        }

        $eventType = $this->request->param('type') === 'refund'
            ? PayNotifyLog::EVENT_REFUND
            : PayNotifyLog::EVENT_PAY;

        $headers = $this->request->header();
        $body    = $this->request->getInput();

        return (new BxPay($this->app))->handleNotify($channel, $eventType, $headers, $body);
    }
}

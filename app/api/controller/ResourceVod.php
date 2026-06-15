<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   VOD 转码回调 — POST /api/v1/resource/vod/notify
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\service\BxVod;
use think\Response;

/**
 * 腾讯云 VOD 转码事件回调入口（M-素材-C，公开接口、不挂鉴权——VOD 服务器回调）。
 * 复刻 M4-C pay/notify 范式：api 应用、无 JwtAuth；安全四件套（验签/幂等/事件处理/ACK）
 * 全部收口于 BxVod::handleNotify，本控制器只取原文 + 头并转交，返回腾讯要求的 ACK 原样输出。
 */
class ResourceVod extends BxController
{
    /**
     * 转码状态变更通知（ProcedureStateChanged 等）。
     */
    public function notify(): Response
    {
        $headers = $this->request->header();
        $body    = $this->request->getInput();

        return (new BxVod($this->app))->handleNotify($headers, $body);
    }
}

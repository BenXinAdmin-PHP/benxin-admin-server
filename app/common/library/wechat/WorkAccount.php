<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   企业微信账号服务 — 配置承载预留（能力按需后续接入）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\wechat;

/**
 * 企业微信（work）账号服务——**预留**（ADR-13）。
 * 企业微信走 qyapi.weixin.qq.com 且 token 语义为 corpid+corpsecret，与 mp/mini 不同源，
 * 故不继承 WechatAccount；本阶段仅承载配置（work_corp_id / work_agent_id / work_secret），
 * 通讯录/应用消息等能力按需后续接入（复杂能力届时评估 easywechat，见任务书）。
 */
class WorkAccount
{
    public function __construct(
        protected string $corpId,
        protected string $agentId,
        protected string $secret,
        protected HttpClientInterface $http,
    ) {
    }

    public function corpId(): string
    {
        return $this->corpId;
    }

    public function agentId(): string
    {
        return $this->agentId;
    }
}

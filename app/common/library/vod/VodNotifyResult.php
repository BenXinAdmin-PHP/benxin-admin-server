<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   VOD 回调解析结果 DTO — 验签结果 + 标准化转码事件字段
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\vod;

/**
 * VOD 事件回调验签 + 解析的标准化结果（渠道差异收敛于 Provider，BxVod 只认本 DTO）。
 * 复刻 M4-C NotifyResult 范式：verified 标志 + 标准化字段 + 原始报文。
 *
 * - verified：验签是否通过（false → BxVod 记 notify_log 后拒绝 + ackFail）。
 * - eventType：归一化事件类型（transcode 转码事件 / 其他透传）。
 * - fileId：点播媒资 ID（= bx_resource.vod_media_id，按它定位素材）。
 * - idemNo：事件去重唯一标识（腾讯 TaskId/EventId，缺则按内容摘要兜底）。
 * - transcodeStatus：目标转码态（3 可播放 / 4 失败；0=非转码事件不更新）。
 * - playUrl：转码完成可选回填的高清播放 URL（v1 可空）。
 */
class VodNotifyResult
{
    /**
     * @param bool                $verified        验签是否通过
     * @param string              $eventType       归一化事件：transcode / other
     * @param string              $fileId          点播媒资 ID（vod_media_id）
     * @param string              $idemNo          幂等去重标识（TaskId/EventId/摘要）
     * @param int                 $transcodeStatus 目标转码态：3 可播放 / 4 失败 / 0 非转码事件
     * @param string              $playUrl         可选高清播放 URL（转码完成回填，v1 可空）
     * @param string              $rawEventType    腾讯原始 EventType（审计）
     * @param array<string,mixed> $raw             原始报文（落 notify_log 审计）
     */
    public function __construct(
        public bool $verified,
        public string $eventType = 'other',
        public string $fileId = '',
        public string $idemNo = '',
        public int $transcodeStatus = 0,
        public string $playUrl = '',
        public string $rawEventType = '',
        public array $raw = [],
    ) {
    }

    /**
     * 是否为应更新 transcode_status 的转码事件（转码中 2 / 可播放 3 / 失败 4）。
     */
    public function isTranscodeEvent(): bool
    {
        return $this->eventType === 'transcode' && in_array($this->transcodeStatus, [2, 3, 4], true);
    }
}

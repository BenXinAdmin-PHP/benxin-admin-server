<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   BxVod 服务 — VOD 接入核心收口（上传凭证/转码回调/删媒资）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service;

use app\common\base\BxService;
use app\common\library\vod\VodException;
use app\common\library\vod\VodManager;
use app\common\library\vod\VodNotifyResult;
use app\common\model\Resource;
use app\common\model\ResourceVodNotifyLog;
use think\facade\Log;
use think\Response;
use Throwable;

/**
 * VOD 点播接入核心服务（M-素材-C，复刻 M4-C「BxPay」自建收口层）。
 *
 * 职责收口：上传凭证签发 / 转码回调处理 / 删云端媒资。
 * ★转码回调安全四件套（复刻 M4-C handleNotify，VOD 适配）：
 *   1) 验签（Provider HMAC-SHA256 校验层）失败 → 记审计(verified=0) + ackFail（不泄露细节）。
 *   2) 幂等：resource_vod_notify_log 唯一键 (event_type, vod_media_id, idem_no) 去重，
 *      已处理重复回调直接 ACK 不重复更新。
 *   3) 事件处理：按 vod_media_id 定位 bx_resource → 更新 transcode_status（2/3/4），终态幂等不回退。
 *   4) ACK：返回腾讯 VOD 要求的响应（{"code":0}）。
 * 全程把回调原文落 notify_log 审计。
 */
class BxVod extends BxService
{
    /**
     * 签发客户端直传上传凭证（admin 侧 upload-sign 调用）。
     * 未开通/配置不全 → VodException::notReady（422101），守 §1 不让默认态挂掉。
     *
     * @param array<string,mixed> $opts media_type / file_name / expire
     * @return array{signature:string,sub_app_id:int,procedure:string,expire:int,region:string}
     */
    public function signUpload(array $opts = []): array
    {
        $provider = VodManager::driver('vod_tx');
        if (!$provider->ready()) {
            throw VodException::notReady();
        }

        return $provider->signUpload($opts);
    }

    /**
     * 删除云端点播媒资（单删/批量删物理清理调用；容错由调用方按 ADR-18 处理）。
     */
    public function deleteMedia(string $fileId): bool
    {
        try {
            return VodManager::driver('vod_tx')->deleteMedia($fileId);
        } catch (Throwable $e) {
            Log::warning('[BxVod] deleteMedia 失败 fileId=' . $fileId . '：' . $e->getMessage());

            return false;
        }
    }

    // ------------------------------------------------------------------
    // 转码回调处理（★核心：验签 → 幂等 → 事件处理（状态机）→ ACK，复刻 M4-C）
    // ------------------------------------------------------------------

    /**
     * 处理 VOD 转码事件回调，返回腾讯 VOD 要求的 ACK。
     *
     * @param array<string,mixed> $headers
     */
    public function handleNotify(array $headers, string $body): Response
    {
        $provider = VodManager::driver('vod_tx');
        $result   = $provider->verifyNotify($headers, $body);

        // 1) 验签失败 → 审计 + 拒绝（不泄露细节；idem_no 用摘要确保审计行可落且相同伪报文去重）
        if (!$result->verified) {
            $this->writeNotifyLog(
                ResourceVodNotifyLog::EVENT_INVALID,
                '',
                'INVALID:' . substr(sha1($body), 0, 40),
                (string) ($result->raw['reason'] ?? ''),
                $body,
                0,
                0,
                '验签失败',
            );

            return $this->ackFail();
        }

        // 2) 幂等锚点：find-or-create；已 processed 直接 ACK
        $log = ResourceVodNotifyLog::where('event_type', $result->eventType)
            ->where('vod_media_id', $result->fileId)
            ->where('idem_no', $result->idemNo)
            ->find();
        if ($log !== null && (int) $log->processed === 1) {
            return $this->ackSuccess();
        }
        if ($log === null) {
            $log = $this->writeNotifyLog($result->eventType, $result->fileId, $result->idemNo, $result->rawEventType, $body, 1, 0, '处理中');
        }

        // 3) 事件处理：转码事件更新 transcode_status；其他事件仅审计 ACK
        try {
            $changed = $this->applyTranscodeNotify($result);
        } catch (Throwable $e) {
            $log->save(['processed' => 0, 'result' => mb_substr($e->getMessage(), 0, 255)]);

            return $this->ackFail();
        }

        $log->save(['processed' => 1, 'result' => $changed ? 'ok' : 'ok(idempotent/ignored)']);

        // 4) ACK
        return $this->ackSuccess();
    }

    /**
     * 应用转码事件：按 vod_media_id 定位素材 → 迁移 transcode_status（2 转码中/3 可播放/4 失败）。
     * 返回是否发生实际更新（终态幂等、未登记媒资、非转码事件均返回 false 不重复处理）。
     */
    protected function applyTranscodeNotify(VodNotifyResult $result): bool
    {
        if (!$result->isTranscodeEvent() || $result->fileId === '') {
            return false; // other 事件或缺 fileId：仅审计，不更新
        }

        $resource = Resource::where('storage', 'vod_tx')
            ->where('vod_media_id', $result->fileId)
            ->find();
        if ($resource === null) {
            return false; // 媒资未在本库登记（confirm 未到/已删）：幂等友好，仅审计
        }

        $current = (int) $resource->transcode_status;
        // 终态（3 可播放 / 4 失败）幂等：不回退、不重复更新
        if (in_array($current, [3, 4], true)) {
            return false;
        }

        $data = ['transcode_status' => $result->transcodeStatus];
        // 可选回填高清播放 URL（仅可播放且回调带 URL 时；v1 不签 PlayAuth，★扩展位见 §7）
        if ($result->transcodeStatus === 3 && $result->playUrl !== '') {
            $data['url'] = mb_substr($result->playUrl, 0, 500);
        }
        $resource->save($data);

        return true;
    }

    // ------------------------------------------------------------------
    // ACK（腾讯 VOD 普通回调：HTTP 200 + {"code":0} 表示已成功接收）
    // ------------------------------------------------------------------

    public function ackSuccess(): Response
    {
        return Response::create(['code' => 0, 'message' => 'success'], 'json', 200);
    }

    public function ackFail(string $msg = ''): Response
    {
        // 验签失败/处理失败：非 0 + HTTP 200（不泄露细节，腾讯按需重试）
        return Response::create(['code' => 1, 'message' => $msg !== '' ? $msg : 'fail'], 'json', 200);
    }

    // ------------------------------------------------------------------
    // 审计
    // ------------------------------------------------------------------

    /**
     * 写回调审计行（幂等锚点 / 验签失败留痕）。raw_body 落原文审计。
     */
    protected function writeNotifyLog(
        string $eventType,
        string $vodMediaId,
        string $idemNo,
        string $rawEventType,
        string $body,
        int $verified,
        int $processed,
        string $result,
    ): ResourceVodNotifyLog {
        return ResourceVodNotifyLog::create([
            'event_type'     => $eventType,
            'vod_media_id'   => mb_substr($vodMediaId, 0, 128),
            'idem_no'        => mb_substr($idemNo, 0, 64),
            'raw_event_type' => mb_substr($rawEventType, 0, 64),
            'raw_body'       => mb_substr($body, 0, 60000),
            'verified'       => $verified,
            'processed'      => $processed,
            'result'         => mb_substr($result, 0, 255),
        ]);
    }
}

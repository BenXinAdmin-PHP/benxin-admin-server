<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   腾讯云 VOD 驱动 — 自建签名适配器（上传凭证/回调验签/删媒资）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\vod;

use app\admin\service\ConfigService;
use think\facade\Log;
use Throwable;

/**
 * 腾讯云 VOD 接入驱动（M-素材-C，ADR-19）。**自建签名，不引第三方 SDK**——
 * 与 BxWechat / Sms 自建 HTTP 签名适配器同范式：更轻、依赖更少、利开源审计（守 §1）。
 *
 * 三类签名各自独立、均为腾讯官方公开算法，可离线对拍：
 *  1) 上传凭证 signUpload：**HMAC-SHA1**（客户端直传签名），signature = base64(hmac_raw . 原文)，
 *     腾讯服务端反向拆 signature 前 20B 重算 hmac 比对——本类生成与服务端校验自洽（mock 对拍）。
 *  2) 回调验签 verifyNotify：**HMAC-SHA256**(rawBody, callback_key) 常量时间比对（复刻 M4-C 验签件）。
 *     ★说明：腾讯 VOD 普通 HTTP 回调原生不对报文签名，本类提供底座共享密钥校验层（签名置于
 *     X-Vod-Signature 头 / sign 查询参 / 报文 Sign 字段）；真实接入若用「可靠回调 + ConfirmEvents」
 *     （TC3 鉴权 API 为权威确认），按此扩展（见 deleteMedia 的 TC3 实现可复用）。真实形态待 daxing 配真实回调核定。
 *  3) 删媒资 deleteMedia：**TC3-HMAC-SHA256**（腾讯云 API v3 标准签名）调 DeleteMedia。
 *
 * 真实凭证下的签发/回调/删媒资为「需真实腾讯云 VOD」边界（同 M4-B/C，留 daxing 验）；
 * 占位配置下经 VodManager::fake 注入伪 Provider 全覆盖编排（mock 验接线就绪）。
 */
class TencentVodProvider implements VodInterface
{
    /** VOD API 主机（TC3 管理类接口，如 DeleteMedia） */
    protected const API_HOST = 'vod.tencentcloudapi.com';
    protected const API_SERVICE = 'vod';
    protected const API_VERSION = '2018-07-17';

    protected string $secretId;
    protected string $secretKey;
    protected int $subAppId;
    protected string $procedure;
    protected string $callbackKey;

    public function __construct(ConfigService $config)
    {
        $this->secretId    = (string) $config->get('vod_tx_secret_id', '');
        $this->secretKey   = (string) $config->get('vod_tx_secret_key', '');
        $this->subAppId    = (int) $config->get('vod_tx_sub_app_id', 0);
        $this->procedure   = trim((string) $config->get('vod_tx_procedure', ''));
        $this->callbackKey = (string) $config->get('vod_tx_callback_key', '');
    }

    /**
     * 必填配置齐全（secret_id/secret_key/sub_app_id 非空）→ 视为「已开通」。
     */
    public function ready(): bool
    {
        return $this->secretId !== '' && $this->secretKey !== '' && $this->subAppId > 0;
    }

    // ------------------------------------------------------------------
    // 1) 客户端直传上传凭证（HMAC-SHA1，腾讯 VOD 官方算法，纯方法可离线对拍）
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $opts media_type / file_name / expire（凭证有效秒，默认 600）
     * @return array{signature:string,sub_app_id:int,procedure:string,expire:int,region:string}
     */
    public function signUpload(array $opts = []): array
    {
        $expire    = max(60, (int) ($opts['expire'] ?? 600)); // 凭证有效期秒（直传窗口，非播放）
        $now       = time();
        $signature = $this->buildUploadSignature($now, $now + $expire, random_int(0, 4294967295));

        return [
            'signature'  => $signature,
            'sub_app_id' => $this->subAppId,
            'procedure'  => $this->procedure, // 非空 → 上传后自动触发转码任务流
            'expire'     => $expire,
            'region'     => '', // VOD 上传无需区域；前端 SDK 自适应
        ];
    }

    /**
     * 构造上传签名原文 + HMAC-SHA1（纯方法，离线对拍用）。
     * 原文 = 标准 query string（值 urlencode）；signature = base64( hmac_sha1_raw(原文,secretKey) . 原文 )。
     */
    public function buildUploadSignature(int $currentTimeStamp, int $expireTime, int $random): string
    {
        $parts = [
            'secretId=' . rawurlencode($this->secretId),
            'currentTimeStamp=' . $currentTimeStamp,
            'expireTime=' . $expireTime,
            'random=' . $random,
        ];
        if ($this->procedure !== '') {
            $parts[] = 'procedure=' . rawurlencode($this->procedure);
        }
        if ($this->subAppId > 0) {
            $parts[] = 'vodSubAppId=' . $this->subAppId;
        }
        $original = implode('&', $parts);

        $hmac = hash_hmac('sha1', $original, $this->secretKey, true); // 20B raw

        return base64_encode($hmac . $original);
    }

    // ------------------------------------------------------------------
    // 2) 转码回调验签 + 解析（HMAC-SHA256 共享密钥校验层，复刻 M4-C 验签件）
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $headers
     */
    public function verifyNotify(array $headers, string $body): VodNotifyResult
    {
        // 未配置回调密钥 → 无法验签，拒绝（不放行未签名回调，安全默认）
        if ($this->callbackKey === '') {
            return new VodNotifyResult(false, raw: ['body' => $body, 'reason' => 'callback_key 未配置']);
        }

        $signature = $this->extractSignature($headers, $body);
        if ($signature === '') {
            return new VodNotifyResult(false, raw: ['body' => $body, 'reason' => '缺少签名']);
        }

        $expected = hash_hmac('sha256', $body, $this->callbackKey);
        if (!hash_equals($expected, strtolower($signature))) {
            return new VodNotifyResult(false, raw: ['body' => $body, 'reason' => '签名不符']);
        }

        return $this->parseNotify($body);
    }

    /**
     * 从请求中取回调签名：X-Vod-Signature 头 / sign 查询参 / 报文 Sign|sign 字段。
     *
     * @param array<string,mixed> $headers
     */
    protected function extractSignature(array $headers, string $body): string
    {
        // header（TP header() 键统一小写）
        foreach (['x-vod-signature', 'x_vod_signature'] as $hk) {
            if (($headers[$hk] ?? '') !== '') {
                return (string) $headers[$hk];
            }
        }
        // query string ?sign=
        $sign = (string) request()->param('sign', '');
        if ($sign !== '') {
            return $sign;
        }
        // body 字段
        $data = json_decode($body, true);
        if (is_array($data)) {
            return (string) ($data['Sign'] ?? $data['sign'] ?? '');
        }

        return '';
    }

    /**
     * 解析腾讯 VOD 事件报文（验签后）为标准 VodNotifyResult。
     * 主处理 ProcedureStateChanged（任务流状态变更，承转码结果）；其余事件归 other 仅 ACK 不更新。
     */
    public function parseNotify(string $body): VodNotifyResult
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return new VodNotifyResult(false, raw: ['body' => $body, 'reason' => '报文非法']);
        }

        $eventType = (string) ($data['EventType'] ?? '');

        if ($eventType === 'ProcedureStateChanged' && is_array($data['ProcedureStateChangeEvent'] ?? null)) {
            $ev     = $data['ProcedureStateChangeEvent'];
            $fileId = (string) ($ev['FileId'] ?? '');
            $status = (string) ($ev['Status'] ?? '');
            $idemNo = (string) ($ev['TaskId'] ?? ($data['EventHandle'] ?? '')) ?: substr(sha1($body), 0, 40);

            // 进行中 → 转码中(2)；完成 → 看结果集 ErrCode 判可播放(3)/失败(4)
            if ($status === 'PROCESSING') {
                $transcode = 2;
            } else {
                $transcode = $this->procedureSucceeded($ev) ? 3 : 4;
            }

            return new VodNotifyResult(
                verified: true,
                eventType: 'transcode',
                fileId: $fileId,
                idemNo: $idemNo,
                transcodeStatus: $transcode,
                playUrl: $this->extractPlayUrl($ev),
                rawEventType: $eventType,
                raw: $data,
            );
        }

        // 其他事件（NewFileUpload / FileDeleted / WorkflowTask 等）：归一 other，仅 ACK 不更新
        $idemNo = (string) ($data['EventHandle'] ?? '') ?: substr(sha1($body), 0, 40);

        return new VodNotifyResult(
            verified: true,
            eventType: 'other',
            idemNo: $idemNo,
            rawEventType: $eventType,
            raw: $data,
        );
    }

    /**
     * 任务流是否成功：所有媒体处理子任务 ErrCode 均为 0（任一非 0 视为失败）。
     *
     * @param array<string,mixed> $event
     */
    protected function procedureSucceeded(array $event): bool
    {
        if (isset($event['ErrCode']) && (int) $event['ErrCode'] !== 0) {
            return false;
        }
        $resultSet = $event['MediaProcessResultSet'] ?? [];
        if (!is_array($resultSet)) {
            return true;
        }
        foreach ($resultSet as $item) {
            if (!is_array($item)) {
                continue;
            }
            // 转码/转自适应码流等子任务 ErrCode（结构因类型而异，统一兜底扫描 ErrCode 字段）
            foreach ($item as $sub) {
                if (is_array($sub) && isset($sub['ErrCode']) && (int) $sub['ErrCode'] !== 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 取转码输出的播放 URL（首个可用），无则空串（v1 不强制回填）。
     *
     * @param array<string,mixed> $event
     */
    protected function extractPlayUrl(array $event): string
    {
        $resultSet = $event['MediaProcessResultSet'] ?? [];
        if (!is_array($resultSet)) {
            return '';
        }
        foreach ($resultSet as $item) {
            if (is_array($item['TranscodeTask']['Output'] ?? null) && ($item['TranscodeTask']['Output']['Url'] ?? '') !== '') {
                return (string) $item['TranscodeTask']['Output']['Url'];
            }
        }

        return '';
    }

    // ------------------------------------------------------------------
    // 3) 删除点播媒资（TC3-HMAC-SHA256，腾讯云 API v3 标准签名）
    // ------------------------------------------------------------------

    /**
     * DeleteMedia(FileId, SubAppId)。容错：异常仅 false，调用方按 ADR-18 仅 Log 不回滚。
     */
    public function deleteMedia(string $fileId): bool
    {
        if ($fileId === '' || !$this->ready()) {
            return false;
        }

        try {
            $payload  = json_encode(['FileId' => $fileId, 'SubAppId' => $this->subAppId], JSON_UNESCAPED_UNICODE) ?: '{}';
            $resp     = $this->callApi('DeleteMedia', $payload);
            $decoded  = json_decode($resp, true);
            $error    = $decoded['Response']['Error'] ?? null;
            if ($error !== null) {
                Log::warning('[TencentVodProvider] DeleteMedia 返回错误：' . json_encode($error, JSON_UNESCAPED_UNICODE));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('[TencentVodProvider] DeleteMedia 失败 fileId=' . $fileId . '：' . $e->getMessage());

            return false;
        }
    }

    /**
     * 调腾讯云 API v3（TC3-HMAC-SHA256 签名，强制 HTTPS + 证书校验）。
     * 纯文本拼装签名（pure，可离线对拍 TC3 算法）；HTTP 发送隔离在 send()。
     */
    protected function callApi(string $action, string $payload): string
    {
        $timestamp = time();
        $headers   = $this->buildTc3Headers($action, $payload, $timestamp);

        return $this->send('https://' . self::API_HOST . '/', $payload, $headers);
    }

    /**
     * 构造 TC3-HMAC-SHA256 鉴权请求头（纯方法，离线对拍腾讯官方示例）。
     *
     * @return array<int,string>
     */
    public function buildTc3Headers(string $action, string $payload, int $timestamp): array
    {
        $date    = gmdate('Y-m-d', $timestamp);
        $service = self::API_SERVICE;
        $host    = self::API_HOST;
        $algo    = 'TC3-HMAC-SHA256';

        // 1) 规范请求串
        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\n";
        $signedHeaders    = 'content-type;host';
        $hashedPayload    = hash('sha256', $payload);
        $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";

        // 2) 待签名串
        $credentialScope = "{$date}/{$service}/tc3_request";
        $stringToSign    = "{$algo}\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // 3) 派生签名密钥 → 签名
        $secretDate    = hash_hmac('sha256', $date, 'TC3' . $this->secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature     = hash_hmac('sha256', $stringToSign, $secretSigning);

        // 4) Authorization
        $authorization = "{$algo} Credential={$this->secretId}/{$credentialScope}, "
            . "SignedHeaders={$signedHeaders}, Signature={$signature}";

        return [
            'Authorization: ' . $authorization,
            'Content-Type: application/json; charset=utf-8',
            'Host: ' . $host,
            'X-TC-Action: ' . $action,
            'X-TC-Timestamp: ' . $timestamp,
            'X-TC-Version: ' . self::API_VERSION,
        ];
    }

    /**
     * 发起 HTTPS POST（强制证书校验，短超时）。隔离便于测试覆盖签名而不真连云。
     *
     * @param array<int,string> $headers
     */
    protected function send(string $url, string $payload, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new \RuntimeException('VOD API 网络错误：' . $err);
        }

        return (string) $resp;
    }
}

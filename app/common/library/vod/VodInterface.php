<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   VOD 接入抽象 — 上传凭证/回调验签/删除媒资统一契约
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\vod;

/**
 * VOD 点播接入统一契约（M-素材-C，ADR-19）。TencentVodProvider 实现（自建签名适配器，
 * 不引第三方 SDK，与 BxWechat/Sms 自建范式一致——更轻、依赖更少、利开源审计）。
 *
 * ★与 StorageInterface 的根本差异（ADR-19/§5）：
 *   VOD = 客户端直传 + 后端签发上传凭证 + 转码回调，**不走服务端 put() 中转**
 *   （视频动辄数百 MB~GB，PHP upload_max_filesize 扛不住）。
 *   故本契约承载 signUpload（签发凭证）/ verifyNotify（回调验签）/ deleteMedia（删媒资），
 *   不含 put()；put 形态差异由 storage\VodTxStorage 适配（put throw、url 播放、delete 删媒资）。
 *
 * 设计：可注入（VodManager::fake）便于离线测试——BxVod 编排（验签/幂等/状态机）
 * 用 FakeProvider 全覆盖，真实凭证下的签发/回调/删媒资为「需真实腾讯云 VOD」边界。
 */
interface VodInterface
{
    /**
     * 必填配置是否齐全（secret_id/secret_key/sub_app_id 非空）；缺则视「未开通」。
     */
    public function ready(): bool;

    /**
     * 签发客户端直传上传凭证（腾讯 VOD 官方 HMAC-SHA1 签名算法，自建）。
     *
     * @param array<string,mixed> $opts 可选：media_type / file_name / expire（凭证有效秒，默认 600）
     * @return array{signature:string,sub_app_id:int,procedure:string,expire:int,region:string}
     *         前端持 signature 直传腾讯 VOD；procedure 非空时上传后自动触发转码任务流。
     */
    public function signUpload(array $opts = []): array;

    /**
     * 验签 + 解析转码事件回调（渠道差异收敛为 VodNotifyResult）。
     * 验签失败返回 verified=false（不泄露细节，交 BxVod 记审计 + 拒绝）。
     *
     * @param array<string,mixed> $headers 回调请求头
     * @param string              $body    回调原文（JSON）
     */
    public function verifyNotify(array $headers, string $body): VodNotifyResult;

    /**
     * 删除云端点播媒资（DeleteMedia，腾讯云 TC3-HMAC-SHA256 API 签名，自建）。
     * 容错由调用方决定（ADR-18：物理删失败仅 Log 不回滚主流程，残留待 GC）。
     *
     * @param string $fileId 点播媒资 ID（= vod_media_id）
     */
    public function deleteMedia(string $fileId): bool;
}

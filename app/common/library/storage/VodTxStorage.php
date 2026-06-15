<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   腾讯云 VOD 存储适配器 — 让 VOD 套入 StorageInterface（put 不适用/url 播放/delete 删媒资）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library\storage;

use app\common\library\vod\VodInterface;
use RuntimeException;

/**
 * VOD 存储适配器（M-素材-C，ADR-19）。让 forMediaType('video'|'audio') 在 vod_tx 开通态
 * 返回一个 StorageInterface 实例，使路由层对 driver 透明。
 *
 * ★VOD 的接口形态差异（§4/§5）：VOD 不是「服务端 put 文件」型驱动——它是**客户端直传 +
 *   后端签发上传凭证 + 转码回调**。故：
 *   - put()    → throw：VOD 不走服务端中转，请用直传端点 /admin/v1/resources/vod/upload-sign + confirm。
 *   - url()    → 播放 URL 透传：v1 用落库的播放 URL（确认回填）起步，不签 PlayAuth；
 *                ★PlayAuth 扩展位见 BxVod（上层业务接官方播放器 SDK + 防盗链时据此扩展，本步不实现）。
 *   - delete() → 删云端点播媒资（DeleteMedia），入参为 vod_media_id(fileId)；容错由调用方按 ADR-18 处理。
 */
class VodTxStorage implements StorageInterface
{
    public function __construct(protected VodInterface $vod)
    {
    }

    /**
     * VOD 不支持服务端 put（视频大，PHP 限额扛不住，走客户端直传）。
     */
    public function put(string $tmpPath, string $saveName): string
    {
        throw new RuntimeException('VOD 走客户端直传，不支持服务端 put；请用 /admin/v1/resources/vod/upload-sign + confirm');
    }

    /**
     * 播放 URL：v1 透传（落库的播放 URL 即取即用，不签 PlayAuth）。
     * ★PlayAuth 防盗链留上层扩展位（ADR-19，本步不实现）。
     */
    public function url(string $path): string
    {
        return $path;
    }

    /**
     * 删除云端点播媒资（DeleteMedia）。$path 传 vod_media_id(fileId)。
     */
    public function delete(string $path): bool
    {
        return $this->vod->deleteMedia($path);
    }
}

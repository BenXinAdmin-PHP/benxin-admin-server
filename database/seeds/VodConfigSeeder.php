<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — VOD 点播配置骨架（group=storage，腾讯云 VOD，M-素材-C，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

use app\common\library\BxCache;
use app\common\library\ConfigCrypt;
use think\facade\Db;
use think\migration\Seeder;

/**
 * M-素材-C 腾讯云 VOD 配置骨架（幂等，逐项 find-or-skip；沿用 group=storage，前缀 vod_tx_ 天然唯一）。
 *  - 音视频驱动选择（非敏感，默认 local）：storage_driver_video / storage_driver_audio（local / vod_tx）。
 *  - 腾讯 VOD secret_id / secret_key / callback_key（敏感 is_sensitive=1 AES 入库，占位假串）。
 *  - sub_app_id（子应用 ID，留 0/空＝未开通）+ procedure（转码任务流模板名，留空＝不触发转码）。
 *  - ★全部占位假串/空值，绝不放真实密钥（守 §8 + git 历史 0 泄露）；secret_id/secret_key/sub_app_id
 *    任一空 → StorageManager 路由 video/audio 自动回退 local（守 §1 默认可跑）。
 *  - group=storage 沿用理由：与 M-素材-B 同组，云存储/点播配置集中、前缀唯一、单缓存组，规避跨组查找。
 *
 * 阿里云 VOD 留扩展位：配置项暂不加（未来加 vod_ali_*，对应 VodManager case 'vod_ali'）。
 */
class VodConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [key, 名称, 是否敏感, 占位值, sort, 备注]
        $items = [
            // 音视频驱动选择（非敏感，默认 local）
            ['storage_driver_video', '素材驱动·视频', 0, 'local', 4, '视频类存储驱动：local / vod_tx（腾讯云VOD），默认 local'],
            ['storage_driver_audio', '素材驱动·音频', 0, 'local', 5, '音频类存储驱动：local / vod_tx（腾讯云VOD），默认 local'],

            // 腾讯云 VOD（敏感 secret AES，sub_app_id/procedure 留空＝未开通/不转码）
            ['vod_tx_secret_id',    '腾讯VOD SecretId',     1, 'PLACEHOLDER_FAKE_VOD_SECRET_ID',              30, '腾讯云 SecretId（占位假串，请在后台改真实值）'],
            ['vod_tx_secret_key',   '腾讯VOD SecretKey',    1, 'PLACEHOLDER_FAKE_VOD_SECRET_KEY_DO_NOT_USE',  31, '腾讯云 SecretKey（占位假串，请在后台改真实值）'],
            ['vod_tx_sub_app_id',   '腾讯VOD 子应用ID',     0, '',  32, 'VOD 子应用 ID（留空/0＝未开通，回退 local）'],
            ['vod_tx_procedure',    '腾讯VOD 转码任务流',   0, '',  33, '转码任务流模板名（留空＝不触发转码，上传即可播）'],
            ['vod_tx_callback_key', '腾讯VOD 回调验签密钥', 1, 'PLACEHOLDER_FAKE_VOD_CALLBACK_KEY',           34, '转码回调验签共享密钥（占位假串，请在后台改真实值）'],
        ];

        $inserted = 0;
        $skipped  = 0;
        foreach ($items as [$key, $name, $sensitive, $value, $sort, $remark]) {
            $exists = Db::name('config')
                ->where(['tenant_id' => 0, 'group' => 'storage', 'key' => $key])
                ->whereNull('deleted_at')
                ->find();
            if ($exists !== null) {
                $skipped++;
                continue;
            }

            Db::name('config')->insert([
                'tenant_id'    => 0,
                'name'         => $name,
                'group'        => 'storage',
                'key'          => $key,
                'value'        => $sensitive === 1 && $value !== '' ? ConfigCrypt::encrypt($value) : $value,
                'is_sensitive' => $sensitive,
                'value_type'   => 'string',
                'sort'         => $sort,
                'remark'       => $remark,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $inserted++;
        }

        // CLI 直插绕过 ConfigService 写失效，手动清聚合缓存
        BxCache::forget('config:all');

        echo "[VodConfigSeeder] 完成：新增 {$inserted}，已存在跳过 {$skipped}。\n";
    }
}

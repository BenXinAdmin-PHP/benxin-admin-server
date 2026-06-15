<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 云存储配置骨架（group=storage，OSS/七牛，M-素材-B，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 16:00:00
// +----------------------------------------------------------------------

use app\common\library\BxCache;
use app\common\library\ConfigCrypt;
use think\facade\Db;
use think\migration\Seeder;

/**
 * M-素材-B 云存储配置骨架（幂等，逐项 find-or-skip；与 ResourceStorageSeeder 同 group=storage 合并）：
 *  - 按 media_type 驱动选择（非敏感，默认 local）：storage_driver_image/document/archive。
 *  - 阿里 OSS + 七牛 AK/SK（敏感 is_sensitive=1 AES 入库）+ endpoint/bucket/domain/有效期（非敏感）。
 *  - ★全部占位假串/空值，绝不放真实密钥（守 §8 + git 历史 0 泄露）；endpoint/bucket/domain 留空＝
 *    「未开通」，StorageManager 按 media_type 路由时自动回退 local（守 §1 默认可跑）。
 *  - 后台改值复用既有参数配置页（M2-B）。
 */
class StorageConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [key, 名称, 是否敏感, 占位值, sort, 备注]
        $items = [
            // 驱动选择（非敏感，默认 local）
            ['storage_driver_image',    '素材驱动·图片',     0, 'local', 1, '图片类存储驱动：local / qiniu（七牛），默认 local'],
            ['storage_driver_document', '素材驱动·文档',     0, 'local', 2, '文档类存储驱动：local / oss（阿里OSS），默认 local'],
            ['storage_driver_archive',  '素材驱动·压缩包',   0, 'local', 3, '压缩包类存储驱动：local / oss（阿里OSS），默认 local'],

            // 阿里 OSS（敏感 AK/SK AES，endpoint/bucket 留空＝未开通）
            ['oss_access_key_id',     '阿里OSS AccessKeyId',     1, 'PLACEHOLDER_FAKE_OSS_AK_ID',              10, '阿里云 AccessKeyId（占位假串，请在后台改真实值）'],
            ['oss_access_key_secret', '阿里OSS AccessKeySecret', 1, 'PLACEHOLDER_FAKE_OSS_AK_SECRET_DO_NOT_USE', 11, '阿里云 AccessKeySecret（占位假串，请在后台改真实值）'],
            ['oss_endpoint',          '阿里OSS Endpoint',        0, '',     12, '如 oss-cn-hangzhou.aliyuncs.com（留空＝未开通，回退 local）'],
            ['oss_bucket',            '阿里OSS Bucket',          0, '',     13, '私有 bucket 名（留空＝未开通）'],
            ['oss_url_expire',        '阿里OSS签名有效期(秒)',   0, '3600', 14, '签名 URL 有效期秒，默认 3600'],

            // 七牛（敏感 AK/SK AES，bucket/domain 留空＝未开通）
            ['qiniu_access_key', '七牛 AccessKey',      1, 'PLACEHOLDER_FAKE_QINIU_AK',              20, '七牛 AccessKey（占位假串，请在后台改真实值）'],
            ['qiniu_secret_key', '七牛 SecretKey',      1, 'PLACEHOLDER_FAKE_QINIU_SK_DO_NOT_USE',   21, '七牛 SecretKey（占位假串，请在后台改真实值）'],
            ['qiniu_bucket',     '七牛 Bucket',         0, '',     22, '私有空间名（留空＝未开通）'],
            ['qiniu_domain',     '七牛 绑定域名',       0, '',     23, '七牛空间绑定的下载域名（留空＝未开通）'],
            ['qiniu_url_expire', '七牛签名有效期(秒)',  0, '3600', 24, '签名 URL 有效期秒，默认 3600'],
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

        echo "[StorageConfigSeeder] 完成：新增 {$inserted}，已存在跳过 {$skipped}。\n";
    }
}

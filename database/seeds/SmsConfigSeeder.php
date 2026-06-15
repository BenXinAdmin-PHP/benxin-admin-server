<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 短信配置骨架（group=sms 前缀 key，M4-D，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

use app\common\library\BxCache;
use app\common\library\ConfigCrypt;
use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-D 短信配置骨架（幂等）：group=sms + 前缀 key（ali_/tencent_）。
 * 全部占位假串、不放真实 AK/SK；敏感项（AK Secret / SecretKey）is_sensitive=1 AES 加密入库。
 * 后台改值复用既有参数配置页（M2-B）。
 */
class SmsConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [key, 名称, 是否敏感, 占位值, sort, 备注]
        $items = [
            ['sms_channel',           '当前短信渠道',     0, 'ali',                          1, '启用渠道：ali / tencent'],
            ['ali_access_key_id',     '阿里云AccessKeyId', 0, 'PLACEHOLDER_FAKE_ALI_AK_ID',  2, '阿里云 AccessKeyId（占位假串）'],
            ['ali_access_key_secret', '阿里云AccessKeySecret', 1, 'PLACEHOLDER_FAKE_ALI_AK_SECRET', 3, '阿里云 AccessKeySecret（AES 入库，占位假串）'],
            ['ali_sign_name',         '阿里云签名',       0, '本心科技',                      4, '阿里云短信签名名（占位）'],
            ['tencent_secret_id',     '腾讯云SecretId',   0, 'PLACEHOLDER_FAKE_TC_SECRET_ID', 5, '腾讯云 SecretId（占位假串）'],
            ['tencent_secret_key',    '腾讯云SecretKey',  1, 'PLACEHOLDER_FAKE_TC_SECRET_KEY', 6, '腾讯云 SecretKey（AES 入库，占位假串）'],
            ['tencent_sdk_app_id',    '腾讯云短信AppId',  0, 'PLACEHOLDER_FAKE_TC_SDK_APPID', 7, '腾讯云短信应用 SdkAppId（占位假串）'],
            ['tencent_sign_name',     '腾讯云签名',       0, '本心科技',                      8, '腾讯云短信签名名（占位）'],
        ];

        $inserted = 0;
        $skipped  = 0;
        foreach ($items as [$key, $name, $sensitive, $value, $sort, $remark]) {
            $exists = Db::name('config')
                ->where(['tenant_id' => 0, 'group' => 'sms', 'key' => $key])
                ->whereNull('deleted_at')
                ->find();
            if ($exists !== null) {
                $skipped++;
                continue;
            }

            Db::name('config')->insert([
                'tenant_id'    => 0,
                'name'         => $name,
                'group'        => 'sms',
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

        BxCache::forget('config:all');

        echo "[SmsConfigSeeder] 完成：新增 {$inserted}，已存在跳过 {$skipped}。\n";
    }
}

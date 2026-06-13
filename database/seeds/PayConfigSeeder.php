<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 支付配置骨架（group=pay 前缀 key，M4-C，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

use app\common\library\BxCache;
use app\common\library\ConfigCrypt;
use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-C 支付配置骨架（幂等）：
 *  - 单 group=pay + 前缀 key（wxpay_ 微信支付 / alipay_ 支付宝）。微信支付 appid 复用 wechat 组。
 *  - 全部占位假串、私钥/证书**不放真实内容**（开源边界）；敏感项 is_sensitive=1 AES 加密入库。
 *  - 私钥类用占位串（运行时由 ConfigService 解密后以字符串注入 yansongda，不落文件）。
 *  - 后台改值复用既有参数配置页（M2-B），不新建专门页。
 */
class PayConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [key, 名称, 是否敏感, 占位值, sort, 备注]
        $items = [
            ['wxpay_mch_id',      '微信商户号',       0, 'PLACEHOLDER_FAKE_MCH_ID',     1, '微信支付商户号（占位假串，请在后台改为真实值）'],
            ['wxpay_api_v3_key',  '微信APIv3密钥',    1, 'PLACEHOLDER_FAKE_APIV3_KEY',  2, '微信支付 APIv3 密钥（占位假串）'],
            ['wxpay_cert_serial', '微信证书序列号',   0, 'PLACEHOLDER_FAKE_CERT_SERIAL', 3, '商户证书序列号（占位假串）'],
            ['wxpay_private_key', '微信商户私钥',     1, 'PLACEHOLDER_FAKE_PRIVATE_KEY_PEM', 4, '商户私钥 PEM 内容（AES 入库，占位假串；不落文件不进仓库）'],
            ['wxpay_notify_url',  '微信回调地址',     0, '',                            5, '微信支付异步通知地址，如 https://域名/api/v1/pay/notify/wechat'],
            ['alipay_app_id',     '支付宝AppID',      0, 'PLACEHOLDER_FAKE_ALIPAY_APPID', 6, '支付宝 AppID（占位假串）'],
            ['alipay_private_key', '支付宝应用私钥',  1, 'PLACEHOLDER_FAKE_ALIPAY_PRIVATE_KEY', 7, '支付宝应用私钥（AES 入库，占位假串）'],
            ['alipay_public_key', '支付宝公钥',       0, 'PLACEHOLDER_FAKE_ALIPAY_PUBLIC_KEY', 8, '支付宝公钥/证书（占位假串）'],
            ['alipay_notify_url', '支付宝回调地址',   0, '',                            9, '支付宝异步通知地址，如 https://域名/api/v1/pay/notify/alipay'],
        ];

        $inserted = 0;
        $skipped  = 0;
        foreach ($items as [$key, $name, $sensitive, $value, $sort, $remark]) {
            $exists = Db::name('config')
                ->where(['tenant_id' => 0, 'group' => 'pay', 'key' => $key])
                ->whereNull('deleted_at')
                ->find();
            if ($exists !== null) {
                $skipped++;
                continue;
            }

            Db::name('config')->insert([
                'tenant_id'    => 0,
                'name'         => $name,
                'group'        => 'pay',
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

        echo "[PayConfigSeeder] 完成：新增 {$inserted}，已存在跳过 {$skipped}。\n";
    }
}

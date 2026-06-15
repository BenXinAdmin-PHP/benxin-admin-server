<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 微信配置骨架（group=wechat 类型前缀 key，M4-B，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 16:00:00
// +----------------------------------------------------------------------

use app\common\library\BxCache;
use app\common\library\ConfigCrypt;
use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-B 微信配置骨架（幂等）：
 *  - 单 group=wechat + 类型前缀 key（mp_ 公众号 / mini_ 小程序 / work_ 企业微信预留），
 *    规避多组同名 key，ConfigService::get 直接可用。
 *  - 全部占位假串、不放真实密钥（沿用 M2-B mp_app_secret 先例）；敏感项 is_sensitive=1 AES 加密入库。
 *  - 与 M2-B 旧示例 wechat/mp_app_secret 幂等合并：已存在的 key 一律跳过不重复灌。
 *  - 后台改值复用既有参数配置页（M2-B），不新建专门页。
 */
class WechatConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // [key, 名称, 是否敏感, 占位值, sort, 备注]
        $items = [
            ['mp_app_id',     '公众号AppID',          0, 'PLACEHOLDER_FAKE_MP_APPID',      1, '公众号 AppID（占位假串，请在后台改为真实值）'],
            ['mp_app_secret', '公众号AppSecret',      1, 'PLACEHOLDER_FAKE_SECRET_DO_NOT_USE', 2, '公众号 AppSecret（占位假串，请在后台改为真实值）'],
            ['mp_token',      '公众号消息校验Token',  0, '',                               3, '公众号消息校验 Token（预留，消息能力接入时配置）'],
            ['mp_aes_key',    '公众号消息加解密Key',  1, '',                               4, '公众号消息加解密 EncodingAESKey（预留）'],
            ['mini_app_id',   '小程序AppID',          0, 'PLACEHOLDER_FAKE_MINI_APPID',    5, '小程序 AppID（占位假串，请在后台改为真实值）'],
            ['mini_app_secret', '小程序AppSecret',    1, 'PLACEHOLDER_FAKE_MINI_SECRET_DO_NOT_USE', 6, '小程序 AppSecret（占位假串，请在后台改为真实值）'],
            ['work_corp_id',  '企业微信CorpID',       0, 'PLACEHOLDER_FAKE_WORK_CORPID',   7, '企业微信 CorpID（预留，占位假串）'],
            ['work_agent_id', '企业微信AgentID',      0, 'PLACEHOLDER_FAKE_WORK_AGENTID',  8, '企业微信应用 AgentID（预留，占位假串）'],
            ['work_secret',   '企业微信Secret',       1, 'PLACEHOLDER_FAKE_WORK_SECRET_DO_NOT_USE', 9, '企业微信应用 Secret（预留，占位假串）'],
        ];

        $inserted = 0;
        $skipped  = 0;
        foreach ($items as [$key, $name, $sensitive, $value, $sort, $remark]) {
            $exists = Db::name('config')
                ->where(['tenant_id' => 0, 'group' => 'wechat', 'key' => $key])
                ->whereNull('deleted_at')
                ->find();
            if ($exists !== null) {
                $skipped++;
                continue;
            }

            Db::name('config')->insert([
                'tenant_id'    => 0,
                'name'         => $name,
                'group'        => 'wechat',
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

        // CLI 直插绕过 ConfigService 写失效，这里手动清聚合缓存（key 同 ConfigService::CACHE_KEY）
        BxCache::forget('config:all');

        echo "[WechatConfigSeeder] 完成：新增 {$inserted}，已存在跳过 {$skipped}。\n";
    }
}

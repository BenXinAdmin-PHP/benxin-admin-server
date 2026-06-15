<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — bx_config 初始示例配置
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-14
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * 配置中心初始数据（幂等，逐项 find-or-skip）：
 *  - 插入站点名示例配置；按 tenant_id+group+key 唯一识别，存在则跳过，避免重跑撞唯一键 uk_tenant_group_key。
 */
class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 初始配置项（保持原内容/字段不变，仅改为幂等增量插入）
        $configs = [
            [
                'tenant_id'  => 0,
                'group'      => 'site',
                'key'        => 'site_name',
                'value'      => 'BenXinAdmin',
                'remark'     => '站点名称（示例配置）',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ];

        foreach ($configs as $config) {
            // 按租户+分组+键查存在（软删感知），存在则跳过，不撞唯一键
            $exists = Db::name('config')
                ->where([
                    'tenant_id' => $config['tenant_id'],
                    'group'     => $config['group'],
                    'key'       => $config['key'],
                ])
                ->whereNull('deleted_at')
                ->find();
            if ($exists !== null) {
                continue;
            }
            Db::name('config')->insert($config);
        }

        echo "[ConfigSeeder] 完成。\n";
    }
}

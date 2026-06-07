<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — bx_config 初始示例配置
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

use think\migration\Seeder;

/**
 * 配置中心初始数据：插入 1 行站点名示例配置，验证 seed 工具链。
 */
class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->table('config')->insert([
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
        ])->saveData();
    }
}

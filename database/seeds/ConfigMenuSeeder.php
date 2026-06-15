<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 参数配置菜单/权限 + 敏感配置示例（M2-B，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// +----------------------------------------------------------------------

use app\common\library\ConfigCrypt;
use think\facade\Db;
use think\migration\Seeder;

/**
 * M2-B 增量种子（幂等）：
 *  - 「系统管理」目录下新增「参数配置」菜单 + system:config:list/create/update/delete 四按钮。
 *  - 敏感配置示例 wechat/mp_app_secret（is_sensitive=1，值为占位假串、AES 加密入库），验证加密链路。
 *  - 注意：不放任何真实密钥。
 */
class ConfigMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'System'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            echo "[ConfigMenuSeeder] 未找到系统管理目录(System)，请先跑 AuthSeeder。\n";
            return;
        }

        $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'SystemConfig'])->whereNull('deleted_at')->value('id');
        if ($menuId === 0) {
            $menuId = (int) Db::name('menu')->insertGetId([
                'tenant_id'  => 0,
                'parent_id'  => $dirId,
                'type'       => 2,
                'name'       => 'SystemConfig',
                'title'      => '参数配置',
                'path'       => '/system/config',
                'component'  => 'system/config/index',
                'perms'      => '',
                'icon'       => 'setting',
                'sort'       => 7,
                'status'     => 1,
                'visible'    => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([['list', '查询', 1], ['create', '新增', 2], ['update', '修改', 3], ['delete', '删除', 4]] as [$act, $title, $sort]) {
            $perms = 'system:config:' . $act;
            if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
                Db::name('menu')->insert([
                    'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => $title,
                    'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => $sort,
                    'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // 敏感配置示例（占位假串，加密入库）
        $exists = Db::name('config')->where(['tenant_id' => 0, 'group' => 'wechat', 'key' => 'mp_app_secret'])->whereNull('deleted_at')->find();
        if ($exists === null) {
            Db::name('config')->insert([
                'tenant_id'    => 0,
                'name'         => '公众号AppSecret',
                'group'        => 'wechat',
                'key'          => 'mp_app_secret',
                'value'        => ConfigCrypt::encrypt('PLACEHOLDER_FAKE_SECRET_DO_NOT_USE'),
                'is_sensitive' => 1,
                'value_type'   => 'string',
                'sort'         => 1,
                'remark'       => '示例敏感配置（占位假串，请在后台改为真实值）',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        echo "[ConfigMenuSeeder] 完成。\n";
    }
}

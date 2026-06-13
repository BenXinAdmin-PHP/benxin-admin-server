<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 支付订单菜单/权限（M4-C，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 11:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-C 增量种子（幂等）：「系统管理」目录下新增「支付订单」菜单 +
 * system:pay:list（列表/详情只读）/ system:pay:refund（退款，敏感）两按钮。
 * 支付订单为「只读 + 退款」特殊形态，无新增/编辑按钮。
 */
class PayMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'System'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            echo "[PayMenuSeeder] 未找到系统管理目录(System)，请先跑 AuthSeeder。\n";
            return;
        }

        $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'PayOrder'])->whereNull('deleted_at')->value('id');
        if ($menuId === 0) {
            $menuId = (int) Db::name('menu')->insertGetId([
                'tenant_id'  => 0,
                'parent_id'  => $dirId,
                'type'       => 2,
                'name'       => 'PayOrder',
                'title'      => '支付订单',
                'path'       => '/system/pay-order',
                'component'  => 'system/pay-order/index',
                'perms'      => '',
                'icon'       => 'money',
                'sort'       => 9,
                'status'     => 1,
                'visible'    => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([['list', '查询', 1], ['refund', '退款', 2]] as [$act, $title, $sort]) {
            $perms = 'system:pay:' . $act;
            if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
                Db::name('menu')->insert([
                    'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => $title,
                    'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => $sort,
                    'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        echo "[PayMenuSeeder] 完成。\n";
    }
}

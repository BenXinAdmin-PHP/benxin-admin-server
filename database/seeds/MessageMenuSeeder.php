<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 消息管理目录 + 短信日志菜单（M4-D D-1，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-D 增量种子（幂等）：顶级「消息管理」目录 + 「短信日志」菜单（只读，system:sms:log:list）。
 * 目录由本 seeder find-or-create；D-2 bx:make 生成的短信模板/系统公告 seeder 经 menuDir=消息管理
 * 复用同一目录（M3-E menuDir 红利，find-or-create 不重复建）。
 */
class MessageMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1) 顶级「消息管理」目录（与 D-2 bx:make menuDir 同名/同路径，幂等复用）
        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'Message'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            $dirId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => 0, 'type' => 1, 'name' => 'Message', 'title' => '消息管理',
                'path' => '/message', 'component' => '', 'perms' => '', 'icon' => 'chat-dot-round',
                'sort' => 3, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 2) 短信日志菜单（只读）
        $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'SmsLog'])->whereNull('deleted_at')->value('id');
        if ($menuId === 0) {
            $menuId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => $dirId, 'type' => 2, 'name' => 'SmsLog', 'title' => '短信日志',
                'path' => '/message/sms-log', 'component' => 'message/sms-log/index', 'perms' => '', 'icon' => 'message',
                'sort' => 9, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => 'system:sms:log:list'])->whereNull('deleted_at')->find() === null) {
            Db::name('menu')->insert([
                'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => '查询',
                'path' => '', 'component' => '', 'perms' => 'system:sms:log:list', 'icon' => '', 'sort' => 1,
                'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        echo "[MessageMenuSeeder] 完成。\n";
    }
}

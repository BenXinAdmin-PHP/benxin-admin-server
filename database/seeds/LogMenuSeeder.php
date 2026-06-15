<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 操作日志/登录日志菜单与权限（M2-C，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M2-C 增量种子（幂等）：系统管理目录下新增「操作日志」「登录日志」菜单 +
 * system:operlog:list|delete、system:loginlog:list|delete 权限。
 */
class LogMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'System'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            echo "[LogMenuSeeder] 未找到系统管理目录(System)，请先跑 AuthSeeder。\n";
            return;
        }

        // [菜单 name, 标题, path, 权限前缀, 排序, 动作集]
        $modules = [
            ['SystemOperLog', '操作日志', '/system/oper-log', 'system:operlog', 8, [['list', '查询', 1], ['delete', '清理', 2]]],
            ['SystemLoginLog', '登录日志', '/system/login-log', 'system:loginlog', 9, [['list', '查询', 1], ['delete', '清理', 2]]],
        ];

        foreach ($modules as [$name, $title, $path, $permPrefix, $sort, $actions]) {
            $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => $name])->whereNull('deleted_at')->value('id');
            if ($menuId === 0) {
                $menuId = (int) Db::name('menu')->insertGetId([
                    'tenant_id' => 0, 'parent_id' => $dirId, 'type' => 2, 'name' => $name, 'title' => $title,
                    'path' => $path, 'component' => ltrim($path, '/') . '/index', 'perms' => '', 'icon' => 'document',
                    'sort' => $sort, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($actions as [$act, $btnTitle, $btnSort]) {
                $perms = $permPrefix . ':' . $act;
                if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
                    Db::name('menu')->insert([
                        'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => $btnTitle,
                        'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => $btnSort,
                        'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        echo "[LogMenuSeeder] 完成。\n";
    }
}

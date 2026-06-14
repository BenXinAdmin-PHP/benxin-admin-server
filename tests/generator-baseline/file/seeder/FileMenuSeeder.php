<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 文件菜单与权限（生成器产出，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * 文件菜单 + system:file:list|create|update|delete 权限（幂等）。
 * 落地方式：复制本文件到 database/seeds/ 后 `php think seed:run -s FileMenuSeeder`，
 * 或并入既有种子流程；依赖 AuthSeeder 已建「系统管理」目录(System)。
 */
class FileMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'System'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            echo "[FileMenuSeeder] 未找到系统管理目录(System)，请先跑 AuthSeeder。\n";
            return;
        }

        $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'SystemFile'])->whereNull('deleted_at')->value('id');
        if ($menuId === 0) {
            $menuId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => $dirId, 'type' => 2, 'name' => 'SystemFile', 'title' => '文件',
                'path' => '/system/file', 'component' => 'system/file/index', 'perms' => '', 'icon' => 'menu',
                'sort' => 10, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([['list', '查询', 1], ['create', '新增', 2], ['update', '修改', 3], ['delete', '删除', 4]] as [$act, $title, $sort]) {
            $perms = 'system:file:' . $act;
            if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
                Db::name('menu')->insert([
                    'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => $title,
                    'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => $sort,
                    'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        echo "[FileMenuSeeder] 完成。\n";
    }
}

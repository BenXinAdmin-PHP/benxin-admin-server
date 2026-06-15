<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 素材分类菜单与权限（生成器产出，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * 素材分类菜单 + system:resource:category:list|create|update|delete 权限（幂等）。
 * 落地方式：复制本文件到 database/seeds/ 后 `php think seed:run -s ResourceCategoryMenuSeeder`，
 * 或并入既有种子流程；「素材管理」目录(Resource)不存在时自动创建。
 */
class ResourceCategoryMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'Resource'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            $dirId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => 0, 'type' => 1, 'name' => 'Resource', 'title' => '素材管理',
                'path' => '/resource', 'component' => '', 'perms' => '', 'icon' => 'picture',
                'sort' => 3, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'ResourceCategory'])->whereNull('deleted_at')->value('id');
        if ($menuId === 0) {
            $menuId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => $dirId, 'type' => 2, 'name' => 'ResourceCategory', 'title' => '素材分类',
                'path' => '/resource/category', 'component' => 'resource/category/index', 'perms' => '', 'icon' => 'folder-opened',
                'sort' => 1, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([['list', '查询', 1], ['create', '新增', 2], ['update', '修改', 3], ['delete', '删除', 4]] as [$act, $title, $sort]) {
            $perms = 'system:resource:category:' . $act;
            if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
                Db::name('menu')->insert([
                    'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => $title,
                    'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => $sort,
                    'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        echo "[ResourceCategoryMenuSeeder] 完成。\n";
    }
}

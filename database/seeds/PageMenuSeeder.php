<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 网站管理目录 + 页面管理菜单 + system:page:* 权限（M6-B，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M6-B 增量种子（幂等，find-or-skip）：
 *  - 顶级「网站管理」目录（find-or-create，不写死 System，与内容模块同范式）。
 *  - 其下「页面管理」菜单，component 占位 site/page/index（真实页 M6-C 出）。
 *  - 三按钮 perms：system:page:list（查询）/ system:page:save（保存=新增+更新）/ system:page:delete（删除），
 *    与 admin 路由声明一致；超管通配策略命中、其余角色经角色分配菜单授权。
 *
 * 注：本任务手写、不入生成器基线（ADR-21）。
 */
class PageMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1) 顶级「网站管理」目录
        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'Site'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            $dirId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => 0, 'type' => 1, 'name' => 'Site', 'title' => '网站管理',
                'path' => '/site', 'component' => '', 'perms' => '', 'icon' => 'monitor',
                'sort' => 9, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 2)「页面管理」菜单
        $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'SitePage'])->whereNull('deleted_at')->value('id');
        if ($menuId === 0) {
            $menuId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => $dirId, 'type' => 2, 'name' => 'SitePage', 'title' => '页面管理',
                'path' => '/site/page', 'component' => 'site/page/index', 'perms' => '', 'icon' => 'document-copy',
                'sort' => 1, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 3) 三按钮 perms（list/save/delete，与路由一致）
        foreach ([['list', '查询', 1], ['save', '保存', 2], ['delete', '删除', 3]] as [$act, $title, $sort]) {
            $perms = 'system:page:' . $act;
            if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
                Db::name('menu')->insert([
                    'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => $title,
                    'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => $sort,
                    'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        echo "[PageMenuSeeder] 完成。\n";
    }
}

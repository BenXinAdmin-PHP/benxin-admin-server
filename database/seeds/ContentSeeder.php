<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 内容模块菜单/权限 + 内容状态字典（M4-A，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 15:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-A 增量种子（幂等，存在则跳过）：
 *  - 顶级「内容管理」目录 + 内容分类/内容/广告位三菜单，
 *    各挂 list/create/update/delete 四按钮（status 动作复用 update perm，与路由一致）。
 *  - 字典 sys_content_status（0草稿/1已发布/2已下架）；复用既有 sys_normal_disable/sys_yes_no。
 *
 * 注：bx:make 生成的三份 MenuSeeder 写死挂「系统管理」目录与 /system 路径（M4-A 吃狗粮
 * 缺口，回炉候选：目录/路径可声明），故按内容模块实际归属手工合并为本文件。
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1) 顶级「内容管理」目录
        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'Content'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            $dirId = (int) Db::name('menu')->insertGetId([
                'tenant_id' => 0, 'parent_id' => 0, 'type' => 1, 'name' => 'Content', 'title' => '内容管理',
                'path' => '/content', 'component' => '', 'perms' => '', 'icon' => 'document',
                'sort' => 2, 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 2) 三个业务菜单 + 各 4 按钮 perms（perms 前缀与 configs/ 元数据一致）
        $modules = [
            ['name' => 'ContentCategory', 'title' => '内容分类', 'dir' => 'category', 'perm' => 'content:category', 'icon' => 'folder-opened', 'sort' => 1],
            ['name' => 'ContentInfo', 'title' => '内容', 'dir' => 'info', 'perm' => 'content:info', 'icon' => 'tickets', 'sort' => 2],
            ['name' => 'ContentBanner', 'title' => '广告位', 'dir' => 'banner', 'perm' => 'content:banner', 'icon' => 'picture', 'sort' => 3],
        ];
        foreach ($modules as $m) {
            $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => $m['name']])->whereNull('deleted_at')->value('id');
            if ($menuId === 0) {
                $menuId = (int) Db::name('menu')->insertGetId([
                    'tenant_id' => 0, 'parent_id' => $dirId, 'type' => 2, 'name' => $m['name'], 'title' => $m['title'],
                    'path' => '/content/' . $m['dir'], 'component' => 'content/' . $m['dir'] . '/index', 'perms' => '', 'icon' => $m['icon'],
                    'sort' => $m['sort'], 'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ([['list', '查询', 1], ['create', '新增', 2], ['update', '修改', 3], ['delete', '删除', 4]] as [$act, $title, $sort]) {
                $perms = $m['perm'] . ':' . $act;
                if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
                    Db::name('menu')->insert([
                        'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => $title,
                        'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => $sort,
                        'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        // 3) 内容状态字典（复刻 M2-A DictSeeder 范式）
        $this->ensureDict('sys_content_status', '内容状态', [
            ['label' => '草稿', 'value' => '0', 'sort' => 1, 'list_class' => 'info', 'is_default' => 1],
            ['label' => '已发布', 'value' => '1', 'sort' => 2, 'list_class' => 'success', 'is_default' => 0],
            ['label' => '已下架', 'value' => '2', 'sort' => 3, 'list_class' => 'warning', 'is_default' => 0],
        ], $now);

        echo "[ContentSeeder] 完成。\n";
    }

    /**
     * 幂等插入字典类型 + 其数据项（同 DictSeeder 范式）。
     *
     * @param array<int,array<string,mixed>> $items
     */
    protected function ensureDict(string $type, string $name, array $items, string $now): void
    {
        $exists = (int) Db::name('dict')->where(['tenant_id' => 0, 'type' => $type])->whereNull('deleted_at')->value('id');
        if ($exists === 0) {
            Db::name('dict')->insert([
                'tenant_id'  => 0,
                'name'       => $name,
                'type'       => $type,
                'status'     => 1,
                'remark'     => '内容模块内置字典',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($items as $item) {
            $has = Db::name('dict_data')
                ->where(['tenant_id' => 0, 'dict_type' => $type, 'value' => $item['value']])
                ->whereNull('deleted_at')->find();
            if ($has !== null) {
                continue;
            }
            Db::name('dict_data')->insert([
                'tenant_id'  => 0,
                'dict_type'  => $type,
                'label'      => $item['label'],
                'value'      => $item['value'],
                'sort'       => $item['sort'],
                'status'     => 1,
                'list_class' => $item['list_class'] ?? '',
                'is_default' => $item['is_default'] ?? 0,
                'remark'     => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 字典管理菜单/权限 + 示例字典（M2-A，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M2-A 增量种子（幂等，存在则跳过）：
 *  - 「系统管理」目录下新增「字典管理」菜单 + system:dict:list/create/update/delete 四按钮。
 *  - 示例字典：sys_normal_disable（正常/停用）、sys_yes_no（是/否），供全站状态枚举统一引用。
 */
class DictSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1) 字典管理菜单（挂在系统管理目录下）
        $dirId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'System'])->whereNull('deleted_at')->value('id');
        if ($dirId === 0) {
            echo "[DictSeeder] 未找到系统管理目录(System)，请先跑 AuthSeeder。\n";
            return;
        }

        $menuId = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => 'SystemDict'])->whereNull('deleted_at')->value('id');
        if ($menuId === 0) {
            $menuId = (int) Db::name('menu')->insertGetId([
                'tenant_id'  => 0,
                'parent_id'  => $dirId,
                'type'       => 2,
                'name'       => 'SystemDict',
                'title'      => '字典管理',
                'path'       => '/system/dict',
                'component'  => 'system/dict/index',
                'perms'      => '',
                'icon'       => 'collection',
                'sort'       => 6,
                'status'     => 1,
                'visible'    => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $actions = [
            ['act' => 'list', 'title' => '查询', 'sort' => 1],
            ['act' => 'create', 'title' => '新增', 'sort' => 2],
            ['act' => 'update', 'title' => '修改', 'sort' => 3],
            ['act' => 'delete', 'title' => '删除', 'sort' => 4],
        ];
        foreach ($actions as $a) {
            $perms = 'system:dict:' . $a['act'];
            $exists = Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find();
            if ($exists === null) {
                Db::name('menu')->insert([
                    'tenant_id'  => 0,
                    'parent_id'  => $menuId,
                    'type'       => 3,
                    'name'       => '',
                    'title'      => $a['title'],
                    'path'       => '',
                    'component'  => '',
                    'perms'      => $perms,
                    'icon'       => '',
                    'sort'       => $a['sort'],
                    'status'     => 1,
                    'visible'    => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2) 示例字典
        $this->ensureDict('sys_normal_disable', '系统状态', [
            ['label' => '正常', 'value' => '1', 'sort' => 1, 'list_class' => 'success', 'is_default' => 1],
            ['label' => '停用', 'value' => '0', 'sort' => 2, 'list_class' => 'danger', 'is_default' => 0],
        ], $now);

        $this->ensureDict('sys_yes_no', '是否', [
            ['label' => '是', 'value' => '1', 'sort' => 1, 'list_class' => 'success', 'is_default' => 0],
            ['label' => '否', 'value' => '0', 'sort' => 2, 'list_class' => 'info', 'is_default' => 1],
        ], $now);

        echo "[DictSeeder] 完成。\n";
    }

    /**
     * 幂等插入字典类型 + 其数据项。
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
                'remark'     => '内置示例字典',
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

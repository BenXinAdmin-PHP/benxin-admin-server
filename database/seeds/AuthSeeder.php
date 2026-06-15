<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — M1 认证基建初始数据（部门/岗位/超管角色账号/菜单/Casbin）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// | @updated   2026-06-14
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M1-A 初始数据，全程幂等（存在则跳过）：
 *  - 根部门「本心科技」、示例岗位「项目经理」。
 *  - 超级管理员角色 super_admin、超管账号 admin（密码取 .env SUPER_ADMIN_INIT_PWD，Argon2id）。
 *  - 菜单骨架：系统管理(目录) → 管理员/角色/菜单/部门/岗位(菜单)，每菜单挂 list/create/update/delete 四按钮。
 *  - Casbin 通配策略 p, super_admin, 0, *, *（domain=tenant_id=0；本步只写数据，加载留 M1-B）。
 *
 * 注：超管密码不硬编码，从 .env 读取；缺省则跳过账号创建并提示。
 */
class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1) 根部门 ----------------------------------------------------
        $deptId = $this->ensure('dept', ['tenant_id' => 0, 'name' => '本心科技'], [
            'tenant_id'  => 0,
            'parent_id'  => 0,
            'name'       => '本心科技',
            'leader'     => '',
            'sort'       => 1,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2) 示例岗位 --------------------------------------------------
        $this->ensure('post', ['tenant_id' => 0, 'code' => 'pm'], [
            'tenant_id'  => 0,
            'code'       => 'pm',
            'name'       => '项目经理',
            'sort'       => 1,
            'status'     => 1,
            'remark'     => '示例岗位',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $postId = (int) Db::name('post')->where(['tenant_id' => 0, 'code' => 'pm'])->whereNull('deleted_at')->value('id');

        // 3) 超级管理员角色 -------------------------------------------
        $roleId = $this->ensure('role', ['tenant_id' => 0, 'code' => 'super_admin'], [
            'tenant_id'  => 0,
            'name'       => '超级管理员',
            'code'       => 'super_admin',
            'sort'       => 1,
            'status'     => 1,
            'data_scope' => 1,
            'remark'     => '内置超级管理员，权限由 Casbin 通配策略承载',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 4) 超管账号（密码取 .env，Argon2id）-------------------------
        $pwd = (string) env('SUPER_ADMIN_INIT_PWD', '');
        if ($pwd === '') {
            echo "[AuthSeeder] 跳过超管账号：.env SUPER_ADMIN_INIT_PWD 为空，请配置后重跑。\n";
        } else {
            // 方案 A：超管账号已存在时不静默跳过，明确提示密码未更新（ensure 不更新既有行）
            $existingAdminId = (int) Db::name('admin')
                ->where(['tenant_id' => 0, 'username' => 'admin'])
                ->whereNull('deleted_at')
                ->value('id');
            if ($existingAdminId > 0) {
                echo "[AuthSeeder] 超管账号 admin 已存在，未更新密码；如需重置请用改密接口或先删除该账号重跑。\n";
            }

            $adminId = $this->ensure('admin', ['tenant_id' => 0, 'username' => 'admin'], [
                'tenant_id'  => 0,
                'username'   => 'admin',
                'password'   => password_hash($pwd, PASSWORD_ARGON2ID),
                'nickname'   => '超级管理员',
                'dept_id'    => $deptId,
                'status'     => 1,
                'remark'     => '内置超管，首次登录后请立即修改密码',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 关联超管角色 + 根部门岗位（关联表无软删，仅 created_at）
            $this->ensure('admin_role', ['admin_id' => $adminId, 'role_id' => $roleId], [
                'admin_id'   => $adminId,
                'role_id'    => $roleId,
                'created_at' => $now,
            ], false);
            if ($postId > 0) {
                $this->ensure('admin_post', ['admin_id' => $adminId, 'post_id' => $postId], [
                    'admin_id'   => $adminId,
                    'post_id'    => $postId,
                    'created_at' => $now,
                ], false);
            }
        }

        // 5) 菜单骨架 --------------------------------------------------
        $this->seedMenus($now);

        // 6) Casbin 通配策略（仅写数据，加载留 M1-B）-------------------
        $this->ensure('casbin_rule', ['ptype' => 'p', 'v0' => 'super_admin', 'v1' => '0', 'v2' => '*', 'v3' => '*'], [
            'ptype' => 'p',
            'v0'    => 'super_admin',
            'v1'    => '0',
            'v2'    => '*',
            'v3'    => '*',
        ], false);

        echo "[AuthSeeder] 完成。\n";
    }

    /**
     * 菜单骨架：系统管理目录 + 5 个业务菜单，每菜单 4 个按钮权限点。
     */
    protected function seedMenus(string $now): void
    {
        // 系统管理目录
        $dirId = $this->ensureMenu([
            'tenant_id' => 0,
            'parent_id' => 0,
            'type'      => 1,
            'name'      => 'System',
            'title'     => '系统管理',
            'path'      => '/system',
            'component' => '',
            'perms'     => '',
            'icon'      => 'setting',
            'sort'      => 1,
            'visible'   => 1,
        ], $now);

        // 业务菜单 + 按钮
        $modules = [
            ['key' => 'admin', 'title' => '管理员管理', 'name' => 'SystemAdmin', 'icon' => 'user'],
            ['key' => 'role',  'title' => '角色管理',   'name' => 'SystemRole',  'icon' => 'avatar'],
            ['key' => 'menu',  'title' => '菜单管理',   'name' => 'SystemMenu',  'icon' => 'menu'],
            ['key' => 'dept',  'title' => '部门管理',   'name' => 'SystemDept',  'icon' => 'office-building'],
            ['key' => 'post',  'title' => '岗位管理',   'name' => 'SystemPost',  'icon' => 'briefcase'],
        ];
        $actions = [
            ['act' => 'list',   'title' => '查询', 'sort' => 1],
            ['act' => 'create', 'title' => '新增', 'sort' => 2],
            ['act' => 'update', 'title' => '修改', 'sort' => 3],
            ['act' => 'delete', 'title' => '删除', 'sort' => 4],
        ];

        $sort = 1;
        foreach ($modules as $m) {
            $menuId = $this->ensureMenu([
                'tenant_id' => 0,
                'parent_id' => $dirId,
                'type'      => 2,
                'name'      => $m['name'],
                'title'     => $m['title'],
                'path'      => '/system/' . $m['key'],
                'component' => 'system/' . $m['key'] . '/index',
                'perms'     => '',
                'icon'      => $m['icon'],
                'sort'      => $sort++,
                'visible'   => 1,
            ], $now);

            foreach ($actions as $a) {
                $this->ensureMenuButton($menuId, 'system:' . $m['key'] . ':' . $a['act'], $a['title'], $a['sort'], $now);
            }
        }
    }

    /**
     * 幂等插入菜单节点（目录/菜单），按 name 唯一识别。
     */
    protected function ensureMenu(array $data, string $now): int
    {
        $id = (int) Db::name('menu')->where(['tenant_id' => 0, 'name' => $data['name']])->whereNull('deleted_at')->value('id');
        if ($id > 0) {
            return $id;
        }

        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        return (int) Db::name('menu')->insertGetId($data);
    }

    /**
     * 幂等插入按钮权限点，按 perms 唯一识别。
     */
    protected function ensureMenuButton(int $parentId, string $perms, string $title, int $sort, string $now): void
    {
        $exists = Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find();
        if ($exists !== null) {
            return;
        }

        Db::name('menu')->insert([
            'tenant_id'  => 0,
            'parent_id'  => $parentId,
            'type'       => 3,
            'name'       => '',
            'title'      => $title,
            'path'       => '',
            'component'  => '',
            'perms'      => $perms,
            'icon'       => '',
            'sort'       => $sort,
            'status'     => 1,
            'visible'    => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 幂等插入：按 $where 查存在则返回其 id；否则插入 $data 并返回新 id。
     *
     * @param bool $softDelete 表是否含软删除字段（关联表/casbin 表无 deleted_at）
     * @return int 行 id（casbin 等无业务意义场景调用方可忽略）
     */
    protected function ensure(string $table, array $where, array $data, bool $softDelete = true): int
    {
        $query = Db::name($table)->where($where);
        if ($softDelete) {
            $query->whereNull('deleted_at');
        }
        $id = (int) $query->value('id');
        if ($id > 0) {
            return $id;
        }

        return (int) Db::name($table)->insertGetId($data);
    }
}

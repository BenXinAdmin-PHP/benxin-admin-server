<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — M1-B 权限验证样例（tester 角色 + 绑定账号，无策略）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 10:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M1-B 验证样例（幂等）：普通角色 tester（不灌任何 p 策略）+ 绑定该角色的 tester 账号。
 * 仅供权限引擎放行/拒绝对比测试；账号密码随机（验证用 BxJwt 直签令牌，不走密码登录）。
 * 如需清理：删 bx_admin/bx_role 中 tester 行及 bx_admin_role 关联、bx_casbin_rule 中 v0='tester' 行。
 */
class TesterSeeder extends Seeder
{
    public function run(): void
    {
        // 生产守门：仅 APP_DEBUG=true 开发态播种权限验证样例，避免 seed:run 全跑时污染生产
        if (!app()->isDebug()) {
            echo "[TesterSeeder] 跳过（仅开发态 APP_DEBUG=true 播种测试角色/账号）。\n";
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 普通角色 tester（无任何权限策略）
        $roleId = (int) Db::name('role')->where(['tenant_id' => 0, 'code' => 'tester'])->whereNull('deleted_at')->value('id');
        if ($roleId === 0) {
            $roleId = (int) Db::name('role')->insertGetId([
                'tenant_id'  => 0,
                'name'       => '测试角色',
                'code'       => 'tester',
                'sort'       => 99,
                'status'     => 1,
                'data_scope' => 1,
                'remark'     => 'M1-B 权限验证用，无任何策略',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // tester 账号（密码随机，仅占位；验证走直签令牌）
        $adminId = (int) Db::name('admin')->where(['tenant_id' => 0, 'username' => 'tester'])->whereNull('deleted_at')->value('id');
        if ($adminId === 0) {
            $adminId = (int) Db::name('admin')->insertGetId([
                'tenant_id'  => 0,
                'username'   => 'tester',
                'password'   => password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID),
                'nickname'   => '测试账号',
                'dept_id'    => 0,
                'status'     => 1,
                'remark'     => 'M1-B 权限验证账号',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 绑定 tester 角色（关联表无软删）
        $linked = Db::name('admin_role')->where(['admin_id' => $adminId, 'role_id' => $roleId])->count();
        if ($linked === 0) {
            Db::name('admin_role')->insert([
                'admin_id'   => $adminId,
                'role_id'    => $roleId,
                'created_at' => $now,
            ]);
        }

        echo "[TesterSeeder] tester 角色 id={$roleId}，账号 id={$adminId} 就位。\n";
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建管理员-角色关联表 bx_admin_role
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 管理员-角色关联表（多对多，无软删，仅 created_at）。表名传 'admin_role' → 物理表 bx_admin_role。
 */
class CreateBxAdminRoleTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('admin_role', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '管理员-角色关联',
            'signed'    => false,
        ]);

        $table
            ->addColumn('admin_id', 'biginteger', ['signed' => false, 'comment' => '管理员ID'])
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'comment' => '角色ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['admin_id', 'role_id'], ['unique' => true, 'name' => 'uk_admin_role'])
            ->addIndex(['role_id'], ['name' => 'idx_role_id'])
            ->create();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建角色-自定义部门关联表 bx_role_dept（data_scope=5）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 18:30:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 角色自定义数据范围（ADR-9，data_scope=5）：角色可见部门集合。
 * 关联表（无软删，仅 created_at）。表名传 'role_dept' → 物理表 bx_role_dept。
 */
class CreateBxRoleDeptTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('role_dept', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '角色-自定义部门关联（数据权限）',
            'signed'    => false,
        ]);

        $table
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'comment' => '角色ID'])
            ->addColumn('dept_id', 'biginteger', ['signed' => false, 'comment' => '部门ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['role_id', 'dept_id'], ['unique' => true, 'name' => 'uk_role_dept'])
            ->addIndex(['dept_id'], ['name' => 'idx_dept_id'])
            ->create();
    }
}

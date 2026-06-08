<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建角色-菜单关联表 bx_role_menu
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 角色-菜单关联表（多对多，无软删，仅 created_at）。表名传 'role_menu' → 物理表 bx_role_menu。
 */
class CreateBxRoleMenuTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('role_menu', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '角色-菜单关联',
            'signed'    => false,
        ]);

        $table
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'comment' => '角色ID'])
            ->addColumn('menu_id', 'biginteger', ['signed' => false, 'comment' => '菜单ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['role_id', 'menu_id'], ['unique' => true, 'name' => 'uk_role_menu'])
            ->addIndex(['menu_id'], ['name' => 'idx_menu_id'])
            ->create();
    }
}

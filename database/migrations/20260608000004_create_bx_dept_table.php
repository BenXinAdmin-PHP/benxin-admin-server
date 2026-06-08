<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建部门表 bx_dept
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 部门表（树形）。表名传 'dept' → 物理表 bx_dept。
 */
class CreateBxDeptTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('dept', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '部门',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('parent_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '父级ID，0为顶级'])
            ->addColumn('name', 'string', ['limit' => 64, 'comment' => '部门名称'])
            ->addColumn('leader', 'string', ['limit' => 64, 'default' => '', 'comment' => '负责人'])
            ->addColumn('phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '联系电话'])
            ->addColumn('email', 'string', ['limit' => 128, 'default' => '', 'comment' => '邮箱'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0禁用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['parent_id'], ['name' => 'idx_parent_id'])
            ->create();
    }
}

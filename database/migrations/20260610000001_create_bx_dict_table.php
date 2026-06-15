<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建字典类型表 bx_dict
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 字典类型表。表名传 'dict' → 物理表 bx_dict。
 */
class CreateBxDictTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('dict', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '字典类型',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('name', 'string', ['limit' => 64, 'comment' => '字典名称'])
            ->addColumn('type', 'string', ['limit' => 64, 'comment' => '字典类型标识，如 sys_normal_disable'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0停用'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['tenant_id', 'type'], ['unique' => true, 'name' => 'uk_tenant_type'])
            ->create();
    }
}

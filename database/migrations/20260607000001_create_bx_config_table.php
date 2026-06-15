<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建配置中心表 bx_config
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 配置中心表（M2 配置中心地基，统一进 bx_config：分组 + key-value）。
 * 表名传 'config'，think-migration 自动加表前缀 → 物理表 bx_config。
 */
class CreateBxConfigTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('config', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '配置中心',
            'signed'    => false, // 主键 id 使用 unsigned
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('group', 'string', ['limit' => 64, 'default' => 'default', 'comment' => '配置分组'])
            ->addColumn('key', 'string', ['limit' => 128, 'comment' => '配置键'])
            ->addColumn('value', 'text', ['null' => true, 'comment' => '配置值（敏感值 AES 加密入库）'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['tenant_id', 'group', 'key'], ['unique' => true, 'name' => 'uk_tenant_group_key'])
            ->create();
    }
}

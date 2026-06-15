<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建素材分类表 bx_resource_category
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:06:31
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 素材分类表（树形，M-素材-A）。表名传 'resource_category' → 物理表 bx_resource_category。
 * parent_id 自引用 → bx:make 自动识别树形；层级浅，子树策略走 memory。
 * 带 create_by（归属审计 + BxModel 自动填充）；公共素材库 data_scope 默认不挂。
 */
class CreateBxResourceCategoryTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('resource_category', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '素材分类',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('parent_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '父级ID，0为顶级'])
            ->addColumn('name', 'string', ['limit' => 64, 'comment' => '分类名称'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0停用'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（自动填充）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['parent_id'], ['name' => 'idx_parent_id'])
            ->create();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建字典数据项表 bx_dict_data
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 字典数据项表（按 dict_type 字符串关联 bx_dict.type）。表名传 'dict_data' → 物理表 bx_dict_data。
 */
class CreateBxDictDataTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('dict_data', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '字典数据项',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('dict_type', 'string', ['limit' => 64, 'comment' => '字典类型标识（关联 bx_dict.type）'])
            ->addColumn('label', 'string', ['limit' => 128, 'comment' => '显示文本'])
            ->addColumn('value', 'string', ['limit' => 128, 'comment' => '字典值（字符串存储）'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0停用'])
            ->addColumn('list_class', 'string', ['limit' => 32, 'default' => '', 'comment' => '标签样式类：success/danger/warning/info'])
            ->addColumn('is_default', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '是否默认项：1是 0否'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['dict_type'], ['name' => 'idx_dict_type'])
            ->addIndex(['tenant_id', 'dict_type', 'value'], ['unique' => true, 'name' => 'uk_tenant_type_value'])
            ->create();
    }
}

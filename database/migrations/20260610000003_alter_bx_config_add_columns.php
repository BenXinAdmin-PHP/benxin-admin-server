<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — bx_config 增列（name/is_sensitive/value_type/sort）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 参数配置（M2-B）：在 M0 已建 bx_config 上增列。
 * value 已为 text，可容纳 AES 密文 base64；其余结构与唯一索引 (tenant_id,group,key) 保留。
 */
class AlterBxConfigAddColumns extends Migrator
{
    public function change(): void
    {
        $this->table('config')
            ->addColumn('name', 'string', ['limit' => 64, 'default' => '', 'after' => 'tenant_id', 'comment' => '配置中文名'])
            ->addColumn('is_sensitive', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '是否敏感（1加密入库+脱敏回显）'])
            ->addColumn('value_type', 'string', ['limit' => 16, 'default' => 'string', 'comment' => '值类型：string/number/bool/json/textarea'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->update();
    }
}

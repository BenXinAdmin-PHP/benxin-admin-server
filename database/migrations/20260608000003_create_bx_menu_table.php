<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建菜单/权限表 bx_menu
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 菜单表（目录/菜单/按钮三态，按钮承载接口权限点 perms）。表名传 'menu' → 物理表 bx_menu。
 */
class CreateBxMenuTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('menu', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '菜单/权限',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('parent_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '父级ID，0为顶级'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 2, 'comment' => '类型：1目录 2菜单 3按钮'])
            ->addColumn('name', 'string', ['limit' => 64, 'default' => '', 'comment' => '路由name（前端用）'])
            ->addColumn('title', 'string', ['limit' => 64, 'comment' => '显示标题'])
            ->addColumn('path', 'string', ['limit' => 191, 'default' => '', 'comment' => '路由路径'])
            ->addColumn('component', 'string', ['limit' => 191, 'default' => '', 'comment' => '前端组件路径'])
            ->addColumn('perms', 'string', ['limit' => 128, 'default' => '', 'comment' => '权限标识，如 system:admin:list'])
            ->addColumn('icon', 'string', ['limit' => 64, 'default' => '', 'comment' => '图标'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0禁用'])
            ->addColumn('visible', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '是否显示：1显示 0隐藏'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['parent_id'], ['name' => 'idx_parent_id'])
            ->create();
    }
}

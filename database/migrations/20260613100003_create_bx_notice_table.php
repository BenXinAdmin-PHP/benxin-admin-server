<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建系统公告表 bx_notice
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 系统公告表（M4-D，D-2 走 bx:make 生成；content 声明 richtext:true 吃 M3-E 红利）。
 * 表名传 'notice' → 物理表 bx_notice。带 create_by（归属审计 + BxModel 自动填充）。
 */
class CreateBxNoticeTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('notice', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '系统公告',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '标题'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '类型：1通知 2公告'])
            ->addColumn('content', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'comment' => '正文，富文本HTML'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '状态：0草稿 1已发布 2已下架'])
            ->addColumn('is_top', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '置顶：1是 0否'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->addColumn('publish_at', 'datetime', ['null' => true, 'comment' => '发布时间'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（自动填充）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['type'], ['name' => 'idx_type'])
            ->addIndex(['create_by'], ['name' => 'idx_create_by'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

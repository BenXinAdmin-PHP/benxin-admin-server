<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建内容表 bx_content
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 内容主体表（M4-A）。表名传 'content' → 物理表 bx_content。
 * 带 create_by/create_dept（归属审计 + BxModel 自动填充；applyDataScope 暂不开启，
 * 内容默认全员可见，上层需部门隔离再按 ADR-9 开）。
 * content 为富文本正文：前端 XEditor 手工槽 + 后端 HtmlPurifier 净化（§8 XSS）。
 */
class CreateBxContentTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('content', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '内容',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('category_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '所属分类'])
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '标题'])
            ->addColumn('cover', 'string', ['limit' => 255, 'default' => '', 'comment' => '封面图'])
            ->addColumn('summary', 'string', ['limit' => 500, 'default' => '', 'comment' => '摘要'])
            ->addColumn('content', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'comment' => '正文，富文本HTML'])
            ->addColumn('author', 'string', ['limit' => 64, 'default' => '', 'comment' => '作者'])
            ->addColumn('source', 'string', ['limit' => 128, 'default' => '', 'comment' => '来源'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '状态：0草稿 1已发布 2已下架'])
            ->addColumn('is_top', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '置顶：1是 0否'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->addColumn('view_count', 'integer', ['signed' => false, 'default' => 0, 'comment' => '浏览量，服务端维护'])
            ->addColumn('publish_at', 'datetime', ['null' => true, 'comment' => '发布时间'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（自动填充）'])
            ->addColumn('create_dept', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建部门id（自动填充，数据权限预留）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['category_id'], ['name' => 'idx_category_id'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['create_by'], ['name' => 'idx_create_by'])
            ->addIndex(['create_dept'], ['name' => 'idx_create_dept'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建页面表 bx_page（通用页面搭建 schema 模型，M6-B）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 页面 schema 模型表（M6-B，ADR-21 方案甲 单表 JSON）。表名 'page' → 物理表 bx_page。
 * blocks 存整页区块有序数组（JSON），按 lang 渲染解析；slug 唯一含软删（uk_tenant_slug，
 * 软删行仍占位 slug 不可复用，§5.1 方案 A，服务层 withTrashed 前置 422）。
 * 带 create_by/create_dept（归属审计 + BxModel 自动填充；data_scope 默认不挂，公共页面）。
 */
class CreateBxPageTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('page', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '页面（通用页面搭建 schema）',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('slug', 'string', ['limit' => 64, 'comment' => '页面唯一标识（如 home），(tenant_id,slug) 唯一含软删'])
            ->addColumn('title', 'string', ['limit' => 128, 'comment' => '页面名（后台管理用，纯文本非i18n）'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1启用/已发布 0草稿'])
            ->addColumn('blocks', 'json', ['null' => true, 'comment' => '整页区块有序数组（JSON，schema 见 ADR-21）'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（自动填充）'])
            ->addColumn('create_dept', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建部门id（自动填充，数据权限预留）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['tenant_id', 'slug'], ['unique' => true, 'name' => 'uk_tenant_slug'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['create_by'], ['name' => 'idx_create_by'])
            ->addIndex(['create_dept'], ['name' => 'idx_create_dept'])
            ->create();
    }
}

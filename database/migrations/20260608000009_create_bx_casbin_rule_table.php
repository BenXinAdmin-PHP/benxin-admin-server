<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建 Casbin 规则表 bx_casbin_rule
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * Casbin 规则表（标准结构：ptype + v0..v5）。
 * 本步仅建表 + 种子（domain=tenant_id 维度落在 v1），中间件加载留 M1-B。
 * 表名传 'casbin_rule' → 物理表 bx_casbin_rule。
 */
class CreateBxCasbinRuleTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('casbin_rule', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'Casbin 权限规则',
            'signed'    => false,
        ]);

        $table
            ->addColumn('ptype', 'string', ['limit' => 8, 'default' => '', 'comment' => '策略类型：p 策略 / g 角色继承'])
            ->addColumn('v0', 'string', ['limit' => 191, 'null' => true, 'comment' => 'subject（角色code/用户）'])
            ->addColumn('v1', 'string', ['limit' => 191, 'null' => true, 'comment' => 'domain（= tenant_id，单租户为0）'])
            ->addColumn('v2', 'string', ['limit' => 191, 'null' => true, 'comment' => 'object（资源/接口/perms）'])
            ->addColumn('v3', 'string', ['limit' => 191, 'null' => true, 'comment' => 'action（动作）'])
            ->addColumn('v4', 'string', ['limit' => 191, 'null' => true, 'comment' => '预留'])
            ->addColumn('v5', 'string', ['limit' => 191, 'null' => true, 'comment' => '预留'])
            ->addIndex(['ptype'], ['name' => 'idx_ptype'])
            ->addIndex(['v0'], ['name' => 'idx_v0'])
            ->addIndex(['v1'], ['name' => 'idx_v1'])
            ->create();
    }
}

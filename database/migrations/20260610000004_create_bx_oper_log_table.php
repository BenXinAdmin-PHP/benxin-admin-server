<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建操作日志表 bx_oper_log
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 操作日志表（只增不改不软删，仅 created_at）。表名传 'oper_log' → 物理表 bx_oper_log。
 */
class CreateBxOperLogTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('oper_log', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '操作日志',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('admin_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '操作人ID，未认证为0'])
            ->addColumn('username', 'string', ['limit' => 64, 'default' => '', 'comment' => '操作人账号（冗余，免联表）'])
            ->addColumn('method', 'string', ['limit' => 10, 'default' => '', 'comment' => 'HTTP方法'])
            ->addColumn('path', 'string', ['limit' => 255, 'default' => '', 'comment' => '请求路径'])
            ->addColumn('perm', 'string', ['limit' => 128, 'default' => '', 'comment' => '路由声明的所需权限标识'])
            ->addColumn('ip', 'string', ['limit' => 64, 'default' => '', 'comment' => '客户端IP'])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'default' => '', 'comment' => 'UA（截断）'])
            ->addColumn('request_body', 'text', ['null' => true, 'comment' => '脱敏后的请求摘要'])
            ->addColumn('response_code', 'integer', ['null' => true, 'comment' => '业务返回码'])
            ->addColumn('http_status', 'integer', ['null' => true, 'comment' => 'HTTP状态码'])
            ->addColumn('duration_ms', 'integer', ['default' => 0, 'comment' => '耗时毫秒'])
            ->addColumn('request_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '请求链路ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['admin_id'], ['name' => 'idx_admin_id'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->addIndex(['path'], ['name' => 'idx_path'])
            ->create();
    }
}

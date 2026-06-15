<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建登录日志表 bx_login_log
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 登录日志表（只增不改不软删，仅 created_at；只记账号不记密码）。
 * 表名传 'login_log' → 物理表 bx_login_log。
 */
class CreateBxLoginLogTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('login_log', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '登录日志',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('username', 'string', ['limit' => 64, 'default' => '', 'comment' => '尝试登录账号（不记密码）'])
            ->addColumn('admin_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '成功时关联ID，失败为0'])
            ->addColumn('ip', 'string', ['limit' => 64, 'default' => '', 'comment' => '客户端IP'])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'default' => '', 'comment' => 'UA（截断）'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '结果：1成功 0失败'])
            ->addColumn('msg', 'string', ['limit' => 255, 'default' => '', 'comment' => '结果说明（统一文案，不泄露枚举）'])
            ->addColumn('request_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '请求链路ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['username'], ['name' => 'idx_username'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->create();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建管理员表 bx_admin
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 管理员表（后台账号）。表名传 'admin'，自动加前缀 → 物理表 bx_admin。
 */
class CreateBxAdminTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('admin', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '管理员',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('username', 'string', ['limit' => 64, 'comment' => '登录账号'])
            ->addColumn('password', 'string', ['limit' => 255, 'comment' => '密码哈希（Argon2id）'])
            ->addColumn('nickname', 'string', ['limit' => 64, 'default' => '', 'comment' => '昵称'])
            ->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '头像URL'])
            ->addColumn('mobile', 'string', ['limit' => 20, 'default' => '', 'comment' => '手机号'])
            ->addColumn('email', 'string', ['limit' => 128, 'default' => '', 'comment' => '邮箱'])
            ->addColumn('dept_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '所属部门ID'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0禁用'])
            ->addColumn('last_login_at', 'datetime', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('last_login_ip', 'string', ['limit' => 64, 'default' => '', 'comment' => '最后登录IP'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['tenant_id', 'username'], ['unique' => true, 'name' => 'uk_tenant_username'])
            ->addIndex(['dept_id'], ['name' => 'idx_dept_id'])
            ->create();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建 C 端用户主表 bx_user
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * C 端用户主表（M5-A，ADR-16）。表名传 'user' → 物理表 bx_user。
 * - mobile：登录即注册必备，(tenant_id,mobile) 唯一含软删（§5.1 软删后不可复用）。
 * - unionid：微信开放平台维度，配开放平台时打通小程序/公众号同一用户（索引）。
 * - 不挂 create_by/create_dept、不接 Casbin（C 端无 RBAC，ADR-16）。
 */
class CreateBxUserTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('user', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'C端用户',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('mobile', 'string', ['limit' => 20, 'comment' => '手机号（登录即注册必备）'])
            ->addColumn('nickname', 'string', ['limit' => 64, 'default' => '', 'comment' => '昵称'])
            ->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '头像URL'])
            ->addColumn('gender', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '性别：0未知 1男 2女'])
            ->addColumn('unionid', 'string', ['limit' => 64, 'default' => '', 'comment' => '微信开放平台unionid（打通多端）'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0停用（字典sys_normal_disable）'])
            ->addColumn('last_login_at', 'datetime', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['tenant_id', 'mobile'], ['unique' => true, 'name' => 'uk_tenant_mobile'])
            ->addIndex(['unionid'], ['name' => 'idx_unionid'])
            ->create();
    }
}

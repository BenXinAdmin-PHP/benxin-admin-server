<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建 C 端第三方账号关联表 bx_user_oauth
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * C 端第三方账号关联表（M5-A，ADR-16）。表名传 'user_oauth' → 物理表 bx_user_oauth。
 * 一个 user 对应多来源 openid（platform=mini/mp，预留 work/app）；靠 unionid 打通同一微信用户。
 * 同 platform 下 openid 唯一：唯一索引 (platform, openid)。
 * 本表不软删（关联随 user 生命周期，仅 created_at/updated_at）。
 */
class CreateBxUserOauthTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('user_oauth', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'C端第三方账号关联',
            'signed'    => false,
        ]);

        $table
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'comment' => '关联bx_user.id'])
            ->addColumn('platform', 'string', ['limit' => 16, 'comment' => '平台：mini小程序 mp公众号（预留work/app）'])
            ->addColumn('openid', 'string', ['limit' => 64, 'comment' => '该平台openid'])
            ->addColumn('unionid', 'string', ['limit' => 64, 'default' => '', 'comment' => '微信开放平台unionid'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['user_id'], ['name' => 'idx_user_id'])
            ->addIndex(['platform', 'openid'], ['unique' => true, 'name' => 'uk_platform_openid'])
            ->create();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建短信日志表 bx_sms_log
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 短信日志表（M4-D，只增不改不软删，仅 created_at，继承 think\Model，类比 oper_log）。
 * 表名传 'sms_log' → 物理表 bx_sms_log。手机号脱敏入库（§8），params 脱敏，验证码不落明文。
 */
class CreateBxSmsLogTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('sms_log', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '短信日志',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('mobile', 'string', ['limit' => 32, 'default' => '', 'comment' => '手机号（脱敏入库）'])
            ->addColumn('channel', 'string', ['limit' => 16, 'default' => '', 'comment' => '渠道：ali/tencent'])
            ->addColumn('scene', 'string', ['limit' => 32, 'default' => '', 'comment' => '场景'])
            ->addColumn('template_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '模板ID'])
            ->addColumn('params', 'string', ['limit' => 255, 'default' => '', 'comment' => '模板参数（脱敏，验证码不落明文）'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '状态：1成功 0失败'])
            ->addColumn('response', 'string', ['limit' => 500, 'default' => '', 'comment' => '渠道响应摘要'])
            ->addColumn('ip', 'string', ['limit' => 64, 'default' => '', 'comment' => '请求IP'])
            ->addColumn('request_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '请求链路ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['mobile'], ['name' => 'idx_mobile'])
            ->addIndex(['scene'], ['name' => 'idx_scene'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

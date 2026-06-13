<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建短信模板表 bx_sms_template
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 短信模板表（M4-D，D-1 建表 / D-2 走 bx:make 生成管理）。表名传 'sms_template' → 物理表 bx_sms_template。
 * scene 唯一（含软删，§5.1）：验证码服务按 scene 查模板拿 template_code/sign_name。
 */
class CreateBxSmsTemplateTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('sms_template', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '短信模板',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('scene', 'string', ['limit' => 32, 'comment' => '场景标识，如login/bind'])
            ->addColumn('channel', 'string', ['limit' => 16, 'default' => 'ali', 'comment' => '渠道：ali/tencent'])
            ->addColumn('template_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '渠道侧审核后模板ID'])
            ->addColumn('sign_name', 'string', ['limit' => 64, 'default' => '', 'comment' => '签名名（可空，覆盖默认签名）'])
            ->addColumn('content', 'string', ['limit' => 500, 'default' => '', 'comment' => '内容参考/参数说明'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0停用'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['scene'], ['name' => 'idx_scene'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

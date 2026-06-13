<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建退款记录表 bx_pay_refund
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 退款记录表（M4-C）。表名传 'pay_refund' → 物理表 bx_pay_refund。
 * 金额整型分；refund_no 唯一（含软删）；out_refund_no 普通索引（幂等查询）。
 */
class CreateBxPayRefundTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('pay_refund', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '退款记录',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('pay_order_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '关联支付订单id'])
            ->addColumn('refund_no', 'string', ['limit' => 64, 'comment' => '内部退款单号'])
            ->addColumn('out_refund_no', 'string', ['limit' => 64, 'comment' => '对外退款号（传渠道）'])
            ->addColumn('channel', 'string', ['limit' => 16, 'comment' => '渠道：wechat/alipay'])
            ->addColumn('amount', 'integer', ['signed' => false, 'default' => 0, 'comment' => '退款金额（分）'])
            ->addColumn('reason', 'string', ['limit' => 255, 'default' => '', 'comment' => '退款原因'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '状态：0退款中 1成功 2失败'])
            ->addColumn('refund_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '渠道退款号'])
            ->addColumn('refunded_at', 'datetime', ['null' => true, 'comment' => '退款成功时间'])
            ->addColumn('notify_data', 'text', ['null' => true, 'comment' => '退款回调原文（审计）'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '操作人admin_id（后台退款自动填充）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['refund_no'], ['unique' => true, 'name' => 'uniq_refund_no'])
            ->addIndex(['out_refund_no'], ['name' => 'idx_out_refund_no'])
            ->addIndex(['pay_order_id'], ['name' => 'idx_pay_order_id'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

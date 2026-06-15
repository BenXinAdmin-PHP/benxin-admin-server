<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建支付订单表 bx_pay_order
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 支付订单表（M4-C）。表名传 'pay_order' → 物理表 bx_pay_order。
 * - 金额一律整型分（amount int unsigned）。
 * - 业务解耦（开源边界 §1）：biz_type/biz_id 透传上层业务单据，底座只透传不理解。
 * - user_id 留 C 端用户字段（M5 关联，本阶段仅留字段不外键）。
 * - 唯一：order_no / out_trade_no（含软删行，§5.1 不可复用）；transaction_id 普通索引（幂等查询）。
 */
class CreateBxPayOrderTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('pay_order', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '支付订单',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('order_no', 'string', ['limit' => 64, 'comment' => '内部单号'])
            ->addColumn('out_trade_no', 'string', ['limit' => 64, 'comment' => '对外交易号（传渠道）'])
            ->addColumn('channel', 'string', ['limit' => 16, 'comment' => '渠道：wechat/alipay'])
            ->addColumn('trade_type', 'string', ['limit' => 16, 'comment' => '交易类型：jsapi/native/h5/app/wap/page'])
            ->addColumn('subject', 'string', ['limit' => 255, 'default' => '', 'comment' => '商品标题'])
            ->addColumn('amount', 'integer', ['signed' => false, 'default' => 0, 'comment' => '金额（分）'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '状态：0待支付 1已支付 2已退款 3部分退款 4已关闭 5支付失败'])
            ->addColumn('openid', 'string', ['limit' => 64, 'default' => '', 'comment' => '微信jsapi openid'])
            ->addColumn('transaction_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '渠道交易号'])
            ->addColumn('attach', 'string', ['limit' => 255, 'default' => '', 'comment' => '业务透传附加数据'])
            ->addColumn('notify_data', 'text', ['null' => true, 'comment' => '回调原文（审计）'])
            ->addColumn('refunded_amount', 'integer', ['signed' => false, 'default' => 0, 'comment' => '累计已退金额（分）'])
            ->addColumn('paid_at', 'datetime', ['null' => true, 'comment' => '支付时间'])
            ->addColumn('expire_at', 'datetime', ['null' => true, 'comment' => '过期时间'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => 'C端用户ID（M5关联，本阶段不外键）'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（后台代客下单时自动填充）'])
            ->addColumn('biz_type', 'string', ['limit' => 32, 'default' => '', 'comment' => '上层业务类型（解耦，底座只透传）'])
            ->addColumn('biz_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '上层业务单据id（解耦，底座只透传）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['order_no'], ['unique' => true, 'name' => 'uniq_order_no'])
            ->addIndex(['out_trade_no'], ['unique' => true, 'name' => 'uniq_out_trade_no'])
            ->addIndex(['transaction_id'], ['name' => 'idx_transaction_id'])
            ->addIndex(['channel'], ['name' => 'idx_channel'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['user_id'], ['name' => 'idx_user_id'])
            ->addIndex(['biz_type', 'biz_id'], ['name' => 'idx_biz'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

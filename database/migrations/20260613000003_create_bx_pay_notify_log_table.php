<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建回调审计/幂等表 bx_pay_notify_log
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 支付回调审计 + 幂等表（M4-C）。表名传 'pay_notify_log' → 物理表 bx_pay_notify_log。
 * - 只增不改不软删，仅 created_at（继承 think\Model，类比 oper_log）。
 * - 回调原文 raw_body 全程落库审计；verified 验签结果、processed 幂等标记。
 * - ★幂等键：唯一索引 (channel, event_type, idem_no)，重复回调命中即直接 ACK。
 *   idem_no = 该事件的去重标识：支付回调存 out_trade_no（一单一付）；
 *   退款回调存 out_refund_no（一单可多退，按退款单去重）。
 */
class CreateBxPayNotifyLogTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('pay_notify_log', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '支付回调审计/幂等',
            'signed'    => false,
        ]);

        $table
            ->addColumn('channel', 'string', ['limit' => 16, 'comment' => '渠道：wechat/alipay'])
            ->addColumn('event_type', 'string', ['limit' => 16, 'comment' => '事件：pay/refund'])
            ->addColumn('idem_no', 'string', ['limit' => 64, 'comment' => '幂等标识：pay取out_trade_no/refund取out_refund_no'])
            ->addColumn('out_trade_no', 'string', ['limit' => 64, 'default' => '', 'comment' => '对外交易号'])
            ->addColumn('transaction_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '渠道交易号（可空）'])
            ->addColumn('raw_body', 'text', ['null' => true, 'comment' => '回调原文（审计）'])
            ->addColumn('verified', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '验签结果：1通过 0失败'])
            ->addColumn('processed', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '幂等标记：1已处理'])
            ->addColumn('result', 'string', ['limit' => 255, 'default' => '', 'comment' => '处理结果摘要'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['channel', 'event_type', 'idem_no'], ['unique' => true, 'name' => 'uniq_idem'])
            ->addIndex(['out_trade_no'], ['name' => 'idx_out_trade_no'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

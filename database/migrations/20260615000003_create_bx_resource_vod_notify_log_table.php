<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建 VOD 转码回调审计/幂等表 bx_resource_vod_notify_log
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * VOD 转码回调审计 + 幂等表（M-素材-C，ADR-19，复刻 M4-C bx_pay_notify_log）。
 * 表名传 'resource_vod_notify_log' → 物理表 bx_resource_vod_notify_log。
 * - 只增不改不软删，仅 created_at（继承 think\Model，类比 oper_log / pay_notify_log）。
 * - 回调原文 raw_body 全程落库审计；verified 验签结果、processed 幂等标记。
 * - ★幂等键：唯一索引 (event_type, vod_media_id, idem_no)，重复回调命中即直接 ACK。
 *   idem_no = 腾讯事件去重标识（ProcedureStateChanged 取 TaskId / 缺则 EventHandle / 兜底报文摘要）。
 *   验签失败行：event_type='invalid'、vod_media_id=''、idem_no='INVALID:'+摘要（不撞键）。
 */
class CreateBxResourceVodNotifyLogTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('resource_vod_notify_log', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'VOD转码回调审计/幂等',
            'signed'    => false,
        ]);

        $table
            ->addColumn('event_type', 'string', ['limit' => 32, 'default' => '', 'comment' => '归一化事件：transcode/other/invalid'])
            ->addColumn('vod_media_id', 'string', ['limit' => 128, 'default' => '', 'comment' => '点播媒资ID（fileId，定位素材）'])
            ->addColumn('idem_no', 'string', ['limit' => 64, 'default' => '', 'comment' => '幂等标识：TaskId/EventHandle/报文摘要'])
            ->addColumn('raw_event_type', 'string', ['limit' => 64, 'default' => '', 'comment' => '腾讯原始 EventType（审计）'])
            ->addColumn('raw_body', 'text', ['null' => true, 'comment' => '回调原文（审计）'])
            ->addColumn('verified', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '验签结果：1通过 0失败'])
            ->addColumn('processed', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '幂等标记：1已处理'])
            ->addColumn('result', 'string', ['limit' => 255, 'default' => '', 'comment' => '处理结果摘要'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['event_type', 'vod_media_id', 'idem_no'], ['unique' => true, 'name' => 'uniq_idem'])
            ->addIndex(['vod_media_id'], ['name' => 'idx_vod_media_id'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 消息模块字典（公告类型 + 短信渠道，M4-D D-2，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 15:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-D D-2 字典（幂等，复刻 DictSeeder 范式）：
 *  - sys_notice_type（1 通知 / 2 公告）：系统公告 type 字段。
 *  - sys_sms_channel（ali 阿里云 / tencent 腾讯云）：短信模板 channel 字段。
 * 公告 status 复用既有 sys_content_status（0草稿/1已发布/2已下架）。
 */
class MessageDictSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->ensureDict('sys_notice_type', '公告类型', [
            ['label' => '通知', 'value' => '1', 'sort' => 1, 'list_class' => 'primary', 'is_default' => 1],
            ['label' => '公告', 'value' => '2', 'sort' => 2, 'list_class' => 'warning', 'is_default' => 0],
        ], $now);

        $this->ensureDict('sys_sms_channel', '短信渠道', [
            ['label' => '阿里云', 'value' => 'ali', 'sort' => 1, 'list_class' => 'primary', 'is_default' => 1],
            ['label' => '腾讯云', 'value' => 'tencent', 'sort' => 2, 'list_class' => 'success', 'is_default' => 0],
        ], $now);

        echo "[MessageDictSeeder] 完成。\n";
    }

    /**
     * 幂等插入字典类型 + 数据项（同 DictSeeder/ContentSeeder 范式）。
     *
     * @param array<int,array<string,mixed>> $items
     */
    protected function ensureDict(string $type, string $name, array $items, string $now): void
    {
        $exists = (int) Db::name('dict')->where(['tenant_id' => 0, 'type' => $type])->whereNull('deleted_at')->value('id');
        if ($exists === 0) {
            Db::name('dict')->insert([
                'tenant_id'  => 0,
                'name'       => $name,
                'type'       => $type,
                'status'     => 1,
                'remark'     => '消息模块内置字典',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($items as $item) {
            $has = Db::name('dict_data')
                ->where(['tenant_id' => 0, 'dict_type' => $type, 'value' => $item['value']])
                ->whereNull('deleted_at')->find();
            if ($has !== null) {
                continue;
            }
            Db::name('dict_data')->insert([
                'tenant_id'  => 0,
                'dict_type'  => $type,
                'label'      => $item['label'],
                'value'      => $item['value'],
                'sort'       => $item['sort'],
                'status'     => 1,
                'list_class' => $item['list_class'] ?? '',
                'is_default' => $item['is_default'] ?? 0,
                'remark'     => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

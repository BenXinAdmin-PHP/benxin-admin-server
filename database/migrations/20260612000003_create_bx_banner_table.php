<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建广告位表 bx_banner
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 广告位/轮播表（M4-A）。表名传 'banner' → 物理表 bx_banner。
 * start_at/end_at 生效区间（daterange 搜索检验标的）；image 走图片上传手工槽（XUpload）。
 */
class CreateBxBannerTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('banner', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '广告位',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('title', 'string', ['limit' => 128, 'comment' => '标题'])
            ->addColumn('image', 'string', ['limit' => 255, 'comment' => '图片'])
            ->addColumn('link', 'string', ['limit' => 255, 'default' => '', 'comment' => '跳转链接'])
            ->addColumn('position', 'string', ['limit' => 64, 'comment' => '位置，分组标识如 home_top'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序，越小越靠前'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '状态：1正常 0停用'])
            ->addColumn('start_at', 'datetime', ['null' => true, 'comment' => '生效开始'])
            ->addColumn('end_at', 'datetime', ['null' => true, 'comment' => '生效结束'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（自动填充）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['position'], ['name' => 'idx_position'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->create();
    }
}

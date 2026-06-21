<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — bx_page 增 seo JSON 列（页面级 SEO，C2 ADR-26）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-21 10:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 页面级 SEO（C2，ADR-26）：在 M6-B 已建 bx_page 上加 nullable JSON 列 seo，
 * 置于 blocks 之后；存 {seo_title:{zh,en}, seo_description:{zh,en}}（键级可选、i18n 形状）。
 * NULL 默认、仅 add column 不回填：存量页 seo=NULL 行为完全不变（site 侧回退 hero 派生）。
 * change() 内 addColumn 可逆——rollback 自动删列（migrate / rollback 一轮可过）。
 */
class AlterBxPageAddSeo extends Migrator
{
    public function change(): void
    {
        $this->table('page')
            ->addColumn('seo', 'json', [
                'null'    => true,
                'after'   => 'blocks',
                'comment' => '页面级SEO（JSON：{seo_title:{zh,en},seo_description:{zh,en}}，可空回退hero派生，C2 ADR-26）',
            ])
            ->update();
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — bx_sms_template.scene 普通索引改唯一（withTrashed，§5.1）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 15:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * scene 业务唯一（含软删行，不可复用，§5.1）。D-1 建表时误设普通索引，此处转唯一，
 * 使 bx:make 自动识别 uniqueField → 生成强唯一守卫（D-2 短信模板 CRUD）。
 */
class AlterBxSmsTemplateSceneUnique extends Migrator
{
    public function up(): void
    {
        $table = $this->table('sms_template');
        if ($table->hasIndexByName('idx_scene')) {
            $table->removeIndexByName('idx_scene')->update();
        }
        $table->addIndex(['scene'], ['unique' => true, 'name' => 'uniq_scene'])->update();
    }

    public function down(): void
    {
        $table = $this->table('sms_template');
        if ($table->hasIndexByName('uniq_scene')) {
            $table->removeIndexByName('uniq_scene')->update();
        }
        $table->addIndex(['scene'], ['name' => 'idx_scene'])->update();
    }
}

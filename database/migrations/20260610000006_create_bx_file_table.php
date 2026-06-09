<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建文件表 bx_file
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 文件表（M2-D，数据权限首落点：带 create_by/create_dept）。
 * 表名传 'file' → 物理表 bx_file。
 */
class CreateBxFileTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('file', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '文件',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（自动填充）'])
            ->addColumn('create_dept', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建部门id（自动填充，数据权限用）'])
            ->addColumn('original_name', 'string', ['limit' => 255, 'default' => '', 'comment' => '原始文件名（展示用）'])
            ->addColumn('file_name', 'string', ['limit' => 128, 'comment' => '存储名（uuid/hash，禁用原名）'])
            ->addColumn('path', 'string', ['limit' => 500, 'comment' => '存储相对路径 / 云 key'])
            ->addColumn('mime', 'string', ['limit' => 128, 'default' => '', 'comment' => '真实MIME（finfo检测）'])
            ->addColumn('ext', 'string', ['limit' => 16, 'default' => '', 'comment' => '扩展名（小写）'])
            ->addColumn('size', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '字节数'])
            ->addColumn('storage', 'string', ['limit' => 16, 'default' => 'local', 'comment' => '驱动：local/oss/qiniu'])
            ->addColumn('hash', 'string', ['limit' => 64, 'default' => '', 'comment' => '内容sha256（去重/秒传基础）'])
            ->addColumn('url', 'string', ['limit' => 500, 'default' => '', 'comment' => '访问URL'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['create_by'], ['name' => 'idx_create_by'])
            ->addIndex(['create_dept'], ['name' => 'idx_create_dept'])
            ->addIndex(['hash'], ['name' => 'idx_hash'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

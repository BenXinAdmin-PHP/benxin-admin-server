<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   迁移 — 创建素材表 bx_resource
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:06:31
// +----------------------------------------------------------------------

use think\migration\Migrator;

/**
 * 素材主表（M-素材-A，ADR-18 多路存储）。表名传 'resource' → 物理表 bx_resource。
 *
 * 物理字段（storage/path/url/file_name/original_name/ext/mime/size/hash）由上传服务端维护，
 * media_type 上传时按 finfo MIME + ext 自动归类（非用户手选）——四件套里全部 readonly（防批量赋值）。
 *
 * VOD 预留（本步建表即留、不写逻辑，ADR-19）：
 *   vod_media_id     点播媒资ID（云 VOD 资源，本步恒空）
 *   transcode_status 转码态：0本地无需转码 / 1上传中 / 2转码中 / 3可播放 / 4失败（本步本地资源恒 0）
 *
 * 带 create_by + create_dept（归属审计 + BxModel 自动填充）；本步 applyDataScope 不挂，
 * create_dept 列建好、调用点保留注释（ADR-9 按需开启，公共素材库默认全员可见）。
 */
class CreateBxResourceTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('resource', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '素材',
            'signed'    => false,
        ]);

        $table
            ->addColumn('tenant_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '租户ID，单租户恒为0'])
            ->addColumn('category_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '素材分类ID，0为未分类'])
            ->addColumn('name', 'string', ['limit' => 255, 'comment' => '素材名（默认取原始名，可改）'])
            ->addColumn('media_type', 'string', ['limit' => 16, 'default' => '', 'comment' => '媒体类型：image/video/audio/document/archive（按MIME+ext自动归类）'])
            ->addColumn('storage', 'string', ['limit' => 16, 'default' => 'local', 'comment' => '存储驱动：local/oss/qiniu/alivod/tencentvod'])
            ->addColumn('path', 'string', ['limit' => 500, 'default' => '', 'comment' => '存储相对路径 / 云 key'])
            ->addColumn('url', 'string', ['limit' => 500, 'default' => '', 'comment' => '访问URL（本地为受控下载路由）'])
            ->addColumn('file_name', 'string', ['limit' => 128, 'default' => '', 'comment' => '存储名（uuid，禁用原名）'])
            ->addColumn('original_name', 'string', ['limit' => 255, 'default' => '', 'comment' => '原始文件名（展示用）'])
            ->addColumn('ext', 'string', ['limit' => 16, 'default' => '', 'comment' => '扩展名（小写）'])
            ->addColumn('mime', 'string', ['limit' => 128, 'default' => '', 'comment' => '真实MIME（finfo检测）'])
            ->addColumn('size', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '字节数'])
            ->addColumn('hash', 'string', ['limit' => 64, 'default' => '', 'comment' => '内容sha256（本步仅记录，不做去重）'])
            ->addColumn('vod_media_id', 'string', ['limit' => 128, 'default' => '', 'comment' => 'VOD点播媒资ID（ADR-19预留，本步恒空）'])
            ->addColumn('transcode_status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '转码态：0无需/1上传中/2转码中/3可播放/4失败（ADR-19预留）'])
            ->addColumn('create_by', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建人admin_id（自动填充）'])
            ->addColumn('create_dept', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '创建部门id（自动填充，数据权限预留）'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间（软删除）'])
            ->addIndex(['category_id'], ['name' => 'idx_category_id'])
            ->addIndex(['media_type'], ['name' => 'idx_media_type'])
            ->addIndex(['create_by'], ['name' => 'idx_create_by'])
            ->addIndex(['create_dept'], ['name' => 'idx_create_dept'])
            ->addIndex(['created_at'], ['name' => 'idx_created_at'])
            ->create();
    }
}

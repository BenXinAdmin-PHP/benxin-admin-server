<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 素材存储白名单配置 + 上传权限点（手工槽，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:06:31
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * 素材管理手工槽种子（幂等，逐项 find-or-skip）：
 *  ① bx_config group=storage 的按 media_type 分组扩展名白名单（resource_allow_ext_{type}）——
 *     上传校验读此配置，缺失/空回退 ResourceService 内置默认（svg 等永久排除恒不可配回）。
 *  ② system:resource:upload 权限点（挂生成器产出的「素材」菜单 ResourceList 下，与生成的
 *     list/create/update/delete 互补）；批量删复用 system:resource:delete、取流复用 list。
 *
 * 依赖：先跑 ResourceMenuSeeder（建「素材管理」目录 + 素材菜单 + 四权限点）。
 */
class ResourceStorageSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ① 存储白名单配置（与 ResourceService::DEFAULT_ALLOW 同口径，可后台改）
        $configs = [
            ['key' => 'resource_allow_ext_image',    'name' => '素材白名单·图片',   'value' => 'jpg,jpeg,png,gif,webp,bmp'],
            ['key' => 'resource_allow_ext_video',    'name' => '素材白名单·视频',   'value' => 'mp4,webm,mov,mkv'],
            ['key' => 'resource_allow_ext_audio',    'name' => '素材白名单·音频',   'value' => 'mp3,wav,ogg,m4a,flac'],
            ['key' => 'resource_allow_ext_document', 'name' => '素材白名单·文档',   'value' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,md'],
            ['key' => 'resource_allow_ext_archive',  'name' => '素材白名单·压缩包', 'value' => 'zip,rar,7z,tar,gz'],
        ];
        foreach ($configs as $c) {
            $exists = Db::name('config')
                ->where(['tenant_id' => 0, 'group' => 'storage', 'key' => $c['key']])
                ->whereNull('deleted_at')
                ->find();
            if ($exists !== null) {
                continue;
            }
            Db::name('config')->insert([
                'tenant_id'    => 0,
                'name'         => $c['name'],
                'group'        => 'storage',
                'key'          => $c['key'],
                'value'        => $c['value'],
                'is_sensitive' => 0,
                'value_type'   => 'string',
                'sort'         => 0,
                'remark'       => '素材上传扩展名白名单（逗号分隔，小写）；缺失回退内置默认',
                'created_at'   => $now,
                'updated_at'   => $now,
                'deleted_at'   => null,
            ]);
        }

        // ② system:resource:upload 权限点（挂「素材」菜单下）
        $menuId = (int) Db::name('menu')
            ->where(['tenant_id' => 0, 'name' => 'ResourceList'])
            ->whereNull('deleted_at')
            ->value('id');
        if ($menuId === 0) {
            echo "[ResourceStorageSeeder] 未找到素材菜单(ResourceList)，请先跑 ResourceMenuSeeder。白名单配置已就绪。\n";
            return;
        }
        $perms = 'system:resource:upload';
        if (Db::name('menu')->where(['tenant_id' => 0, 'perms' => $perms])->whereNull('deleted_at')->find() === null) {
            Db::name('menu')->insert([
                'tenant_id' => 0, 'parent_id' => $menuId, 'type' => 3, 'name' => '', 'title' => '上传',
                'path' => '', 'component' => '', 'perms' => $perms, 'icon' => '', 'sort' => 5,
                'status' => 1, 'visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        echo "[ResourceStorageSeeder] 完成。\n";
    }
}

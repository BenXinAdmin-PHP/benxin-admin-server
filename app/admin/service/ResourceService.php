<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 素材 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// | @updated   2026-06-15 15:06:31
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\ErrorCode;
use app\common\library\storage\LocalStorage;
use app\common\library\storage\StorageManager;
use app\common\library\Uuid;
use app\common\model\Resource;
use think\facade\Db;
use think\facade\Log;
use think\file\UploadedFile;

/**
 * 素材服务。
 *
 * 【生成器产出（bx:make 纯 CRUD 复刻 post 母版）】list / detail / create / update / delete。
 * 【手工槽（M-素材-A，本步后端部分，不进防污染基线）】upload（本地全类型上传安全）/
 *   readable（受控取流）/ batchDelete（事务软删 + 物理删容错）+ media_type 自动归类 / 白名单读配置。
 *
 * ★物理字段与 media_type 由 upload 服务端直写（绕过 FILLABLE，可信来源）；
 *   用户面 save/update 仅可改白名单 {category_id, name}（readonly 字段防批量赋值，§8）。
 */
class ResourceService extends BxService
{
    protected const FILLABLE = ['category_id', 'name'];

    // ===================== 手工槽：上传安全配置（M-素材-A） =====================

    /** 上传大小上限：100MB（应用层；生产需 php.ini post_max_size/upload_max_filesize ≥ 此值才走 app 层 422） */
    public const MAX_SIZE = 104857600;

    /**
     * 按 media_type 分组的内置默认扩展名白名单（ADR-18，配置缺失时回退）。
     * svg 永久排除（可内联脚本，归入 PERMANENT_DENY）。
     *
     * @var array<string,array<int,string>>
     */
    protected const DEFAULT_ALLOW = [
        'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
        'video'    => ['mp4', 'webm', 'mov', 'mkv'],
        'audio'    => ['mp3', 'wav', 'ogg', 'm4a', 'flac'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md'],
        'archive'  => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];

    /**
     * 永久排除（黑名单恒覆盖配置白名单——即便管理员误配也拒绝；§8 程序可执行文件天然排除）。
     *
     * @var array<int,string>
     */
    protected const PERMANENT_DENY = [
        'php', 'phtml', 'php5', 'pht', 'phar', 'go', 'exe', 'dll', 'bat', 'sh', 'cmd',
        'js', 'mjs', 'html', 'htm', 'svg', 'jsp', 'asp', 'aspx', 'cgi', 'htaccess',
    ];

    /**
     * 扩展名 → 允许的真实 MIME（finfo 双重校验）。
     * 二进制媒体/压缩包容器 finfo 偶报 application/octet-stream（无法精确嗅探），
     * 故对已属受信媒体扩展名放行 octet-stream —— 伪装脚本 finfo 必报 text/*（非 octet-stream），
     * 不破坏「.php 改名 .mp4」拦截，且文件落非 Web 目录恒不可执行（§8）。
     *
     * @var array<string,array<int,string>>
     */
    protected const EXT_MIME = [
        // image
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
        // video
        'mp4'  => ['video/mp4', 'application/mp4', 'application/octet-stream'],
        'webm' => ['video/webm', 'application/octet-stream'],
        'mov'  => ['video/quicktime', 'application/octet-stream'],
        'mkv'  => ['video/x-matroska', 'application/octet-stream'],
        // audio
        'mp3'  => ['audio/mpeg', 'application/octet-stream'],
        'wav'  => ['audio/x-wav', 'audio/wav', 'audio/wave', 'application/octet-stream'],
        'ogg'  => ['audio/ogg', 'application/ogg', 'video/ogg', 'application/octet-stream'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a', 'audio/mpeg', 'application/octet-stream'],
        'flac' => ['audio/flac', 'audio/x-flac', 'application/octet-stream'],
        // document
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'md'   => ['text/plain', 'text/markdown'],
        // archive
        'zip'  => ['application/zip'],
        'rar'  => ['application/x-rar', 'application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
        '7z'   => ['application/x-7z-compressed', 'application/octet-stream'],
        'tar'  => ['application/x-tar', 'application/octet-stream'],
        'gz'   => ['application/gzip', 'application/x-gzip', 'application/octet-stream'],
    ];

    /**
     * 分页列表（keyword: name；category_id 精确；media_type 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Resource::order('created_at', 'desc')->order('id', 'desc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', "%{$keyword}%");
            });
        }
        if (($filters['category_id'] ?? '') !== '') {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (($filters['media_type'] ?? '') !== '') {
            $query->where('media_type', (string) $filters['media_type']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Resource
    {
        return Resource::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Resource
    {
        $data = $this->fillable($data);
        $data['tenant_id'] = Resource::currentTenantId();

        return Resource::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Resource
    {
        $resource = Resource::findOrFail($id);
        $data = $this->fillable($data);

        $resource->save($data);

        return $resource;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $resource = Resource::findOrFail($id);
        $resource->delete();
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }

    // ==================== 手工槽：上传 / 取流 / 批量删（M-素材-A） ====================

    /**
     * 上传（本地驱动）：复用 M2-D 上传安全 ——
     * app 层大小上限 + finfo 真实 MIME + 按 media_type 分组扩展名白名单 + MIME/ext 双重校验 +
     * uuid 重命名 + 落非 Web 目录；media_type 按 finfo MIME + ext 自动归类（非用户手选）。
     * 落库 storage=本地驱动名、transcode_status=0（本地无需转码）、VOD 字段留空（ADR-19）。
     *
     * @return array<string,mixed> { id, name, media_type, url, size, mime, ext }
     */
    public function upload(?UploadedFile $file, int $categoryId = 0): array
    {
        if ($file === null) {
            throw new BusinessException('未检测到上传文件');
        }

        // 1) 大小上限
        $size = (int) $file->getSize();
        if ($size > self::MAX_SIZE) {
            throw new BusinessException('文件超过大小上限（' . (int) (self::MAX_SIZE / 1048576) . 'MB）');
        }

        // 2) 扩展名白名单（小写）+ 自动归类 media_type（命中哪组白名单即为该类型）
        $ext       = strtolower((string) $file->getOriginalExtension());
        $mediaType = $this->classifyMediaType($ext);
        if ($mediaType === null) {
            throw new BusinessException('不支持的文件类型：' . ($ext ?: '未知'), ErrorCode::RESOURCE_UNSUPPORTED_TYPE);
        }

        // 3) finfo 真实 MIME + 与扩展名双重校验（防 .php 改名 .mp4）
        $mime       = (string) $file->getMime();
        $allowMimes = self::EXT_MIME[$ext] ?? [];
        if (!in_array($mime, $allowMimes, true)) {
            throw new BusinessException('文件内容与扩展名不匹配，已拒绝', ErrorCode::RESOURCE_UNSUPPORTED_TYPE);
        }

        // 4) 重命名（uuid，禁原名）+ 按 media_type/年月 分目录
        $hash     = (string) hash_file('sha256', $file->getRealPath());
        $fileName = str_replace('-', '', Uuid::v4()) . '.' . $ext;
        $saveName = 'resources/' . $mediaType . '/' . date('Y/m') . '/' . $fileName;

        // 5) 按 media_type 路由存储（本步全部 local），经驱动落地
        $storage     = StorageManager::forMediaType($mediaType);
        $storageName = StorageManager::driverNameForMediaType($mediaType);
        $storedPath  = $storage->put($file->getRealPath(), $saveName);

        // 6) 入库（create_by/create_dept 由 BxModel 钩子自动填充；VOD 字段留默认，本地 transcode_status=0）
        $originalName = mb_substr((string) $file->getOriginalName(), 0, 255);
        $record       = Resource::create([
            'tenant_id'        => Resource::currentTenantId(),
            'category_id'      => max(0, $categoryId),
            'name'             => $originalName,
            'media_type'       => $mediaType,
            'storage'          => $storageName,
            'path'             => $storedPath,
            'url'              => '',
            'file_name'        => $fileName,
            'original_name'    => $originalName,
            'ext'              => $ext,
            'mime'             => $mime,
            'size'             => $size,
            'hash'             => $hash,
            'transcode_status' => 0,
        ]);

        // 7) 访问 URL：本地走后端受控取流路由；云驱动走 storage->url
        $url = $storageName === 'local'
            ? '/admin/v1/resources/' . $record->id . '/raw'
            : $storage->url($storedPath);
        $record->url = $url;
        $record->save();

        return [
            'id'         => (int) $record->id,
            'name'       => $record->name,
            'media_type' => $mediaType,
            'url'        => $url,
            'size'       => $size,
            'mime'       => $mime,
            'ext'        => $ext,
        ];
    }

    /**
     * 受控取流：返回文件内容 + mime + 原名（仅本地驱动；本地音视频播放靠它）。
     *
     * @return array{content:string,mime:string,name:string}
     */
    public function readable(int $id): array
    {
        $record = Resource::findOrFail($id);
        if ((string) $record->storage !== 'local') {
            throw new BusinessException('该素材由云存储托管，请用其公网 URL 访问');
        }

        $local = new LocalStorage();
        if (!$local->exists((string) $record->path)) {
            throw new BusinessException('素材文件不存在或已被清理');
        }

        return [
            'content' => (string) file_get_contents($local->absolutePath((string) $record->path)),
            'mime'    => (string) $record->mime,
            'name'    => (string) $record->original_name,
        ];
    }

    /**
     * 批量删（ADR-18）：事务软删记录 + 同步物理删（StorageInterface::delete）。
     * ★容错：部分物理删失败仅 Log::error 不回滚记录删除（残留待后续 GC），不拖垮主流程。
     *
     * @param array<int,mixed> $ids
     * @return array{deleted:int,physical_failed:array<int,int>}
     */
    public function batchDelete(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($i) => (int) $i, $ids),
            static fn ($i) => $i > 0
        )));
        if ($ids === []) {
            throw new BusinessException('请选择要删除的素材');
        }

        $records = Resource::whereIn('id', $ids)->select();
        if (count($records) === 0) {
            throw new BusinessException('未找到可删除的素材');
        }

        // 先快照物理信息：ThinkPHP delete() 后模型内存属性被清空，故必须在软删前取 storage/path
        $physical = [];
        foreach ($records as $r) {
            $physical[] = [
                'id'      => (int) $r->id,
                'storage' => (string) $r->storage,
                'path'    => (string) $r->path,
            ];
        }

        // 事务软删记录（主流程强一致）
        Db::transaction(static function () use ($records) {
            foreach ($records as $r) {
                $r->delete();
            }
        });

        // 物理删（容错：失败仅记日志，不回滚记录删除；残留待后续 GC）
        $physicalFailed = [];
        foreach ($physical as $p) {
            try {
                if ($p['storage'] === 'local' && $p['path'] !== '') {
                    (new LocalStorage())->delete($p['path']);
                }
            } catch (\Throwable $e) {
                $physicalFailed[] = $p['id'];
                Log::error('[ResourceService] 物理删除失败 id=' . $p['id'] . '：' . $e->getMessage());
            }
        }

        return [
            'deleted'         => count($physical),
            'physical_failed' => $physicalFailed,
        ];
    }

    // ------------------------------------------------------------------

    /**
     * 按扩展名归类 media_type：命中哪组白名单即为该类型，均不命中返回 null。
     */
    protected function classifyMediaType(string $ext): ?string
    {
        if ($ext === '') {
            return null;
        }
        foreach (array_keys(self::DEFAULT_ALLOW) as $type) {
            if (in_array($ext, $this->allowedExtFor($type), true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * 某 media_type 的允许扩展名：配置优先（bx_config group=storage，key resource_allow_ext_{type}），
     * 缺失/空回退内置默认；PERMANENT_DENY 恒覆盖（即便配置误放也剔除）。
     *
     * @return array<int,string>
     */
    protected function allowedExtFor(string $mediaType): array
    {
        $raw  = trim((string) (new ConfigService(app()))->get('resource_allow_ext_' . $mediaType, ''));
        $list = self::DEFAULT_ALLOW[$mediaType] ?? [];
        if ($raw !== '') {
            $parsed = array_values(array_filter(
                array_map(static fn ($e) => strtolower(trim($e)), explode(',', $raw)),
                static fn ($e) => $e !== ''
            ));
            if ($parsed !== []) {
                $list = $parsed;
            }
        }

        return array_values(array_diff($list, self::PERMANENT_DENY));
    }
}

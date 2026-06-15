<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 素材 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// | @updated   2026-06-15 18:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\ErrorCode;
use app\common\library\storage\LocalStorage;
use app\common\library\storage\StorageManager;
use app\common\library\Uuid;
use app\common\library\vod\VodException;
use app\common\model\Resource;
use app\common\service\BxVod;
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

        // 取数适配（M-素材-B/C）：url 实时解析——local→raw 代理路由；云→实时签名 URL；
        // VOD→落库播放 URL 直接起步（不签 PlayAuth，§7）。（签名 URL 实时签发不缓存，§4/§7）
        foreach ($list as &$row) {
            $row['url'] = $this->resolveUrl((string) $row['storage'], (int) $row['id'], (string) $row['path'], (string) $row['url']);
        }
        unset($row);

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Resource
    {
        $record      = Resource::findOrFail($id);
        $record->url = $this->resolveUrl((string) $record->storage, (int) $record->id, (string) $record->path, (string) $record->url);

        return $record;
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
     * 删除（软删记录 + 同步物理删，容错；与 batchDelete 同口径，支持 local/云/VOD）。
     */
    public function delete(int $id): void
    {
        $resource = Resource::findOrFail($id);
        // 软删前快照（TP delete() 后模型属性清空）
        $snap = [
            'storage'      => (string) $resource->storage,
            'path'         => (string) $resource->path,
            'vod_media_id' => (string) $resource->vod_media_id,
        ];
        $resource->delete();

        try {
            $this->physicalDelete($snap['storage'], $snap['path'], $snap['vod_media_id']);
        } catch (\Throwable $e) {
            Log::error('[ResourceService] 物理删除失败 id=' . $id . '：' . $e->getMessage());
        }
    }

    /**
     * 按 storage 物理删除（容错由调用方包 try/catch，ADR-18 仅 Log 不回滚主流程）：
     *  - local      → LocalStorage::delete(path)
     *  - vod_tx     → DeleteMedia(vod_media_id)（VodTxStorage 适配）
     *  - oss/qiniu  → 对应驱动 delete(path)（删云端对象）
     */
    protected function physicalDelete(string $storage, string $path, string $vodMediaId): void
    {
        if ($storage === 'local') {
            if ($path !== '') {
                (new LocalStorage())->delete($path);
            }

            return;
        }
        if ($storage === 'vod_tx') {
            if ($vodMediaId !== '') {
                StorageManager::makeByName('vod_tx')->delete($vodMediaId);
            }

            return;
        }
        // oss / qiniu：按 storage 取对应驱动删云端对象
        if ($path !== '') {
            StorageManager::makeByName($storage)->delete($path);
        }
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

        // 5) 按 media_type 路由存储（image→qiniu / document·archive→oss / video·audio→local；
        //    配置不全自动回退 local，driverNameForMediaType 与 forMediaType 解析一致）。
        //    ★云 put 失败不落库（不留半条记录）；本地临时文件由框架请求末自动清理。
        $storageName = StorageManager::driverNameForMediaType($mediaType);
        // ★VOD 不走服务端中转上传（视频大，PHP 限额扛不住）：音视频配置为 VOD 时引导走直传端点（§5）。
        if (in_array($storageName, ['vod_tx', 'vod_ali'], true)) {
            throw new BusinessException('音视频已配置 VOD 点播，请使用直传：先 POST /admin/v1/resources/vod/upload-sign 取凭证直传腾讯 VOD，再 POST /admin/v1/resources/vod/confirm 回填');
        }
        $storage = StorageManager::forMediaType($mediaType);
        try {
            $storedPath = $storage->put($file->getRealPath(), $saveName);
        } catch (\Throwable $e) {
            Log::error('[ResourceService] 素材落地失败 driver=' . $storageName . '：' . $e->getMessage());
            throw new BusinessException('素材上传到存储失败，请稍后重试或检查云存储配置');
        }

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

        // 7) 回填 url 列为「稳定标识」：local→后端 raw 代理路由；云→存储 key（path），
        //    取数时按 storage 实时签名（签名 URL 会过期，故列里存的是可重签的稳定值，§4 标红）
        $record->url = $storageName === 'local'
            ? '/admin/v1/resources/' . $record->id . '/raw'
            : $storedPath;
        $record->save();

        return [
            'id'         => (int) $record->id,
            'name'       => $record->name,
            'media_type' => $mediaType,
            'storage'    => $storageName,
            // 响应即时可用 URL：云=实时签名 URL、local=raw 路由
            'url'        => $this->resolveUrl($storageName, (int) $record->id, $storedPath),
            'size'       => $size,
            'mime'       => $mime,
            'ext'        => $ext,
        ];
    }

    // ==================== 手工槽：VOD 客户端直传（M-素材-C，ADR-19） ====================

    /**
     * 签发 VOD 客户端直传上传凭证（§5）。
     * 校验：media_type 必须 video/audio 且对应 storage_driver_* 解析为 vod_tx（已开通+配置完整），
     *       否则 422（未开通，driverNameForMediaType 已含「配置不全回退 local」防线，守 §1）。
     *
     * @param array<string,mixed> $opts media_type / file_name / expire
     * @return array<string,mixed>
     */
    public function vodUploadSign(array $opts): array
    {
        $mediaType = (string) ($opts['media_type'] ?? 'video');
        if (!in_array($mediaType, ['video', 'audio'], true)) {
            throw new BusinessException('VOD 直传仅支持 video / audio');
        }
        // 必须已开通 VOD（选了 vod_tx 且配置完整才解析为 vod_tx；否则回退 local → 此处拒签）
        if (StorageManager::driverNameForMediaType($mediaType) !== 'vod_tx') {
            throw VodException::notReady('当前「' . $mediaType . '」未开通 VOD（需在后台将 storage_driver_' . $mediaType . ' 设为 vod_tx 并完善腾讯云 VOD 配置）');
        }

        return (new BxVod($this->app))->signUpload($opts);
    }

    /**
     * 直传完成回填落库（§5）：前端直传腾讯 VOD 成功后，回填 file_id(=vod_media_id) + 播放 url 等。
     * 落库 storage=vod_tx；配了 procedure→transcode_status=1（待转码，等回调迁移 2/3/4），否则 0（无需转码）。
     *
     * ★不可信前端字段校验程度（report 说明）：file_id 非空 + media_type 白名单 + 按 vod_media_id 防重复登记；
     *   最低限度落库 + 标记待回调确认（transcode_status 反映待转码态）。
     *   更强核验（调 VOD DescribeMedia 确认 fileId 真属本 sub_app）留 daxing 真实凭证扩展（同 M4-C 真实商户待验）。
     *
     * @param array<string,mixed> $data file_id / media_type / name / url / category_id / size
     * @return array<string,mixed>
     */
    public function vodConfirm(array $data): array
    {
        $fileId = trim((string) ($data['file_id'] ?? ''));
        if ($fileId === '') {
            throw new BusinessException('缺少 file_id（VOD 直传返回的媒资 ID）');
        }
        $mediaType = (string) ($data['media_type'] ?? 'video');
        if (!in_array($mediaType, ['video', 'audio'], true)) {
            throw new BusinessException('media_type 仅支持 video / audio');
        }

        // 防重复登记（幂等友好）：同 vod_media_id 已存在直接返回既有记录
        $exist = Resource::where('storage', 'vod_tx')->where('vod_media_id', $fileId)->find();
        if ($exist !== null) {
            return $this->vodConfirmResult($exist);
        }

        // 校验 VOD 已开通（不可信前端不能伪造开通态）
        if (StorageManager::driverNameForMediaType($mediaType) !== 'vod_tx') {
            throw VodException::notReady();
        }

        $procedure = trim((string) (new ConfigService(app()))->get('vod_tx_procedure', ''));
        $name      = mb_substr((string) ($data['name'] ?? $fileId), 0, 255);

        $record = Resource::create([
            'tenant_id'    => Resource::currentTenantId(),
            'category_id'  => max(0, (int) ($data['category_id'] ?? 0)),
            'name'         => $name,
            'media_type'   => $mediaType,
            'storage'      => 'vod_tx',
            'path'         => $fileId, // 以 fileId 作存储 key（删媒资 DeleteMedia 用，与 vod_media_id 同值）
            'url'          => mb_substr((string) ($data['url'] ?? ''), 0, 500),
            'file_name'    => '',
            'original_name' => $name,
            'ext'          => '',
            'mime'         => '',
            'size'         => max(0, (int) ($data['size'] ?? 0)),
            'hash'         => '',
            'vod_media_id' => $fileId,
            // 配了 procedure → 1 待转码（等回调迁移）；否则 0 无需转码（直接可播）
            'transcode_status' => $procedure !== '' ? 1 : 0,
        ]);

        return $this->vodConfirmResult($record);
    }

    /**
     * @return array<string,mixed>
     */
    protected function vodConfirmResult(Resource $record): array
    {
        return [
            'id'               => (int) $record->id,
            'name'             => $record->name,
            'media_type'       => (string) $record->media_type,
            'storage'          => 'vod_tx',
            'vod_media_id'     => (string) $record->vod_media_id,
            'url'              => (string) $record->url,
            'transcode_status' => (int) $record->transcode_status,
        ];
    }

    /**
     * 受控取流目标（M-素材-B 适配，按 storage 分流）：
     *  - local      → 返回文件内容流（inline 输出，本地音视频播放靠它）
     *  - oss/qiniu  → 返回 302 重定向目标（实时签名 URL，前端/浏览器直连云、不经后端扛流量，ADR-18）
     *
     * @return array{type:string,content?:string,mime?:string,name?:string,url?:string}
     */
    public function rawTarget(int $id): array
    {
        $record  = Resource::findOrFail($id);
        $storage = (string) $record->storage;

        if ($storage === 'local') {
            $local = new LocalStorage();
            if (!$local->exists((string) $record->path)) {
                throw new BusinessException('素材文件不存在或已被清理');
            }

            return [
                'type'    => 'local',
                'content' => (string) file_get_contents($local->absolutePath((string) $record->path)),
                'mime'    => (string) $record->mime,
                'name'    => (string) $record->original_name,
            ];
        }

        // VOD：后端不代理大流量，302 到落库播放 URL（v1 不签 PlayAuth）；缺地址多为转码未完成
        if ($storage === 'vod_tx') {
            $playUrl = (string) $record->url;
            if ($playUrl === '') {
                throw new BusinessException('VOD 播放地址缺失（可能转码尚未完成）');
            }

            return ['type' => 'redirect', 'url' => $playUrl];
        }

        // 云：302 到实时签名 URL（签发失败直接报错，不回退避免重定向环）
        $url = $this->signCloudUrl($storage, (string) $record->path);
        if ($url === '') {
            throw new BusinessException('素材访问地址签发失败，请检查云存储配置');
        }

        return ['type' => 'redirect', 'url' => $url];
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

        // 先快照物理信息：ThinkPHP delete() 后模型内存属性被清空，故必须在软删前取 storage/path/vod_media_id
        $physical = [];
        foreach ($records as $r) {
            $physical[] = [
                'id'           => (int) $r->id,
                'storage'      => (string) $r->storage,
                'path'         => (string) $r->path,
                'vod_media_id' => (string) $r->vod_media_id,
            ];
        }

        // 事务软删记录（主流程强一致）
        Db::transaction(static function () use ($records) {
            foreach ($records as $r) {
                $r->delete();
            }
        });

        // 物理删（容错：失败仅记日志，不回滚记录删除；残留待后续 GC）；
        // 混合 local/云(oss/qiniu)/VOD 按各自 storage 分别走对应 delete（§8）。
        $physicalFailed = [];
        foreach ($physical as $p) {
            try {
                $this->physicalDelete($p['storage'], $p['path'], $p['vod_media_id']);
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

    /**
     * 解析素材访问 URL（取数路径用，M-素材-B/C）：
     *  - vod_tx     → 落库播放 URL 直接起步（v1 不签 PlayAuth；★PlayAuth 防盗链扩展位见 BxVod，ADR-19 本步不实现）
     *  - local      → 后端 raw 代理路由
     *  - oss/qiniu  → 实时签名 URL；签名失败回退 raw 路由（raw 再做 302 托底，不让单条坏数据拖垮列表）
     */
    protected function resolveUrl(string $storage, int $id, string $path, string $storedUrl = ''): string
    {
        if ($storage === 'vod_tx') {
            return $storedUrl; // ★PlayAuth 扩展位：上层接 VOD 高级防盗链时在此/BxVod 据此签发，v1 透传播放 URL
        }
        if ($storage === 'oss' || $storage === 'qiniu') {
            $signed = $this->signCloudUrl($storage, $path);
            if ($signed !== '') {
                return $signed;
            }
        }

        return '/admin/v1/resources/' . $id . '/raw';
    }

    /**
     * 实时签发云存储签名 URL（私有 bucket/空间，带有效期）；异常返回空串交调用方决定回退/报错。
     */
    protected function signCloudUrl(string $driverName, string $path): string
    {
        try {
            return StorageManager::makeByName($driverName)->url($path);
        } catch (\Throwable $e) {
            Log::warning('[ResourceService] 云签名 URL 失败 driver=' . $driverName . '：' . $e->getMessage());

            return '';
        }
    }
}

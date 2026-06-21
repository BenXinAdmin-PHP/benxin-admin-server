<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 页面 schema 模型（校验 + CRUD + 按 lang 渲染，M6-B）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// | @updated   2026-06-17 19:56:30（B1-① 新增 listPublished 已发布页清单，供 Nuxt SSG 枚举 + sitemap）
// | @updated   2026-06-20 11:00:00（B-增强-② slug 保留词强校验 en/preview → 160001）
// | @updated   2026-06-21 10:00:00（C2 ADR-26 页面级 seo：validateSeo 校验 + renderBySlug 按 lang 解析）
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\ErrorCode;
use app\common\model\Page;
use think\facade\Db;

/**
 * 页面服务（ADR-21）：整页 JSON blocks 的 schema 校验 + 后台 CRUD + api 按 lang 渲染。
 *
 * 设计：区块 schema 用一张 BLOCK_SCHEMA 元数据表驱动「校验」与「渲染解析」两件事（DRY）。
 * 字段 kind：
 *   - i18n          ：{zh,en} 对象，zh 必填非空、en 可空；渲染解析为字符串（取 lang，空回退 zh）。
 *   - text          ：纯标量字符串（href/icon/image/variant/quickstart/label 等），原样透传。
 *   - object        ：嵌套对象，按 fields 子 schema 递归。
 *   - arrayOfI18n   ：数组，每元素为 i18n 对象（如 security.chips）。
 *   - arrayOfObject ：数组，每元素按 item 子 schema 递归（如 feature-grid.items / cta.buttons）。
 *
 * 未知字段宽容忽略（§4.5，便于 M6-C 扩展）；type/必填/ i18n 形状强校验，失败 422 指明 block 路径。
 * 富文本：M6-B 区块文本皆纯文本；未来若引入富文本块（如 prose 允许 HTML），写入须复刻
 * ADR-14 HtmlPurifier::clean 白名单净化——此处留扩展位，不实现半成品。
 */
class PageService extends BxService
{
    /** 后台可写字段白名单（防批量赋值，§8） */
    protected const FILLABLE = ['slug', 'title', 'status', 'blocks', 'seo'];

    /** seo 顶层允许键（与 BLOCK_SCHEMA 同理，多余键宽容忽略；扩展位：未来 og_image 等在此加） */
    protected const SEO_KEYS = ['seo_title', 'seo_description'];

    /** 渲染接口语言白名单（防注入；非白名单归一为 zh） */
    protected const LANGS = ['zh', 'en'];

    /**
     * slug 保留词黑名单：与 site(Nuxt) 静态路由冲突，写入强校验拦截（B-增强-②，160001）。
     * 'en'      —— i18n 英文 locale 前缀（/en 会撞英文首页静态路由）。
     * 'preview' —— 草稿预览路由（/preview，B2-② 跨源握手落点）。
     * 注：'home' 为 seeder 规范首页，靠 uk_tenant_slug 唯一约束保留，不入此黑名单（避免误伤首页编辑）。
     *     未来 site 新增静态路由（如 /api 落地页）在此追加；下划线类无需纳入（slug 正则 [a-z0-9-]+ 已排除 _）。
     *
     * @var array<int,string>
     */
    protected const RESERVED_SLUGS = ['en', 'preview'];

    /**
     * 区块 type → 字段 schema。type 白名单即本表键集合（hero/prose/feature-grid/
     * moat/security/badge-list/showcase/cta，对齐 M6-A 首页区块、ADR-21）。
     *
     * @var array<string,array<string,array<string,mixed>>>
     */
    protected const BLOCK_SCHEMA = [
        'hero' => [
            'eyebrow'      => ['kind' => 'i18n', 'required' => true],
            'title'        => ['kind' => 'i18n', 'required' => true],
            'subtitle'     => ['kind' => 'i18n', 'required' => true],
            'ctaPrimary'   => ['kind' => 'object', 'required' => true, 'fields' => [
                'text' => ['kind' => 'i18n', 'required' => true],
                'href' => ['kind' => 'text', 'required' => false],
            ]],
            'ctaSecondary' => ['kind' => 'object', 'required' => true, 'fields' => [
                'text' => ['kind' => 'i18n', 'required' => true],
                'href' => ['kind' => 'text', 'required' => false],
            ]],
        ],
        'prose' => [
            'title' => ['kind' => 'i18n', 'required' => true],
            'body'  => ['kind' => 'i18n', 'required' => true],
        ],
        'feature-grid' => [
            'title' => ['kind' => 'i18n', 'required' => true],
            'items' => ['kind' => 'arrayOfObject', 'required' => true, 'item' => [
                'title' => ['kind' => 'i18n', 'required' => true],
                'desc'  => ['kind' => 'i18n', 'required' => true],
                'icon'  => ['kind' => 'text', 'required' => false],
            ]],
        ],
        'moat' => [
            'title'         => ['kind' => 'i18n', 'required' => true],
            'body'          => ['kind' => 'i18n', 'required' => true],
            'verifyCaption' => ['kind' => 'i18n', 'required' => false],
        ],
        'security' => [
            'title' => ['kind' => 'i18n', 'required' => true],
            'body'  => ['kind' => 'i18n', 'required' => true],
            'chips' => ['kind' => 'arrayOfI18n', 'required' => true],
        ],
        'badge-list' => [
            'title'   => ['kind' => 'i18n', 'required' => true],
            'caption' => ['kind' => 'i18n', 'required' => false],
            'items'   => ['kind' => 'arrayOfObject', 'required' => true, 'item' => [
                'label' => ['kind' => 'text', 'required' => true],
            ]],
        ],
        'showcase' => [
            'title' => ['kind' => 'i18n', 'required' => true],
            'items' => ['kind' => 'arrayOfObject', 'required' => true, 'item' => [
                'caption' => ['kind' => 'i18n', 'required' => true],
                'image'   => ['kind' => 'text', 'required' => false],
            ]],
        ],
        'cta' => [
            'title'      => ['kind' => 'i18n', 'required' => true],
            'body'       => ['kind' => 'i18n', 'required' => true],
            'buttons'    => ['kind' => 'arrayOfObject', 'required' => true, 'item' => [
                'text'    => ['kind' => 'i18n', 'required' => true],
                'href'    => ['kind' => 'text', 'required' => false],
                'variant' => ['kind' => 'text', 'required' => false],
            ]],
            'quickstart' => ['kind' => 'text', 'required' => false],
        ],
    ];

    // ===================== schema 校验 =====================

    /**
     * 校验整页 blocks（写入 create/update 时强制）。失败抛 BusinessException 422，msg 指明路径。
     *
     * @param mixed $blocks
     */
    public function validateBlocks(mixed $blocks): void
    {
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new BusinessException('blocks 必须为区块数组');
        }

        foreach ($blocks as $i => $block) {
            $path = "blocks[{$i}]";
            if (!is_array($block)) {
                throw new BusinessException("{$path} 必须为对象");
            }
            $type = $block['type'] ?? null;
            if (!is_string($type) || !isset(self::BLOCK_SCHEMA[$type])) {
                throw new BusinessException("{$path}.type 非法或不在白名单：" . self::typeList());
            }
            $this->validateFields($block, self::BLOCK_SCHEMA[$type], "{$path}({$type})");
        }
    }

    /**
     * 校验页面级 SEO（C2，ADR-26）。写入 create/update(seo 分支) 前调用。
     * 口径：null/缺省 → 跳过（合法，存量/回退场景）；提供则须为对象（非列表），
     * 仅校验白名单键 seo_title/seo_description（键级可选，多余键宽容忽略——与 validateBlocks
     * 未知字段口径一致），每个存在的键复用 assertI18n（zh 非空字符串、en 可空），失败 422 指明路径。
     *
     * @param mixed $seo
     */
    public function validateSeo(mixed $seo): void
    {
        if ($seo === null) {
            return; // 不填即合法
        }
        // {} 解出为空数组([])，视同未填；非空列表(纯数字键) 则非法
        if (!is_array($seo) || (array_is_list($seo) && count($seo) > 0)) {
            throw new BusinessException('seo 必须为 {seo_title,seo_description} 对象');
        }
        foreach (self::SEO_KEYS as $key) {
            if (!array_key_exists($key, $seo) || $seo[$key] === null) {
                continue; // 该键缺省 → 跳过（键级可选）
            }
            $this->assertI18n($seo[$key], "seo.{$key}");
        }
    }

    /**
     * 按字段 schema 校验一个对象。
     *
     * @param array<string,mixed>                 $data
     * @param array<string,array<string,mixed>>   $schema
     */
    protected function validateFields(array $data, array $schema, string $path): void
    {
        foreach ($schema as $field => $def) {
            $present = array_key_exists($field, $data) && $data[$field] !== null;
            $value   = $data[$field] ?? null;
            $fpath   = "{$path}.{$field}";
            $required = (bool) ($def['required'] ?? false);

            if (!$present) {
                if ($required) {
                    throw new BusinessException("{$fpath} 为必填");
                }
                continue;
            }

            switch ($def['kind']) {
                case 'i18n':
                    $this->assertI18n($value, $fpath);
                    break;
                case 'text':
                    if (!is_scalar($value)) {
                        throw new BusinessException("{$fpath} 必须为字符串");
                    }
                    if ($required && trim((string) $value) === '') {
                        throw new BusinessException("{$fpath} 不能为空");
                    }
                    break;
                case 'object':
                    if (!is_array($value)) {
                        throw new BusinessException("{$fpath} 必须为对象");
                    }
                    $this->validateFields($value, $def['fields'], $fpath);
                    break;
                case 'arrayOfI18n':
                    $this->assertNonEmptyList($value, $fpath, $required);
                    foreach ($value as $k => $el) {
                        $this->assertI18n($el, "{$fpath}[{$k}]");
                    }
                    break;
                case 'arrayOfObject':
                    $this->assertNonEmptyList($value, $fpath, $required);
                    foreach ($value as $k => $el) {
                        if (!is_array($el)) {
                            throw new BusinessException("{$fpath}[{$k}] 必须为对象");
                        }
                        $this->validateFields($el, $def['item'], "{$fpath}[{$k}]");
                    }
                    break;
            }
        }
    }

    /**
     * i18n 字段形状：必须是含 zh 键的对象，zh 非空字符串；en 可空。
     *
     * @param mixed $value
     */
    protected function assertI18n(mixed $value, string $path): void
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new BusinessException("{$path} 必须为 {zh,en} 对象");
        }
        if (!array_key_exists('zh', $value) || !is_scalar($value['zh']) || trim((string) $value['zh']) === '') {
            throw new BusinessException("{$path}.zh 文案不能为空");
        }
        if (array_key_exists('en', $value) && $value['en'] !== null && !is_scalar($value['en'])) {
            throw new BusinessException("{$path}.en 必须为字符串");
        }
    }

    /**
     * 数组型字段校验：必须为列表数组；required 时不可为空数组。
     *
     * @param mixed $value
     */
    protected function assertNonEmptyList(mixed $value, string $path, bool $required): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new BusinessException("{$path} 必须为数组");
        }
        if ($required && count($value) === 0) {
            throw new BusinessException("{$path} 不能为空数组");
        }
    }

    /** 白名单 type 列表（提示用） */
    protected static function typeList(): string
    {
        return implode('/', array_keys(self::BLOCK_SCHEMA));
    }

    // ===================== 后台 CRUD =====================

    /**
     * 分页列表（keyword: title/slug；status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Page::order('id', 'desc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', "%{$keyword}%")->whereOr('slug', 'like', "%{$keyword}%");
            });
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Page
    {
        return Page::findOrFail($id);
    }

    /**
     * 新建（slug 唯一含软删 + blocks schema 校验，事务包裹）。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): Page
    {
        $data = $this->fillable($data);
        $slug = (string) ($data['slug'] ?? '');
        $this->assertSlugNotReserved($slug);
        $this->assertSlugUnique($slug, null);
        $this->validateBlocks($data['blocks'] ?? []);
        $this->validateSeo($data['seo'] ?? null);
        $data['tenant_id'] = Page::currentTenantId();

        return Db::transaction(fn () => Page::create($data));
    }

    /**
     * 更新（按字段选择性更新；slug/blocks 参与时各自校验，事务包裹）。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Page
    {
        $page = Page::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('slug', $data)) {
            $slug = (string) $data['slug'];
            $this->assertSlugNotReserved($slug);
            $this->assertSlugUnique($slug, $id);
        }
        if (array_key_exists('blocks', $data)) {
            $this->validateBlocks($data['blocks']);
        }
        if (array_key_exists('seo', $data)) {
            $this->validateSeo($data['seo']);
        }

        return Db::transaction(function () use ($page, $data) {
            $page->save($data);
            return $page;
        });
    }

    public function delete(int $id): void
    {
        $page = Page::findOrFail($id);
        $page->delete();
    }

    // ===================== api 渲染 =====================

    /**
     * 已发布页清单（api 公开只读，B1-①）。
     * 供 Nuxt 官网 SSG build 枚举 /[slug] 预渲染路由 + 动态生成 sitemap 双语条目。
     * 字段白名单仅 slug + updated_at（查询层 field() 收紧，不靠后置裁剪）；
     * 仅 status=已发布，软删 + 租户作用域由 BxModel 全局作用域自动排除；
     * 排序 updated_at 倒序（sitemap lastmod 友好），同值再按 slug 升序兜底确定性。
     * 语言无关（清单只含 slug），lang 由消费方拉单页 renderBySlug 时决定。
     *
     * @return array<int,array{slug:string,updated_at:string}>
     */
    public function listPublished(): array
    {
        return Page::where('status', Page::STATUS_PUBLISHED)
            ->field(['slug', 'updated_at'])
            ->order('updated_at', 'desc')
            ->order('slug', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 按 slug + lang 渲染整页（api 公开只读）。
     * 取 status=已发布 的页；未命中 → 404。i18n 字段按 lang 解析为字符串（空回退 zh），
     * 非 i18n 字段原样透传；字段白名单仅返 {slug,title,blocks,seo}，不外露内部字段。
     * seo（C2 ADR-26）：按 lang 解析为字符串（空回退 zh，与 blocks 同口径）；页无 seo → seo:null
     * （site 侧据此走 hero 派生回退）。白名单严格：仅返解析后字符串，不外露 i18n 原始对象。
     *
     * @return array{slug:string,title:string,blocks:array<int,mixed>,seo:array{seo_title:string,seo_description:string}|null}
     */
    public function renderBySlug(string $slug, string $lang): array
    {
        $lang = in_array($lang, self::LANGS, true) ? $lang : 'zh';

        $page = Page::where('slug', $slug)->where('status', Page::STATUS_PUBLISHED)->find();
        if ($page === null) {
            throw new BusinessException('页面不存在', ErrorCode::NOT_FOUND);
        }

        $blocks = is_array($page->blocks) ? $page->blocks : [];
        $resolved = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = $block['type'] ?? '';
            $schema = self::BLOCK_SCHEMA[$type] ?? null;
            if ($schema === null) {
                continue; // 非白名单块跳过（防御）
            }
            $resolved[] = ['type' => $type] + $this->resolveFields($block, $schema, $lang);
        }

        // seo 解析：有 seo 对象 → 按 lang 解析两字段为字符串；无（NULL/空对象）→ null 供 site 回退 hero
        $seo = null;
        if (is_array($page->seo) && $page->seo !== []) {
            $seo = [
                'seo_title'       => $this->pickLang($page->seo['seo_title'] ?? null, $lang),
                'seo_description' => $this->pickLang($page->seo['seo_description'] ?? null, $lang),
            ];
        }

        return ['slug' => $page->slug, 'title' => $page->title, 'blocks' => $resolved, 'seo' => $seo];
    }

    /**
     * 按 schema 解析一个对象的字段（i18n → 字符串，其余原样）。
     *
     * @param array<string,mixed>               $data
     * @param array<string,array<string,mixed>> $schema
     * @return array<string,mixed>
     */
    protected function resolveFields(array $data, array $schema, string $lang): array
    {
        $out = [];
        foreach ($schema as $field => $def) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }
            $value = $data[$field];
            switch ($def['kind']) {
                case 'i18n':
                    $out[$field] = $this->pickLang($value, $lang);
                    break;
                case 'text':
                    $out[$field] = is_scalar($value) ? (string) $value : '';
                    break;
                case 'object':
                    $out[$field] = is_array($value) ? $this->resolveFields($value, $def['fields'], $lang) : [];
                    break;
                case 'arrayOfI18n':
                    $out[$field] = array_map(fn ($el) => $this->pickLang($el, $lang), is_array($value) ? $value : []);
                    break;
                case 'arrayOfObject':
                    $out[$field] = array_map(
                        fn ($el) => is_array($el) ? $this->resolveFields($el, $def['item'], $lang) : [],
                        is_array($value) ? $value : [],
                    );
                    break;
            }
        }

        return $out;
    }

    /**
     * 取 i18n 字段指定语言文案，空则回退 zh。
     *
     * @param mixed $value
     */
    protected function pickLang(mixed $value, string $lang): string
    {
        if (!is_array($value)) {
            return '';
        }
        $val = $value[$lang] ?? '';
        if (!is_scalar($val) || trim((string) $val) === '') {
            $val = $value['zh'] ?? '';
        }

        return is_scalar($val) ? (string) $val : '';
    }

    // ===================== 内部 =====================

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }

    /**
     * slug 保留词拦截（B-增强-②）：命中与 site 静态路由冲突的保留词 → 160001 拒绝。
     * 调用点紧贴格式校验（PageValidate 正则 [a-z0-9-]+ 已在控制器层先行）之后、
     * 唯一性查询（assertSlugUnique）之前：值层先行省一次 DB 命中；
     * slug 此时已是小写规范串，in_array 严格比对即可命中保留词。
     */
    protected function assertSlugNotReserved(string $slug): void
    {
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new BusinessException(
                '该 slug 为系统保留字（与官网路由冲突），请更换：' . $slug,
                ErrorCode::PAGE_RESERVED_SLUG,
            );
        }
    }

    /**
     * slug 唯一（含 withTrashed，软删 slug 不可复用，§5.1 方案 A）。
     */
    protected function assertSlugUnique(string $slug, ?int $exceptId): void
    {
        if ($slug === '') {
            throw new BusinessException('slug 不能为空');
        }
        $query = Page::withTrashed()->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('页面标识已存在：' . $slug);
        }
    }
}

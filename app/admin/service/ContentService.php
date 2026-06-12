<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 内容 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// | @updated   2026-06-12 14:40:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\HtmlPurifier;
use app\common\model\Content;

/**
 * 内容服务：标准 CRUD（生成器复刻 post 母版）。
 *
 * 生成后手工接线（M4-A 吃狗粮缺口，回炉候选详见完成报告）：
 *  - 富文本净化：create/update 落库前对 content 字段 HtmlPurifier::clean
 *    （§8 XSS 二次防护，与前端 XEditor 配合；回炉候选 richtext: true 自动注入）。
 *  - view_count 只读：服务端维护，已从 FILLABLE 剔除防越权改值（回炉候选 readonly: true）。
 *  - 置顶排序：is_top desc 优先（回炉候选 listOrder 可声明）。
 */
class ContentService extends BxService
{
    protected const FILLABLE = ['category_id', 'title', 'cover', 'summary', 'content', 'author', 'source', 'status', 'is_top', 'sort', 'publish_at'];

    /**
     * 分页列表（keyword: title；category_id 精确；status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Content::order('is_top', 'desc')->order('sort', 'asc')->order('id', 'asc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', "%{$keyword}%");
            });
        }
        if (($filters['category_id'] ?? '') !== '') {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Content
    {
        return Content::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Content
    {
        $data = $this->fillable($data);
        $data = $this->purifyContent($data);
        $data['tenant_id'] = Content::currentTenantId();

        return Content::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Content
    {
        $content = Content::findOrFail($id);
        $data = $this->fillable($data);
        $data = $this->purifyContent($data);

        $content->save($data);

        return $content;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $content = Content::findOrFail($id);
        $content->delete();
    }

    public function setStatus(int $id, int $status): Content
    {
        $content         = Content::findOrFail($id);
        $content->status = $status;
        $content->save();

        return $content;
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

    /**
     * 富文本字段落库前净化（白名单剥离 script、on* 事件、javascript: 协议等，§8 XSS）。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function purifyContent(array $data): array
    {
        if (array_key_exists('content', $data)) {
            $data['content'] = HtmlPurifier::clean((string) $data['content']);
        }

        return $data;
    }
}

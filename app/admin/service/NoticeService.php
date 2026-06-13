<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 系统公告 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 20:00:39
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\HtmlPurifier;
use app\common\model\Notice;

/**
 * 系统公告服务：标准 CRUD（生成器复刻 post 母版）。
 */
class NoticeService extends BxService
{
    protected const FILLABLE = ['title', 'type', 'content', 'status', 'is_top', 'sort', 'publish_at'];

    /**
     * 分页列表（keyword: title；type 精确；status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Notice::order('is_top', 'desc')->order('sort', 'asc')->order('id', 'desc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', "%{$keyword}%");
            });
        }
        if (($filters['type'] ?? '') !== '') {
            $query->where('type', (int) $filters['type']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Notice
    {
        return Notice::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Notice
    {
        $data = $this->fillable($data);
        $data = $this->purifyContent($data);
        $data['tenant_id'] = Notice::currentTenantId();

        return Notice::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Notice
    {
        $notice = Notice::findOrFail($id);
        $data = $this->fillable($data);
        $data = $this->purifyContent($data);

        $notice->save($data);

        return $notice;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $notice = Notice::findOrFail($id);
        $notice->delete();
    }

    public function setStatus(int $id, int $status): Notice
    {
        $notice         = Notice::findOrFail($id);
        $notice->status = $status;
        $notice->save();

        return $notice;
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

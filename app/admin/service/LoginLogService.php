<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 登录日志查询/清理（只读 + 物理清理）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\LoginLog;

/**
 * 登录日志服务。仅列表/详情/批量物理清理；清理需时间范围或 all=1。
 */
class LoginLogService extends BxService
{
    /**
     * 分页列表（username/status + 时间范围）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = LoginLog::order('id', 'desc');

        if (($filters['username'] ?? '') !== '') {
            $query->whereLike('username', '%' . trim((string) $filters['username']) . '%');
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }
        $this->applyTimeRange($query, $filters);

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): LoginLog
    {
        return LoginLog::findOrFail($id);
    }

    /**
     * 批量清理：all=1 清全部；否则按时间范围（至少一个）。
     *
     * @param array<string,mixed> $params
     * @return int 删除行数
     */
    public function clear(array $params): int
    {
        $all   = (string) ($params['all'] ?? '') === '1';
        $start = trim((string) ($params['start_time'] ?? ''));
        $end   = trim((string) ($params['end_time'] ?? ''));

        if (!$all && $start === '' && $end === '') {
            throw new BusinessException('清理日志需指定时间范围（start_time/end_time）或 all=1');
        }

        $query = LoginLog::where('id', '>', 0);
        if (!$all) {
            $this->applyTimeRange($query, ['start_time' => $start, 'end_time' => $end]);
        }

        return $query->delete();
    }

    /**
     * @param mixed               $query
     * @param array<string,mixed> $filters
     */
    protected function applyTimeRange($query, array $filters): void
    {
        if (($filters['start_time'] ?? '') !== '') {
            $query->where('created_at', '>=', (string) $filters['start_time']);
        }
        if (($filters['end_time'] ?? '') !== '') {
            $query->where('created_at', '<=', (string) $filters['end_time']);
        }
    }
}

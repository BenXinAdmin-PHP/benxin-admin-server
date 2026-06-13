<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 短信日志查询（只读，复刻 oper_log）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\model\SmsLog;

/**
 * 短信日志服务（只读，复刻 OperLogService 范式）。手机号已脱敏入库。
 */
class SmsLogService extends BxService
{
    /**
     * 分页列表（mobile/scene/channel/status + 时间范围）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = SmsLog::order('id', 'desc');

        if (($filters['mobile'] ?? '') !== '') {
            // 入库已脱敏，按脱敏片段模糊匹配
            $query->whereLike('mobile', '%' . trim((string) $filters['mobile']) . '%');
        }
        if (($filters['scene'] ?? '') !== '') {
            $query->where('scene', (string) $filters['scene']);
        }
        if (($filters['channel'] ?? '') !== '') {
            $query->where('channel', (string) $filters['channel']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (($filters['start_time'] ?? '') !== '') {
            $query->where('created_at', '>=', (string) $filters['start_time']);
        }
        if (($filters['end_time'] ?? '') !== '') {
            $query->where('created_at', '<=', (string) $filters['end_time']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): SmsLog
    {
        return SmsLog::findOrFail($id);
    }
}

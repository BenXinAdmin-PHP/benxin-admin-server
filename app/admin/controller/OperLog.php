<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   操作日志 — GET /admin/v1/oper-logs[/:id], DELETE /admin/v1/oper-logs
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\OperLogService;
use app\common\base\BxController;
use think\Response;

/**
 * 操作日志（只读 + 批量清理；日志由中间件自动产生）。
 */
class OperLog extends BxController
{
    /**
     * 列表（分页 + admin_id/username/method/path/时间范围）。
     * GET /admin/v1/oper-logs
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new OperLogService($this->app))->list([
            'admin_id'   => $this->request->param('admin_id', ''),
            'username'   => $this->request->param('username', ''),
            'method'     => $this->request->param('method', ''),
            'path'       => $this->request->param('path', ''),
            'start_time' => $this->request->param('start_time', ''),
            'end_time'   => $this->request->param('end_time', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/oper-logs/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new OperLogService($this->app))->detail($id));
    }

    /**
     * 批量清理（需时间范围或 all=1，防误清全表）。
     * DELETE /admin/v1/oper-logs
     */
    public function clear(): Response
    {
        $count = (new OperLogService($this->app))->clear([
            'all'        => $this->request->param('all', ''),
            'start_time' => $this->request->param('start_time', ''),
            'end_time'   => $this->request->param('end_time', ''),
        ]);

        return $this->success(['deleted' => $count], '清理完成');
    }
}

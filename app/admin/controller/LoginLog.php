<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   登录日志 — GET /admin/v1/login-logs[/:id], DELETE /admin/v1/login-logs
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\LoginLogService;
use app\common\base\BxController;
use think\Response;

/**
 * 登录日志（只读 + 批量清理；由登录流自动产生）。
 */
class LoginLog extends BxController
{
    /**
     * 列表（分页 + username/status/时间范围）。
     * GET /admin/v1/login-logs
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new LoginLogService($this->app))->list([
            'username'   => $this->request->param('username', ''),
            'status'     => $this->request->param('status', ''),
            'start_time' => $this->request->param('start_time', ''),
            'end_time'   => $this->request->param('end_time', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/login-logs/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new LoginLogService($this->app))->detail($id));
    }

    /**
     * 批量清理（需时间范围或 all=1）。
     * DELETE /admin/v1/login-logs
     */
    public function clear(): Response
    {
        $count = (new LoginLogService($this->app))->clear([
            'all'        => $this->request->param('all', ''),
            'start_time' => $this->request->param('start_time', ''),
            'end_time'   => $this->request->param('end_time', ''),
        ]);

        return $this->success(['deleted' => $count], '清理完成');
    }
}

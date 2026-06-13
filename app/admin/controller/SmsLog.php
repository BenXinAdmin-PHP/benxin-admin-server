<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信日志 — GET /admin/v1/sms-logs[/:id]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\SmsLogService;
use app\common\base\BxController;
use think\Response;

/**
 * 短信日志（只读，复刻 OperLog 控制器范式）。
 */
class SmsLog extends BxController
{
    /**
     * 列表。GET /admin/v1/sms-logs
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new SmsLogService($this->app))->list([
            'mobile'     => $this->request->param('mobile', ''),
            'scene'      => $this->request->param('scene', ''),
            'channel'    => $this->request->param('channel', ''),
            'status'     => $this->request->param('status', ''),
            'start_time' => $this->request->param('start_time', ''),
            'end_time'   => $this->request->param('end_time', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。GET /admin/v1/sms-logs/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new SmsLogService($this->app))->detail($id));
    }
}

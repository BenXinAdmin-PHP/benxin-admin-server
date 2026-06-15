<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   后台健康检查 — GET /admin/v1/ping
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\common\base\BxController;
use think\Response;

/**
 * 后台健康检查（脚手架自测载体 + 统一返回黄金样板雏形）。
 */
class Ping extends BxController
{
    public function index(): Response
    {
        return $this->success([
            'app'     => 'admin',
            'project' => 'BenXinAdmin',
            'stage'   => 'M0',
        ], 'pong');
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   C端健康检查 — GET /api/v1/ping
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use think\Response;

/**
 * C 端健康检查（脚手架自测载体 + 统一返回黄金样板雏形）。
 */
class Ping extends BxController
{
    public function index(): Response
    {
        return $this->success([
            'app'     => 'api',
            'project' => 'BenXinAdmin',
            'stage'   => 'M0',
        ], 'pong');
    }
}

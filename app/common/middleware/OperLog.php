<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   操作日志中间件（纯空壳透传，落库留 M2）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 操作日志中间件（M0 纯空壳透传）。
 *
 * 注意：M0 不写任何日志、不依赖任何数据表。
 * TODO M2：bx_oper_log 表建好后，在此采集后台敏感操作（操作人/动作/对象/IP/结果/request_id）并落库。
 */
class OperLog
{
    public function handle(Request $request, Closure $next): Response
    {
        // M0 不做任何落库，直接放行；真实逻辑见上方 TODO M2。
        return $next($request);
    }
}

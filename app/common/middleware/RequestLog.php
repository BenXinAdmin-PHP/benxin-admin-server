<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   请求上下文中间件 — 注入全局唯一 request_id（访问日志宿主）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\middleware;

use app\common\library\Uuid;
use Closure;
use think\Request;
use think\Response;

/**
 * 请求入口中间件：
 * - 为每个请求生成（或复用上游传入的）全局唯一 request_id，注入请求上下文，
 *   供统一响应 Result 与后续日志读取；并回写响应头 X-Request-Id 便于链路追踪。
 * - M0 仅做 request_id 注入；访问日志落库留待 M2（bx_oper_log）。
 */
class RequestLog
{
    public function handle(Request $request, Closure $next): Response
    {
        // 复用上游网关传入的 X-Request-Id（分布式追踪），缺失则生成
        $requestId = (string) $request->header('X-Request-Id', '');
        if ($requestId === '') {
            $requestId = Uuid::v4();
        }
        // 注入请求上下文（写入 Request 中间件数据袋，非请求参数，不可被入参覆盖）
        $request->request_id = $requestId;

        /** @var Response $response */
        $response = $next($request);

        // 回写响应头，前端/网关可据此追踪本次请求
        $response->header(['X-Request-Id' => $requestId]);

        // TODO M2: 在此落地访问日志（method/uri/ip/ua/耗时 + request_id），bx_oper_log 建表后接入

        return $response;
    }
}

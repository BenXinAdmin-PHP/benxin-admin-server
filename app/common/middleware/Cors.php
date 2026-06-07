<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   跨域中间件 — CORS 头处理与 OPTIONS 预检
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
 * 跨域处理（真实可用）：
 * - 反射来源 Origin 以兼容 Allow-Credentials；无 Origin 时回退 *。
 * - OPTIONS 预检直接返回 204，不进入业务。
 */
class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->header('Origin', '');

        $headers = [
            'Access-Control-Allow-Origin'      => $origin !== '' ? $origin : '*',
            'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, X-Requested-With, X-Request-Id',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
        ];

        // 预检请求直接放行
        if (strtoupper($request->method(true)) === 'OPTIONS') {
            return Response::create()->code(204)->header($headers);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response->header($headers);
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   跨域中间件 — CORS 头处理与 OPTIONS 预检
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-08 14:05:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 跨域处理（按环境放行来源）：
 * - 显式白名单：env CORS_ALLOW_ORIGINS（逗号分隔，全环境生效）。
 * - 开发环境（APP_DEBUG=true）：额外放行 localhost / 127.0.0.1 的任意端口（兼容 Vite 端口漂移）。
 * - 生产环境：仅放行白名单内来源，未命中不下发 Allow-Origin（浏览器自然拦截），避免反射任意来源 + 凭证的风险。
 * - 命中放行才回 Allow-Credentials，并加 Vary: Origin。
 * - OPTIONS 预检直接返回 204（带全部 CORS 头），不进入业务。
 */
class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin      = (string) $request->header('Origin', '');
        $allowOrigin = $this->resolveAllowOrigin($origin);

        $headers = [
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, X-Request-Id',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Max-Age'       => '86400',
        ];

        // 仅当来源被放行时才下发 Allow-Origin / 凭证头
        if ($allowOrigin !== null) {
            $headers['Access-Control-Allow-Origin']      = $allowOrigin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
            $headers['Vary']                             = 'Origin';
        }

        // 预检请求直接放行
        if (strtoupper($request->method(true)) === 'OPTIONS') {
            return Response::create()->code(204)->header($headers);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response->header($headers);
    }

    /**
     * 计算应放行的来源；不放行返回 null。
     */
    protected function resolveAllowOrigin(string $origin): ?string
    {
        if ($origin === '') {
            return null;
        }

        // 1) 显式白名单（env CORS_ALLOW_ORIGINS，逗号分隔），全环境生效
        $whitelist = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_ORIGINS', ''))));
        if (in_array($origin, $whitelist, true)) {
            return $origin;
        }

        // 2) 开发环境放行本地任意端口
        if (app()->isDebug() && $this->isLocalOrigin($origin)) {
            return $origin;
        }

        return null;
    }

    /**
     * 是否本地来源：http(s)://localhost 或 127.0.0.1，端口任意。
     */
    protected function isLocalOrigin(string $origin): bool
    {
        return (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $origin);
    }
}

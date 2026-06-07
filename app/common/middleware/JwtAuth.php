<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   JWT 鉴权中间件（骨架，逻辑留 M1）
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
 * JWT 鉴权中间件（M0 占位透传）。
 *
 * TODO M1：
 * - 解析 Authorization: Bearer <access_token>，校验签名/过期；
 * - 后台与 C 端各自独立 guard + 独立密钥；
 * - 校验登出黑名单（jti，存 Valkey）；过期/未登录抛认证异常 → 401xxx；
 * - 将登录主体注入请求上下文（$request->auth）。
 */
class JwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // M0 不做任何鉴权，直接放行；真实逻辑见上方 TODO M1。
        return $next($request);
    }
}

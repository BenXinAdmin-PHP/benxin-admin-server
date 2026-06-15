<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   C 端 JWT 鉴权中间件 — 校验 api access 并注入登录用户
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\middleware;

use app\common\exception\AuthException;
use app\common\library\BxJwt;
use app\common\model\User;
use Closure;
use think\Request;
use think\Response;

/**
 * C 端鉴权（api guard，M5-A 由 M0 占位透传升级为完整版）：
 * - 取 Authorization: Bearer <access_token> → BxJwt::parse('api', ..., access)。
 * - parse 内部已完成签名/iss/aud/过期/类型/黑名单校验，失败抛 AuthException
 *   （401001 无效/黑名单命中 / 401003 过期）。
 * - 解析 uid 取 bx_user（status=1），注入请求上下文：
 *     $request->userId / $request->userInfo / $request->jwtClaims。
 * - 与 admin guard 完全隔离：独立密钥 JWT_API_SECRET、独立 Valkey 名单 bx:bxjwt:api:*；
 *   C 端无 Casbin（身份验证即可，不挂 CasbinAuth）。
 * - 懒登录（ADR-17）：仅核心接口挂本中间件；浏览类接口免登录不挂。
 */
class JwtAuth
{
    protected const GUARD = 'api';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);
        if ($token === '') {
            throw AuthException::unauthorized();
        }

        // 校验 api access（含黑名单），失败由全局异常处理器映射为 401xxx
        $claims = BxJwt::parse(self::GUARD, $token, BxJwt::TYPE_ACCESS);

        // 取登录用户，要求存在且状态正常
        $user = User::where('id', $claims['uid'])->where('status', 1)->find();
        if ($user === null) {
            throw AuthException::unauthorized();
        }

        // 注入请求上下文，供控制器/服务读取（非请求参数，不可被入参覆盖）
        $request->userId    = (int) $user->id;
        $request->userInfo  = $user;
        $request->jwtClaims = $claims;

        return $next($request);
    }

    /**
     * 从 Authorization 头提取 Bearer token（不区分大小写），无则返回空串。
     */
    protected function bearerToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');
        if ($header !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return trim($m[1]);
        }

        return '';
    }
}

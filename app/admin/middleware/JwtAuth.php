<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   后台 JWT 鉴权中间件 — 校验 access 并注入登录管理员
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\middleware;

use app\common\exception\AuthException;
use app\common\library\BxJwt;
use app\common\model\Admin;
use Closure;
use think\Request;
use think\Response;

/**
 * 后台鉴权（admin guard）：
 * - 取 Authorization: Bearer <access_token> → BxJwt::parse('admin', ..., access)。
 * - parse 内部已完成签名/iss/aud/过期/类型/黑名单校验，失败抛 AuthException
 *   （401001 无效 / 401003 过期 / 黑名单命中 401001）。
 * - 解析 uid 取 bx_admin（status=1），注入请求上下文：
 *     $request->adminId / $request->adminUser / $request->jwtClaims。
 * - 登录、刷新接口不挂本中间件（刷新单独校验 refresh token）。
 */
class JwtAuth
{
    protected const GUARD = 'admin';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);
        if ($token === '') {
            throw AuthException::unauthorized();
        }

        // 校验 access（含黑名单），失败由全局异常处理器映射为 401xxx
        $claims = BxJwt::parse(self::GUARD, $token, BxJwt::TYPE_ACCESS);

        // 取登录管理员，要求存在且状态正常
        $admin = Admin::where('id', $claims['uid'])->where('status', 1)->find();
        if ($admin === null) {
            throw AuthException::unauthorized();
        }

        // 注入请求上下文，供控制器/服务读取（非请求参数，不可被入参覆盖）
        $request->adminId    = (int) $admin->id;
        $request->adminUser  = $admin;
        $request->jwtClaims  = $claims;

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

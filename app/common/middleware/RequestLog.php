<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   请求上下文中间件 — request_id 注入 + 操作日志自动记录
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-10 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\middleware;

use app\common\library\LogSanitizer;
use app\common\library\Uuid;
use app\common\model\OperLog;
use Closure;
use think\facade\Log;
use think\Request;
use think\Response;
use Throwable;

/**
 * 请求入口中间件（全局，最外层）：
 * - 生成/复用 request_id，注入上下文并回写响应头 X-Request-Id。
 * - 写操作（POST/PUT/DELETE/PATCH）自动落操作日志 bx_oper_log：
 *   request_body 经 LogSanitizer 脱敏（安全红线）；操作人/perm 取内层中间件注入；
 *   全程 try/catch 吞错——日志故障**绝不影响主请求**。GET 不记（防刷屏）。
 */
class RequestLog
{
    /** 记录的写方法 */
    protected const WRITE_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    /** request_body 摘要长度上限 */
    protected const BODY_MAX = 2000;

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        // 复用上游网关传入的 X-Request-Id（分布式追踪），缺失则生成
        $requestId = (string) $request->header('X-Request-Id', '');
        if ($requestId === '') {
            $requestId = Uuid::v4();
        }
        $request->request_id = $requestId;

        /** @var Response $response */
        $response = $next($request);

        $response->header(['X-Request-Id' => $requestId]);

        // 操作日志（写操作）——失败吞错，不影响主响应
        $this->recordOperLog($request, $response, $start);

        return $response;
    }

    /**
     * 落操作日志（仅写方法）。任何异常仅记文件 log，不抛。
     */
    protected function recordOperLog(Request $request, Response $response, float $start): void
    {
        try {
            $method = strtoupper($request->method(true));
            if (!in_array($method, self::WRITE_METHODS, true)) {
                return;
            }

            $path = '/' . ltrim($request->pathinfo(), '/');

            // 请求体脱敏（/configs 写接口额外打码 value）
            $extra = str_contains($path, '/configs') ? ['value'] : [];
            $body  = LogSanitizer::sanitize((array) $request->param(), $extra);
            $bodyJson = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (mb_strlen($bodyJson) > self::BODY_MAX) {
                $bodyJson = mb_substr($bodyJson, 0, self::BODY_MAX);
            }

            // 业务 code 取自统一信封
            $decoded = json_decode((string) $response->getContent(), true);
            $code    = is_array($decoded) ? ($decoded['code'] ?? null) : null;

            $admin = $request->adminUser ?? null;

            OperLog::create([
                'tenant_id'     => 0,
                'admin_id'      => (int) ($request->adminId ?? 0),
                'username'      => $admin->username ?? '',
                'method'        => $method,
                'path'          => $path,
                'perm'          => (string) ($request->requiredPerm ?? ''),
                'ip'            => $request->ip(),
                'user_agent'    => mb_substr((string) $request->header('user-agent', ''), 0, 512),
                'request_body'  => $bodyJson,
                'response_code' => $code,
                'http_status'   => $response->getCode(),
                'duration_ms'   => (int) round((microtime(true) - $start) * 1000),
                'request_id'    => (string) $request->request_id,
            ]);
        } catch (Throwable $e) {
            // 日志故障不影响主流程
            Log::error('[oper_log] record failed: ' . $e->getMessage());
        }
    }
}

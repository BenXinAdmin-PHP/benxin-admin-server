<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   统一异常处理 — 收敛为统一信封，生产不泄露堆栈
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\exception;

use app\common\library\ErrorCode;
use app\common\library\Result;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle as ThinkHandle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 全局异常处理器：所有异常统一渲染为业务码风格 A 的信封。
 * - HTTP 默认 200，结果以 code 为准；鉴权类（401xxx）返回 HTTP 401 便于前端拦截。
 * - 生产态（APP_DEBUG=false）不泄露异常堆栈。
 */
class Handle extends ThinkHandle
{
    /**
     * 渲染异常为统一响应。
     */
    public function render($request, Throwable $e): Response
    {
        // 主动抛出的响应（重定向/自定义响应）直接交还
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }

        // 认证失败（JWT 缺失/无效/过期、refresh 失效）→ 401xxx + HTTP 401
        if ($e instanceof AuthException) {
            return Result::fail($e->bizCode, $e->getMessage(), null, 401);
        }

        // 业务异常（Service 层业务规则不满足）→ 422xxx（HTTP 200）
        if ($e instanceof BusinessException) {
            return Result::fail($e->bizCode, $e->getMessage());
        }

        // 入参校验失败 → 422xxx
        if ($e instanceof ValidateException) {
            return Result::fail(ErrorCode::VALIDATE_FAIL, $e->getError() ?: $e->getMessage());
        }

        // 模型/数据未找到 → 404xxx
        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return Result::fail(ErrorCode::NOT_FOUND);
        }

        // HTTP 异常（含路由未匹配 404、方法不允许 405 等）
        if ($e instanceof HttpException) {
            return $this->mapHttpException($e);
        }

        // 其它未捕获异常 → 500xxx
        $msg  = ErrorCode::message(ErrorCode::SERVER_ERROR);
        $data = null;
        if ($this->isDebug()) {
            $msg  = $e->getMessage();
            $data = [
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ];
        }

        return Result::fail(ErrorCode::SERVER_ERROR, $msg, $data);
    }

    /**
     * 按 HTTP 状态码映射到业务错误码。
     */
    protected function mapHttpException(HttpException $e): Response
    {
        return match ($e->getStatusCode()) {
            401     => Result::fail(ErrorCode::UNAUTHORIZED, '', null, 401),
            403     => Result::fail(ErrorCode::FORBIDDEN),
            404     => Result::fail(ErrorCode::NOT_FOUND),
            405     => Result::fail(ErrorCode::PARAM_ERROR, '请求方法不被允许'),
            429     => Result::fail(ErrorCode::TOO_MANY_REQUESTS),
            default => Result::fail(ErrorCode::SERVER_ERROR, $this->isDebug() ? $e->getMessage() : ''),
        };
    }

    /**
     * 是否调试态。
     */
    protected function isDebug(): bool
    {
        return (bool) $this->app->isDebug();
    }
}

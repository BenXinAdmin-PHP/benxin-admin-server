<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   统一响应封装 — 业务码风格 A（ARCHITECTURE §6.1）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

use think\Response;

/**
 * 统一返回结构：
 *   { code, msg, data, request_id, timestamp }
 * - code = 0 表示成功；HTTP 默认 200，业务结果以 code 为准。
 * - request_id 由 RequestLog 中间件在请求入口注入（同一请求全局唯一）；
 *   此处只读取，不再每次生成，缺失时兜底。
 */
class Result
{
    /**
     * 成功响应。
     *
     * @param mixed  $data 业务数据
     * @param string $msg  提示语
     */
    public static function success(mixed $data = null, string $msg = 'success'): Response
    {
        return self::build(ErrorCode::SUCCESS, $msg, $data, 200);
    }

    /**
     * 失败响应。
     *
     * @param int    $code       业务错误码
     * @param string $msg        提示语（留空时取错误码默认提示）
     * @param mixed  $data       附加数据
     * @param int    $httpStatus HTTP 状态码（默认 200，鉴权类可传 401）
     */
    public static function fail(int $code, string $msg = '', mixed $data = null, int $httpStatus = 200): Response
    {
        return self::build($code, $msg !== '' ? $msg : ErrorCode::message($code), $data, $httpStatus);
    }

    /**
     * 分页响应（data:{ list, total, page, page_size }）。
     *
     * @param array<int,mixed> $list
     */
    public static function paginate(array $list, int $total, int $page, int $pageSize, string $msg = 'success'): Response
    {
        return self::success([
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ], $msg);
    }

    /**
     * 组装统一信封。
     */
    protected static function build(int $code, string $msg, mixed $data, int $httpStatus): Response
    {
        return Response::create([
            'code'       => $code,
            'msg'        => $msg,
            'data'       => $data,
            'request_id' => self::requestId(),
            'timestamp'  => time(),
        ], 'json', $httpStatus);
    }

    /**
     * 读取请求上下文中的 request_id（由 RequestLog 注入），缺失时兜底生成。
     */
    protected static function requestId(): string
    {
        $id = '';
        try {
            $id = (string) (request()->request_id ?? '');
        } catch (\Throwable) {
            // 非 HTTP 上下文（如命令行）忽略
        }

        return $id !== '' ? $id : Uuid::v4();
    }
}

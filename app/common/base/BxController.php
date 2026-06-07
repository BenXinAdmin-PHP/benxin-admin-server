<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   控制器基类 — 统一响应入口
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\base;

use app\common\library\Result;
use think\App;
use think\Request;
use think\Response;

/**
 * 所有业务控制器的基类，统一收口响应。
 * 控制器一律返回 Result::* 构造的统一信封，禁止手拼数组。
 */
abstract class BxController
{
    protected App $app;
    protected Request $request;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $app->request;

        $this->initialize();
    }

    /**
     * 初始化钩子（子类可覆写）。
     */
    protected function initialize(): void
    {
    }

    /**
     * 成功响应。
     */
    protected function success(mixed $data = null, string $msg = 'success'): Response
    {
        return Result::success($data, $msg);
    }

    /**
     * 失败响应。
     */
    protected function fail(int $code, string $msg = '', mixed $data = null, int $httpStatus = 200): Response
    {
        return Result::fail($code, $msg, $data, $httpStatus);
    }

    /**
     * 分页响应。
     *
     * @param array<int,mixed> $list
     */
    protected function paginate(array $list, int $total, int $page, int $pageSize, string $msg = 'success'): Response
    {
        return Result::paginate($list, $total, $page, $pageSize, $msg);
    }
}

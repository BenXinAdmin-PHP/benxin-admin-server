<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务层基类 — 业务编排收口
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\base;

use think\App;

/**
 * 服务层基类：承载跨模型的业务编排，控制器只调用 Service，不直接写复杂业务。
 */
abstract class BxService
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   限流配置（think-throttle）— 全局兜底 + 敏感接口分组预留
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

use app\common\library\ErrorCode;
use app\common\library\Result;
use think\middleware\throttle\CounterSlider;
use think\middleware\Throttle;
use think\Request;

return [
    // 缓存键前缀，防止与其他应用冲突
    'prefix'                       => 'throttle_',
    // 缓存的键，true 表示使用来源 IP
    'key'                          => true,
    // 要被限制的请求类型
    'visit_method'                 => ['GET', 'HEAD', 'POST', 'PUT', 'DELETE'],
    // 全局兜底访问频率（null 表示不限制）
    'visit_rate'                   => '600/m',
    // 限流算法：滑动窗口
    'driver_name'                  => CounterSlider::class,
    // 在响应头返回速率限制信息
    'visit_enable_show_rate_limit' => true,
    // 访问受限时返回统一信封（429xxx）
    'visit_fail_response'          => function (Throttle $throttle, Request $request, int $wait_seconds) {
        return Result::fail(ErrorCode::TOO_MANY_REQUESTS, '请求过于频繁，请 ' . $wait_seconds . ' 秒后再试');
    },

    // ------------------------------------------------------------------
    // 敏感接口分组预留（M1 登录/短信等按路由单独挂更严格的限流）：
    //   Route::post('login', 'Auth/login')->middleware(Throttle::class, ['visit_rate' => '5/m']);
    //   Route::post('sms',   'Sms/send')  ->middleware(Throttle::class, ['visit_rate' => '1/m']);
    // 全局启用兜底限流：在 app/middleware.php 注册 \think\middleware\Throttle::class。
    // M0 暂不全局注册（避免干扰自测），仅落地配置。
    // ------------------------------------------------------------------
];

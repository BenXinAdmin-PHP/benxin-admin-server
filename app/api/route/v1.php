<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   C端路由 v1 — 对外前缀 /api/v1
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-13 21:30:00
// +----------------------------------------------------------------------

use app\common\middleware\JwtAuth;
use think\facade\Route;
use think\middleware\Throttle;

// 版本分组：完整路径 /api/v1/...（/api 来自多应用前缀）
// 路由顺序约定：具体 action > /:id > 集合
Route::group('v1', function () {
    Route::get('ping', 'Ping/index');

    // ---- C 端认证（M5-A，api guard）----
    // 刷新：自校验 refresh token，不挂 JwtAuth；签发新 access（refresh 不轮换）
    Route::post('refresh', 'Auth/refresh');
    // 登出：拉黑当前 access + 撤 refresh 白名单（需登录，挂 api JwtAuth）
    Route::post('logout', 'Auth/logout')->middleware(JwtAuth::class);

    // ---- 微信能力（M4-B）----
    // JSSDK 签名：H5 公众号取签名四元组（懒登录不强制）；公开接口挂限流 60 次/分/IP
    Route::get('wechat/jssdk', 'Wechat/jssdk')->middleware(Throttle::class, ['visit_rate' => '60/m']);

    // ---- 支付回调（M4-C，渠道异步通知，公开不鉴权；安全四件套在 BxPay 收口）----
    Route::post('pay/notify/:channel', 'Pay/notify');

    // ---- 短信验证码（M4-D，懒登录不强制）----
    // 接口级严格限流防轰炸（1 次/分/IP）；业务多维限流（手机号间隔/天上限）在 SmsCodeService
    Route::post('sms/code', 'Sms/code')->middleware(Throttle::class, ['visit_rate' => '1/m']);

    // ---- 前台公告（M4-D D-2，只读已发布；C 端展示）----
    Route::get('notices/:id', 'Notice/read')->pattern(['id' => '\d+']);
    Route::get('notices', 'Notice/index');

    // ---- 前台内容（M5-A A-2，只读已发布；列表无正文，详情浏览量+1）----
    Route::get('contents/:id', 'Content/read')->pattern(['id' => '\d+']);
    Route::get('contents', 'Content/index');

    // ---- 前台广告位（M5-A A-2，启用 + 生效区间，按 position 过滤）----
    Route::get('banners', 'Banner/index');
});

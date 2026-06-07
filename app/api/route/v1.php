<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   C端路由 v1 — 对外前缀 /api/v1
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

use think\facade\Route;

// 版本分组：完整路径 /api/v1/...（/api 来自多应用前缀）
// 路由顺序约定：具体 action > /:id > 集合
Route::group('v1', function () {
    Route::get('ping', 'Ping/index');
});

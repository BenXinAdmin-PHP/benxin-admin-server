<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   后台路由 v1 — 对外前缀 /admin/v1
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-08 16:00:00
// +----------------------------------------------------------------------

use app\admin\middleware\CasbinAuth;
use app\admin\middleware\JwtAuth;
use think\facade\Route;
use think\middleware\Throttle;

// 版本分组：完整路径 /admin/v1/...（/admin 来自多应用前缀）
// 路由顺序约定：具体 action > /:id > 集合
Route::group('v1', function () {
    Route::get('ping', 'Ping/index');

    // ---- 认证（无需登录）----
    // 登录：敏感接口单独限流 10 次/分/IP（think-throttle）
    Route::post('login', 'Auth/login')->middleware(Throttle::class, ['visit_rate' => '10/m']);
    // 刷新：自校验 refresh token，不挂 JwtAuth
    Route::post('refresh', 'Auth/refresh');

    // ---- 认证（需登录，挂 JwtAuth）----
    Route::group(function () {
        Route::post('logout', 'Auth/logout');
        Route::get('profile', 'Auth/profile');
    })->middleware(JwtAuth::class);

    // ---- 系统管理 CRUD（需登录 + 按 perm 鉴权；JwtAuth → CasbinAuth）----
    // 路由顺序铁律：具体 action > /:id > 集合。
    Route::group(function () {
        // 菜单（perm: system:menu:*）
        Route::get('menus/tree', 'Menu/tree')->middleware(CasbinAuth::class, 'system:menu:list');
        Route::put('menus/:id/status', 'Menu/status')->middleware(CasbinAuth::class, 'system:menu:update')->pattern(['id' => '\d+']);
        Route::get('menus/:id', 'Menu/read')->middleware(CasbinAuth::class, 'system:menu:list')->pattern(['id' => '\d+']);
        Route::put('menus/:id', 'Menu/update')->middleware(CasbinAuth::class, 'system:menu:update')->pattern(['id' => '\d+']);
        Route::delete('menus/:id', 'Menu/delete')->middleware(CasbinAuth::class, 'system:menu:delete')->pattern(['id' => '\d+']);
        Route::post('menus', 'Menu/save')->middleware(CasbinAuth::class, 'system:menu:create');

        // 角色（perm: system:role:*；分配菜单复用 update）
        Route::get('roles/:id/menus', 'Role/menus')->middleware(CasbinAuth::class, 'system:role:list')->pattern(['id' => '\d+']);
        Route::put('roles/:id/menus', 'Role/assignMenus')->middleware(CasbinAuth::class, 'system:role:update')->pattern(['id' => '\d+']);
        Route::put('roles/:id/status', 'Role/status')->middleware(CasbinAuth::class, 'system:role:update')->pattern(['id' => '\d+']);
        Route::get('roles/:id', 'Role/read')->middleware(CasbinAuth::class, 'system:role:list')->pattern(['id' => '\d+']);
        Route::put('roles/:id', 'Role/update')->middleware(CasbinAuth::class, 'system:role:update')->pattern(['id' => '\d+']);
        Route::delete('roles/:id', 'Role/delete')->middleware(CasbinAuth::class, 'system:role:delete')->pattern(['id' => '\d+']);
        Route::get('roles', 'Role/index')->middleware(CasbinAuth::class, 'system:role:list');
        Route::post('roles', 'Role/save')->middleware(CasbinAuth::class, 'system:role:create');
    })->middleware(JwtAuth::class);

    // ---- M1-B 权限探针（仅调试态注册；JwtAuth → CasbinAuth）----
    // 验证 RBAC enforce：需 system:admin:list 权限，超管通配放行、无策略角色 403000。
    if (app()->isDebug()) {
        Route::get('_perm_probe', 'Probe/index')
            ->middleware(JwtAuth::class)
            ->middleware(CasbinAuth::class, 'system:admin:list');
    }
});

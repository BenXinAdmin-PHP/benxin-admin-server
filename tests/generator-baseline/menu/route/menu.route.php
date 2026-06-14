<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   路由片段 — 菜单（并入 app/admin/route/v1.php 的「系统管理 CRUD」分组）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------
//
// 这是生成的【路由片段】，不会自动改动既有 v1.php（降风险）。
// 并入方式：把下方 6（或 5）行复制进 app/admin/route/v1.php 内
//   Route::group(function () { ... })->middleware(JwtAuth::class); 这一「系统管理 CRUD」分组里，
// 保持「具体 action > /:id > 集合」顺序即可（:id 已用 \d+ 约束）。
//
// 依赖（v1.php 顶部已 use）：
//   use app\admin\middleware\CasbinAuth;
//   use think\facade\Route;

        // 菜单（perm: system:menu:*）
        Route::get('menus/tree', 'Menu/tree')->middleware(CasbinAuth::class, 'system:menu:list');
        Route::put('menus/:id/status', 'Menu/status')->middleware(CasbinAuth::class, 'system:menu:update')->pattern(['id' => '\d+']);
        Route::get('menus/:id', 'Menu/read')->middleware(CasbinAuth::class, 'system:menu:list')->pattern(['id' => '\d+']);
        Route::put('menus/:id', 'Menu/update')->middleware(CasbinAuth::class, 'system:menu:update')->pattern(['id' => '\d+']);
        Route::delete('menus/:id', 'Menu/delete')->middleware(CasbinAuth::class, 'system:menu:delete')->pattern(['id' => '\d+']);
        Route::post('menus', 'Menu/save')->middleware(CasbinAuth::class, 'system:menu:create');

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   路由片段 — 角色（并入 app/admin/route/v1.php 的「系统管理 CRUD」分组）
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

        // 角色（perm: system:role:*）
        Route::get('roles/:id/menus', 'Role/menus')->middleware(CasbinAuth::class, 'system:role:list')->pattern(['id' => '\d+']);
        Route::put('roles/:id/menus', 'Role/assignMenus')->middleware(CasbinAuth::class, 'system:role:update')->pattern(['id' => '\d+']);
        Route::put('roles/:id/status', 'Role/status')->middleware(CasbinAuth::class, 'system:role:update')->pattern(['id' => '\d+']);
        Route::get('roles/:id', 'Role/read')->middleware(CasbinAuth::class, 'system:role:list')->pattern(['id' => '\d+']);
        Route::put('roles/:id', 'Role/update')->middleware(CasbinAuth::class, 'system:role:update')->pattern(['id' => '\d+']);
        Route::delete('roles/:id', 'Role/delete')->middleware(CasbinAuth::class, 'system:role:delete')->pattern(['id' => '\d+']);
        Route::get('roles', 'Role/index')->middleware(CasbinAuth::class, 'system:role:list');
        Route::post('roles', 'Role/save')->middleware(CasbinAuth::class, 'system:role:create');

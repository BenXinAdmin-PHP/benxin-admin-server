<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   路由片段 — 岗位（并入 app/admin/route/v1.php 的「系统管理 CRUD」分组）
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

        // 岗位（perm: system:post:*）
        Route::put('posts/:id/status', 'Post/status')->middleware(CasbinAuth::class, 'system:post:update')->pattern(['id' => '\d+']);
        Route::get('posts/:id', 'Post/read')->middleware(CasbinAuth::class, 'system:post:list')->pattern(['id' => '\d+']);
        Route::put('posts/:id', 'Post/update')->middleware(CasbinAuth::class, 'system:post:update')->pattern(['id' => '\d+']);
        Route::delete('posts/:id', 'Post/delete')->middleware(CasbinAuth::class, 'system:post:delete')->pattern(['id' => '\d+']);
        Route::get('posts', 'Post/index')->middleware(CasbinAuth::class, 'system:post:list');
        Route::post('posts', 'Post/save')->middleware(CasbinAuth::class, 'system:post:create');

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   路由片段 — 部门（并入 app/admin/route/v1.php 的「系统管理 CRUD」分组）
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

        // 部门（perm: system:dept:*）
        Route::get('depts/tree', 'Dept/tree')->middleware(CasbinAuth::class, 'system:dept:list');
        Route::put('depts/:id/status', 'Dept/status')->middleware(CasbinAuth::class, 'system:dept:update')->pattern(['id' => '\d+']);
        Route::get('depts/:id', 'Dept/read')->middleware(CasbinAuth::class, 'system:dept:list')->pattern(['id' => '\d+']);
        Route::put('depts/:id', 'Dept/update')->middleware(CasbinAuth::class, 'system:dept:update')->pattern(['id' => '\d+']);
        Route::delete('depts/:id', 'Dept/delete')->middleware(CasbinAuth::class, 'system:dept:delete')->pattern(['id' => '\d+']);
        Route::post('depts', 'Dept/save')->middleware(CasbinAuth::class, 'system:dept:create');

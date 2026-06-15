<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   路由片段 — 字典类型（并入 app/admin/route/v1.php 的「系统管理 CRUD」分组）
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

        // 字典类型（perm: system:dict:*）
        Route::put('dicts/:id/status', 'Dict/status')->middleware(CasbinAuth::class, 'system:dict:update')->pattern(['id' => '\d+']);
        Route::get('dicts/:id', 'Dict/read')->middleware(CasbinAuth::class, 'system:dict:list')->pattern(['id' => '\d+']);
        Route::put('dicts/:id', 'Dict/update')->middleware(CasbinAuth::class, 'system:dict:update')->pattern(['id' => '\d+']);
        Route::delete('dicts/:id', 'Dict/delete')->middleware(CasbinAuth::class, 'system:dict:delete')->pattern(['id' => '\d+']);
        Route::get('dicts', 'Dict/index')->middleware(CasbinAuth::class, 'system:dict:list');
        Route::post('dicts', 'Dict/save')->middleware(CasbinAuth::class, 'system:dict:create');

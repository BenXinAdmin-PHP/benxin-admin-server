<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   后台路由 v1 — 对外前缀 /admin/v1
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-13 11:00:00
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
        // 自助改密（改自己，不挂 CasbinAuth）；与 /admins/:id/password（重置他人）区分
        Route::put('password', 'Auth/changePassword');
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

        // 管理员（perm: system:admin:*；改密/分配角色复用 update）
        Route::put('admins/:id/password', 'Admin/password')->middleware(CasbinAuth::class, 'system:admin:update')->pattern(['id' => '\d+']);
        Route::put('admins/:id/status', 'Admin/status')->middleware(CasbinAuth::class, 'system:admin:update')->pattern(['id' => '\d+']);
        Route::get('admins/:id', 'Admin/read')->middleware(CasbinAuth::class, 'system:admin:list')->pattern(['id' => '\d+']);
        Route::put('admins/:id', 'Admin/update')->middleware(CasbinAuth::class, 'system:admin:update')->pattern(['id' => '\d+']);
        Route::delete('admins/:id', 'Admin/delete')->middleware(CasbinAuth::class, 'system:admin:delete')->pattern(['id' => '\d+']);
        Route::get('admins', 'Admin/index')->middleware(CasbinAuth::class, 'system:admin:list');
        Route::post('admins', 'Admin/save')->middleware(CasbinAuth::class, 'system:admin:create');

        // 部门（perm: system:dept:*）
        Route::get('depts/tree', 'Dept/tree')->middleware(CasbinAuth::class, 'system:dept:list');
        Route::put('depts/:id/status', 'Dept/status')->middleware(CasbinAuth::class, 'system:dept:update')->pattern(['id' => '\d+']);
        Route::get('depts/:id', 'Dept/read')->middleware(CasbinAuth::class, 'system:dept:list')->pattern(['id' => '\d+']);
        Route::put('depts/:id', 'Dept/update')->middleware(CasbinAuth::class, 'system:dept:update')->pattern(['id' => '\d+']);
        Route::delete('depts/:id', 'Dept/delete')->middleware(CasbinAuth::class, 'system:dept:delete')->pattern(['id' => '\d+']);
        Route::post('depts', 'Dept/save')->middleware(CasbinAuth::class, 'system:dept:create');

        // 岗位（perm: system:post:*）
        Route::put('posts/:id/status', 'Post/status')->middleware(CasbinAuth::class, 'system:post:update')->pattern(['id' => '\d+']);
        Route::get('posts/:id', 'Post/read')->middleware(CasbinAuth::class, 'system:post:list')->pattern(['id' => '\d+']);
        Route::put('posts/:id', 'Post/update')->middleware(CasbinAuth::class, 'system:post:update')->pattern(['id' => '\d+']);
        Route::delete('posts/:id', 'Post/delete')->middleware(CasbinAuth::class, 'system:post:delete')->pattern(['id' => '\d+']);
        Route::get('posts', 'Post/index')->middleware(CasbinAuth::class, 'system:post:list');
        Route::post('posts', 'Post/save')->middleware(CasbinAuth::class, 'system:post:create');

        // 字典类型（perm: system:dict:*）；type/:type 取数须排在 /:id 之前
        Route::get('dicts/type/:type', 'Dict/dataByType')->middleware(CasbinAuth::class, 'system:dict:list');
        Route::put('dicts/:id/status', 'Dict/status')->middleware(CasbinAuth::class, 'system:dict:update')->pattern(['id' => '\d+']);
        Route::get('dicts/:id', 'Dict/read')->middleware(CasbinAuth::class, 'system:dict:list')->pattern(['id' => '\d+']);
        Route::put('dicts/:id', 'Dict/update')->middleware(CasbinAuth::class, 'system:dict:update')->pattern(['id' => '\d+']);
        Route::delete('dicts/:id', 'Dict/delete')->middleware(CasbinAuth::class, 'system:dict:delete')->pattern(['id' => '\d+']);
        Route::get('dicts', 'Dict/index')->middleware(CasbinAuth::class, 'system:dict:list');
        Route::post('dicts', 'Dict/save')->middleware(CasbinAuth::class, 'system:dict:create');

        // 字典数据项（perm 复用 system:dict:*）
        Route::put('dict-data/:id/status', 'DictData/status')->middleware(CasbinAuth::class, 'system:dict:update')->pattern(['id' => '\d+']);
        Route::get('dict-data/:id', 'DictData/read')->middleware(CasbinAuth::class, 'system:dict:list')->pattern(['id' => '\d+']);
        Route::put('dict-data/:id', 'DictData/update')->middleware(CasbinAuth::class, 'system:dict:update')->pattern(['id' => '\d+']);
        Route::delete('dict-data/:id', 'DictData/delete')->middleware(CasbinAuth::class, 'system:dict:delete')->pattern(['id' => '\d+']);
        Route::get('dict-data', 'DictData/index')->middleware(CasbinAuth::class, 'system:dict:list');
        Route::post('dict-data', 'DictData/save')->middleware(CasbinAuth::class, 'system:dict:create');

        // 参数配置（perm: system:config:*）；group/:group 取数须排在 /:id 之前
        Route::get('configs/group/:group', 'Config/group')->middleware(CasbinAuth::class, 'system:config:list');
        Route::get('configs/:id', 'Config/read')->middleware(CasbinAuth::class, 'system:config:list')->pattern(['id' => '\d+']);
        Route::put('configs/:id', 'Config/update')->middleware(CasbinAuth::class, 'system:config:update')->pattern(['id' => '\d+']);
        Route::delete('configs/:id', 'Config/delete')->middleware(CasbinAuth::class, 'system:config:delete')->pattern(['id' => '\d+']);
        Route::get('configs', 'Config/index')->middleware(CasbinAuth::class, 'system:config:list');
        Route::post('configs', 'Config/save')->middleware(CasbinAuth::class, 'system:config:create');

        // 操作日志（只读 + 清理；perm: system:operlog:*）
        Route::get('oper-logs/:id', 'OperLog/read')->middleware(CasbinAuth::class, 'system:operlog:list')->pattern(['id' => '\d+']);
        Route::get('oper-logs', 'OperLog/index')->middleware(CasbinAuth::class, 'system:operlog:list');
        Route::delete('oper-logs', 'OperLog/clear')->middleware(CasbinAuth::class, 'system:operlog:delete');

        // 登录日志（只读 + 清理；perm: system:loginlog:*）
        Route::get('login-logs/:id', 'LoginLog/read')->middleware(CasbinAuth::class, 'system:loginlog:list')->pattern(['id' => '\d+']);
        Route::get('login-logs', 'LoginLog/index')->middleware(CasbinAuth::class, 'system:loginlog:list');
        Route::delete('login-logs', 'LoginLog/clear')->middleware(CasbinAuth::class, 'system:loginlog:delete');

        // 文件管理（perm: system:file:list|upload|delete）；具体 action > /:id > 集合
        Route::post('files/upload', 'File/upload')->middleware(CasbinAuth::class, 'system:file:upload');
        Route::get('files/:id/raw', 'File/raw')->middleware(CasbinAuth::class, 'system:file:list')->pattern(['id' => '\d+']);
        Route::get('files/:id', 'File/read')->middleware(CasbinAuth::class, 'system:file:list')->pattern(['id' => '\d+']);
        Route::delete('files/:id', 'File/delete')->middleware(CasbinAuth::class, 'system:file:delete')->pattern(['id' => '\d+']);
        Route::get('files', 'File/index')->middleware(CasbinAuth::class, 'system:file:list');

        // ---- 内容模块（M4-A，bx:make 生成路由片段并入）----
        // 内容分类（perm: content:category:*）
        Route::get('content-categories/tree', 'ContentCategory/tree')->middleware(CasbinAuth::class, 'content:category:list');
        Route::put('content-categories/:id/status', 'ContentCategory/status')->middleware(CasbinAuth::class, 'content:category:update')->pattern(['id' => '\d+']);
        Route::get('content-categories/:id', 'ContentCategory/read')->middleware(CasbinAuth::class, 'content:category:list')->pattern(['id' => '\d+']);
        Route::put('content-categories/:id', 'ContentCategory/update')->middleware(CasbinAuth::class, 'content:category:update')->pattern(['id' => '\d+']);
        Route::delete('content-categories/:id', 'ContentCategory/delete')->middleware(CasbinAuth::class, 'content:category:delete')->pattern(['id' => '\d+']);
        Route::post('content-categories', 'ContentCategory/save')->middleware(CasbinAuth::class, 'content:category:create');

        // 内容（perm: content:info:*）
        Route::put('contents/:id/status', 'Content/status')->middleware(CasbinAuth::class, 'content:info:update')->pattern(['id' => '\d+']);
        Route::get('contents/:id', 'Content/read')->middleware(CasbinAuth::class, 'content:info:list')->pattern(['id' => '\d+']);
        Route::put('contents/:id', 'Content/update')->middleware(CasbinAuth::class, 'content:info:update')->pattern(['id' => '\d+']);
        Route::delete('contents/:id', 'Content/delete')->middleware(CasbinAuth::class, 'content:info:delete')->pattern(['id' => '\d+']);
        Route::get('contents', 'Content/index')->middleware(CasbinAuth::class, 'content:info:list');
        Route::post('contents', 'Content/save')->middleware(CasbinAuth::class, 'content:info:create');

        // 广告位（perm: content:banner:*）
        Route::put('banners/:id/status', 'Banner/status')->middleware(CasbinAuth::class, 'content:banner:update')->pattern(['id' => '\d+']);
        Route::get('banners/:id', 'Banner/read')->middleware(CasbinAuth::class, 'content:banner:list')->pattern(['id' => '\d+']);
        Route::put('banners/:id', 'Banner/update')->middleware(CasbinAuth::class, 'content:banner:update')->pattern(['id' => '\d+']);
        Route::delete('banners/:id', 'Banner/delete')->middleware(CasbinAuth::class, 'content:banner:delete')->pattern(['id' => '\d+']);
        Route::get('banners', 'Banner/index')->middleware(CasbinAuth::class, 'content:banner:list');
        Route::post('banners', 'Banner/save')->middleware(CasbinAuth::class, 'content:banner:create');

        // ---- 支付订单管理（M4-C，手写；只读 + 退款）----
        // 退款为敏感操作（perm: system:pay:refund + 二次确认）；列表/详情只读（perm: system:pay:list）
        Route::post('pay-orders/:id/refund', 'Pay/refund')->middleware(CasbinAuth::class, 'system:pay:refund')->pattern(['id' => '\d+']);
        Route::get('pay-orders/:id', 'Pay/read')->middleware(CasbinAuth::class, 'system:pay:list')->pattern(['id' => '\d+']);
        Route::get('pay-orders', 'Pay/index')->middleware(CasbinAuth::class, 'system:pay:list');
    })->middleware(JwtAuth::class);

    // ---- 调试探针（仅调试态注册，生产不暴露）----
    if (app()->isDebug()) {
        // M1-B 权限探针：验证 RBAC enforce，需 system:admin:list 权限
        Route::get('_perm_probe', 'Probe/index')
            ->middleware(JwtAuth::class)
            ->middleware(CasbinAuth::class, 'system:admin:list');
        // M4-B 微信探针：配置就绪态 + token/签名/oauth URL 样例（需登录）
        Route::get('_wechat_probe', 'Probe/wechat')->middleware(JwtAuth::class);
        // M4-C 支付探针：配置就绪态 + 下单参数构造 + 状态机迁移样例（需登录）
        Route::get('_pay_probe', 'Probe/pay')->middleware(JwtAuth::class);
    }
});

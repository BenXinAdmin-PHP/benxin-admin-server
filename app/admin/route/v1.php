<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   后台路由 v1 — 对外前缀 /admin/v1
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-15 18:30:00
// | @updated   2026-06-16 —— 新增 configs/groups（去重分组+计数，供配置页顶栏 Tab）
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

        // 参数配置（perm: system:config:*）；groups/group/:group 等具体 action 须排在 /:id 之前
        Route::get('configs/groups', 'Config/groups')->middleware(CasbinAuth::class, 'system:config:list');
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

        // ---- 素材管理（M-素材-A）----
        // 素材分类（bx:make 树形产出；perm: system:resource:category:*）
        Route::get('resource-categories/tree', 'ResourceCategory/tree')->middleware(CasbinAuth::class, 'system:resource:category:list');
        Route::put('resource-categories/:id/status', 'ResourceCategory/status')->middleware(CasbinAuth::class, 'system:resource:category:update')->pattern(['id' => '\d+']);
        Route::get('resource-categories/:id', 'ResourceCategory/read')->middleware(CasbinAuth::class, 'system:resource:category:list')->pattern(['id' => '\d+']);
        Route::put('resource-categories/:id', 'ResourceCategory/update')->middleware(CasbinAuth::class, 'system:resource:category:update')->pattern(['id' => '\d+']);
        Route::delete('resource-categories/:id', 'ResourceCategory/delete')->middleware(CasbinAuth::class, 'system:resource:category:delete')->pattern(['id' => '\d+']);
        Route::post('resource-categories', 'ResourceCategory/save')->middleware(CasbinAuth::class, 'system:resource:category:create');

        // 素材（bx:make 纯 CRUD 产出 + 手工槽 upload/raw/batch；perm: system:resource:*）
        // 手工槽在前（具体 action > /:id > 集合）：批量删 batch 在 delete /:id 前
        Route::post('resources/upload', 'Resource/upload')->middleware(CasbinAuth::class, 'system:resource:upload');
        // VOD 客户端直传（M-素材-C）：签发上传凭证 + 直传完成回填落库（复用 upload 权限）
        Route::post('resources/vod/upload-sign', 'Resource/vodUploadSign')->middleware(CasbinAuth::class, 'system:resource:upload');
        Route::post('resources/vod/confirm', 'Resource/vodConfirm')->middleware(CasbinAuth::class, 'system:resource:upload');
        Route::delete('resources/batch', 'Resource/batchDelete')->middleware(CasbinAuth::class, 'system:resource:delete');
        Route::get('resources/:id/raw', 'Resource/raw')->middleware(CasbinAuth::class, 'system:resource:list')->pattern(['id' => '\d+']);
        Route::get('resources/:id', 'Resource/read')->middleware(CasbinAuth::class, 'system:resource:list')->pattern(['id' => '\d+']);
        Route::put('resources/:id', 'Resource/update')->middleware(CasbinAuth::class, 'system:resource:update')->pattern(['id' => '\d+']);
        Route::delete('resources/:id', 'Resource/delete')->middleware(CasbinAuth::class, 'system:resource:delete')->pattern(['id' => '\d+']);
        Route::get('resources', 'Resource/index')->middleware(CasbinAuth::class, 'system:resource:list');
        Route::post('resources', 'Resource/save')->middleware(CasbinAuth::class, 'system:resource:create');

        // ---- 支付订单管理（M4-C，手写；只读 + 退款）----
        // 退款为敏感操作（perm: system:pay:refund + 二次确认）；列表/详情只读（perm: system:pay:list）
        Route::post('pay-orders/:id/refund', 'Pay/refund')->middleware(CasbinAuth::class, 'system:pay:refund')->pattern(['id' => '\d+']);
        Route::get('pay-orders/:id', 'Pay/read')->middleware(CasbinAuth::class, 'system:pay:list')->pattern(['id' => '\d+']);
        Route::get('pay-orders', 'Pay/index')->middleware(CasbinAuth::class, 'system:pay:list');

        // ---- 短信日志（M4-D，只读；perm: system:sms:log:list）----
        Route::get('sms-logs/:id', 'SmsLog/read')->middleware(CasbinAuth::class, 'system:sms:log:list')->pattern(['id' => '\d+']);
        Route::get('sms-logs', 'SmsLog/index')->middleware(CasbinAuth::class, 'system:sms:log:list');

        // ---- 短信模板（M4-D D-2，bx:make 生成路由片段并入；perm: system:sms:template:*）----
        Route::put('sms-templates/:id/status', 'SmsTemplate/status')->middleware(CasbinAuth::class, 'system:sms:template:update')->pattern(['id' => '\d+']);
        Route::get('sms-templates/:id', 'SmsTemplate/read')->middleware(CasbinAuth::class, 'system:sms:template:list')->pattern(['id' => '\d+']);
        Route::put('sms-templates/:id', 'SmsTemplate/update')->middleware(CasbinAuth::class, 'system:sms:template:update')->pattern(['id' => '\d+']);
        Route::delete('sms-templates/:id', 'SmsTemplate/delete')->middleware(CasbinAuth::class, 'system:sms:template:delete')->pattern(['id' => '\d+']);
        Route::get('sms-templates', 'SmsTemplate/index')->middleware(CasbinAuth::class, 'system:sms:template:list');
        Route::post('sms-templates', 'SmsTemplate/save')->middleware(CasbinAuth::class, 'system:sms:template:create');

        // ---- 系统公告（M4-D D-2，bx:make 生成路由片段并入；perm: system:notice:*）----
        Route::put('notices/:id/status', 'Notice/status')->middleware(CasbinAuth::class, 'system:notice:update')->pattern(['id' => '\d+']);
        Route::get('notices/:id', 'Notice/read')->middleware(CasbinAuth::class, 'system:notice:list')->pattern(['id' => '\d+']);
        Route::put('notices/:id', 'Notice/update')->middleware(CasbinAuth::class, 'system:notice:update')->pattern(['id' => '\d+']);
        Route::delete('notices/:id', 'Notice/delete')->middleware(CasbinAuth::class, 'system:notice:delete')->pattern(['id' => '\d+']);
        Route::get('notices', 'Notice/index')->middleware(CasbinAuth::class, 'system:notice:list');
        Route::post('notices', 'Notice/save')->middleware(CasbinAuth::class, 'system:notice:create');
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
        // M4-D 短信探针：配置就绪态 + 双渠道签名构造样例（需登录）
        Route::get('_sms_probe', 'Probe/sms')->middleware(JwtAuth::class);
        // M5-A C 端登录探针：为 user_id 直签 api 双令牌验证令牌闭环（需登录，不依赖微信）
        Route::post('_api_login_probe', 'Probe/apiLogin')->middleware(JwtAuth::class);
    }
});

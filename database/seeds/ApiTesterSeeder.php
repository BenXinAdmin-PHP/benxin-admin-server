<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — M5-A C 端令牌闭环验证样例（C 端测试用户）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M5-A 验证样例（幂等）：C 端测试用户（mobile 占位、status=1），供
 * _api_login_probe 直签 api 双令牌、验证 api JwtAuth 放行 / refresh / logout 闭环。
 * 真实登录即注册（openid+mobile 缺一不可）留 M5-B；本用户仅 APP_DEBUG 联调用。
 * 如需清理：删 bx_user 中 mobile='19900000000' 行。
 */
class ApiTesterSeeder extends Seeder
{
    public function run(): void
    {
        // 生产守门：仅 APP_DEBUG=true 开发态播种测试用户，避免 seed:run 全跑时污染生产
        if (!app()->isDebug()) {
            echo "[ApiTesterSeeder] 跳过（仅开发态 APP_DEBUG=true 播种测试用户）。\n";
            return;
        }

        $now    = date('Y-m-d H:i:s');
        $mobile = '19900000000';

        $userId = (int) Db::name('user')
            ->where(['tenant_id' => 0, 'mobile' => $mobile])
            ->value('id'); // withTrashed 不需要：唯一含软删，此处仅取存活/任意行做幂等判断

        if ($userId === 0) {
            $userId = (int) Db::name('user')->insertGetId([
                'tenant_id'  => 0,
                'mobile'     => $mobile,
                'nickname'   => 'C端测试用户',
                'avatar'     => '',
                'gender'     => 0,
                'unionid'    => '',
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "[ApiTesterSeeder] C 端测试用户 id={$userId}（mobile={$mobile}）就位。\n";
    }
}

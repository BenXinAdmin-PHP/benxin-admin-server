<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   C 端用户 — GET /api/v1/user/profile（当前登录用户信息）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-14 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\model\User as UserModel;
use think\Response;

/**
 * C 端当前用户信息（M5-C，挂 api JwtAuth）。我的页登录态消费。
 * 字段精简：不外露 openid/unionid/tenant_id/deleted_at（本表无 create_by）。
 * mobile 为本人数据返回完整（如需脱敏由 PM 后续定）。
 */
class User extends BxController
{
    /**
     * 当前登录用户信息。GET /api/v1/user/profile（需登录）
     */
    public function profile(): Response
    {
        /** @var UserModel $user JwtAuth 已注入并校验 status=1 */
        $user = $this->request->userInfo;

        return $this->success([
            'id'            => (int) $user->id,
            'nickname'      => (string) $user->nickname,
            'avatar'        => (string) $user->avatar,
            'gender'        => (int) $user->gender,
            'mobile'        => (string) $user->mobile,
            'last_login_at' => $user->last_login_at,
        ]);
    }
}

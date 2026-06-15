<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — C 端登录即注册（openid+mobile 缺一不可 / unionid 打通）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 22:50:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\library\ErrorCode;
use app\common\model\User;
use app\common\model\UserOauth;
use think\facade\Db;

/**
 * C 端登录即注册核心（M5-B，ADR-16/17）。
 *
 * 定位四级优先级（D3，全程事务包裹跨 bx_user/bx_user_oauth 写）：
 *   ① (platform,openid) 命中 oauth → 老用户静默登录（忽略入参 mobile）。
 *   ② openid 未命中 + unionid 非空 → 按 unionid 找已注册 user，补建当前 platform 关联（两端打通同一 user）。
 *   ③ 仍未命中 + 同手机号正常 user（另一端已注册、无 unionid 打通）→ 复用该 user + 补建关联（同手机号即同人）。
 *   ④ 全新 → 建 bx_user + bx_user_oauth。
 *
 * 安全/约束：
 * - mobile (tenant_id,mobile) 唯一含软删：命中软删行直接 150002，不静默新建撞唯一键（§5.1 方案 A，D4）。
 * - status=0 / 关联悬挂（user 被删）→ 150002。
 * - 新建 user 不写 create_by/create_dept（bx_user 无该列，BxModel 钩子本就跳过），C 端无 Casbin。
 * - oauth 表无软删（think\Model）；user 软删用 BxModel 默认作用域，withTrashed 仅用于 mobile 唯一性前置检测。
 */
class UserAuthService extends BxService
{
    /** 支持的登录平台（mini 小程序 / mp 公众号；预留 work/app） */
    private const PLATFORMS = ['mini', 'mp'];

    /**
     * 登录即注册：定位或新建 user，返回登录主体（令牌签发由调用方走 BxJwt::issueForApi）。
     *
     * @param string      $platform mini / mp
     * @param string      $openid   该平台 openid（静默授权所得）
     * @param string|null $mobile   手机号（新用户必备；老用户路径可空）
     * @param string|null $unionid  微信开放平台 unionid（非空才走两端打通）
     *
     * @throws BusinessException 150001 编排前置由 Controller 判 / 150002 禁用·软删 / 150099 通用
     */
    public function loginOrRegister(string $platform, string $openid, ?string $mobile, ?string $unionid): User
    {
        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new BusinessException('不支持的登录平台', ErrorCode::LOGIN_FAILED);
        }
        $openid = trim($openid);
        if ($openid === '') {
            throw new BusinessException('缺少 openid', ErrorCode::LOGIN_FAILED);
        }
        $mobile  = $mobile !== null ? trim($mobile) : null;
        $unionid = $unionid !== null ? trim($unionid) : null;
        if ($unionid === '') {
            $unionid = null;
        }

        return Db::transaction(function () use ($platform, $openid, $mobile, $unionid): User {
            // ① openid 命中 → 老用户静默登录（忽略入参 mobile）
            $oauth = $this->findOauth($platform, $openid);
            if ($oauth !== null) {
                $user = User::find((int) $oauth->user_id);
                if ($user === null) {
                    // 关联悬挂（user 被删/软删）→ 视为已禁用或注销
                    throw new BusinessException('', ErrorCode::LOGIN_DISABLED);
                }
                $this->assertActive($user);

                return $this->touchLastLogin($user);
            }

            // ② unionid 非空 → 跨端打通已注册 user，补建当前 platform 关联
            if ($unionid !== null) {
                $user = $this->findUserByUnionid($unionid);
                if ($user !== null) {
                    $this->assertActive($user);
                    $this->ensureOauth((int) $user->id, $platform, $openid, $unionid);

                    return $this->touchLastLogin($user);
                }
            }

            // ③/④ 新用户落地：要求 mobile 非空（为空属上层编排错误 → 150099）
            if ($mobile === null || $mobile === '') {
                throw new BusinessException('', ErrorCode::LOGIN_FAILED);
            }

            // mobile 唯一含软删前置检测（§5.1）
            $existing = $this->findUserByMobileWithTrashed($mobile);
            if ($existing !== null) {
                // 命中软删行 → 手机号不可复用（D4），不静默新建撞唯一键
                if ($this->isTrashed($existing)) {
                    throw new BusinessException('', ErrorCode::LOGIN_DISABLED);
                }
                // 同手机号正常 user（另一端已注册、无 unionid 打通）→ 复用 + 补建当前 platform 关联
                $this->assertActive($existing);
                if ($unionid !== null && (string) $existing->unionid === '') {
                    $existing->unionid = $unionid; // 补写打通维度，后续两端可经 unionid 命中
                    $existing->save();
                }
                $this->ensureOauth((int) $existing->id, $platform, $openid, $unionid);

                return $this->touchLastLogin($existing);
            }

            // 全新用户：建 user + oauth
            $user = User::create([
                'tenant_id'     => User::currentTenantId(),
                'mobile'        => $mobile,
                'nickname'      => $this->defaultNickname($mobile),
                'avatar'        => '',
                'gender'        => 0,
                'unionid'       => $unionid ?? '',
                'status'        => 1,
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);
            $this->ensureOauth((int) $user->id, $platform, $openid, $unionid);

            return $user;
        });
    }

    /**
     * 该 (platform, openid) 是否已注册——供 Controller 在调微信换手机号/校验验证码前
     * 判断是否走老用户静默登录（D1：未注册才要求补手机号，返回 150001 引导）。
     */
    public function isRegistered(string $platform, string $openid): bool
    {
        return $this->findOauth($platform, trim($openid)) !== null;
    }

    // ------------------------------------------------------------------
    // 定位 / 写入私有方法
    // ------------------------------------------------------------------

    /**
     * 按 (platform, openid) 查关联（oauth 表无软删，直查）。
     */
    private function findOauth(string $platform, string $openid): ?UserOauth
    {
        return UserOauth::where('platform', $platform)->where('openid', $openid)->find();
    }

    /**
     * 按 unionid 查已注册的正常 user（BxModel 默认排除软删 + 租户作用域）。
     */
    private function findUserByUnionid(string $unionid): ?User
    {
        return User::where('unionid', $unionid)->find();
    }

    /**
     * 按 (tenant_id, mobile) 查 user（含软删行）——仅用于唯一性前置检测（D4）。
     */
    private function findUserByMobileWithTrashed(string $mobile): ?User
    {
        return User::withTrashed()
            ->where('tenant_id', User::currentTenantId())
            ->where('mobile', $mobile)
            ->find();
    }

    /**
     * 幂等补建 platform 关联；已存在则按需补 unionid（理论上 openid 命中已在 ① 处理）。
     */
    private function ensureOauth(int $userId, string $platform, string $openid, ?string $unionid): void
    {
        $exists = $this->findOauth($platform, $openid);
        if ($exists !== null) {
            if ($unionid !== null && (string) $exists->unionid === '') {
                $exists->unionid = $unionid;
                $exists->save();
            }

            return;
        }

        UserOauth::create([
            'user_id'  => $userId,
            'platform' => $platform,
            'openid'   => $openid,
            'unionid'  => $unionid ?? '',
        ]);
    }

    /**
     * 更新登录痕迹。
     */
    private function touchLastLogin(User $user): User
    {
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        return $user;
    }

    /**
     * 状态校验：非正常（status≠1）→ 150002。
     */
    private function assertActive(User $user): void
    {
        if ((int) $user->status !== 1) {
            throw new BusinessException('', ErrorCode::LOGIN_DISABLED);
        }
    }

    /**
     * 是否软删行（withTrashed 取出后判 deleted_at；hidden 仅影响序列化不影响 getData）。
     */
    private function isTrashed(User $user): bool
    {
        return !empty($user->getData('deleted_at'));
    }

    /**
     * 默认昵称：用户 + 手机号后 4 位（避免空昵称，不外露完整号码）。
     */
    private function defaultNickname(string $mobile): string
    {
        $suffix = strlen($mobile) >= 4 ? substr($mobile, -4) : $mobile;

        return '用户' . $suffix;
    }
}

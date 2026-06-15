<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   Casbin 服务 — Enforcer 单例工厂 + enforce/授权封装
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service;

use app\common\library\BxCasbinAdapter;
use Casbin\Enforcer;
use Throwable;

/**
 * Casbin 鉴权服务（单例）：用 rbac_model.conf + BxCasbinAdapter 构建 Enforcer，
 * 收口 enforce 与（供 M1-C 角色授权用的）增删策略 / reload。
 *
 * 约定（任务书 §2）：dom = tenant_id（单租户恒 0）；普通策略 act 统一 'do'，
 * 超管策略 act = '*'。中间件与未来 CRUD 只调本服务，不直接 new Enforcer。
 */
class CasbinService
{
    /** 普通策略统一动作占位 */
    public const ACTION = 'do';

    protected static ?Enforcer $enforcer = null;

    /**
     * 取 Enforcer 单例（首次构建时由适配器自动 loadPolicy）。
     */
    public static function enforcer(): Enforcer
    {
        if (self::$enforcer === null) {
            $modelPath = root_path() . 'config' . DIRECTORY_SEPARATOR . 'casbin' . DIRECTORY_SEPARATOR . 'rbac_model.conf';
            self::$enforcer = new Enforcer($modelPath, new BxCasbinAdapter());
        }

        return self::$enforcer;
    }

    /**
     * 鉴权：角色 $sub 在域 $dom 是否可对资源 $obj 执行 $act。
     *
     * @param string     $sub 角色 code
     * @param int|string $dom 租户域（tenant_id）
     * @param string     $obj 资源（perms 串）
     * @param string     $act 动作，默认 do
     */
    public static function enforce(string $sub, int|string $dom, string $obj, string $act = self::ACTION): bool
    {
        try {
            return (bool) self::enforcer()->enforce($sub, (string) $dom, $obj, $act);
        } catch (Throwable) {
            // 鉴权异常一律视为拒绝（不泄露策略细节）
            return false;
        }
    }

    /**
     * 角色任一命中即放行：对角色 code 列表逐个 enforce。
     *
     * @param array<int,string> $roleCodes
     */
    public static function enforceAny(array $roleCodes, int|string $dom, string $obj, string $act = self::ACTION): bool
    {
        foreach ($roleCodes as $code) {
            if (self::enforce((string) $code, $dom, $obj, $act)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 为角色新增一条 p 策略（M1-C 角色授权调用）。autoSave 落 bx_casbin_rule。
     */
    public static function addPolicyForRole(string $roleCode, int|string $dom, string $perm, string $act = self::ACTION): bool
    {
        return (bool) self::enforcer()->addPolicy($roleCode, (string) $dom, $perm, $act);
    }

    /**
     * 删除角色的一条 p 策略。
     */
    public static function removePolicyForRole(string $roleCode, int|string $dom, string $perm, string $act = self::ACTION): bool
    {
        return (bool) self::enforcer()->removePolicy($roleCode, (string) $dom, $perm, $act);
    }

    /**
     * 清空某角色在某域下的全部 p 策略（删除角色 / 覆盖式重新分配前调用）。
     * 按字段 v0(sub)=roleCode、v1(dom)=dom 过滤删除。
     */
    public static function removeAllForRole(string $roleCode, int|string $dom): bool
    {
        return (bool) self::enforcer()->removeFilteredPolicy(0, $roleCode, (string) $dom);
    }

    /**
     * 删除引用了某 perm 的全部 p 策略（删除菜单按钮时清理悬空授权）。
     * 按字段 v2(obj)=perm 过滤删除（跨角色、跨域）。
     */
    public static function removePolicyByPerm(string $perm): bool
    {
        return (bool) self::enforcer()->removeFilteredPolicy(2, $perm);
    }

    /**
     * 重新从存储装载策略（增删策略后或测试中调用）。
     */
    public static function reload(): void
    {
        self::enforcer()->loadPolicy();
    }
}

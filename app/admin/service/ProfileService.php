<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 当前管理员聚合（用户/角色/菜单树/perms）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\model\Admin;
use app\common\model\Menu;
use app\common\model\Role;
use think\facade\Db;

/**
 * 个人信息聚合：把登录管理员的可见菜单树 + 按钮权限点聚合出来，
 * 作为前端动态路由（menus）与按钮级鉴权（perms，与后端 enforce 同源）的契约。
 *
 * - 普通管理员：其全部启用角色 → role_menu → menu 取并集；
 *   menus 取目录/菜单(type∈1,2)且 status 启用、visible 可见并补全祖先建树；
 *   perms 取并集中 status 启用、非空 perms 去重（含按钮）。
 * - 超管（含 super_admin 角色）：直接给全量启用菜单树 + 全量 perms（与通配策略一致）。
 * - 菜单 status 停用 → 既不进 menus，也不计入 perms。
 */
class ProfileService extends BxService
{
    public const SUPER_ROLE = 'super_admin';

    /**
     * 构建聚合结构。
     *
     * @return array{user:array,roles:array<int,string>,menus:array,perms:array<int,string>}
     */
    public function build(Admin $admin): array
    {
        $roles   = $admin->roleCodes();
        $isSuper = in_array(self::SUPER_ROLE, $roles, true);

        $enabledMenus = $isSuper
            ? Menu::where('status', 1)->order('sort', 'asc')->order('id', 'asc')->select()->toArray()
            : $this->grantedMenus($admin);

        $perms = $this->collectPerms($enabledMenus);
        $menus = $this->buildVisibleTree($enabledMenus);

        return [
            'user' => [
                'id'       => (int) $admin->id,
                'username' => $admin->username,
                'nickname' => $admin->nickname,
                'avatar'   => $admin->avatar,
                'mobile'   => $admin->mobile,
                'email'    => $admin->email,
                'dept_id'  => (int) $admin->dept_id,
            ],
            'roles' => $roles,
            'menus' => $menus,
            'perms' => $perms,
        ];
    }

    /**
     * 普通管理员被授予的启用菜单（经其启用角色的 role_menu 并集）。
     *
     * @return array<int,array>
     */
    protected function grantedMenus(Admin $admin): array
    {
        // 管理员的角色 → 仅启用角色
        $roleIds = Db::name('admin_role')->where('admin_id', $admin->id)->column('role_id');
        if ($roleIds === []) {
            return [];
        }
        $activeRoleIds = Role::whereIn('id', $roleIds)->where('status', 1)->column('id');
        if ($activeRoleIds === []) {
            return [];
        }

        $menuIds = Db::name('role_menu')->whereIn('role_id', $activeRoleIds)->column('menu_id');
        if ($menuIds === []) {
            return [];
        }

        return Menu::whereIn('id', array_unique($menuIds))
            ->where('status', 1)
            ->order('sort', 'asc')->order('id', 'asc')
            ->select()->toArray();
    }

    /**
     * 从菜单集合取非空 perms 去重（用于按钮级鉴权）。
     *
     * @param array<int,array> $menus
     * @return array<int,string>
     */
    protected function collectPerms(array $menus): array
    {
        $perms = [];
        foreach ($menus as $m) {
            $p = trim((string) ($m['perms'] ?? ''));
            if ($p !== '') {
                $perms[$p] = true;
            }
        }

        return array_keys($perms);
    }

    /**
     * 由给定菜单集合构建可见目录/菜单树，并补全缺失的可见祖先以保证树连通。
     *
     * @param array<int,array> $menus
     * @return array<int,array>
     */
    protected function buildVisibleTree(array $menus): array
    {
        // 目标节点：目录/菜单 + 启用 + 可见
        $picked = [];
        foreach ($menus as $m) {
            if (in_array((int) $m['type'], [1, 2], true) && (int) $m['status'] === 1 && (int) $m['visible'] === 1) {
                $picked[(int) $m['id']] = $m;
            }
        }
        if ($picked === []) {
            return [];
        }

        // 可见目录/菜单全集，用于按 parent_id 上溯补全祖先
        $pool = [];
        foreach (Menu::whereIn('type', [1, 2])->where('status', 1)->where('visible', 1)->select()->toArray() as $m) {
            $pool[(int) $m['id']] = $m;
        }

        $selected = [];
        foreach ($picked as $id => $node) {
            $cursor = $id;
            // 防御性深度上限，避免脏数据导致死循环
            for ($i = 0; $i < 100 && $cursor !== 0 && isset($pool[$cursor]); $i++) {
                $selected[$cursor] = $pool[$cursor];
                $cursor            = (int) $pool[$cursor]['parent_id'];
            }
        }

        $list = array_values($selected);
        usort($list, static function ($a, $b) {
            return [$a['sort'], $a['id']] <=> [$b['sort'], $b['id']];
        });

        return (new MenuService($this->app))->buildTree($list, 0);
    }
}

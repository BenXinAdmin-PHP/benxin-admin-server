<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 角色 CRUD + 分配菜单（同步 Casbin p 策略）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 15:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Menu;
use app\common\model\Role;
use app\common\service\CasbinService;
use think\facade\Db;

/**
 * 角色服务：CRUD + 覆盖式分配菜单并同步 Casbin。
 * super_admin 走通配策略，受保护：不可删/停/改 code/分配菜单。
 */
class RoleService extends BxService
{
    protected const FILLABLE   = ['name', 'code', 'sort', 'status', 'data_scope', 'remark'];
    public const SUPER_CODE     = 'super_admin';

    /**
     * 分页列表（关键词 name/code 模糊；status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Role::order('sort', 'asc')->order('id', 'asc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', "%{$keyword}%")->whereOr('code', 'like', "%{$keyword}%");
            });
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 详情（含已分配 menu_ids）。
     *
     * @return array<string,mixed>
     */
    public function detail(int $id): array
    {
        $role          = Role::findOrFail($id)->toArray();
        $role['menu_ids'] = $this->menuIds($id);

        return $role;
    }

    /**
     * 该角色已分配菜单 id 列表。
     *
     * @return array<int,int>
     */
    public function menuIds(int $id): array
    {
        return array_map('intval', Db::name('role_menu')->where('role_id', $id)->column('menu_id'));
    }

    /**
     * 新增角色。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): Role
    {
        $data = $this->fillable($data);
        $this->assertCodeUnique((string) $data['code'], null);

        $data['tenant_id'] = Role::currentTenantId();

        return Role::create($data);
    }

    /**
     * 更新角色（选择性字段）。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Role
    {
        $role = Role::findOrFail($id);
        $data = $this->fillable($data);

        // super_admin 保护：不可改 code、不可停用
        if ($role->code === self::SUPER_CODE) {
            if (array_key_exists('code', $data) && $data['code'] !== self::SUPER_CODE) {
                throw new BusinessException('超级管理员角色标识不可修改');
            }
            if (array_key_exists('status', $data) && (int) $data['status'] !== 1) {
                throw new BusinessException('超级管理员角色不可停用');
            }
        }

        if (array_key_exists('code', $data)) {
            $this->assertCodeUnique((string) $data['code'], $id);
        }

        $role->save($data);

        return $role;
    }

    /**
     * 启停。
     */
    public function setStatus(int $id, int $status): Role
    {
        $role = Role::findOrFail($id);
        if ($role->code === self::SUPER_CODE && $status !== 1) {
            throw new BusinessException('超级管理员角色不可停用');
        }
        $role->status = $status;
        $role->save();

        return $role;
    }

    /**
     * 删除角色：super_admin 拒绝；仍有管理员绑定拒绝；
     * 否则事务软删 + 清 role_menu + 清该角色 casbin 策略 + reload。
     */
    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);
        if ($role->code === self::SUPER_CODE) {
            throw new BusinessException('超级管理员角色不可删除');
        }

        $bound = Db::name('admin_role')->where('role_id', $id)->count();
        if ($bound > 0) {
            throw new BusinessException("仍有 {$bound} 个管理员绑定该角色，请先解绑");
        }

        $code = (string) $role->code;
        $dom  = (int) $role->tenant_id;

        try {
            Db::transaction(function () use ($id, $role, $code, $dom) {
                Db::name('role_menu')->where('role_id', $id)->delete();
                CasbinService::removeAllForRole($code, $dom);
                $role->delete();
            });
        } finally {
            // 无论提交/回滚，重载使内存策略与库一致
            CasbinService::reload();
        }
    }

    /**
     * 覆盖式分配菜单 + 同步 Casbin（★ 核心授权链路，事务）。
     *
     * @param array<int,int> $menuIds
     */
    public function assignMenus(int $id, array $menuIds): void
    {
        $role = Role::findOrFail($id);
        if ($role->code === self::SUPER_CODE) {
            throw new BusinessException('超级管理员权限由通配策略承载，无需分配菜单');
        }

        // 仅保留真实存在的菜单 id
        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
        $valid   = $menuIds === []
            ? []
            : array_map('intval', Menu::whereIn('id', $menuIds)->column('id'));
        if (count($valid) !== count($menuIds)) {
            throw new BusinessException('提交的菜单中存在不存在的项');
        }

        // 取选中菜单的非空 perms（按钮/菜单级），去重
        $perms = $valid === []
            ? []
            : array_values(array_unique(array_filter(
                Menu::whereIn('id', $valid)->column('perms'),
                static fn ($p) => trim((string) $p) !== '',
            )));

        $code = (string) $role->code;
        $dom  = (int) $role->tenant_id;

        try {
            Db::transaction(function () use ($id, $valid, $perms, $code, $dom) {
                // 覆盖写 role_menu
                Db::name('role_menu')->where('role_id', $id)->delete();
                if ($valid !== []) {
                    $now  = date('Y-m-d H:i:s');
                    $rows = array_map(static fn ($mid) => [
                        'role_id'    => $id,
                        'menu_id'    => $mid,
                        'created_at' => $now,
                    ], $valid);
                    Db::name('role_menu')->insertAll($rows);
                }

                // 覆盖同步 casbin：先清该角色旧策略，再按新 perms 重建
                CasbinService::removeAllForRole($code, $dom);
                foreach ($perms as $perm) {
                    CasbinService::addPolicyForRole($code, $dom, $perm);
                }
            });
        } finally {
            CasbinService::reload();
        }
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }

    /**
     * code 全局唯一校验。含软删记录（withTrashed）：DB 唯一索引 uk_tenant_code 不区分
     * 软删，故已删除的 code 仍占位、不可复用（复用需 M2 回收站/彻底删除），
     * 在此提前拦为 422，避免落库触发完整性异常 500。
     */
    protected function assertCodeUnique(string $code, ?int $exceptId): void
    {
        $query = Role::withTrashed()->where('code', $code);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('角色标识已存在：' . $code);
        }
    }
}

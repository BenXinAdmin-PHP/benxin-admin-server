<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 管理员 CRUD（角色/岗位关联 + 超管护栏）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Admin;
use app\common\model\Post;
use app\common\model\Role;
use think\facade\Db;

/**
 * 管理员服务：CRUD + 角色/岗位关联（事务）+ 超管护栏。
 * 输出统一不含 password（Admin 模型 hidden 兜底）。
 */
class AdminService extends BxService
{
    protected const FILLABLE   = ['username', 'nickname', 'avatar', 'mobile', 'email', 'dept_id', 'status', 'remark'];
    public const SUPER_USERNAME = 'admin';
    public const SUPER_ROLE     = 'super_admin';

    /**
     * 分页列表（keyword: username/nickname/mobile；dept_id / status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Admin::order('id', 'desc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('username', "%{$keyword}%")
                    ->whereOr('nickname', 'like', "%{$keyword}%")
                    ->whereOr('mobile', 'like', "%{$keyword}%");
            });
        }
        if (($filters['dept_id'] ?? '') !== '') {
            $query->where('dept_id', (int) $filters['dept_id']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        // 模型查询 → toArray 触发 hidden（password 不外泄）
        $list = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 详情（含 role_ids / post_ids，不含 password）。
     *
     * @return array<string,mixed>
     */
    public function detail(int $id): array
    {
        $admin             = Admin::findOrFail($id)->toArray();
        $admin['role_ids'] = $this->roleIds($id);
        $admin['post_ids'] = $this->postIds($id);

        return $admin;
    }

    /**
     * 新增管理员（密码 Argon2id；写 role/post 关联，事务）。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): Admin
    {
        $base = $this->fillable($data);
        $this->assertUsernameUnique((string) ($base['username'] ?? ''), null);

        $roleIds = $this->normalizeIds($data['role_ids'] ?? []);
        $postIds = $this->normalizeIds($data['post_ids'] ?? []);
        $this->assertRolesExist($roleIds);
        $this->assertPostsExist($postIds);

        $base['password']  = password_hash((string) $data['password'], PASSWORD_ARGON2ID);
        $base['tenant_id'] = Admin::currentTenantId();

        return Db::transaction(function () use ($base, $roleIds, $postIds) {
            $admin = Admin::create($base);
            $this->syncRoles((int) $admin->id, $roleIds);
            $this->syncPosts((int) $admin->id, $postIds);

            return $admin;
        });
    }

    /**
     * 更新管理员（选择性字段；密码不在此改；可重设 role_ids/post_ids）。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $actingId): Admin
    {
        $admin = Admin::findOrFail($id);
        $base  = $this->fillable($data);

        if (array_key_exists('username', $base)) {
            $this->assertUsernameUnique((string) $base['username'], $id);
        }

        // 停用保护（不能停用超管 / 自己）
        if (array_key_exists('status', $base) && (int) $base['status'] !== 1) {
            $this->assertNotSuper($id, '超级管理员不可停用');
            $this->assertNotSelf($id, $actingId, '不可停用当前登录账号');
        }

        // 角色重设：禁止移除超管的 super_admin 角色
        $hasRoleIds = array_key_exists('role_ids', $data);
        $roleIds    = $this->normalizeIds($data['role_ids'] ?? []);
        if ($hasRoleIds) {
            $this->assertRolesExist($roleIds);
            if ($this->isSuperAdmin($id) && !$this->idsContainSuperRole($roleIds)) {
                throw new BusinessException('不可移除超级管理员的 super_admin 角色');
            }
        }
        $hasPostIds = array_key_exists('post_ids', $data);
        $postIds    = $this->normalizeIds($data['post_ids'] ?? []);
        if ($hasPostIds) {
            $this->assertPostsExist($postIds);
        }

        return Db::transaction(function () use ($admin, $base, $hasRoleIds, $roleIds, $hasPostIds, $postIds) {
            if ($base !== []) {
                $admin->save($base);
            }
            if ($hasRoleIds) {
                $this->syncRoles((int) $admin->id, $roleIds);
            }
            if ($hasPostIds) {
                $this->syncPosts((int) $admin->id, $postIds);
            }

            return $admin;
        });
    }

    /**
     * 删除（软删 + 清角色/岗位关联）。护栏：不可删超管 / 自己。
     */
    public function delete(int $id, int $actingId): void
    {
        $admin = Admin::findOrFail($id);
        $this->assertNotSuper($id, '超级管理员不可删除');
        $this->assertNotSelf($id, $actingId, '不可删除当前登录账号');

        Db::transaction(function () use ($id, $admin) {
            Db::name('admin_role')->where('admin_id', $id)->delete();
            Db::name('admin_post')->where('admin_id', $id)->delete();
            $admin->delete();
        });
    }

    /**
     * 启停。护栏：不可停用超管 / 自己。
     */
    public function setStatus(int $id, int $status, int $actingId): Admin
    {
        $admin = Admin::findOrFail($id);
        if ($status !== 1) {
            $this->assertNotSuper($id, '超级管理员不可停用');
            $this->assertNotSelf($id, $actingId, '不可停用当前登录账号');
        }
        $admin->status = $status;
        $admin->save();

        return $admin;
    }

    /**
     * 管理员重置他人密码（Argon2id）。
     */
    public function resetPassword(int $id, string $password): void
    {
        $admin           = Admin::findOrFail($id);
        $admin->password = password_hash($password, PASSWORD_ARGON2ID);
        $admin->save();
    }

    // ------------------------------------------------------------------
    // 关联与护栏
    // ------------------------------------------------------------------

    /** @return array<int,int> */
    public function roleIds(int $adminId): array
    {
        return array_map('intval', Db::name('admin_role')->where('admin_id', $adminId)->column('role_id'));
    }

    /** @return array<int,int> */
    public function postIds(int $adminId): array
    {
        return array_map('intval', Db::name('admin_post')->where('admin_id', $adminId)->column('post_id'));
    }

    /**
     * 覆盖式写管理员-角色关联。
     *
     * @param array<int,int> $roleIds
     */
    protected function syncRoles(int $adminId, array $roleIds): void
    {
        Db::name('admin_role')->where('admin_id', $adminId)->delete();
        if ($roleIds !== []) {
            $now  = date('Y-m-d H:i:s');
            $rows = array_map(static fn ($rid) => ['admin_id' => $adminId, 'role_id' => $rid, 'created_at' => $now], $roleIds);
            Db::name('admin_role')->insertAll($rows);
        }
    }

    /**
     * 覆盖式写管理员-岗位关联。
     *
     * @param array<int,int> $postIds
     */
    protected function syncPosts(int $adminId, array $postIds): void
    {
        Db::name('admin_post')->where('admin_id', $adminId)->delete();
        if ($postIds !== []) {
            $now  = date('Y-m-d H:i:s');
            $rows = array_map(static fn ($pid) => ['admin_id' => $adminId, 'post_id' => $pid, 'created_at' => $now], $postIds);
            Db::name('admin_post')->insertAll($rows);
        }
    }

    /**
     * 是否超管：内置 admin 账号，或拥有 super_admin 角色。
     */
    public function isSuperAdmin(int $adminId): bool
    {
        $username = Admin::where('id', $adminId)->value('username');
        if ($username === self::SUPER_USERNAME) {
            return true;
        }

        return $this->idsContainSuperRole($this->roleIds($adminId));
    }

    /**
     * 给定角色 id 集合是否包含 super_admin 角色。
     *
     * @param array<int,int> $roleIds
     */
    protected function idsContainSuperRole(array $roleIds): bool
    {
        if ($roleIds === []) {
            return false;
        }
        $superId = (int) Role::where('code', self::SUPER_ROLE)->value('id');

        return $superId > 0 && in_array($superId, $roleIds, true);
    }

    protected function assertNotSuper(int $id, string $message): void
    {
        if ($this->isSuperAdmin($id)) {
            throw new BusinessException($message);
        }
    }

    protected function assertNotSelf(int $id, int $actingId, string $message): void
    {
        if ($id === $actingId) {
            throw new BusinessException($message);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }

    /**
     * username 全局唯一（含 withTrashed，已删账号不可复用，避免落库 500）。
     */
    protected function assertUsernameUnique(string $username, ?int $exceptId): void
    {
        $query = Admin::withTrashed()->where('username', $username);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('账号已存在：' . $username);
        }
    }

    /**
     * @param mixed $ids
     * @return array<int,int>
     */
    protected function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @param array<int,int> $roleIds */
    protected function assertRolesExist(array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }
        $exist = array_map('intval', Role::whereIn('id', $roleIds)->column('id'));
        if (count($exist) !== count($roleIds)) {
            throw new BusinessException('提交的角色中存在不存在的项');
        }
    }

    /** @param array<int,int> $postIds */
    protected function assertPostsExist(array $postIds): void
    {
        if ($postIds === []) {
            return;
        }
        $exist = array_map('intval', Post::whereIn('id', $postIds)->column('id'));
        if (count($exist) !== count($postIds)) {
            throw new BusinessException('提交的岗位中存在不存在的项');
        }
    }
}

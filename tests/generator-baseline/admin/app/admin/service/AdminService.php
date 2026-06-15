<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 管理员 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Admin;

/**
 * 管理员服务：标准 CRUD（生成器复刻 post 母版）。
 * username 唯一含软删（§5.1）。
 */
class AdminService extends BxService
{
    protected const FILLABLE = ['username', 'password', 'nickname', 'avatar', 'mobile', 'email', 'dept_id', 'status', 'last_login_at', 'last_login_ip', 'remark'];

    /**
     * 分页列表（status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Admin::order('id', 'asc');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Admin
    {
        return Admin::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Admin
    {
        $data = $this->fillable($data);
        $this->assertUsernameUnique((string) $data['username'], null);
        $data['tenant_id'] = Admin::currentTenantId();

        return Admin::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Admin
    {
        $admin = Admin::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('username', $data)) {
            $this->assertUsernameUnique((string) $data['username'], $id);
        }

        $admin->save($data);

        return $admin;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $admin = Admin::findOrFail($id);
        $admin->delete();
    }

    public function setStatus(int $id, int $status): Admin
    {
        $admin         = Admin::findOrFail($id);
        $admin->status = $status;
        $admin->save();

        return $admin;
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
     * username 全局唯一（含 withTrashed，已删 username 不可复用，§5.1）。
     */
    protected function assertUsernameUnique(string $username, ?int $exceptId): void
    {
        $query = Admin::withTrashed()->where('username', $username);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('登录账号已存在：' . $username);
        }
    }
}

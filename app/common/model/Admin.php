<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 管理员 bx_admin
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// | @updated   2026-06-15 (M3-G-sweep: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;
use think\facade\Db;
use think\model\relation\BelongsToMany;

/**
 * 管理员模型。
 *
 * @property int    $id
 * @property int    $tenant_id
 * @property string $username
 * @property string $password
 * @property int    $status
 */
class Admin extends BxModel
{
    protected $name = 'admin';

    // 输出隐藏字段（密码绝不外泄；软删字段无需下发）
    protected $hidden = ['deleted_at', 'tenant_id', 'password'];

    // 字段类型转换
    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'dept_id'   => 'integer',
        'status'    => 'integer',
    ];

    /**
     * 管理员 ↔ 角色（多对多，中间表 bx_admin_role）。
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role', 'role_id', 'admin_id');
    }

    /**
     * 取该管理员的角色 code 列表（仅启用、未软删的角色）。
     * 分两步避免中间表前缀/联表解析歧义：先取 role_id，再走 Role 模型
     *（自动应用 bx_ 前缀与软删除作用域）。
     *
     * @return array<int,string>
     */
    public function roleCodes(): array
    {
        $roleIds = Db::name('admin_role')->where('admin_id', $this->id)->column('role_id');
        if ($roleIds === []) {
            return [];
        }

        return Role::whereIn('id', $roleIds)->where('status', 1)->column('code');
    }
}

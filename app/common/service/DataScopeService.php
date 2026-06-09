<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 数据权限范围解析（ADR-9，多角色取最宽）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 18:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service;

use app\admin\service\DeptService;
use app\common\base\BxService;
use app\common\library\DataScope;
use app\common\model\Admin;
use app\common\model\Role;
use think\db\BaseQuery;
use think\facade\Db;

/**
 * 数据权限解析（ADR-9）。计算某管理员在其全部启用角色下的可见范围，
 * 多角色**取最宽合并**：任一为“全部”则全部；否则各角色 dept 可见集合求并集，
 * 全为“仅本人”才限本人。结果交给 applyTo() 拼成查询条件。
 */
class DataScopeService extends BxService
{
    /**
     * 解析可见范围。
     *
     * @return array{all:bool,deptIds:array<int,int>,self:bool}
     */
    public function resolve(Admin $admin): array
    {
        $result = ['all' => false, 'deptIds' => [], 'self' => false];

        $roleIds = Db::name('admin_role')->where('admin_id', $admin->id)->column('role_id');
        if ($roleIds === []) {
            return $result; // 无角色 → 看不到任何数据
        }

        /** @var array<int,array{id:int,data_scope:int}> $roles */
        $roles = Role::whereIn('id', $roleIds)->where('status', 1)->field('id,data_scope')->select()->toArray();
        if ($roles === []) {
            return $result;
        }

        $deptIds  = [];
        $selfOnly = false;
        $deptSvc  = new DeptService($this->app);
        $myDept   = (int) $admin->dept_id;

        foreach ($roles as $role) {
            switch ((int) $role['data_scope']) {
                case DataScope::ALL:
                    return ['all' => true, 'deptIds' => [], 'self' => false];
                case DataScope::DEPT:
                    if ($myDept > 0) {
                        $deptIds[] = $myDept;
                    }
                    break;
                case DataScope::DEPT_AND_BELOW:
                    $deptIds = array_merge($deptIds, $deptSvc->descendantIds($myDept));
                    break;
                case DataScope::CUSTOM:
                    $deptIds = array_merge($deptIds, array_map(
                        'intval',
                        Db::name('role_dept')->where('role_id', (int) $role['id'])->column('dept_id'),
                    ));
                    break;
                case DataScope::SELF:
                    $selfOnly = true;
                    break;
            }
        }

        return [
            'all'     => false,
            'deptIds' => array_values(array_unique(array_map('intval', $deptIds))),
            'self'    => $selfOnly,
        ];
    }

    /**
     * 把可见范围拼到查询：dept 维度 `deptField IN (集合)`、本人维度 `ownerField = adminId`，二者 OR。
     * “全部”不加条件；既无 dept 集合又非本人 → 不可见（1=0）。
     *
     * @param BaseQuery $query
     */
    public function applyTo(BaseQuery $query, Admin $admin, string $deptField, string $ownerField): void
    {
        $scope = $this->resolve($admin);
        if ($scope['all']) {
            return;
        }

        $deptIds = $scope['deptIds'];
        $self    = $scope['self'];

        if ($deptIds === [] && !$self) {
            $query->whereRaw('1 = 0');
            return;
        }

        $adminId = (int) $admin->id;
        $query->where(function ($q) use ($deptIds, $self, $deptField, $ownerField, $adminId) {
            $applied = false;
            if ($deptIds !== []) {
                $q->whereIn($deptField, $deptIds);
                $applied = true;
            }
            if ($self) {
                $applied ? $q->whereOr($ownerField, $adminId) : $q->where($ownerField, $adminId);
            }
        });
    }
}

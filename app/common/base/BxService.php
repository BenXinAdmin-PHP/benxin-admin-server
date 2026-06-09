<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务层基类 — 业务编排收口 + 数据权限作用域入口
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-09 18:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\base;

use app\common\model\Admin;
use app\common\service\DataScopeService;
use think\App;
use think\db\BaseQuery;

/**
 * 服务层基类：承载跨模型的业务编排，控制器只调用 Service，不直接写复杂业务。
 */
abstract class BxService
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 数据权限作用域（ADR-9）：模块**按需调用**，把当前管理员的可见范围拼进查询。
     * 业务表通常 dept 维度用 `create_dept`、本人维度用 `create_by`；
     * 核心表 bx_admin 用 `dept_id` / `id`（见 AdminService::list 示范）。
     *
     * @param BaseQuery $query
     */
    protected function applyDataScope(BaseQuery $query, Admin $admin, string $deptField = 'create_dept', string $ownerField = 'create_by'): void
    {
        (new DataScopeService($this->app))->applyTo($query, $admin, $deptField, $ownerField);
    }
}

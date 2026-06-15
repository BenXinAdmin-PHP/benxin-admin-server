<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型基类 — 统一时间戳 / 软删除 / 多租户作用域
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-10 22:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\base;

use think\db\Query;
use think\Model;
use think\model\concern\SoftDelete;
use Throwable;

/**
 * 所有业务模型的基类：
 * - 统一时间字段 created_at / updated_at（datetime）。
 * - 软删除走 deleted_at。
 * - 多租户全局作用域（ADR-1）：单租户（app.multi_tenant=false）下为空操作，
 *   启用多租户后自动按 tenant_id 过滤；钩子已“接通”，无需逐处手写。
 * - 创建归属自动填充（§5.1）：insert 时若表含 create_by/create_dept 列且为登录态，
 *   自动写当前 adminId / dept_id；CLI/seeder/未认证安全跳过。
 */
abstract class BxModel extends Model
{
    use SoftDelete;

    // 统一时间字段
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = 'deleted_at';

    // 时间字段类型（与 config/database.php auto_timestamp 一致）
    protected $autoWriteTimestamp = 'datetime';

    // 软删除默认值（未删除时 deleted_at 为 null）
    protected $defaultSoftDelete = null;

    // 全局作用域：所有查询自动套用租户过滤（单租户为空操作）
    protected $globalScope = ['tenant'];

    /**
     * 当前租户 ID。单租户（ADR-1）恒为 0；多租户启用后在此从上下文解析。
     */
    public static function currentTenantId(): int
    {
        // TODO 多租户：从登录态/域名上下文解析；当前单租户恒 0
        return 0;
    }

    /**
     * 多租户全局作用域。
     * 单租户模式（config('app.multi_tenant') = false）下不加条件；
     * 启用多租户后按当前租户过滤。
     */
    public function scopeTenant(Query $query): void
    {
        if (config('app.multi_tenant')) {
            $query->where('tenant_id', static::currentTenantId());
        }
    }

    /**
     * 创建归属自动填充钩子（§5.1）。
     * insert 时：表含 create_by/create_dept 列且为登录态 → 自动写 adminId / dept_id；
     * 入参已显式给出则不覆盖；CLI/seeder/未认证（无 adminId）安全跳过。
     */
    public static function onBeforeInsert(Model $model): void
    {
        try {
            $request = request();
            $adminId = (int) ($request->adminId ?? 0);
            if ($adminId <= 0) {
                return; // 无登录态（CLI/seeder/未认证）跳过
            }

            $fields = $model->getTableFields();
            $data   = $model->getData();

            if (in_array('create_by', $fields, true) && !array_key_exists('create_by', $data)) {
                $model->create_by = $adminId;
            }
            if (in_array('create_dept', $fields, true) && !array_key_exists('create_dept', $data)) {
                $admin              = $request->adminUser ?? null;
                $model->create_dept = $admin !== null ? (int) $admin->dept_id : 0;
            }
        } catch (Throwable) {
            // 非 HTTP 上下文或解析失败 → 跳过，不影响写入
        }
    }
}

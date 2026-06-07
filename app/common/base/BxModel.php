<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型基类 — 统一时间戳 / 软删除 / 多租户作用域预留
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\base;

use think\db\Query;
use think\Model;
use think\model\concern\SoftDelete;

/**
 * 所有业务模型的基类：
 * - 统一时间字段 created_at / updated_at（datetime）。
 * - 软删除走 deleted_at。
 * - 预留多租户作用域钩子（ADR-1，默认不启用）。
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

    /**
     * 多租户作用域（预留钩子）。
     * 单租户模式（config('app.multi_tenant') = false）下不生效；
     * 启用多租户后，M1 在此按 tenant_id/domain 过滤。
     *
     * @param Query    $query
     * @param int|null $tenantId 为空时取当前租户（M1 从上下文解析），单租户恒为 0
     */
    public function scopeTenant(Query $query, ?int $tenantId = null): void
    {
        if (config('app.multi_tenant')) {
            $query->where('tenant_id', $tenantId ?? 0);
        }
    }
}

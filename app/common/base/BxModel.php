<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型基类 — 统一时间戳 / 软删除 / 多租户作用域
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// | @updated   2026-06-09 14:00:00
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
 * - 多租户全局作用域（ADR-1）：单租户（app.multi_tenant=false）下为空操作，
 *   启用多租户后自动按 tenant_id 过滤；钩子已“接通”，无需逐处手写。
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
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   数据权限范围枚举常量（ADR-9）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 18:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

/**
 * data_scope 范围枚举（bx_role.data_scope）。
 */
class DataScope
{
    public const ALL            = 1; // 全部
    public const DEPT           = 2; // 本部门
    public const DEPT_AND_BELOW = 3; // 本部门及以下
    public const SELF           = 4; // 仅本人
    public const CUSTOM         = 5; // 自定义（取自 bx_role_dept）

    /** 合法值集合（校验用） */
    public const VALUES = [self::ALL, self::DEPT, self::DEPT_AND_BELOW, self::SELF, self::CUSTOM];
}

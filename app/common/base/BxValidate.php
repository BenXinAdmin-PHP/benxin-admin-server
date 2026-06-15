<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器基类 — 校验失败抛 ValidateException（统一 422）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\base;

use think\Validate;

/**
 * 校验器基类：校验失败统一抛 ValidateException，
 * 由 app\common\exception\Handle 收敛为 422xxx 统一信封。
 */
abstract class BxValidate extends Validate
{
    /**
     * 批量校验：false 表示遇到第一个错误即抛出。
     * @var bool
     */
    protected $batch = false;
}

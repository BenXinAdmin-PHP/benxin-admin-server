<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 配置中心（create/update 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 配置中心入参校验。group 唯一（含软删）校验在 ConfigService。
 */
class ConfigValidate extends BxValidate
{
    protected $rule = [
        'name'         => 'max:64',
        'group'        => 'max:64',
        'key'          => 'max:128',
        'value'        => 'max:65535',
        'remark'       => 'max:255',
        'is_sensitive' => 'integer',
        'value_type'   => 'max:16',
        'sort'         => 'integer',
    ];

    protected $message = [];

    public function sceneCreate(): static
    {
        return $this->only(['name', 'group', 'key', 'value', 'remark', 'is_sensitive', 'value_type', 'sort']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['name', 'group', 'key', 'value', 'remark', 'is_sensitive', 'value_type', 'sort']);
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 字典类型（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 字典类型入参校验。type 唯一（含软删）校验在 DictService。
 */
class DictValidate extends BxValidate
{
    protected $rule = [
        'name'   => 'max:64',
        'type'   => 'max:64',
        'status' => 'integer',
        'remark' => 'max:255',
    ];

    protected $message = [];

    public function sceneCreate(): static
    {
        return $this->only(['name', 'type', 'status', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['name', 'type', 'status', 'remark']);
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

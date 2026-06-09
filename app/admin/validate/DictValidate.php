<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 字典类型（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 字典类型入参校验。type 唯一（含软删）在 DictService。
 */
class DictValidate extends BxValidate
{
    protected $rule = [
        'name'   => 'require|max:64',
        'type'   => 'require|max:64|alphaDash',
        'status' => 'in:0,1',
        'remark' => 'max:255',
    ];

    protected $message = [
        'name.require'   => '请输入字典名称',
        'type.require'   => '请输入字典类型标识',
        'type.alphaDash' => '字典类型标识只能含字母、数字、下划线和短横线',
        'status.in'      => '状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['name', 'type', 'status', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['name', 'type', 'status', 'remark'])
            ->remove('name', 'require')
            ->remove('type', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 字典数据项（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 字典数据项入参校验。dict_type 存在性、同类型 value 唯一在 DictDataService。
 */
class DictDataValidate extends BxValidate
{
    protected $rule = [
        'dict_type'  => 'require|max:64',
        'label'      => 'require|max:128',
        'value'      => 'require|max:128',
        'sort'       => 'integer|egt:0',
        'status'     => 'in:0,1',
        'list_class' => 'max:32',
        'is_default' => 'in:0,1',
        'remark'     => 'max:255',
    ];

    protected $message = [
        'dict_type.require' => '请选择所属字典类型',
        'label.require'     => '请输入显示文本',
        'value.require'     => '请输入字典值',
        'status.in'         => '状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['dict_type', 'label', 'value', 'sort', 'status', 'list_class', 'is_default', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['dict_type', 'label', 'value', 'sort', 'status', 'list_class', 'is_default', 'remark'])
            ->remove('dict_type', 'require')
            ->remove('label', 'require')
            ->remove('value', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

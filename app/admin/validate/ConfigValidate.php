<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 参数配置（create/update 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 参数配置入参校验。(group,key) 唯一含软删、敏感值加解密在 ConfigService。
 */
class ConfigValidate extends BxValidate
{
    protected $rule = [
        'name'         => 'max:64',
        'group'        => 'require|max:64',
        'key'          => 'require|max:128',
        'value'        => 'max:65535',
        'is_sensitive' => 'in:0,1',
        'value_type'   => 'in:string,number,bool,json,textarea',
        'sort'         => 'integer|egt:0',
        'remark'       => 'max:255',
    ];

    protected $message = [
        'group.require'   => '请输入配置分组',
        'key.require'     => '请输入配置键',
        'value_type.in'   => '值类型非法',
        'is_sensitive.in' => '敏感标记非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['name', 'group', 'key', 'value', 'is_sensitive', 'value_type', 'sort', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['name', 'group', 'key', 'value', 'is_sensitive', 'value_type', 'sort', 'remark'])
            ->remove('group', 'require')
            ->remove('key', 'require');
    }
}

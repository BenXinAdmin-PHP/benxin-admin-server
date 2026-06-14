<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 部门（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 部门入参校验。
 */
class DeptValidate extends BxValidate
{
    protected $rule = [
        'parent_id' => 'integer|egt:0',
        'name'      => 'require|max:64',
        'leader'    => 'max:64',
        'phone'     => 'max:20',
        'email'     => 'email|max:128',
        'sort'      => 'integer|egt:0',
        'status'    => 'in:0,1',
    ];

    protected $message = [
        'name.require' => '请输入部门名称',
        'name.max'     => '部门名称最长 64 字符',
        'email.email'  => '邮箱格式不正确',
        'status.in'    => '状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['parent_id', 'name', 'leader', 'phone', 'email', 'sort', 'status']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['parent_id', 'name', 'leader', 'phone', 'email', 'sort', 'status'])
            ->remove('name', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

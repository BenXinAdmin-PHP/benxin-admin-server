<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 菜单（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 菜单入参校验。
 */
class MenuValidate extends BxValidate
{
    protected $rule = [
        'parent_id' => 'integer|egt:0',
        'type'      => 'require|in:1,2,3',
        'name'      => 'max:64',
        'title'     => 'require|max:64',
        'path'      => 'max:191',
        'component' => 'max:191',
        'perms'     => 'max:128',
        'icon'      => 'max:64',
        'sort'      => 'integer|egt:0',
        'status'    => 'in:0,1',
        'visible'   => 'in:0,1',
    ];

    protected $message = [
        'type.require'  => '请选择菜单类型',
        'type.in'       => '菜单类型非法（1目录/2菜单/3按钮）',
        'title.require' => '请输入菜单标题',
        'title.max'     => '标题最长 64 字符',
        'status.in'     => '状态非法',
        'visible.in'    => '显示状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['parent_id', 'type', 'name', 'title', 'path', 'component', 'perms', 'icon', 'sort', 'status', 'visible']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['parent_id', 'type', 'name', 'title', 'path', 'component', 'perms', 'icon', 'sort', 'status', 'visible'])
            ->remove('type', 'require')
            ->remove('title', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

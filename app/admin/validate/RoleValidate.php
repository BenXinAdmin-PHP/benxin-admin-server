<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 角色（create/update/status/assignMenus 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 15:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 角色入参校验。code 唯一性、super_admin 保护等业务规则在 RoleService。
 */
class RoleValidate extends BxValidate
{
    protected $rule = [
        'name'       => 'require|max:64',
        'code'       => 'require|max:64|alphaDash',
        'sort'       => 'integer|egt:0',
        'status'     => 'in:0,1',
        'data_scope' => 'in:1,2,3,4,5',
        'remark'     => 'max:255',
        'menu_ids'   => 'array',
        'dept_ids'   => 'array',
    ];

    protected $message = [
        'name.require'   => '请输入角色名称',
        'code.require'   => '请输入角色标识',
        'code.alphaDash' => '角色标识只能含字母、数字、下划线和短横线',
        'status.in'      => '状态非法',
        'menu_ids.array' => 'menu_ids 必须为数组',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['name', 'code', 'sort', 'status', 'data_scope', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['name', 'code', 'sort', 'status', 'data_scope', 'remark', 'dept_ids'])
            ->remove('name', 'require')
            ->remove('code', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }

    public function sceneAssignMenus(): static
    {
        return $this->only(['menu_ids'])->append('menu_ids', 'require');
    }
}

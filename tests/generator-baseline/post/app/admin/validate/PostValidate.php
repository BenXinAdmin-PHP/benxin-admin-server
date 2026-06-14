<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 岗位（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 岗位入参校验。code 唯一（含软删）校验在 PostService。
 */
class PostValidate extends BxValidate
{
    protected $rule = [
        'code'   => 'require|max:64|alphaDash',
        'name'   => 'require|max:64',
        'sort'   => 'integer|egt:0',
        'status' => 'in:0,1',
        'remark' => 'max:255',
    ];

    protected $message = [
        'code.require'   => '请输入岗位编码',
        'code.alphaDash' => '岗位编码只能含字母、数字、下划线和短横线',
        'name.require'   => '请输入岗位名称',
        'status.in'      => '状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['code', 'name', 'sort', 'status', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['code', 'name', 'sort', 'status', 'remark'])
            ->remove('code', 'require')
            ->remove('name', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

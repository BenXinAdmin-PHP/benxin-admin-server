<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 内容分类（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 内容分类入参校验。
 */
class ContentCategoryValidate extends BxValidate
{
    protected $rule = [
        'parent_id' => 'integer|egt:0',
        'name'      => 'require|max:64',
        'sort'      => 'integer|egt:0',
        'status'    => 'in:0,1',
        'icon'      => 'max:128',
    ];

    protected $message = [
        'name.require' => '请输入分类名称',
        'name.max'     => '分类名称最长 64 字符',
        'status.in'    => '状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['parent_id', 'name', 'sort', 'status', 'icon']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['parent_id', 'name', 'sort', 'status', 'icon'])
            ->remove('name', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

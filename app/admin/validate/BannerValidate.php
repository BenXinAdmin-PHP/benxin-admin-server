<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 广告位（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 广告位入参校验。
 */
class BannerValidate extends BxValidate
{
    protected $rule = [
        'title'    => 'require|max:128',
        'image'    => 'require|max:255',
        'link'     => 'max:255',
        'position' => 'require|max:64',
        'sort'     => 'integer|egt:0',
        'status'   => 'in:0,1',
        'start_at' => 'date',
        'end_at'   => 'date',
    ];

    protected $message = [
        'title.require'    => '请输入标题',
        'title.max'        => '标题最长 128 字符',
        'image.require'    => '请上传图片',
        'image.max'        => '图片地址最长 255 字符',
        'position.require' => '请输入位置标识',
        'position.max'     => '位置标识最长 64 字符',
        'status.in'        => '状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['title', 'image', 'link', 'position', 'sort', 'status', 'start_at', 'end_at']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['title', 'image', 'link', 'position', 'sort', 'status', 'start_at', 'end_at'])
            ->remove('title', 'require')
            ->remove('image', 'require')
            ->remove('position', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

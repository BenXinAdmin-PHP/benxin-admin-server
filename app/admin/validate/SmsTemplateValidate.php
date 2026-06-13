<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 短信模板（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 20:00:39
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 短信模板入参校验。scene 唯一（含软删）校验在 SmsTemplateService。
 */
class SmsTemplateValidate extends BxValidate
{
    protected $rule = [
        'scene'         => 'require|max:32',
        'channel'       => 'require|in:ali,tencent',
        'template_code' => 'require|max:64',
        'sign_name'     => 'max:64',
        'content'       => 'max:500',
        'status'        => 'in:0,1',
        'remark'        => 'max:255',
    ];

    protected $message = [
        'scene.require'         => '请输入场景标识',
        'scene.max'             => '场景标识最长 32 字符',
        'channel.require'       => '请选择渠道',
        'channel.in'            => '渠道非法（ali/tencent）',
        'template_code.require' => '请输入模板ID',
        'template_code.max'     => '模板ID最长 64 字符',
        'status.in'             => '状态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['scene', 'channel', 'template_code', 'sign_name', 'content', 'status', 'remark']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['scene', 'channel', 'template_code', 'sign_name', 'content', 'status', 'remark'])
            ->remove('scene', 'require')
            ->remove('channel', 'require')
            ->remove('template_code', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

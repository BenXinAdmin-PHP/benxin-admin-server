<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 系统公告（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 20:00:39
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 系统公告入参校验。
 */
class NoticeValidate extends BxValidate
{
    protected $rule = [
        'title'      => 'require|max:200',
        'type'       => 'require|in:1,2',
        'content'    => 'require',
        'status'     => 'in:0,1,2',
        'is_top'     => 'in:0,1',
        'sort'       => 'integer|egt:0',
        'publish_at' => 'date',
    ];

    protected $message = [
        'title.require'   => '请输入标题',
        'title.max'       => '标题最长 200 字符',
        'type.require'    => '请选择类型',
        'type.in'         => '类型非法（1通知/2公告）',
        'content.require' => '请输入正文',
        'status.in'       => '状态非法（0草稿/1已发布/2已下架）',
        'is_top.in'       => '置顶标记非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['title', 'type', 'content', 'status', 'is_top', 'sort', 'publish_at']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['title', 'type', 'content', 'status', 'is_top', 'sort', 'publish_at'])
            ->remove('title', 'require')
            ->remove('type', 'require')
            ->remove('content', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 内容（create/update/status 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:26:41
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 内容入参校验。
 */
class ContentValidate extends BxValidate
{
    protected $rule = [
        'category_id' => 'require|integer|gt:0',
        'title'       => 'require|max:200',
        'cover'       => 'max:255',
        'summary'     => 'max:500',
        'content'     => 'require',
        'author'      => 'max:64',
        'source'      => 'max:128',
        'status'      => 'in:0,1,2',
        'is_top'      => 'in:0,1',
        'sort'        => 'integer|egt:0',
        'view_count'  => 'integer|egt:0',
        'publish_at'  => 'date',
    ];

    protected $message = [
        'category_id.require' => '请选择所属分类',
        'category_id.integer' => '所属分类非法',
        'category_id.gt'      => '所属分类非法',
        'title.require'       => '请输入标题',
        'title.max'           => '标题最长 200 字符',
        'content.require'     => '请输入正文',
        'status.in'           => '状态非法（0草稿/1已发布/2已下架）',
        'is_top.in'           => '置顶标记非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['category_id', 'title', 'cover', 'summary', 'content', 'author', 'source', 'status', 'is_top', 'sort', 'view_count', 'publish_at']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['category_id', 'title', 'cover', 'summary', 'content', 'author', 'source', 'status', 'is_top', 'sort', 'view_count', 'publish_at'])
            ->remove('category_id', 'require')
            ->remove('title', 'require')
            ->remove('content', 'require');
    }

    public function sceneStatus(): static
    {
        return $this->only(['status'])->append('status', 'require');
    }
}

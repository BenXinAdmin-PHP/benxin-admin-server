<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 页面（create/update 场景，入参形状；区块深校验在 PageService）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 页面入参校验（浅层形状）。slug 字符集 [a-z0-9-]+；blocks 数组。
 * 区块 type 白名单 / 必填 / i18n 形状的深校验由 PageService::validateBlocks 承担。
 */
class PageValidate extends BxValidate
{
    protected $rule = [
        'slug'   => 'require|regex:/^[a-z0-9-]+$/|max:64',
        'title'  => 'require|max:128',
        'status' => 'in:0,1',
        'blocks' => 'require|array',
    ];

    protected $message = [
        'slug.require' => '请输入页面标识 slug',
        'slug.regex'   => '页面标识仅允许小写字母、数字与连字符（[a-z0-9-]）',
        'slug.max'     => '页面标识最长 64 字符',
        'title.require' => '请输入页面名',
        'title.max'    => '页面名最长 128 字符',
        'status.in'    => '状态非法（0草稿/1已发布）',
        'blocks.require' => '请提供页面区块 blocks',
        'blocks.array' => '区块 blocks 必须为数组',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['slug', 'title', 'status', 'blocks']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['slug', 'title', 'status', 'blocks'])
            ->remove('slug', 'require')
            ->remove('title', 'require')
            ->remove('blocks', 'require');
    }
}

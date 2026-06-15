<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 素材（create/update 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 15:08:44
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 素材入参校验。
 */
class ResourceValidate extends BxValidate
{
    protected $rule = [
        'category_id'      => 'integer|egt:0',
        'name'             => 'require|max:255',
        'media_type'       => 'in:image,video,audio,document,archive',
        'storage'          => 'max:16',
        'path'             => 'max:500',
        'url'              => 'max:500',
        'file_name'        => 'max:128',
        'original_name'    => 'max:255',
        'ext'              => 'max:16',
        'mime'             => 'max:128',
        'size'             => 'integer|egt:0',
        'hash'             => 'max:64',
        'vod_media_id'     => 'max:128',
        'transcode_status' => 'in:0,1,2,3,4',
    ];

    protected $message = [
        'category_id.integer' => '所属分类非法',
        'name.require'        => '请输入素材名称',
        'name.max'            => '素材名称最长 255 字符',
        'media_type.in'       => '媒体类型非法',
        'transcode_status.in' => '转码态非法',
    ];

    public function sceneCreate(): static
    {
        return $this->only(['category_id', 'name', 'media_type', 'storage', 'path', 'url', 'file_name', 'original_name', 'ext', 'mime', 'size', 'hash', 'vod_media_id', 'transcode_status']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['category_id', 'name', 'media_type', 'storage', 'path', 'url', 'file_name', 'original_name', 'ext', 'mime', 'size', 'hash', 'vod_media_id', 'transcode_status'])
            ->remove('name', 'require');
    }
}

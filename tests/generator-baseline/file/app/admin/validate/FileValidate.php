<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   校验器 — 文件（create/update 场景）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\validate;

use app\common\base\BxValidate;

/**
 * 文件入参校验。
 */
class FileValidate extends BxValidate
{
    protected $rule = [
        'original_name' => 'max:255',
        'file_name'     => 'max:128',
        'path'          => 'max:500',
        'mime'          => 'max:128',
        'ext'           => 'max:16',
        'size'          => 'integer',
        'storage'       => 'max:16',
        'hash'          => 'max:64',
        'url'           => 'max:500',
    ];

    protected $message = [];

    public function sceneCreate(): static
    {
        return $this->only(['original_name', 'file_name', 'path', 'mime', 'ext', 'size', 'storage', 'hash', 'url']);
    }

    public function sceneUpdate(): static
    {
        return $this->only(['original_name', 'file_name', 'path', 'mime', 'ext', 'size', 'storage', 'hash', 'url']);
    }
}

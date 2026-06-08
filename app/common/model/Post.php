<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 岗位 bx_post
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-08 16:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 岗位模型。
 */
class Post extends BxModel
{
    protected $name = 'post';

    protected $hidden = ['deleted_at'];

    protected $type = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];
}

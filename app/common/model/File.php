<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   模型 — 文件 bx_file
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 22:00:00
// | @updated   2026-06-15 (M3-G: $hidden 并入 tenant_id，最小暴露)
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\model;

use app\common\base\BxModel;

/**
 * 文件模型。create_by/create_dept 由 BxModel 钩子自动填充（数据权限首落点）。
 */
class File extends BxModel
{
    protected $name = 'file';

    protected $hidden = ['deleted_at', 'tenant_id'];

    protected $type = [
        'id'          => 'integer',
        'tenant_id'   => 'integer',
        'create_by'   => 'integer',
        'create_dept' => 'integer',
        'size'        => 'integer',
    ];
}

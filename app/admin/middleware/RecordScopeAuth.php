<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   单条管理员与文件记录 — 数据范围校验
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-10-20 18:42:23
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\middleware;

use app\common\exception\BusinessException;
use app\common\library\ErrorCode;
use app\common\model\Admin;
use app\common\model\File;
use app\common\service\DataScopeService;
use Closure;
use think\Request;
use think\Response;

/** 列表与按 ID 操作共用数据范围，避免猜测 ID 绕过列表过滤。 */
class RecordScopeAuth
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        $actor = $request->adminUser ?? null;
        $id = (int) $request->route('id');
        if (!$actor instanceof Admin || $id <= 0) {
            throw new BusinessException('记录不存在', ErrorCode::NOT_FOUND);
        }

        if ($kind === 'admin') {
            $query = Admin::where('id', $id);
            $deptField = 'dept_id';
            $ownerField = 'id';
        } elseif ($kind === 'file') {
            $query = File::where('id', $id);
            $deptField = 'create_dept';
            $ownerField = 'create_by';
        } else {
            throw new \LogicException('未知的数据范围类型');
        }

        (new DataScopeService(app()))->applyTo($query, $actor, $deptField, $ownerField);
        if ($query->find() === null) {
            throw new BusinessException('记录不存在', ErrorCode::NOT_FOUND);
        }

        return $next($request);
    }
}

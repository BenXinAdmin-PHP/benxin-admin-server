<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   前台公告 — GET /api/v1/notices[/:id]（只读已发布）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 15:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\model\Notice as NoticeModel;
use think\Response;

/**
 * 前台公告（C 端只读，懒登录不强制）。仅展示已发布（status=1）且 publish_at 已到/为空的公告。
 * 后台公告 CRUD 见 admin/controller/Notice（bx:make 生成）。
 */
class Notice extends BxController
{
    private const STATUS_PUBLISHED = 1;

    /**
     * 已发布公告列表（置顶优先）。GET /api/v1/notices
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $query = $this->publishedQuery()->field('id,title,type,is_top,publish_at,created_at');

        $total = $query->count();
        $list  = $query->order('is_top', 'desc')->order('sort', 'asc')->order('id', 'desc')
            ->page($page, $size)->select()->toArray();

        return $this->paginate($list, $total, $page, $size);
    }

    /**
     * 公告详情（含正文）。GET /api/v1/notices/:id
     */
    public function read(int $id): Response
    {
        $notice = $this->publishedQuery()->where('id', $id)->find();
        if ($notice === null) {
            return $this->fail(\app\common\library\ErrorCode::NOT_FOUND, '公告不存在或未发布');
        }

        return $this->success($notice);
    }

    /**
     * 已发布范围查询：status=已发布 且（publish_at 为空 或 已到）。
     */
    private function publishedQuery()
    {
        return NoticeModel::where('status', self::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->whereNull('publish_at')->whereOr('publish_at', '<=', date('Y-m-d H:i:s'));
            });
    }
}

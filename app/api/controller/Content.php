<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   前台内容 — GET /api/v1/contents[/:id]（只读已发布）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 21:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\library\ErrorCode;
use app\common\model\Content as ContentModel;
use think\Response;

/**
 * 前台内容（C 端只读，懒登录不强制）。仅展示已发布（status=1）且 publish_at 已到/为空的内容。
 * 字段精简：不外露 create_by/create_dept/tenant_id 等内部字段；列表不返回正文。
 * 后台内容 CRUD 见 admin/controller/Content（bx:make 生成）。
 */
class Content extends BxController
{
    private const STATUS_PUBLISHED = 1;

    /** 列表字段白名单：不含正文 content，不含内部归属字段 */
    private const LIST_FIELDS = 'id,category_id,title,cover,summary,author,source,is_top,view_count,publish_at,created_at';

    /** 详情字段白名单：含正文 content，仍不含内部归属字段 */
    private const DETAIL_FIELDS = 'id,category_id,title,cover,summary,content,author,source,is_top,view_count,publish_at,created_at';

    /**
     * 已发布内容列表（置顶优先，列表不含正文）。GET /api/v1/contents
     * 支持 category_id 过滤、keyword（标题模糊）。
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();

        $query = $this->publishedQuery()->field(self::LIST_FIELDS);

        if (($cid = (int) $this->request->param('category_id', 0)) > 0) {
            $query->where('category_id', $cid);
        }
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->whereLike('title', "%{$keyword}%");
        }

        $total = $query->count();
        $list  = $query->order('is_top', 'desc')->order('sort', 'asc')->order('id', 'desc')
            ->page($page, $size)->select()->toArray();

        return $this->paginate($list, $total, $page, $size);
    }

    /**
     * 内容详情（含正文）；命中即浏览量 +1（服务端维护，原子自增）。
     * GET /api/v1/contents/:id
     */
    public function read(int $id): Response
    {
        $exists = $this->publishedQuery()->where('id', $id)->value('id');
        if ($exists === null) {
            return $this->fail(ErrorCode::NOT_FOUND, '内容不存在或未发布');
        }

        // 浏览量原子 +1（不经模型批量赋值，规避并发覆盖）
        ContentModel::where('id', $id)->inc('view_count')->update();

        $content = $this->publishedQuery()->where('id', $id)->field(self::DETAIL_FIELDS)->find();

        return $this->success($content);
    }

    /**
     * 已发布范围查询：status=已发布 且（publish_at 为空 或 已到）。
     */
    private function publishedQuery()
    {
        return ContentModel::where('status', self::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->whereNull('publish_at')->whereOr('publish_at', '<=', date('Y-m-d H:i:s'));
            });
    }
}

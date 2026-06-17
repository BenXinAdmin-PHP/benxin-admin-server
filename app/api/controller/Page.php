<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   前台页面 — GET /api/v1/pages/:slug?lang=zh（公开免登录只读渲染）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// | @updated   2026-06-17 19:56:30（B1-① 新增 index 列表 — GET /api/v1/pages 已发布页清单）
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\api\controller;

use app\common\base\BxController;
use app\common\service\PageService;
use think\Response;

/**
 * 前台页面渲染（公开只读，懒登录不强制；供官网 Nuxt M6-D 消费）。
 * 按 lang 解析 i18n 字段为字符串、字段白名单（仅 slug/title/blocks），slug 不存在或未发布 → 404000。
 */
class Page extends BxController
{
    /**
     * 已发布页清单。GET /api/v1/pages
     * 无入参；返回数组，元素仅 {slug, updated_at}，按 updated_at 倒序。
     * 供 Nuxt 官网 SSG 枚举 /[slug] 预渲染路由 + sitemap 双语条目（B1-② ）。
     */
    public function index(): Response
    {
        $data = (new PageService($this->app))->listPublished();

        return $this->success($data);
    }

    /**
     * 渲染整页。GET /api/v1/pages/:slug?lang=zh
     * lang 缺省 zh、非 zh/en 归一为 zh（白名单在 PageService）。
     */
    public function render(string $slug): Response
    {
        $lang = (string) $this->request->param('lang', 'zh');
        $data = (new PageService($this->app))->renderBySlug($slug, $lang);

        return $this->success($data);
    }
}

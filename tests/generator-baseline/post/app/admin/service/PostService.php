<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 岗位 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 19:55:40
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\Post;
use think\facade\Db;

/**
 * 岗位服务：标准 CRUD（生成器复刻 post 母版）。
 * code 唯一含软删（§5.1）。
 */
class PostService extends BxService
{
    protected const FILLABLE = ['code', 'name', 'sort', 'status', 'remark'];

    /**
     * 分页列表（keyword: code/name；status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = Post::order('sort', 'asc')->order('id', 'asc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('code', "%{$keyword}%")->whereOr('name', 'like', "%{$keyword}%");
            });
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): Post
    {
        return Post::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Post
    {
        $data = $this->fillable($data);
        $this->assertCodeUnique((string) $data['code'], null);
        $data['tenant_id'] = Post::currentTenantId();

        return Post::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): Post
    {
        $post = Post::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('code', $data)) {
            $this->assertCodeUnique((string) $data['code'], $id);
        }

        $post->save($data);

        return $post;
    }

    /**
     * 删除岗位：有管理员绑定（bx_admin_post.post_id）拒绝；否则软删。
     */
    public function delete(int $id): void
    {
        $post = Post::findOrFail($id);

        if (Db::name('admin_post')->where('post_id', $id)->count() > 0) {
            throw new BusinessException('该岗位已被管理员绑定，无法删除');
        }

        $post->delete();
    }

    public function setStatus(int $id, int $status): Post
    {
        $post         = Post::findOrFail($id);
        $post->status = $status;
        $post->save();

        return $post;
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }

    /**
     * code 全局唯一（含 withTrashed，已删 code 不可复用，§5.1）。
     */
    protected function assertCodeUnique(string $code, ?int $exceptId): void
    {
        $query = Post::withTrashed()->where('code', $code);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('岗位编码已存在：' . $code);
        }
    }
}

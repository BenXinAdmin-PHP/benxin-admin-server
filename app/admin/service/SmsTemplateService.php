<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   服务 — 短信模板 CRUD（生成器复刻 post 母版）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 20:00:39
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\service;

use app\common\base\BxService;
use app\common\exception\BusinessException;
use app\common\model\SmsTemplate;

/**
 * 短信模板服务：标准 CRUD（生成器复刻 post 母版）。
 * scene 唯一含软删（§5.1）。
 */
class SmsTemplateService extends BxService
{
    protected const FILLABLE = ['scene', 'channel', 'template_code', 'sign_name', 'content', 'status', 'remark'];

    /**
     * 分页列表（keyword: scene；channel 精确；status 精确）。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,mixed>,total:int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = SmsTemplate::order('id', 'asc');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('scene', "%{$keyword}%");
            });
        }
        if (($filters['channel'] ?? '') !== '') {
            $query->where('channel', (string) $filters['channel']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function detail(int $id): SmsTemplate
    {
        return SmsTemplate::findOrFail($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): SmsTemplate
    {
        $data = $this->fillable($data);
        $this->assertSceneUnique((string) $data['scene'], null);
        $data['tenant_id'] = SmsTemplate::currentTenantId();

        return SmsTemplate::create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): SmsTemplate
    {
        $smsTemplate = SmsTemplate::findOrFail($id);
        $data = $this->fillable($data);

        if (array_key_exists('scene', $data)) {
            $this->assertSceneUnique((string) $data['scene'], $id);
        }

        $smsTemplate->save($data);

        return $smsTemplate;
    }

    /**
     * 删除（纯 CRUD 软删；关联护栏按需在 config 声明）。
     */
    public function delete(int $id): void
    {
        $smsTemplate = SmsTemplate::findOrFail($id);
        $smsTemplate->delete();
    }

    public function setStatus(int $id, int $status): SmsTemplate
    {
        $smsTemplate         = SmsTemplate::findOrFail($id);
        $smsTemplate->status = $status;
        $smsTemplate->save();

        return $smsTemplate;
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
     * scene 全局唯一（含 withTrashed，已删 scene 不可复用，§5.1）。
     */
    protected function assertSceneUnique(string $scene, ?int $exceptId): void
    {
        $query = SmsTemplate::withTrashed()->where('scene', $scene);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new BusinessException('场景标识已存在：' . $scene);
        }
    }
}

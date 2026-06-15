<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   短信模板 — GET|POST|PUT|DELETE /admin/v1/sms-templates[/:id|/:id/status]
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 20:00:39
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\SmsTemplateService;
use app\admin\validate\SmsTemplateValidate;
use app\common\base\BxController;
use think\Response;

/**
 * 短信模板 CRUD（生成器复刻 post 纯 CRUD 母版）。
 */
class SmsTemplate extends BxController
{
    /**
     * 列表（分页 + keyword/channel/status 筛选）。
     * GET /admin/v1/sms-templates
     */
    public function index(): Response
    {
        [$page, $size] = $this->pageParam();
        $result = (new SmsTemplateService($this->app))->list([
            'keyword' => $this->request->param('keyword', ''),
            'channel' => $this->request->param('channel', ''),
            'status'  => $this->request->param('status', ''),
        ], $page, $size);

        return $this->paginate($result['list'], $result['total'], $page, $size);
    }

    /**
     * 详情。
     * GET /admin/v1/sms-templates/:id
     */
    public function read(int $id): Response
    {
        return $this->success((new SmsTemplateService($this->app))->detail($id));
    }

    /**
     * 新增。
     * POST /admin/v1/sms-templates
     */
    public function save(): Response
    {
        validate(SmsTemplateValidate::class)->scene('create')->check($this->request->post());

        $smsTemplate = (new SmsTemplateService($this->app))->create($this->request->post());

        return $this->success($smsTemplate, '新增成功');
    }

    /**
     * 更新。
     * PUT /admin/v1/sms-templates/:id
     */
    public function update(int $id): Response
    {
        validate(SmsTemplateValidate::class)->scene('update')->check($this->request->param());

        $smsTemplate = (new SmsTemplateService($this->app))->update($id, $this->request->param());

        return $this->success($smsTemplate, '更新成功');
    }

    /**
     * 删除。
     * DELETE /admin/v1/sms-templates/:id
     */
    public function delete(int $id): Response
    {
        (new SmsTemplateService($this->app))->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 启停。
     * PUT /admin/v1/sms-templates/:id/status
     */
    public function status(int $id): Response
    {
        validate(SmsTemplateValidate::class)->scene('status')->check($this->request->param());

        $smsTemplate = (new SmsTemplateService($this->app))->setStatus($id, (int) $this->request->param('status'));

        return $this->success($smsTemplate, '状态更新成功');
    }
}

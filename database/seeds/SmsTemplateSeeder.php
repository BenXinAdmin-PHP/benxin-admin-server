<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 默认短信模板（登录验证码，M4-D，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

use think\facade\Db;
use think\migration\Seeder;

/**
 * M4-D 默认短信模板（幂等）：登录/绑定验证码场景骨架，template_code 为占位（待后台填渠道审核后模板ID）。
 * 验证码服务按 scene 查模板取 template_code/sign_name。
 */
class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            ['scene' => 'login', 'channel' => 'ali', 'template_code' => 'SMS_PLACEHOLDER_LOGIN', 'content' => '您的登录验证码为 ${code}，5 分钟内有效，请勿泄露。', 'remark' => '登录验证码（占位模板ID，请在后台改为渠道审核后的真实模板ID）'],
            ['scene' => 'bind', 'channel' => 'ali', 'template_code' => 'SMS_PLACEHOLDER_BIND', 'content' => '您的绑定验证码为 ${code}，5 分钟内有效，请勿泄露。', 'remark' => '绑定手机验证码（占位模板ID）'],
        ];

        foreach ($rows as $r) {
            $exists = Db::name('sms_template')->where(['tenant_id' => 0, 'scene' => $r['scene']])->whereNull('deleted_at')->find();
            if ($exists !== null) {
                continue;
            }
            Db::name('sms_template')->insert([
                'tenant_id'     => 0,
                'scene'         => $r['scene'],
                'channel'       => $r['channel'],
                'template_code' => $r['template_code'],
                'sign_name'     => '',
                'content'       => $r['content'],
                'status'        => 1,
                'remark'        => $r['remark'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        echo "[SmsTemplateSeeder] 完成。\n";
    }
}

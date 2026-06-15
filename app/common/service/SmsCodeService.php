<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   验证码服务 — 多维限流(防轰炸) + 校验(防爆破) + 发送(M5 登录铺垫)
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service;

use app\common\base\BxService;
use app\common\exception\SmsException;
use app\common\library\BxCache;
use app\common\library\ErrorCode;
use app\common\library\sms\MessageManager;
use app\common\model\SmsLog;
use app\common\model\SmsTemplate;

/**
 * 验证码服务（M4-D，★为 M5 懒登录铺垫）。
 *
 * 安全（§8）：
 *  - 防轰炸多维限流：同手机号发送间隔 60s、同手机号天上限、同 IP 天上限（Valkey 计数器）。
 *  - 防爆破：校验错误计数，超限锁定并删码（需重新获取）。
 *  - 验证码仅存 Valkey、TTL 5min、消费即删、不日志明文；手机号脱敏入库 bx_sms_log。
 *
 * Valkey key（经 store 前缀实际为 bx:...）：
 *  - 码：       sms:code:{scene}:{mobile}            TTL 300
 *  - 发送间隔： sms:code:sent:{scene}:{mobile}       TTL 60
 *  - 手机号日： sms:code:day:mobile:{mobile}         TTL 86400
 *  - IP 日：    sms:code:day:ip:{ip}                 TTL 86400
 *  - 错误计数： sms:code:err:{scene}:{mobile}        TTL 300
 */
class SmsCodeService extends BxService
{
    protected const CODE_TTL      = 300;  // 验证码有效期 5min
    protected const SEND_INTERVAL = 60;   // 同手机号发送间隔
    protected const MOBILE_DAILY  = 10;   // 同手机号天上限
    protected const IP_DAILY      = 20;   // 同 IP 天上限
    protected const MAX_ERR       = 5;    // 校验错误次数上限
    protected const DAY_TTL       = 86400;

    /**
     * 发送验证码：多维限流 → 生成 → 按 scene 查模板发送 → 记日志（手机号脱敏）。
     *
     * @return string 脱敏手机号（供接口回显，不回明文）
     */
    public function send(string $mobile, string $scene, string $ip): string
    {
        $mobile = trim($mobile);
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            throw SmsException::channel(ErrorCode::SMS_SEND_FAILED, '手机号格式不正确');
        }

        $store = BxCache::store();

        // 1) 多维限流（防轰炸）
        if ($store->has("sms:code:sent:{$scene}:{$mobile}")) {
            throw new SmsException('请求过于频繁，请 60 秒后再试', ErrorCode::SMS_CODE_TOO_OFTEN);
        }
        if ((int) $store->get("sms:code:day:mobile:{$mobile}", 0) >= self::MOBILE_DAILY) {
            throw new SmsException('今日该手机号获取验证码次数已达上限', ErrorCode::SMS_CODE_TOO_OFTEN);
        }
        if ((int) $store->get("sms:code:day:ip:{$ip}", 0) >= self::IP_DAILY) {
            throw new SmsException('今日该网络获取验证码次数已达上限', ErrorCode::SMS_CODE_TOO_OFTEN);
        }

        // 2) 发送间隔锁先占位（防并发/快速重发），即便后续发送失败也需等待 60s
        $store->set("sms:code:sent:{$scene}:{$mobile}", 1, self::SEND_INTERVAL);

        // 3) 生成 6 位码
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 4) 按 scene 查模板
        $template = SmsTemplate::where('scene', $scene)->where('status', 1)->find();
        if ($template === null) {
            throw SmsException::configMissing("场景模板 {$scene}（未配置或已停用）");
        }

        // 5) 发送（渠道取模板 channel，缺省取配置 sms_channel）
        $channelName = (string) $template->channel !== '' ? (string) $template->channel : null;
        $signName    = (string) $template->sign_name !== '' ? (string) $template->sign_name : null;
        try {
            $result = MessageManager::channel($channelName)->send($mobile, (string) $template->template_code, ['code' => $code], $signName);
        } catch (SmsException $e) {
            $this->log($mobile, (string) ($channelName ?? ''), $scene, (string) $template->template_code, 0, $e->getMessage(), $ip);
            throw $e;
        }

        // 6) 成功：存码（消费即删）+ 计数 + 清错误计数 + 记日志（脱敏）
        $store->set("sms:code:{$scene}:{$mobile}", $code, self::CODE_TTL);
        $this->incrWithTtl($store, "sms:code:day:mobile:{$mobile}", self::DAY_TTL);
        $this->incrWithTtl($store, "sms:code:day:ip:{$ip}", self::DAY_TTL);
        BxCache::forget("sms:code:err:{$scene}:{$mobile}");
        $this->log($mobile, $result->code !== '' ? (string) ($channelName ?? '') : '', $scene, (string) $template->template_code, 1, $result->message, $ip);

        return self::maskMobile($mobile);
    }

    /**
     * 校验验证码（M5 登录流调用）：成功消费即删；失败计数，超限锁定。
     */
    public function verify(string $mobile, string $scene, string $code): bool
    {
        $store   = BxCache::store();
        $codeKey = "sms:code:{$scene}:{$mobile}";
        $errKey  = "sms:code:err:{$scene}:{$mobile}";

        $stored = $store->get($codeKey);
        if ($stored === null || $stored === '') {
            throw new SmsException('', ErrorCode::SMS_CODE_EXPIRED);
        }

        // 已达错误上限：锁定并删码
        if ((int) $store->get($errKey, 0) >= self::MAX_ERR) {
            $store->delete($codeKey);
            $store->delete($errKey);
            throw new SmsException('', ErrorCode::SMS_CODE_LOCKED);
        }

        if (hash_equals((string) $stored, trim($code))) {
            // 成功消费即删
            $store->delete($codeKey);
            $store->delete($errKey);

            return true;
        }

        // 失败计数（TTL 跟随验证码有效期）
        $errCount = $this->incrWithTtl($store, $errKey, self::CODE_TTL);
        if ($errCount >= self::MAX_ERR) {
            $store->delete($codeKey);
            $store->delete($errKey);
            throw new SmsException('', ErrorCode::SMS_CODE_LOCKED);
        }

        throw new SmsException('', ErrorCode::SMS_CODE_WRONG);
    }

    // ------------------------------------------------------------------
    // 内部工具
    // ------------------------------------------------------------------

    /**
     * 计数自增 + 首次设置 TTL（Valkey incr + 首次 expire）。
     */
    protected function incrWithTtl($store, string $key, int $ttl): int
    {
        $handler = $store->handler();
        $full    = $store->getCacheKey($key);
        $count   = (int) $handler->incr($full);
        if ($count === 1) {
            $handler->expire($full, $ttl);
        }

        return $count;
    }

    /**
     * 记短信日志（手机号脱敏、验证码不落明文：params 仅记占位）。
     */
    protected function log(string $mobile, string $channel, string $scene, string $templateCode, int $status, string $response, string $ip): void
    {
        try {
            SmsLog::create([
                'tenant_id'     => 0,
                'mobile'        => self::maskMobile($mobile),
                'channel'       => $channel,
                'scene'         => $scene,
                'template_code' => $templateCode,
                'params'        => 'code=******', // 验证码不落明文
                'status'        => $status,
                'response'      => mb_substr($response, 0, 500),
                'ip'            => $ip,
                'request_id'    => (string) (request()->request_id ?? ''),
            ]);
        } catch (\Throwable) {
            // 日志失败不拖垮主流程
        }
    }

    /**
     * 手机号脱敏：11 位 → 前 3 + **** + 后 4；其它保留首尾。
     */
    public static function maskMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        if (preg_match('/^\d{11}$/', $mobile)) {
            return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
        }
        $len = strlen($mobile);
        if ($len <= 4) {
            return '****';
        }

        return substr($mobile, 0, 2) . '****' . substr($mobile, -2);
    }
}

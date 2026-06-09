<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   日志脱敏 — 请求体敏感字段黑名单递归打码（安全红线）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 18:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

/**
 * 日志脱敏（安全红线）：记录 request_body 前对敏感字段打码，绝不落明文。
 * - 显式黑名单 + 语义正则（pass/secret/token/credential/api_key/app_secret）。
 * - 递归遍历嵌套数组；命中键整体替换为 ******。
 * - extraKeys 供调用方按路径补充（如 /configs 写接口的 value）。
 */
class LogSanitizer
{
    public const MASK = '******';

    /** 显式敏感字段名（小写精确匹配） */
    protected const BLACKLIST = [
        'password', 'old_password', 'new_password', 'confirm_password',
        'access_token', 'refresh_token', 'token',
    ];

    /** 语义匹配（键名含这些片段即视为敏感） */
    protected const PATTERN = '/pass|secret|token|credential|api[_-]?key|app[_-]?secret/i';

    /**
     * 脱敏请求体。
     *
     * @param array<string,mixed> $data
     * @param array<int,string>   $extraKeys 额外敏感键（小写）
     * @return array<string,mixed>
     */
    public static function sanitize(array $data, array $extraKeys = []): array
    {
        $extra = array_map('strtolower', $extraKeys);

        return self::walk($data, $extra);
    }

    /**
     * @param array<mixed,mixed> $data
     * @param array<int,string>  $extra
     * @return array<mixed,mixed>
     */
    protected static function walk(array $data, array $extra): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && self::isSensitive(strtolower($k), $extra)) {
                $out[$k] = self::MASK;
                continue;
            }
            $out[$k] = is_array($v) ? self::walk($v, $extra) : $v;
        }

        return $out;
    }

    protected static function isSensitive(string $key, array $extra): bool
    {
        if (in_array($key, self::BLACKLIST, true) || in_array($key, $extra, true)) {
            return true;
        }

        return (bool) preg_match(self::PATTERN, $key);
    }
}

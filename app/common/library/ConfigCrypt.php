<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   配置敏感值加解密 — AES-256-CBC
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 14:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

/**
 * 敏感配置值加解密（§9 / ADR-4）。
 * - AES-256-CBC，随机 IV，存储形态 base64(iv + cipher)。
 * - 密钥取 .env CONFIG_CRYPT_KEY（≥32 字节随机），经 SHA-256 规整为 32 字节 key。
 * - decrypt 失败安全降级（返回空串），不泄露细节。
 * 如需认证加密防篡改，可平滑切 AES-256-GCM（在 cipher 常量与 iv 长度处调整）。
 */
class ConfigCrypt
{
    protected const CIPHER  = 'aes-256-cbc';
    protected const IV_LEN  = 16;

    /**
     * 加密：返回 base64(iv + 密文)。
     */
    public static function encrypt(string $plain): string
    {
        $iv     = random_bytes(self::IV_LEN);
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return '';
        }

        return base64_encode($iv . $cipher);
    }

    /**
     * 解密：反解 base64(iv + 密文)；失败返回空串（安全降级）。
     */
    public static function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) <= self::IV_LEN) {
            return '';
        }

        $iv     = substr($raw, 0, self::IV_LEN);
        $cipher = substr($raw, self::IV_LEN);
        $plain  = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);

        return $plain === false ? '' : $plain;
    }

    /**
     * 脱敏：保留前后各 2 位，中间固定 ****（过短则全部 ******）。
     */
    public static function mask(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $len = mb_strlen($plain);
        if ($len <= 4) {
            return '******';
        }

        return mb_substr($plain, 0, 2) . '****' . mb_substr($plain, -2);
    }

    /**
     * 规整密钥为 32 字节（SHA-256 原始输出），保证任意长度 .env 值可用。
     */
    protected static function key(): string
    {
        $secret = (string) env('CONFIG_CRYPT_KEY', '');

        return hash('sha256', $secret, true);
    }
}

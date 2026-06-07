<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   UUID 工具 — 生成 RFC4122 v4 标识（request_id 等用）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

use Random\RandomException;

/**
 * 轻量 UUID 工具（不依赖扩展）。
 */
class Uuid
{
    /**
     * 生成 RFC 4122 版本 4（随机）UUID。
     *
     * @return string 形如 xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     */
    public static function v4(): string
    {
        try {
            $data = random_bytes(16);
        } catch (RandomException) {
            // 极端情况下退化为伪随机，保证可用
            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        // 版本号置 4，变体位置 10
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

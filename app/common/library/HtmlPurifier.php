<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   富文本净化 — HTMLPurifier 白名单封装（§8 XSS 二次防护）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:30:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

use HTMLPurifier as Purifier;
use HTMLPurifier_Config;

/**
 * 富文本净化（M4-A，ADR-14）：封装 ezyang/htmlpurifier（LGPL，作依赖不传染 Apache-2.0）。
 *
 * 防护定位：前端编辑器（wangEditor）出 HTML，后端落库前二次净化——
 * 白名单之外的标签/属性一律剥离（script、on* 事件、javascript: 协议、style 等危险项）。
 *
 * 用法：HtmlPurifier::clean($html)。单例缓存 Purifier 实例（构建配置较重）。
 * 候选回炉点（ADR-15）：生成器字段属性 richtext: true → Service create/update 自动注入本调用。
 */
class HtmlPurifier
{
    /** 允许的标签与属性白名单（常见富文本元素；HTMLPurifier 语法：tag[attr|attr]） */
    private const ALLOWED = 'p,br,span,strong,b,em,i,u,s,a[href|title|target],img[src|alt|title|width|height],'
        . 'ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,code,pre,hr,'
        . 'table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]';

    private static ?Purifier $purifier = null;

    /**
     * 按白名单净化富文本 HTML。
     */
    public static function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): Purifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::ALLOWED);
        // 链接协议白名单：剥离 javascript: / data: 等危险协议
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        // a[target] 需显式开启 _blank 白名单，并自动补 rel=noopener 防反向窃取
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        // 禁内联 style（XSS 载体之一）；编辑器排版统一走标签语义
        $config->set('CSS.AllowedProperties', []);
        // 不写缓存目录（每进程构建一次即可，避免 runtime 写入权限问题）
        $config->set('Cache.DefinitionImpl', null);

        return self::$purifier = new Purifier($config);
    }
}

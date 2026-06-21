<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   富文本净化 — HTMLPurifier 白名单封装（§8 XSS 二次防护）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 14:30:00
// | @updated   2026-06-21（ADR-27-①：新增搭建器富文本专用白名单 cleanBuilderRichtext，与内容模块 clean 分离）
// | @updated   2026-06-21（ADR-27 修订①：富文本白名单扩 table/span/video + CSS 属性白名单安全放宽）
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

    /**
     * 搭建器富文本块（ADR-27-① + 修订①）专用白名单：放行常见富排版——段落/换行/二~四级标题/强调/列表/
     * 链接/图片/引用/行内代码/表格系/span/视频（方案A直链）；*[style] 允许内联 style（内容受 CSS.AllowedProperties 限）。
     * 不放行 iframe/input/form/script/h1/h5/h6/pre/hr/u/s。与内容模块 ALLOWED 独立可调、互不影响。
     */
    private const RICHTEXT_ALLOWED = '*[style],p,br,span,strong,em,h2,h3,h4,ul,ol,li,a[href|title|target],img[src|alt],'
        . 'blockquote,code,table,thead,tbody,tr,td[colspan|rowspan],th[colspan|rowspan],video[src|controls]';

    /** 搭建器富文本内联 style 的 CSS 属性白名单（ADR-27 修订①）：仅文字/排版安全属性，剥 position/display/float/url()/expression() 等 */
    private const RICHTEXT_CSS_ALLOWED = ['color', 'background-color', 'font-size', 'font-family', 'line-height', 'text-align'];

    private static ?Purifier $purifier = null;

    private static ?Purifier $richtextPurifier = null;

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

    /**
     * 搭建器富文本块净化（ADR-27-①）：按 RICHTEXT_ALLOWED 收敛白名单过滤——
     * 剥离 script/iframe/style/form/on* 事件/javascript:|data: 协议等危险项；img/a 仅 http(s)/相对/mailto。
     * 与内容模块 clean() 用不同白名单 + 独立缓存实例，互不影响。
     */
    public static function cleanBuilderRichtext(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return self::richtextPurifier()->purify($html);
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

    private static function richtextPurifier(): Purifier
    {
        if (self::$richtextPurifier !== null) {
            return self::$richtextPurifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::RICHTEXT_ALLOWED);
        // 协议白名单：剥离 javascript:/data: 等；img/video src 限 http(s)/相对、a href 限 http(s)/mailto/相对
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        // 内联 style 限 CSS 属性白名单（修订①）：仅放行 6 个文字/排版属性，剥 position/display/float/url()/expression() 等逃逸向量
        $config->set('CSS.AllowedProperties', self::RICHTEXT_CSS_ALLOWED);
        // 不写缓存（Impl=null 同时使下方 getHTMLDefinition(true) 免 DefinitionID 即可生效）
        $config->set('Cache.DefinitionImpl', null);
        // 教 HTMLPurifier 认识 HTML5 <video>（默认不识别）：方案 A 直链，src 走 URI 类型 → 受协议白名单过滤（剥 javascript:/data:）
        $def = $config->getHTMLDefinition(true);
        $def->addElement('video', 'Block', 'Flow', 'Common', [
            'src'      => 'URI',
            'controls' => 'Bool',
        ]);

        return self::$richtextPurifier = new Purifier($config);
    }
}

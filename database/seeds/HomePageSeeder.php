<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — 默认官网首页（slug=home 八区块中英文案，进默认 seed:run，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// +----------------------------------------------------------------------

use app\common\model\Page;
use app\common\service\PageService;
use think\facade\Db;
use think\migration\Seeder;

/**
 * 默认首页页面数据（M6-B，ADR-21）：
 *  - 进默认 seed:run 链路（区别于 DemoContentSeeder 守门）——默认首页是底座门面的一部分，
 *    全新库 seed:run 后官网即有体面默认内容（守 §1）。
 *  - 幂等 find-or-skip：按 slug='home' 探测，已存在则跳过，不撞唯一键、不覆盖后续编辑。
 *  - blocks 内容取自 M6-A 首页中英文案（八区块），写入前过 PageService::validateBlocks 自校验
 *    （seeder 内容即合法样例，双重保障）。M6-D 接通后 Nuxt 改读 api 渲染接口。
 */
class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Db::name('page')->where(['tenant_id' => 0, 'slug' => 'home'])->whereNull('deleted_at')->find();
        if ($existing !== null) {
            echo "[HomePageSeeder] 跳过（slug=home 已存在）。\n";
            return;
        }

        $blocks = json_decode($this->blocksJson(), true);
        if (!is_array($blocks)) {
            echo "[HomePageSeeder] 跳过（内置 blocks JSON 解析失败）。\n";
            return;
        }

        // 自校验：seeder 内容即合法样例（与写入接口同一校验路径，双重保障）
        (new PageService(app()))->validateBlocks($blocks);

        $now = date('Y-m-d H:i:s');
        Db::name('page')->insert([
            'tenant_id'   => 0,
            'slug'        => 'home',
            'title'       => '官网首页',
            'status'      => Page::STATUS_PUBLISHED,
            'blocks'      => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'create_by'   => 0,
            'create_dept' => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        echo "[HomePageSeeder] 完成：灌入默认首页 slug=home（status=1，8 区块）。\n";
    }

    /**
     * 默认首页八区块（中英文案，逐字对齐 M6-A 首页）。
     */
    protected function blocksJson(): string
    {
        return <<<'JSON'
[
  {
    "type": "hero",
    "eyebrow": { "zh": "开源 · Apache-2.0 · 可商用", "en": "Open Source · Apache-2.0 · Commercial-Friendly" },
    "title": { "zh": "可运行的通用管理后台开源底座", "en": "A production-ready admin foundation, open source" },
    "subtitle": { "zh": "用户权限、代码生成器、通用业务一次到位——在它之上叠加你的业务，不再重复造轮子。", "en": "Auth, RBAC, a code generator, and common business modules out of the box. Build your product on top — stop reinventing the foundation." },
    "ctaPrimary": { "text": { "zh": "在 GitHub 上查看", "en": "View on GitHub" }, "href": "https://github.com/BenXinAdmin-PHP/benxin-admin-server" },
    "ctaSecondary": { "text": { "zh": "快速开始", "en": "Get Started" }, "href": "#get-started" }
  },
  {
    "type": "prose",
    "title": { "zh": "每个后台项目都在重复同样的开始", "en": "Every admin project starts by rebuilding the same thing" },
    "body": { "zh": "认证、权限、菜单、字典、日志、文件、支付、消息……这些公共部分占掉大量工期，却和真正的业务无关。BenXinAdmin 把它们固化为一个规范、安全、可运行的底座，让你从第一天就专注业务。", "en": "Authentication, permissions, menus, dictionaries, logs, files, payments, messaging — the plumbing eats your timeline yet has nothing to do with your actual product. BenXinAdmin hardens all of it into one consistent, secure, runnable foundation, so you can focus on the business from day one." }
  },
  {
    "type": "feature-grid",
    "title": { "zh": "一个底座，覆盖后台的公共能力", "en": "One foundation, all the admin essentials" },
    "items": [
      { "icon": "code", "title": { "zh": "代码生成器", "en": "Code Generator" }, "desc": { "zh": "读表结构一键生成后端四件套 + 前端列表/表单/分配弹窗，严格复刻手写黄金样板的规范与注释。", "en": "Read a table schema and generate the backend quartet plus frontend list/form/assign dialogs — faithfully replicating the hand-written golden template, conventions and all." } },
      { "icon": "shield", "title": { "zh": "角色权限 + 数据权限", "en": "RBAC + Data Scope" }, "desc": { "zh": "Casbin 驱动的权限到按钮级，叠加全部/本部门/本部门及以下/仅本人/自定义五档数据范围。", "en": "Casbin-driven permissions down to the button level, plus five data-scope tiers: all / department / department-and-below / self / custom." } },
      { "icon": "key", "title": { "zh": "双端认证", "en": "Dual-Guard Auth" }, "desc": { "zh": "后台与 C 端各自独立 guard、独立密钥、JWT 双令牌，Valkey 白黑名单收口。", "en": "Separate guards and secrets for admin and client, dual-token JWT, with allow/deny lists backed by Valkey." } },
      { "icon": "storage", "title": { "zh": "多路存储", "en": "Pluggable Storage" }, "desc": { "zh": "按媒体类型路由本地 / 阿里 OSS / 七牛 / 腾讯 VOD，云能力默认关闭，纯本地零配置即可跑。", "en": "Route by media type across local / Aliyun OSS / Qiniu / Tencent VOD. Cloud is off by default — runs fully on local storage with zero config." } },
      { "icon": "blocks", "title": { "zh": "通用业务框架", "en": "Business Building Blocks" }, "desc": { "zh": "内容、支付、消息、微信能力均已框架化，密钥加密入库、回调安全四件套、事件解耦上层业务。", "en": "Content, payments, messaging, and WeChat are framework-level: encrypted secrets, a four-part callback-security pattern, and events that decouple your business logic." } },
      { "icon": "phone", "title": { "zh": "C 端懒登录", "en": "Lazy Login for Clients" }, "desc": { "zh": "uni-app 一套代码输出小程序 + H5，懒登录、登录即注册，微信 + 手机号双端登录流。", "en": "One uni-app codebase ships Mini Program + H5, with lazy login, login-as-registration, and WeChat + phone flows across both." } }
    ]
  },
  {
    "type": "moat",
    "title": { "zh": "先手写样板，再让生成器复刻", "en": "Golden template first. The generator replicates it." },
    "body": { "zh": "我们不追求“生成器无所不能”，而是先手写规范到极致的黄金样板，再让生成器逐字复刻。仓库内置防污染基线与保真回归脚本——任何人 clone 下来都能一键验证“生成 == 手写”，护城河自证，而非自说自话。", "en": "We don't chase a do-everything generator. We hand-write a golden template polished to the last detail, then make the generator replicate it character-for-character. The repo ships an anti-drift baseline and a fidelity-regression script — clone it and verify \"generated == hand-written\" in a single command. The moat proves itself." },
    "verifyCaption": { "zh": "保真回归：通过", "en": "Fidelity regression: passing" }
  },
  {
    "type": "security",
    "title": { "zh": "安全是底线，不是话术", "en": "Security as a baseline, not a tagline" },
    "body": { "zh": "每个模块按统一安全清单验收：ORM 参数化杜绝拼接 SQL、字段白名单防批量赋值、上传真实 MIME + 扩展名双重校验、敏感接口独立限流、三方密钥 AES 加密入库、富文本后端净化 + 前端转义、日志脱敏、操作全审计。", "en": "Every module ships against one security checklist: parameterized ORM with zero string-built SQL, field allowlists against mass assignment, real-MIME + extension double-checks on upload, dedicated rate limits on sensitive endpoints, AES-encrypted third-party secrets, server-side rich-text sanitizing plus front-end escaping, log redaction, and full audit trails." },
    "chips": [
      { "zh": "双 guard 认证隔离", "en": "Isolated dual-guard auth" },
      { "zh": "Casbin + 数据权限五档", "en": "Casbin + 5-tier data scope" },
      { "zh": "密钥 AES 入库 · git 0 泄露", "en": "AES-encrypted secrets · zero git leaks" },
      { "zh": "ORM 参数化 + 白名单", "en": "Parameterized ORM + allowlists" },
      { "zh": "XSS 双重防护", "en": "Double-layer XSS defense" },
      { "zh": "上传双重校验", "en": "Double-checked uploads" },
      { "zh": "日志脱敏", "en": "Log redaction" },
      { "zh": "回调安全四件套", "en": "Four-part callback security" }
    ]
  },
  {
    "type": "badge-list",
    "title": { "zh": "现代、稳定、可商用的技术选型", "en": "A modern, stable, commercial-friendly stack" },
    "caption": { "zh": "全部开源可商用，零 AGPL 传染。", "en": "All open source and commercial-friendly, with zero AGPL contamination." },
    "items": [
      { "label": "ThinkPHP 8" }, { "label": "Vue 3" }, { "label": "Element Plus" }, { "label": "uni-app" },
      { "label": "MySQL 8" }, { "label": "Valkey" }, { "label": "Casbin" }, { "label": "TypeScript" }
    ]
  },
  {
    "type": "showcase",
    "title": { "zh": "开箱即用的后台与 C 端", "en": "Admin and client, ready out of the box" },
    "items": [
      { "caption": { "zh": "五套可切换主题 + 明暗模式", "en": "Five switchable themes + light/dark" }, "image": "" },
      { "caption": { "zh": "Bento 数据仪表盘", "en": "Bento data dashboard" }, "image": "" },
      { "caption": { "zh": "C 端小程序门面", "en": "Mini Program storefront" }, "image": "" }
    ]
  },
  {
    "type": "cta",
    "title": { "zh": "自由使用，欢迎 Star", "en": "Free to use. Stars welcome." },
    "body": { "zh": "采用 Apache-2.0（含专利授权），可自由用于商业项目。底座与基础代码生成器完全开源。", "en": "Licensed under Apache-2.0 (with patent grant) — free for commercial use. The foundation and the base code generator are fully open source." },
    "buttons": [
      { "text": { "zh": "在 GitHub 上 Star", "en": "Star on GitHub" }, "href": "https://github.com/BenXinAdmin-PHP/benxin-admin-server", "variant": "primary" },
      { "text": { "zh": "在 Gitee 上查看", "en": "View on Gitee" }, "href": "https://gitee.com/binxin-admin/benxin-admin-server", "variant": "secondary" }
    ],
    "quickstart": "git clone https://github.com/BenXinAdmin-PHP/benxin-admin-server.git\ncomposer install\ncp .env.example .env\nphp think migrate:run && php think seed:run\nphp think run -p 8801"
  }
]
JSON;
    }
}

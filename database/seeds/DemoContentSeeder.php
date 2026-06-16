<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   种子 — C 端演示数据（内容分类 + 演示文章，默认关闭，幂等）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-16 10:00:00
// | @updated   2026-06-16（封面同步 PM 自绘素材 cover-*.png）
// +----------------------------------------------------------------------

use app\common\library\HtmlPurifier;
use think\facade\Db;
use think\migration\Seeder;

/**
 * C 端演示门面数据（独立可选，默认不随 seed:run 全量链路播种）：
 *  - 守门：仅 .env 的 DEMO_SEED_ENABLE=true 才播种，否则直接跳过并 echo（守底座铁律 §1：
 *    生产默认干净，演示数据是可选扩展，不污染全新库）。
 *  - 幂等 find-or-skip：分类按 name、文章按 title 命中即跳过，连跑两次零重复、零撞唯一键。
 *  - 内容：3 个内容分类（入门/架构/安全）+ 6 篇讲 BenXinAdmin 自身的演示文章
 *    （功能/护城河/架构/安全/快速开始，纯自我介绍零版权风险），status=已发布、
 *    publish_at 设过去时间、其中 2 篇置顶（让首页精选有置顶优先效果）。
 *  - 正文走既有净化路径 HtmlPurifier::clean（与 admin ContentService 同源，§8 XSS 二次防护）。
 *  - 封面写前端可解析路径 /static/demo/covers/cN.jpg（uniapp resolveMedia 解析，素材随 uniapp 仓打包）。
 *
 * 启用播种：.env 置 DEMO_SEED_ENABLE=true 后 `php think seed:run -s DemoContentSeeder`。
 * 清理：删 bx_content 中本批 title 行 + bx_content_category 中三分类行（软删字段 deleted_at）。
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        // 守门：默认关闭。env 兼容 true/false/1/0/字符串，统一按布尔解析。
        if (!filter_var(env('DEMO_SEED_ENABLE', false), FILTER_VALIDATE_BOOLEAN)) {
            echo "[DemoContentSeeder] 跳过（演示数据默认关闭；置 .env DEMO_SEED_ENABLE=true 后再 seed:run -s DemoContentSeeder 播种）。\n";
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 1) 内容分类（幂等，按 name 命中跳过）
        $categoryDefs = [
            ['name' => '入门', 'sort' => 1],
            ['name' => '架构', 'sort' => 2],
            ['name' => '安全', 'sort' => 3],
        ];
        $catId = [];
        foreach ($categoryDefs as $c) {
            $id = (int) Db::name('content_category')
                ->where(['tenant_id' => 0, 'name' => $c['name']])->whereNull('deleted_at')->value('id');
            if ($id === 0) {
                $id = (int) Db::name('content_category')->insertGetId([
                    'tenant_id'  => 0,
                    'parent_id'  => 0,
                    'name'       => $c['name'],
                    'sort'       => $c['sort'],
                    'status'     => 1,
                    'icon'       => '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $catId[$c['name']] = $id;
        }

        // 2) 演示文章（幂等，按 title 命中跳过）。publish_at 取过去时间（越靠前的发布越早）。
        $articles = [
            [
                'cat'   => '入门',
                'title' => '5 分钟跑通 BenXinAdmin 开发底座',
                'cover' => '/static/demo/covers/cover-quickstart.png',
                'summary' => '从克隆仓库到登录后台，一条龙跑通 Docker 依赖、迁移与种子、本地裸装两种姿势，零门槛上手。',
                'is_top' => 1,
                'days'  => 12,
                'content' => '<h3>三步跑起来</h3><p>BenXinAdmin 是一套开箱即用的通用管理后台底座，纯本地零配置即可完整运行。</p>'
                    . '<ul><li><strong>起依赖</strong>：<code>docker compose up -d</code> 拉起 MySQL 8 与 Valkey；</li>'
                    . '<li><strong>建表灌种</strong>：<code>php think migrate:run</code> + <code>php think seed:run</code>；</li>'
                    . '<li><strong>启服务</strong>：<code>php think run -p 8801</code>，浏览器登录后台即见 Bento 首页。</li></ul>'
                    . '<p>不想用 Docker？README 另附本地裸装 MySQL/Valkey 的「方式二」，照抄即可。</p>',
            ],
            [
                'cat'   => '入门',
                'title' => '三种范式 + 数据权限：代码生成器护城河速览',
                'cover' => '/static/demo/covers/cover-codegen.png',
                'summary' => 'bx:make 一条命令复刻纯 CRUD / 树形 / 授权链路三类范式，数据权限作为横切能力按需挂载。',
                'is_top' => 0,
                'days'  => 10,
                'content' => '<h3>复刻范式，而非复制逻辑</h3><p>代码生成器是 BenXinAdmin 的护城河：输入一张表，输出前后端全套规范产物。</p>'
                    . '<ul><li><strong>纯 CRUD</strong>：列表 + 表单 + 校验 + 路由一把出；</li>'
                    . '<li><strong>树形</strong>：parent_id 自识别，treeSelect 与子树护栏到位；</li>'
                    . '<li><strong>授权链路</strong>：角色分配菜单 + Casbin 策略同步。</li></ul>'
                    . '<p>数据权限（五档）是横切特性，任何模块按需挂 applyDataScope，不必为它单独造范式。</p>',
            ],
            [
                'cat'   => '架构',
                'title' => 'ThinkPHP8 多应用 + Vue3 + uni-app：一套底座三端通吃',
                'cover' => '/static/demo/covers/cover-multiend.png',
                'summary' => '后端多应用分 admin/api，后台 Vue3 + Element Plus，C 端 uni-app 一码出小程序与 H5。',
                'is_top' => 1,
                'days'  => 8,
                'content' => '<h3>三仓协同</h3><p>BenXinAdmin 由三仓组成，职责清晰、各自独立演进。</p>'
                    . '<ul><li><strong>server</strong>：ThinkPHP 8 多应用，<code>/admin</code> 与 <code>/api</code> 各自 guard 与 JWT 密钥；</li>'
                    . '<li><strong>web</strong>：Vue3 + Element Plus，XTable / XFormDrawer 配置化黄金样板；</li>'
                    . '<li><strong>uniapp</strong>：一套代码出微信小程序 + H5，懒登录、双端登录流内置。</li></ul>'
                    . '<p>统一返回信封、request_id 全链路贯穿、错误码分段，前后端协作无歧义。</p>',
            ],
            [
                'cat'   => '架构',
                'title' => '多路存储与 VOD：素材模块如何默认本地、按需上云',
                'cover' => '/static/demo/covers/cover-datascope.png',
                'summary' => '按 media_type 路由本地 / 阿里 OSS / 七牛 / 腾讯 VOD，云能力默认关闭，纯本地零配置即可跑通。',
                'is_top' => 0,
                'days'  => 6,
                'content' => '<h3>默认本地，可选上云</h3><p>素材模块守底座铁律：不默认强依赖任何付费云服务。</p>'
                    . '<ul><li><strong>路由</strong>：图片走本地/七牛，音视频走本地/VOD，文档走本地/OSS；</li>'
                    . '<li><strong>回退</strong>：未开通云配置自动回退本地，默认态绝不挂掉；</li>'
                    . '<li><strong>大文件</strong>：视频走客户端直传 + 转码回调，绕开 PHP 限额。</li></ul>'
                    . '<p>云访问一律私有 bucket + 签名 URL，不裸公网直链。</p>',
            ],
            [
                'cat'   => '安全',
                'title' => '八项安全基线：参数化 / 白名单 / AES / 限流如何落地',
                'cover' => '/static/demo/covers/cover-security.png',
                'summary' => '每个模块的验收硬指标：ORM 参数化、字段白名单防批量赋值、三方密钥 AES 入库、敏感接口限流。',
                'is_top' => 0,
                'days'  => 4,
                'content' => '<h3>安全是验收门，不是事后补丁</h3><p>BenXinAdmin 把安全基线固化为每个模块的硬指标。</p>'
                    . '<ul><li><strong>注入</strong>：全程 ORM / 查询构造器参数化，杜绝拼接 SQL；</li>'
                    . '<li><strong>批量赋值</strong>：字段白名单 FILLABLE 收敛，物理字段服务端写；</li>'
                    . '<li><strong>密钥</strong>：支付 / 短信 / OSS 密钥 AES 加密入库，展示脱敏；</li>'
                    . '<li><strong>限流</strong>：登录 / 短信等敏感接口单独限流。</li></ul>'
                    . '<p>富文本统一经 HTMLPurifier 白名单净化，前端 rich-text 不执行脚本，双重防 XSS。</p>',
            ],
            [
                'cat'   => '安全',
                'title' => '懒登录 + 双端登录流：C 端认证是怎么设计的',
                'cover' => '/static/demo/covers/cover-rbac.png',
                'summary' => '浏览免登录、核心操作才拦截；小程序 code2session + 手机号，H5 公众号 oauth + 短信验证码。',
                'is_top' => 0,
                'days'  => 2,
                'content' => '<h3>懒登录：体验与安全兼得</h3><p>C 端不强制先登录，浏览内容零打扰，核心操作才触发登录守卫。</p>'
                    . '<ul><li><strong>小程序</strong>：wx.login → code2session 拿 openid，新用户 getPhoneNumber 换手机号；</li>'
                    . '<li><strong>H5 公众号</strong>：oauth 拿 openid，新用户短信验证码补手机号；</li>'
                    . '<li><strong>登录即注册</strong>：openid + mobile 缺一不可，token 双令牌 + 401 单飞续期。</li></ul>'
                    . '<p>后台与 C 端各自独立 guard 与 JWT 密钥，互不串号。</p>',
            ],
        ];

        $seeded = 0;
        foreach ($articles as $i => $a) {
            $exists = Db::name('content')
                ->where(['tenant_id' => 0, 'title' => $a['title']])->whereNull('deleted_at')->find();
            if ($exists !== null) {
                continue;
            }
            Db::name('content')->insert([
                'tenant_id'   => 0,
                'category_id' => $catId[$a['cat']] ?? 0,
                'title'       => $a['title'],
                'cover'       => $a['cover'],
                'summary'     => $a['summary'],
                'content'     => HtmlPurifier::clean($a['content']),
                'author'      => 'BenXinAdmin',
                'source'      => '官方演示',
                'status'      => 1,
                'is_top'      => $a['is_top'],
                'sort'        => $i + 1,
                'view_count'  => 0,
                'publish_at'  => date('Y-m-d H:i:s', time() - $a['days'] * 86400),
                'create_by'   => 0,
                'create_dept' => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $seeded++;
        }

        echo "[DemoContentSeeder] 完成：分类 3、演示文章新增 {$seeded} 篇（幂等，已存在的跳过）。\n";
    }
}

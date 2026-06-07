# BenXinAdmin · 架构基线与约定文档

> **版本**：v1.0 ｜ **最后更新**：2026-06-07 ｜ **维护**：项目经理/架构师（Claude）+ 决策人 仗键天涯(daxing)
>
> **本文档用途**：这是 BenXinAdmin 项目的"纲领文件"，固化所有架构决策与开发约定。
> **跨会话使用方式**：每开一个新对话，把本文件完整贴给项目经理（Claude），即可无缝续接项目，无需重述背景。本文件应放入仓库 `benxin-admin-server/docs/ARCHITECTURE.md` 并随项目同步更新。

---

## 1. 项目概述

| 项 | 内容 |
|---|---|
| 项目英文名 | **BenXinAdmin** |
| 中文名 | 本心通用管理后台底座 |
| 定位 | **可运行的通用管理后台开源底座**，供后续各类项目（打卡、商城、知识付费等）在其上叠加业务，避免重复开发公共部分 |
| 与"知识付费" | **无关**。知识付费等具体业务一律作为独立的、闭源的上层项目叠加，本仓库不含 |
| 商业模式 | 底座 + 基础代码生成器**开源**（引流、攒 star、接单背书）；高级生成器/企业模板/具体业务**闭源** |
| 开源协议 | **Apache-2.0**（带专利授权条款，比 MIT 更适合商用引流） |
| 开源边界 | 开源仓库只放：用户/权限/系统管理 + 基础代码生成器 + 通用业务（内容/支付框架/消息/微信配置）。支付等三方**密钥一律加密存储，不进仓库** |

---

## 2. 关键架构决策记录（ADR）

| # | 决策 | 结论 |
|---|---|---|
| ADR-1 | 租户模型 | **默认单租户**。但在基类、查询作用域、Casbin 上**预留 domain/tenant 维度（钩子留好，默认不启用）**，通过配置开关 `app.multi_tenant`（默认 `false`）一键切多租户。业务表统一预留 `tenant_id`（默认 0） |
| ADR-2 | 认证 | 后台与 C 端**分离的双 guard、独立密钥**；均用 **JWT 双令牌（access + refresh）** |
| ADR-3 | C 端登录策略 | **懒登录**：用到核心业务前不强制登录（含"我的"页）。登录即注册，微信 + 手机号缺一不可 |
| ADR-4 | C 端报文加密 | BenXin 那套 C 端 AES-256-CBC 报文加密做成**可配置开关，默认关**，需要时开 |
| ADR-5 | 数据库结构管理 | 用 **think-migration + Seeder**，禁止散落裸 SQL；初始数据（超管/菜单/字典/Casbin 策略）走 Seeder |
| ADR-6 | 代码生成器时序 | **约定先行**：先手写 2~3 个标准 CRUD 模块沉淀"黄金样板"，**再**让生成器复刻样板。不可反序 |
| ADR-7 | 缓存选型 | **Valkey（BSD）** 为默认，规避 Redis 8 的 AGPLv3 争议；协议兼容，PHP 客户端无缝 |

---

## 3. 技术栈定稿（截至 2026-06）

### 后端 `benxin-admin-server`
| 组件 | 选型 | 版本/说明 |
|---|---|---|
| PHP | PHP **8.4** | ThinkPHP 8.1.4 已支持到 PHP 8.5，未来可平滑升 |
| 框架 | ThinkPHP **8.1.4** | Apache-2.0 |
| 数据库 | MySQL **8** | InnoDB / utf8mb4 |
| 缓存 | **Valkey** 最新 | BSD-3，Redis 协议兼容 |
| Web | Nginx | 生产裸机 + 宝塔 + SafeLine（沿用现有） |
| 鉴权 | JWT 双令牌 + **php-casbin** | RBAC，domain 维度预留 |
| 迁移 | think-migration + Seeder | — |
| 限流 | think-throttle | — |
| API 文档 | Apifox（主）/ OpenAPI 注解 | 前端据 OpenAPI 自动生成调用文件 |

### 管理后台 `benxin-admin-web`
| 组件 | 选型 | 版本 |
|---|---|---|
| Node | 24 | — |
| Vue | **3.5+** | 当前 3.x 稳定线 |
| 构建 | Vite **7/8**（跟随脚手架默认，勿锁 5） | Vite 8 为 Rolldown 内核 |
| UI | Element Plus **2.14.x** | — |
| 语言 | TypeScript | 强制 |
| 状态/路由 | Pinia + Vue Router 4 | — |
| 原子 CSS | **UnoCSS** | — |
| HTTP | Axios（封装）+ OpenAPI 自动生成 API | — |
| 工程参考 | vue-pure-admin / vue-vben-admin(5.x EP 版) | 仅借鉴结构，不直接用作底座 |

### C 端 `benxin-admin-uniapp`
| 组件 | 选型 |
|---|---|
| 框架 | uni-app + Vue3 + TS + Vite |
| UI | **wot-design-uni**（MIT，TS 优先） |
| 状态/请求 | Pinia + 封装 `uni.request` |
| 产物 | 一套代码输出 **微信小程序 + H5**（H5 主要跑在公众号环境内） |

### 官网（延后）
- Nuxt **4.x**，放到最后 M6，前期可不做。

---

## 4. 仓库与目录约定

三个**独立仓库**，均 **GitHub + Gitee 双开双推**：

1. `benxin-admin-server`（ThinkPHP 8 后端，含代码生成器）
2. `benxin-admin-web`（Element Plus 后台）
3. `benxin-admin-uniapp`（C 端）

### 后端目录骨架（ThinkPHP 8 多应用）
```
benxin-admin-server/
├── app/
│   ├── common/            # 公共：基类/中间件/异常/Trait/工具
│   │   ├── base/          # BxController / BxModel / BxService / BxValidate
│   │   ├── middleware/    # JWT鉴权 / Casbin / 操作日志 / 限流 / CORS
│   │   ├── exception/     # 统一异常处理器
│   │   └── library/       # JWT / 加密 / 统一响应封装
│   ├── admin/             # 后台应用，对外前缀 /admin
│   └── api/               # C端应用，对外前缀 /api
├── config/
├── database/
│   ├── migrations/        # think-migration
│   └── seeds/             # 初始数据
├── extend/
│   └── generator/         # 代码生成器（M3）
├── public/
├── docs/
│   └── ARCHITECTURE.md    # ← 本文件
├── .env.example
├── docker-compose.yml     # 仅 MySQL + Valkey
├── composer.json
├── LICENSE                # Apache-2.0
└── README.md
```

### 前端骨架（关键目录）
```
benxin-admin-web/src/
├── api/          # OpenAPI 自动生成的调用文件
├── components/   # 含 XTable 等配置化 CRUD 组件
├── directives/   # v-permission 按钮级权限
├── layouts/      # 含多标签页布局
├── locales/      # i18n（初期只做中文，框架预留）
├── router/       # 动态路由（菜单从后端拉取）
├── stores/       # Pinia
└── utils/        # axios 封装 / 主题切换
```

---

## 5. 命名与注释约定

### 5.1 数据库
- **表前缀**：`bx_`，表名 **snake_case 单数**（如 `bx_admin`、`bx_role`、`bx_menu`、`bx_dept`、`bx_post`、`bx_dict`、`bx_config`、`bx_oper_log`、`bx_login_log`、`bx_file`，Casbin 规则表 `bx_casbin_rule`）。
- **统一时间字段**：`created_at` / `updated_at` / `deleted_at`（datetime），软删除走 `deleted_at`。
- **多租户预留**：业务表统一带 `tenant_id`（unsigned bigint，默认 0；单租户模式下恒为 0）。
- 字段命名 snake_case；金额用整型分或 decimal；状态用 tinyint + 字典。

### 5.2 PHP
- 命名空间沿用 TP8：`app\admin`、`app\api`、`app\common`。
- 类命名 PascalCase：`XxxController` / `Xxx`(Model) / `XxxService` / `XxxValidate`。
- **基类前缀 `Bx`**：`BxController` / `BxModel` / `BxService` / `BxValidate`，统一收口响应、软删除、租户作用域。

### 5.3 文件注释头（强制，生成器也按此产出）
**PHP：**
```php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   <中文模块名 — METHOD /api/路径（接口类才写路径）>
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      <创建时间 YYYY-MM-DD HH:mm:ss>
// | @updated   <修改时间，仅在修改既有文件时追加此行>
// +----------------------------------------------------------------------
```
**JS / TS / Vue（等价）：**
```ts
/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   <中文模块名>
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      <创建时间>
 * | @updated   <修改时间，仅修改时追加>
 * +----------------------------------------------------------------------
 */
```

---

## 6. API 规范（业务码风格 A）

### 6.1 统一返回结构
```json
{
  "code": 0,
  "msg": "success",
  "data": null,
  "request_id": "uuid",
  "timestamp": 1717000000
}
```
- `code = 0` 表示成功，非 0 表示业务/系统错误。
- HTTP 默认 200，业务结果以 `code` 为准；鉴权类（未登录/过期）**允许返回 HTTP 401** 便于前端拦截器统一处理（M1 与前端约定时定稿）。

### 6.2 错误码段
| 段 | 含义 | 示例 |
|---|---|---|
| `0` | 成功 | — |
| `400xxx` | 请求/参数错误 | 400000 |
| `401xxx` | 认证 | 401001 未登录、401003 token 过期、401004 刷新失效 |
| `403xxx` | 权限不足 | 403000 |
| `404xxx` | 资源不存在 | 404000 |
| `422xxx` | 业务校验失败 | 422000 |
| `429xxx` | 触发限流 | 429000 |
| `500xxx` | 服务端错误 | 500000 |
| 业务段 | 各模块分配 | 内容 11xxxx、支付 12xxxx、消息 13xxxx、微信 14xxxx |

### 6.3 路由与分页
- 后台 `/admin/v1/...`，C 端 `/api/v1/...`（多应用前缀 + 路由分组版本）。
- 分页统一：`data: { list: [], total, page, page_size }`。
- 路由顺序约定：**具体 action > `/:id` > 集合**；更新用 `$request->has()` 判断字段是否参与更新。

---

## 7. 认证与权限
- 后台、C 端各自独立 guard + 独立 JWT 密钥。
- access token 短期（建议 2h）、refresh 长期（建议 14d）；refresh 白名单与登出黑名单（jti）存 Valkey，至 exp 过期。
- **Casbin RBAC**：model 预留 domain（= tenant_id）维度，单租户时使用统一 domain。权限粒度到接口/按钮。
- C 端懒登录；登录即注册，微信 + 手机号缺一不可。

---

## 8. 安全基线（每个模块的验收硬指标）
- [ ] 全程 ORM/查询构造器参数化，**杜绝拼接 SQL**。
- [ ] 字段白名单防批量赋值；输出转义防 XSS。
- [ ] 上传：真实 MIME + 扩展名白名单 + 重命名 + 存非 Web 可执行目录。
- [ ] 接口限流（think-throttle）；登录/短信等敏感接口单独限流。
- [ ] JWT 短期 + refresh + 登出黑名单；密码 `password_hash`（Argon2id/bcrypt）。
- [ ] 后台敏感操作二次校验；全量操作审计日志（`bx_oper_log`）。
- [ ] 三方密钥（支付/短信/OSS）**AES 加密入库**，密钥放 `.env`，展示脱敏。
- [ ] 关闭生产 `APP_DEBUG`；统一异常处理不泄露堆栈。

---

## 9. 配置约定（后台可配项）
统一进 `bx_config`（分组 + key-value），敏感值 AES 加密，加密密钥取自 `.env` 的 `CONFIG_CRYPT_KEY`。后台可配：
- H5 公众号 appid/secret、小程序 appid/secret、企业微信。
- 微信支付、支付宝支付配置。
- 短信渠道（阿里/腾讯）切换与配置。
- 文件存储（AliOSS / 七牛）与音视频存储切换与配置。

---

## 10. 字体 / 图标 / 图片合规（只用开源/可商用）
- **字体**：优先系统字体栈（不嵌入）；需品牌字时用 HarmonyOS Sans / MiSans / 思源黑体(SIL OFL)，拉丁字用 Inter(OFL)。**禁用**方正/汉仪/华康等商用字库。
- **图标**：Element Plus Icons(MIT) + Iconify 开源集（Remix Apache-2.0 / Tabler MIT / Lucide ISC）。慎用 iconfont 来源不明图标。
- **图片**：自有 / CC0(Unsplash、Pexels) / AI 生成；禁用站酷、包图网等。

---

## 11. 代码生成器约定（护城河，M3）
- 输入：数据库表结构（或元数据配置）。
- 输出：后端 Model/Controller/Service/Validate/路由 + 前端列表页/新增编辑表单/API 调用文件 + 菜单与权限的 migration/seeder。
- **必须严格复刻第 5/6/7/8 节的所有约定与注释头**（黄金样板原则）。

---

## 12. 本地开发环境
- **依赖（MySQL + Valkey）跑容器**：Mac 上用 **OrbStack**（省内存、Apple Silicon 原生、个人免费）。仓库附 `docker-compose.yml` 仅编排这两个依赖，`docker compose up -d` 即起。
- **PHP-FPM/`php think run` 本地原生跑**（调试/Xdebug 体验更好）。Node 24 跑 web/uniapp。
- **生产不用 Docker**，沿用裸机 + 宝塔 + SafeLine。

---

## 13. Git 与协作流程

### 13.1 命名权威性（重要，易踩坑）
- **项目内一切统一用 `BenXinAdmin` / `benxin` / `bx_`**（项目名、命名空间、`@project` 注释头、表前缀、Composer 包名、README 标题等），这是**唯一权威拼写**。
- **Git 仓库地址是例外**：实际仓库名与项目名不一致，且 **Gitee（binxin）与 GitHub（benxin）两边仓库名也不一致**，保持现状不改。**任何代码/文档/注释一律写 `BenXinAdmin`，只有 `git remote` 地址按下表实际值。**

### 13.2 实际仓库地址（双推用）
| 仓库 | Gitee（binxin） | GitHub（benxin） |
|---|---|---|
| 后端 | `https://gitee.com/binxin-admin/binxin-admin-server.git` | `https://github.com/BenXinAdmin-PHP/benxin-admin-server.git` |
| 后台前端 | `https://gitee.com/binxin-admin/binxin-admin-web.git` | `https://github.com/BenXinAdmin-PHP/benxin-admin-web.git` |
| C 端 | `https://gitee.com/binxin-admin/binxin-admin-uniapp.git` | `https://github.com/BenXinAdmin-PHP/benxin-admin-uniapp.git` |

### 13.3 流程
- 双远程（Gitee + GitHub）双推。
- 分支：`main`（稳定）/ `dev`（开发）/ `feature/*`。
- 提交规范：Conventional Commits（feat/fix/docs/refactor/chore/test/build）。
- **分工**：项目经理(Claude)出可复制 Markdown 任务书与架构决策；**Claude Code** 负责编码 + API 测试 + Git 提交；**daxing** 负责主要决策 + 浏览器/小程序测试。
- 每完成一个任务，Claude Code 须回填**可复制 Markdown 完成报告**（模板见各任务书）。

---

## 14. 模块进度看板
| 阶段 | 内容 | 状态 |
|---|---|---|
| **M0** | 约定与脚手架（三仓库初始化、骨架、统一返回/异常/中间件、migration、依赖容器、本文件落地） | 🔵 进行中 |
| M1 | 认证 + RBAC（JWT 双令牌、Casbin、管理员/角色/权限/菜单/部门/岗位） | ⚪ 未开始 |
| M2 | 系统管理（字典/参数/操作日志/登录日志/文件管理） | ⚪ 未开始 |
| M3 | 代码生成器 | ⚪ 未开始 |
| M4 | 通用业务（内容/支付/消息/微信配置） | ⚪ 未开始 |
| M5 | C 端 uni-app（登录/首页/我的，懒登录） | ⚪ 未开始 |
| M6 | （可选）官网 + 首页拖拽搭建 | ⚪ 未开始 |

> 状态图例：⚪ 未开始 ｜ 🔵 进行中 ｜ ✅ 已完成 ｜ ⏸ 暂停

---

## 15. 跨会话续接说明
新对话开始时：
1. 把本文件完整贴给项目经理(Claude)。
2. 说明当前进行到哪个 Mxx，以及上一个完成报告的关键结论。
3. 项目经理据此继续出任务书或做架构决策，并同步更新本文件第 14 节看板。

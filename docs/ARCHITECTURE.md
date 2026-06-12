# BenXinAdmin · 架构基线与约定文档

> **版本**：v2.1 ｜ **最后更新**：2026-06-09 ｜ **维护**：项目经理/架构师（Claude）+ 决策人 仗键天涯(daxing)
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
| ADR-5 | 数据库结构管理 | 用 **think-migration + Seeder**，禁止散落裸 SQL；初始数据（超管/菜单/字典/Casbin 策略）走 Seeder。**各表迁移随其所属阶段产出，M0 不预建核心表**（M0 仅 bx_config；bx_admin/role/menu/dept/post/casbin_rule 等核心表归 M1，bx_dict/oper_log/login_log/file 归 M2） |
| ADR-6 | 代码生成器时序 | **约定先行**：先手写 2~3 个标准 CRUD 模块沉淀"黄金样板"，**再**让生成器复刻样板。不可反序 |
| ADR-7 | 缓存选型 | **Valkey（BSD）** 为默认，规避 Redis 8 的 AGPLv3 争议；协议兼容，PHP 客户端无缝 |
| ADR-8 | M1 JWT 库选型 | 用 **lcobucci/jwt**（BSD-3，支持 PHP 8.2~8.5）作底层令牌库，**自建 `BxJwt` 服务层**承载双 guard / 双令牌 / Valkey 白黑名单。不用 firebase/php-jwt（太底层），不用 thans·xiaodi 等 TP 全家桶中间件（其黑名单偏 SSO 单点语义、逻辑藏第三方包不利于黄金样板复刻与长期可控）。签名算法 **HS256 起步**，双 guard 各自独立 secret 入 `.env`（`JWT_ADMIN_SECRET` / `JWT_API_SECRET`，≥32 字节随机）；未来需跨服务校验再平滑切 RS256。Casbin 仅负责 RBAC，JWT 库不碰权限 |
| ADR-9 | 数据权限模型（M1-D） | `data_scope` 范围枚举 **1 全部 / 2 本部门 / 3 本部门及以下 / 4 仅本人 / 5 自定义**；多角色取**最宽**（任一为全部则全部；否则各角色 dept 可见集合求并集；全为仅本人才限本人）。"本部门及以下"用 MySQL8 **递归 CTE** 实时查子树（零冗余、移动部门免维护路径）；自定义范围存关联表 **`bx_role_dept`**。过滤注入点为 `BxModel`/`BxService` 的**数据范围作用域**，模块**按需开启**（非全局强制）。业务表启用数据权限须带 `create_by`(+可选 `create_dept`)；核心表 `bx_admin` 用自身 `dept_id`、"仅本人"用 `id`。M1-D 在 `bx_admin` 列表上示范全五档 |
| ADR-10 | M3 生成器技术选型 | ① 模板引擎用 **stub 文件 + 占位符替换**（模板即黄金样板代码、可读易维护，stub 由 M1/M2 已沉淀样板抽取）；② 输入源 = **表结构（information_schema 反读字段）+ 模块元数据**（中文名 / perms 前缀 / 是否树形 / 是否挂数据权限 / 字段属性：列表显示·查询条件·必填·敏感）；③ 产物**分阶段**——M3 先**后端四件套**(Model/Controller/Service/Validate)+ 路由 + 菜单 perms seeder，前端产物与 migration 后续；④ 形态 = **CLI 命令**（`php think bx:make` 风），可视化界面后续；⑤ 生成安全：默认**不覆盖**已存在文件 + `--force`；⑥ 首个复刻目标 = **post 纯 CRUD**（验证"生成 == 手写黄金样板"保真），再扩树形(dept/menu)、授权(role)。生成产物**严格复刻 §5/6/7/8 注释头与全部范式**（ADR-6） |

---

## 3. 技术栈定稿（截至 2026-06）

### 后端 `benxin-admin-server`
| 组件 | 选型 | 版本/说明 |
|---|---|---|
| PHP | PHP **8.4** | ThinkPHP 8.1.4 已支持到 PHP 8.5，未来可平滑升 |
| 框架 | ThinkPHP **^8.1**（实测装 8.1.2；8.1.4 上架后随 composer update 跟进） | Apache-2.0 |
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
| 状态/路由 | Pinia + Vue Router（4/5 均可，跟随脚手架，当前实测 5.x） | — |
| 原子 CSS | **UnoCSS** | — |
| HTTP | Axios（封装）+ OpenAPI 自动生成 API | — |
| 工程参考 | vue-pure-admin / vue-vben-admin(5.x EP 版) | 仅借鉴结构，不直接用作底座 |

> M0-B 实测锁定版本（均跟随脚手架，未手动锁）：Vue 3.5.x / Vite 8.x / Element Plus 2.14.x / UnoCSS 66.x / Vue Router 5.0.x / Pinia 3.x / TypeScript 6.x。

### C 端 `benxin-admin-uniapp`
| 组件 | 选型 |
|---|---|
| 框架 | uni-app + Vue3 + TS + Vite |
| UI | **wot-design-uni**（MIT，TS 优先） |
| 状态/请求 | Pinia + 封装 `uni.request` |
| 产物 | 一套代码输出 **微信小程序 + H5**（H5 主要跑在公众号环境内） |

> M0-C 实测锁定版本：uni-app 3.x(vue3) / Vue 3.5.x / Vite 5.x / wot-design-uni 1.14.x(MIT) / Pinia 2.3.x。

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
- **软删唯一键不可复用**：唯一索引（如 `role.code`、`admin.username`）不区分软删行，唯一校验含已删行（`withTrashed`，前置拦 422 避免落库 500）；软删后该值**不可复用**，需复用走 M2 彻底删除。不采用"后缀释放（`__del_{id}`）"或"生成列联合唯一"方案——保软删语义纯粹与索引简单（方案 A 定案，2026-06-09）。`bx_menu.perms` 无 DB 唯一索引，按启用态校验。
- **创建归属字段（M2 起）**：新建的**业务数据表**标配 `create_by`（创建人 admin_id）；需部门维度数据权限的表加 `create_dept`（创建部门 id）。`BxModel` 提供创建自动填充钩子（insert 时写当前登录 adminId / dept_id），配合 ADR-9 的 `applyDataScope` 默认字段名即开即用。**系统配置/日志类表（dict/config/oper_log/login_log）按需，不强制**；M1 已有核心表不回填。首个落地点为 M2-D 文件管理。
- 字段命名 snake_case；金额用整型分或 decimal；状态用 tinyint + 字典。

### 5.2 PHP
- 命名空间沿用 TP8：`app\admin`、`app\api`、`app\common`。
- 类命名 PascalCase：`XxxController` / `Xxx`(Model) / `XxxService` / `XxxValidate`。
- **基类前缀 `Bx`**：`BxController` / `BxModel` / `BxService` / `BxValidate`，统一收口响应、软删除、租户作用域。
- **基类职责（M1-C 黄金样板落地，后续模块与 M3 生成器一律复刻）**：
  - `BxController`：`success()` / `fail()` / `paginate()` 统一响应 + `pageParam()`（page≥1、page_size 默认 15 上限 100）。控制器只做"参数编排 → 调 Service → 返回"，不写业务与查询。
  - `BxModel`：软删 `deleted_at` + 时间戳 + 租户全局作用域 `globalScope=['tenant']`（单租户为空操作，钩子已接通）+ `currentTenantId()`；敏感字段用 `hidden`（如 `password`）兜底剔除。
  - `BxService`：业务逻辑 + 事务（`Db::transaction` 包裹跨表写）+ 字段白名单（`fillable` 二次拦截，与 Validate 双重把关）。
  - `BxValidate`：场景化——`sceneCreate`（必填）、`sceneUpdate`（`remove()` 去必填，配合 `$request->has()` 选择性更新）、`sceneStatus/Password/Assign*` 等。
  - 异常分工：`ValidateException`（入参校验，400xxx）/ `BusinessException`（业务规则，422xxx）/ `AuthException`（认证，401xxx）统一接入全局 Handle，不泄露堆栈。

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
| `401xxx` | 认证 | 401001 未登录/无效 token、401002 账号或密码错误（防枚举统一文案）、401003 token 过期、401004 刷新失效 |
| `403xxx` | 权限不足 | 403000 |
| `404xxx` | 资源不存在 | 404000 |
| `422xxx` | 业务校验失败 | 422000 |
| `429xxx` | 触发限流 | 429000 |
| `500xxx` | 服务端错误 | 500000 |
| 业务段 | 各模块分配 | 内容 11xxxx、支付 12xxxx、消息 13xxxx、微信 14xxxx |

### 6.3 路由与分页
- 后台 `/admin/v1/...`，C 端 `/api/v1/...`（多应用前缀 + 路由分组版本）。
- 分页统一：`data: { list: [], total, page, page_size }`；**page≥1、page_size 默认 15、上限 100**（`BxController::pageParam()` 收口）。
- 路由顺序约定：**具体 action > `/:id` > 集合**；更新用 `$request->has()` 判断字段是否参与更新。

---

## 7. 认证与权限
- 后台、C 端各自独立 guard + 独立 JWT 密钥。
- access token 短期（建议 2h）、refresh 长期（建议 14d）；refresh 白名单与登出黑名单（jti）存 Valkey，至 exp 过期。
- **Casbin RBAC**：model 预留 domain（= tenant_id）维度，单租户时使用统一 domain。权限粒度到接口/按钮。
- C 端懒登录；登录即注册，微信 + 手机号缺一不可。
- **后台登录契约（M1 定稿，前端据此对接）**：`POST /admin/v1/login` → `{ access_token, refresh_token, token_type:"Bearer", expires_in, refresh_expires_in }`；刷新 `POST /admin/v1/refresh`、登出 `POST /admin/v1/logout`。
- **profile 契约**：`GET /admin/v1/profile` → `{ user, roles, menus, perms }`，即前端动态路由 + 按钮权限的数据源。`menus` 为目录/菜单类型树（节点含 name/title/path/component/icon/sort 与 children，自动补全祖先目录保证树连通），`perms` 为按钮权限串数组，**与后端 Casbin enforce 同源**。超管（含 super_admin）返回全量启用菜单树 + 全量 perms。菜单停用时同步移出 `menus` 与 `perms`。

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
- **依赖容器端口（实测隔离）**：MySQL 映射宿主机 **3308**、Valkey **6380**（避开本机原生 MySQL 3306 与另一容器占用的 3307）。改端口后须 `docker compose down -v` 清卷重起，使 MySQL 按新 .env 重新初始化账号。
- **后端本地服务端口**：`php think run -p 8801`（独占 8801，避开本机其他项目占用的 8000）。前端 baseURL（web 的 `VITE_API_BASE`、uniapp 的 `VITE_API_BASE_URL`）默认指向 8801。

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
| **M0-A** | 后端脚手架（server：骨架/统一返回/中间件/迁移/端口隔离/路由+CORS） | ✅ 已完成 |
| **M0-B** | 后台前端脚手架（web：Vue3.5+Vite8+EP2.14+Pinia+Router5+UnoCSS+ping 联调） | ✅ 已完成 |
| **M0-C** | C 端脚手架（uniapp：uni-app+wot-design-uni+H5/微信小程序双端+ping 联调） | ✅ 已完成 |
| **M1-A** | 认证基建（核心表+种子 / lcobucci JWT 双令牌 / BxJwt / JwtAuth 中间件 / 后台登录·刷新·登出·profile 闭环） | ✅ 已完成 |
| **M1-B** | php-casbin（domain=tenant_id、bx_casbin_rule 适配器、CasbinAuth 中间件、超管通配策略启用） | ✅ 已完成 |
| **M1-C** | 管理员/角色/菜单 CRUD（黄金样板核心，规范度拉满；profile 补全菜单+权限点聚合） | ✅ 已完成 |
| **M1-D** | 部门/岗位 CRUD + 数据权限(data_scope) + 与 web 登录全流程联调 + 自助改密 | ✅ 已完成 |
| **M2-A** | 字典管理（bx_dict 类型 + bx_dict_data 数据项 + 取数接口 + 缓存） | ✅ 已完成 |
| **M2-B** | 参数配置（bx_config CRUD + 分组 + 敏感值 AES 入库 + 缓存） | ✅ 已完成 |
| **M2-C** | 操作日志 + 登录日志（RequestLog 中间件自动记录 + 接入登录流） | ✅ 已完成 |
| **M2-D** | 文件管理（bx_file 上传 + 本地驱动起步 + OSS/七牛抽象、密钥 AES；首次落地 create_by/create_dept + BxModel 自动填充钩子） | ✅ 已完成 |
| **M3-A** | 生成器骨架（CLI `bx:make` + 表结构读取 + 元数据收集 + stub 引擎）+ 纯 CRUD 复刻（以 post 验证保真） | ✅ 已完成 |
| M3-B | 树形模块复刻（dept/menu 范式） | ✅ 已完成 |
| M3-C | 带授权链路复刻（role：分配菜单 + casbin 同步） | ✅ 已完成 |
| M3-D0 | 前端黄金样板（手写后台 CRUD 页：XTable 配置化 + 编辑表单 + 分配菜单树形勾选弹窗） | ✅ 已完成 |
| M3-D1 | 前端产物生成器（bx:make 出列表/表单/分配弹窗 + api 薄壳，甲案手写 axios；OpenAPI 为远期可选） | ✅ 已完成 |
| M3-E | 吃狗粮回炉(M4-A 暴露缺口:richtext/image/daterange/readonly/listOrder/exact 分流/seeder 目录声明) | ✅ 已完成 |
| **M4-A** | 内容(分类树 + 内容 + 广告位,bx:make 吃狗粮首发 + 富文本/图片手工槽) | ✅ 已完成 |
| M4 | 通用业务（内容/支付/消息/微信配置） | ⚪ 未开始 |
| M5 | C 端 uni-app（登录/首页/我的，懒登录） | ⚪ 未开始 |
| M6 | （可选）官网 + 首页拖拽搭建 | ⚪ 未开始 |

> 状态图例：⚪ 未开始 ｜ 🔵 进行中 ｜ ✅ 已完成 ｜ ⏸ 暂停

> M0-A 落地（2026-06-07，server 仓 5981d94，三端 dev 同步）：ThinkPHP 8.1.2 多应用骨架、统一返回/异常、request_id 全局贯穿、CORS、JWT/Casbin/OperLog 占位、bx_config 首表 + 迁移工具链、依赖端口隔离(3308/6380)。已知项：本地 PHP 8.2 经 --ignore-platform-req 安装（生产 8.4 无此项）。

> M0 三端落地（2026-06-08，三仓 dev 双推同步）：server（路由+CORS 修复，独占 8801）、web（Vue3.5/Vite8/EP2.14/Router5，ping 联调通）、uniapp（uni-app3.x/wot-design-uni，H5+微信小程序双端 ping 均通）。已知项：本地 PHP 8.2 经 --ignore-platform-req 安装（生产 8.4）；wot-design-uni 1.14 内部 Sass @import deprecation 警告（上游问题，不影响构建）。

> M1 启动（2026-06-08）：ADR-8 定案（lcobucci/jwt + 自建 BxJwt，HS256 双 guard 独立 secret）。M1 拆 A/B/C/D 四步：A 认证基建（核心表 bx_admin/role/menu/dept/post/casbin_rule 及关联表 + 种子 + BxJwt 双令牌 + JwtAuth 中间件 + 后台登录/刷新/登出/profile 闭环）→ B php-casbin（domain=tenant_id、bx_casbin_rule 适配器、CasbinAuth 中间件、超管通配策略启用）→ C 管理员/角色/菜单 CRUD（黄金样板核心，规范度拉满）→ D 部门/岗位 CRUD + 数据权限 + 与 web 登录全流程联调。M1-A 任务书已下发，待 Claude Code 回填完成报告。

> M1-A 落地（2026-06-08，server 仓 commit 8661660，GitHub+Gitee dev 双推）：9 表（bx_admin/role/menu/dept/post + admin_role/role_menu/admin_post + casbin_rule，think-migration 无裸 SQL）+ AuthSeeder 幂等种子（部门/岗位/super_admin 角色/超管 admin 账号 Argon2id/菜单 26 条 [1 目录+5 菜单+20 按钮 perms]/Casbin 超管通配 `p,super_admin,0,*,*`）；lcobucci/jwt 5.6.0 自建 BxJwt 双 guard 双令牌（access 2h/refresh 14d），refresh 白名单 + 登出黑名单(jti) 落 Valkey；admin guard 登录/刷新/登出/profile 闭环，13/13 API 自测通过。错误码新增并固化 **401002 账号或密码错误**（防枚举统一文案，已补入 §6.2）。已知项：① 本地 PHP 8.2 经 --ignore-platform-req=php 安装 lcobucci（生产 8.4 无此项）；② **`.env` 注释内的 ASCII 圆括号会触发 TP Env 解析报错，注释一律用全角（）**；③ Valkey key 实际带 TP 全局前缀，形如 `bx:bxjwt:admin:wl:refresh:{jti}`(TTL 14d) / `bx:bxjwt:admin:bl:{jti}`(TTL≤access 剩余)；④ 旧 `app/common/middleware/JwtAuth` 占位透传保留给 api guard/M5，admin 实现为新建的 `app/admin/middleware/JwtAuth`；⑤ 按默认决策未加 is_super 逃生通道、refresh 不轮换（超管权限由 M1-B Casbin 通配承载）。

> M1-B 落地（2026-06-09，server 仓 commit e705158，GitHub+Gitee dev 双推）：casbin/casbin v0.2.1 集成。Model 采用 **rbac_with_domains 含 g**（实测 DefaultRoleManager 对 sub==p.sub 自反命中，无 g 策略时角色仍直接匹配自身 p 策略，故保留 g 维度供未来角色继承）。**自建 `app/common/library/BxCasbinAdapter`**（loadPolicy/savePolicy/addPolicy/removePolicy/removeFilteredPolicy，直读写 bx_casbin_rule，ORM 参数化）+ `app/common/service/CasbinService` 单例工厂（enforce/enforceAny/addPolicyForRole/removePolicyForRole/reload）。`app/admin/middleware/CasbinAuth` 挂载顺序 JwtAuth→CasbinAuth，所需权限走**路由显式声明** `->middleware(CasbinAuth::class, 'system:模块:动作')`，拒绝返回 **403000 + HTTP 403**。验证矩阵 6/6 通过（超管通配放行 / 无策略 403 / 加策略放行 / 删策略 403 / domain=1 拒绝 dom=0 请求 / 中间件顺序）。**bx_casbin_rule 字段映射固化**：p 策略 v0=角色code、v1=dom(tenant_id)、v2=perms 串、v3=act（普通统一 `do`、超管 `*`）；g 关系 v0=子、v1=父、v2=dom（本步未灌）。策略缓存本步未做（数据量小、php -S 每请求重建 Enforcer，DB 写入即时生效），列为常驻进程（fpm/swoole）下后续优化项。探针 `GET /admin/v1/_perm_probe` 仅 APP_DEBUG=true 注册（+ Probe 控制器 + TesterSeeder），生产不暴露，留作回归载体。已知项：旧 `app/common/middleware/CasbinAuth` 占位透传保留给 api guard/M5。

> M1-C 落地（2026-06-09，server 仓 C-1 3a405e5 / C-2 6bd17a3 / C-3 d6e25c7，GitHub+Gitee dev 双推）：黄金样板核心三批落地。**四件套基类职责固化**（见 §5.2）：BxController(success/fail/paginate/pageParam)、BxModel(软删+时间戳+租户 globalScope+hidden)、BxService(业务+事务+fillable 白名单)、BxValidate(场景化)；新增 `BusinessException`→422xxx 接入 Handle。**菜单 CRUD**：tree 内存建树、按钮必有 perms 且唯一、改父防自指/成环、删除有子拒绝 + 事务清 role_menu + 按 perm 清 casbin + reload。**角色 CRUD + 分配菜单**（核心授权链路）：`PUT roles/:id/menus` 事务内覆盖 bx_role_menu → removeAllForRole → 逐 perm addPolicyForRole → finally reload；非法 menu_id 整单回滚不留半套策略（BxCasbinAdapter 用默认连接同入 Db::transaction）；super_admin 不可删/停/改 code/分配菜单(422)；角色有管理员绑定则拒删。**管理员 CRUD**：超管护栏(admin 账号/super_admin 角色不可删·停·降权) + 自我保护(不可删/停当前登录者)；password 走独立改密接口、hidden 兜底剔除；关联覆盖式事务写。**profile 升级**为 `{user, roles, menus, perms}`（契约见 §7），普通管理员按角色聚合并自动补全祖先目录、超管全量、菜单停用同步移出 menus+perms。安全基线 §6/§8 全勾选，权限端到端越权 403000 验证通过。
> **样板取舍定案（2026-06-09，M1-C 后）**：① 软删唯一键**不可复用**（方案 A，已固化入 §5.1）；② 数据权限模型**升为 ADR-9**（data_scope 五档 + 递归 CTE 子树 + bx_role_dept 自定义）；③ 自助改密接口并入 M1-D。三项已写入 M1-D 任务书。

> M1-D 落地（2026-06-09，server D-1 ef7e8ae / D-2 fcc5a74、web D-3 d24afff，GitHub+Gitee dev 双推）：**部门树 CRUD**（复刻菜单样板，删除护栏：有子部门 / 有管理员挂靠 → 422）+ **岗位 CRUD**（复刻角色样板，code 含 withTrashed 唯一、有管理员绑定 → 422）+ **自助改密** `PUT /admin/v1/password`（仅 JwtAuth，验旧密码、Argon2id、**改后强制重登**＝拉黑当前 access jti + 撤 refresh 白名单）。**data_scope（ADR-9）**：新增 `bx_role_dept`（自定义范围并入 `PUT roles/:id` 当 data_scope=5）；`DeptService::descendantIds` 递归 CTE 查子树（参数化）；`BxService::applyDataScope($q,$admin,$deptField,$ownerField)` + `DataScopeService`（多角色取最宽 resolve + applyTo），模块按需调用；**bx_admin 列表示范全五档**，多角色取最宽 9/9 对拍通过，与软删/租户/超管护栏叠加无冲突。**web 首次实质编码**（benxin-admin-web）：真实登录 + token(Pinia+localStorage) + axios 401 分流（401003 单飞 refresh 静默续期重试、401001/401004 跳登录）+ profile 驱动动态路由（`import.meta.glob` 映射、未实现页 PlaceholderView 占位）+ v-permission 按钮权限 + 登出清会话；vue-tsc + 生产构建通过。已知项：① web token 存 localStorage（注明 XSS 风险，httpOnly cookie 列后续）；② 各 CRUD 页面为占位，随后续前端排期补齐（D-3 只验认证闭环）；③ **web 路由改用 hash history**（免服务器重写，定案保持）；④ data_scope 仅 bx_admin 示范，其它模块按 ADR-9 模式各自开启。

> **M1 收官（2026-06-09）**：认证 + RBAC 全链路闭环——JWT 双令牌(白/黑名单) → Casbin RBAC(domain 预留) → 管理员/角色/菜单/部门/岗位五大 CRUD(黄金样板四件套基类已沉淀，见 §5.2) → 数据权限(ADR-9) → web 登录/动态路由/按钮权限。黄金样板已成型，M3 生成器据此复刻。M2 起的衔接约定见下方"M2 启动约定"。

> **M2 启动约定（2026-06-09）**：① **创建归属字段**——业务数据表标配 `create_by`(+按需 `create_dept`) + BxModel 自动填充钩子，系统配置/日志表按需不强制，首个落地点 M2-D（见 §5.1）；② **M2 拆 A→B→C→D**（字典 → 参数 → 操作/登录日志 → 文件管理）；③ **暂不做回收站**（软删唯一键不可复用，待真有复用需求再于后续做彻底删除入口）。M2-A 字典管理任务书已下发。

> M2-A 落地（2026-06-09，server 仓 commit 2107fc5，GitHub+Gitee dev 双推）：**字典管理**复刻黄金样板。新增 `bx_dict`（type 唯一 withTrashed）+ `bx_dict_data`（dict_type 关联、(dict_type,value) 同类型唯一 withTrashed、list_class/is_default）。DictSeeder 幂等增量：「字典管理」菜单 + `system:dict:*` 4 perms（菜单 26→31），示例字典 `sys_normal_disable`(正常1/停用0) + `sys_yes_no`。类型/数据项 CRUD 共用 `system:dict:*`；类型删除事务级联软删数据项；**改 type 名时数据项 dict_type 同事务跟随迁移**（主从一致无孤儿）。**取数接口** `GET /admin/v1/dicts/type/:type`（路由排 `/:id` 前）+ **缓存通用能力**：key `bx:dict:data:{type}`、只缓存启用项、读回填 + 写失效（改名清新旧 key）、兜底 TTL 86400。17/17 自测全绿。已知项：① 字典前端页面随前端排期补（菜单已就位，动态路由回退占位页）；② 字典为系统配置表，不加 create_by/不做数据权限。**对 M2-B 建议**：缓存 helper 建议从 DictDataService 抽成可复用 trait/类供参数配置复用；bx_config（M0 已建，group/key/value/remark + 唯一 (tenant_id,group,key)）直接做 CRUD 无需新表，新增点为 §9 敏感值 AES 加密入库（需加解密 helper 放 app/common/library）。

> M2-B 落地（2026-06-09，server 仓 commit 2d9e3f5，GitHub+Gitee dev 双推）：**参数配置**。bx_config 增列 `name`/`is_sensitive`/`value_type`/`sort`（value 沿用 text 容纳密文）。**缓存抽件 `app/common/library/BxCache`**（remember/forget/store，走 Valkey），DictDataService 改用并回归不回退。**加解密 `app/common/library/ConfigCrypt`**：AES-256-CBC、随机 IV、存 `base64(iv+cipher)`、密钥取 .env `CONFIG_CRYPT_KEY` 经 **SHA-256 规整为 32 字节**、decrypt 失败降级空串、`mask` 脱敏（前后各 2 位+****）；GCM 切换点已在类注释标注。配置 CRUD：敏感项加密入库、HTTP 一律脱敏回显、**更新提交脱敏占位则不更新原值**（防误清）、切换 is_sensitive 转换形态；(group,key) 唯一 withTrashed。取数双形态：HTTP `GET /admin/v1/configs/group/:group`（脱敏）+ 内部 `ConfigService::get($key)`/`getGroup($group)`（解密明文供业务调用）。**缓存单聚合键 `bx:config:all` 存原始库值（敏感=密文）**，写失效、TTL 86400，Valkey 实测无明文敏感值。种子：参数配置菜单 + `system:config:*` 4 perms + 敏感示例 `wechat/mp_app_secret`（占位假串、不放真实密钥）。已知项：① 缓存用单键 config:all（配置体量小的等价简化）；② `ConfigService::get($key)` 要求 key 跨分组唯一，**M4 多组同名 key 前需补 `getByGroupKey($group,$key)`**；③ 加密为 CBC（GCM 切换点已注释）。

> M2-C 落地（2026-06-09，server 仓 commit 4324e06，GitHub+Gitee dev 双推）：**操作日志 + 登录日志**。新增 `bx_oper_log`（admin_id/username/method/path/perm/ip/ua/request_body/response_code/http_status/duration_ms/request_id）+ `bx_login_log`（username/admin_id/ip/ua/status/msg/request_id），**两表仅 created_at、不软删/不挂租户作用域**（Model 直接继承 think\Model）。**RequestLog 中间件**（全局最外层）扩展自动记录：仅记写方法（POST/PUT/DELETE/PATCH）、GET 跳过；$next 返回后读 JwtAuth 注入的 adminId + CasbinAuth 注入的 requiredPerm，捕获最终 code/耗时；全程 try/catch 吞错（失败仅 Log::error）。**登录日志**接入 AuthService::login（成功 status=1 / 失败 status=0 统一文案不区分账号不存在与密码错、失败也记 IP）。**脱敏红线 `app/common/library/LogSanitizer`**：黑名单（password/old_password/new_password/confirm_password/access_token/refresh_token/token）+ 语义正则（pass/secret/token/credential/api_key/app_secret）+ /configs 写接口额外打码 value，递归遍历嵌套 body 命中键整体替换 ******；改密/敏感配置/登录三处实测 request_body 无明文。查询/清理接口：oper-logs/login-logs list+detail+清理；**清理防裸 DELETE 全表**（需 start_time/end_time 或显式 all=1，否则 422）。日志故障不拖垮主流程（RENAME TABLE 模拟写失败 → 主接口仍 code0/200）。LogMenuSeeder 幂等：操作日志/登录日志菜单 + `system:operlog:list|delete`/`system:loginlog:list|delete`（菜单 36→42）。14/14 自测全绿。已知项：① 日志同步轻量写，异步队列(think-queue)列高并发后续；② 日志分区/归档/定时清理列运维后续。**运维经验**：本机 Docker Desktop 异常关闭后，MySQL 容器因**残留 socket lock 文件**陷入重启循环；`docker compose down && up -d` 重建容器即修复，**命名数据卷 `benxin-mysql-data` 保留、表与数据完好**（印证幂等种子 + 命名卷的零损失设计）。

> M2-D 落地（2026-06-09，server 仓 commit cf4a079，GitHub+Gitee dev 双推）：**文件管理 + M2 收官**。新增 `bx_file`（create_by/create_dept + original_name/file_name/path/mime/ext/size/storage/hash/url + 软删）。**BxModel 自动填充钩子 `onBeforeInsert`**（首落点）：`getTableFields()` 判断表含 create_by/create_dept 列 → 登录态自动写 adminId/dept_id、入参显式给出则不覆盖、`adminId<=0`(CLI/seeder/未认证)或异常 try/catch 安全跳过。**存储抽象 `app/common/library/storage`**：`StorageInterface`(put/url/delete) + `LocalStorage`（存项目根 `storage/`、public 之外不可直链执行、realpath 防穿越）+ OssStorage/QiniuStorage 骨架（throw 未实现 + TODO）+ `StorageManager::driver()`（驱动名取 bx_config `storage_driver` 默认 local、云 AK/SK 经 ConfigService::get 敏感 AES 注入）。**上传安全（§8 硬指标）**：app 层大小上限 + **finfo 真实 MIME**（不信任客户端）+ 扩展名白名单 + **MIME/ext 双重校验**（.php 拒、php 改名 .jpg 拒）+ uuid 重命名禁原名（防穿越/覆盖/可执行）+ 落非 Web 目录 + 受控下载 `GET /files/:id/raw`。**ADR-9 真业务表首示范**：bx_file 列表挂 `applyDataScope(create_dept, create_by)`，两部门 data_scope=2 账号对拍各见本部门、超管见全部（与 M1-D 在 bx_admin 用 dept_id/id 互补）。FileMenuSeeder 幂等：文件管理菜单 + `system:file:list|upload|delete`（菜单 42→46）。删除＝软删记录、物理文件保留（GC 后续）。17/18 自测（唯一未达 app 层 422 的"超大文件"因本机 php.ini post_max_size=8M 在 PHP 层先拒、更安全不入库，属环境约定）。已知项：① 生产需把 upload_max_filesize/post_max_size 调 ≥ app 限额才走 app 层 422；② 物理文件 GC / 秒传去重 / 缩略图 / 受控下载流式输出 / OSS·七牛实现 列后续。

> **M2 收官（2026-06-09，server 仓 dev）**：系统管理五块全闭环——字典(A) / 参数配置+敏感 AES(B) / 操作+登录日志(C) / 文件管理(D)。**新沉淀可复用基建**：`BxCache`(读回填/写失效)、`ConfigCrypt`(AES-256-CBC + 脱敏)、`LogSanitizer`(日志脱敏)、`BxModel` 自动填充钩子(create_by/create_dept)、`StorageInterface`(存储抽象)；ADR-9 数据权限已在真业务表 bx_file 跑通。**黄金样板要素齐备**（四件套基类 / CRUD 七动作 / 路由范式 / 树形 / 授权链路 / 数据权限 / 缓存 / 日志脱敏 / 上传安全），M3 代码生成器据此复刻。下一步 M3 进入设计评审（生成器技术选型）。

> **M3 启动约定（2026-06-09）**：生成器技术选型定案为 **ADR-10**——stub + 占位符 / 表结构+元数据输入 / 后端四件套+路由+seeder 产物(前端与 migration 后续) / CLI 命令形态 / 默认不覆盖+--force / 首个复刻目标 post 纯 CRUD。M3 拆 **A→B→C→D**：A 生成器骨架 + 纯 CRUD 复刻（post 保真验证）→ B 树形(dept/menu) → C 授权链路(role + casbin 同步) → D 前端产物。M3-A 任务书已下发。

---

## 15. 跨会话续接说明
新对话开始时：
1. 把本文件完整贴给项目经理(Claude)。
2. 说明当前进行到哪个 Mxx，以及上一个完成报告的关键结论。
3. 项目经理据此继续出任务书或做架构决策，并同步更新本文件第 14 节看板。

M3-B 落地（2026-06-09，server 仓 commit 79ee955，GitHub+Gitee dev 双推 568f958..79ee955）：树形模块复刻。bx:make 自动识别树形表（TableReader::selfRefColumn 检测 parent_id/pid 整型自引用、优先 parent_id；hasColumn 辅助），命令信息行报「树形：是」。ModuleMeta 新增 isTree/parentField/sortField/subtreeStrategy/treeDeleteGuard（config 显式优先于推导）。复用 M3-A 六 stub 未建第二套，树形分支经计算块注入：collectionMethods(list↔tree+buildTree) / createParentGuard / updateGuards(防自指防成环) / deleteGuard(子节点拒删 + M3-C 锚点) / publicTreeExtras(descendantIds) / treeHelperMethods（memory 用 collectDescendants、cte 用 descendantIds CTE）/ treePathHint(/tree 路由)，非树形渲染空串。新增 config 样例 dept.php(cte)/menu.php(memory)。★保真验证：dept(cte) 与 menu(memory) 双标的逐文件 diff——tree/buildTree/assertParent/assertNotCycle/descendantIds 范式逐字一致，Model/Validate/Controller/Route 仅 @date+文案差，type/perms/component 等专属字段经元数据透传非写死；/tree 归 list perm、排 /:id 前，与手写一致。防污染硬门：bx_post 重跑与 M3-A 输出逐字一致（已知差异行 0），树形改造未污染纯 CRUD。实跑校验：自指拦截 / 成环拦截（父 9004∈descendants(9001)）/ 有子拒删（子数 2），临时子树跑后硬删清理无残留；全量 php -l 通过；防覆盖/--force 复测（6 跳过）。已知 diff（→M3-C）：dept admin 挂靠拒删（bx_admin.dept_id 计数）、menu role_menu 清理 + casbin removePolicyByPerm/reload 级联——均留 // TODO M3-C 锚点；menu 的 normalizeByType/assertPermsUnique/TYPE_* 属 menu 专有业务、非树形范式，本步不生成。已知项：文案类差异（生成器走 post 母版极简 doc，手写更详尽）、子节点拒删消息按 menu 口径统一、复数/不规则词仍靠 config 覆盖（沿用 M3-A）。

M3-C 落地（2026-06-10，server 仓 dev，GitHub+Gitee 双推）：授权链路复刻。元数据新增 5 组可选键（缺省不生成、纯 CRUD/树形零影响）：deleteBindingGuards（绑定拒删 count>0→422，软删表经可选 model 键走 Model 计数不含已删行，如 dept→Admin）/ deleteCascade（删除级联：事务内清关系表+casbin → 事务外 reload；casbin 双变体——menu 用 removeByPerm(permField) 条件 reload、role 用 removeAllForRole(subField,domainField) finally reload，后者为任务书 schema 的对手写保真扩展）/ relationEndpoints（分配关系：GET /:id/<rel> 回显 + PUT /:id/<rel> 覆盖式分配 + casbinSync 整单回滚，GET 回显与 menuIds 服务方法为对手写补全）/ protectedRows（受保护行 delete/disable/changeCode/assign 四动作 422）/ 字段级 unique+nullable+uniqueScope=active（可空唯一：非空才校验、不含 withTrashed，复刻 menu.perms；label 键截断列注释作消息前缀）。复用六 stub，新增计算块 modelImports/serviceImports/relationIdMethods/relationAssignMethods/relationRead·AssignActions/assignScenes/deleteGuard·deleteAction 重组/seederPermItems，无声明渲染空串。新增 configs/role.php，dept.php 补绑定拒删、menu.php 补级联+perms 可空唯一。★保真：role 六件 diff——assignMenus（事务/覆盖写/removeAllForRole+逐 perm addPolicyForRole/finally reload/非法 id 整单回滚）、delete（super_admin 拒/绑定拒/级联清 role_menu+role_dept+casbin）、setStatus/update 护栏、路由含 GET|PUT roles/:id/menus（assign perm 以手写为准复用 system:role:update，seeder 四动作即一一对应）逐字范式一致；dept/menu 重生成 TODO M3-C 锚点清零（grep 0 命中），menu 删除级联与 assertPermsUnique 逐字、与 M3-B 基线相比仅 Service 变化其余文件字节级稳定。防污染硬门：bx_post 重跑与 M3-A 输出除 @date 外 0 差异。实跑 27/27：未分配 403→分配后 enforce 放行→非法 id 422 且 role_menu/casbin 无半套残留→绑定拒删 422→super_admin 四护栏 422→perms 空可重复/非空启用态重复 422/软删行不参与→menu 删除后 role_menu 清空+casbin perm 移除+其余策略 enforce 复验→临时 role/admin/menu 硬删零残留。已知 diff：role 的 data_scope=5 自定义部门（dept_ids/syncRoleDepts/DataScope，ADR-9 role 专有）、detail 聚合 menu_ids/dept_ids、SUPER_CODE 常量 vs 字面量、护栏/绑定消息统一口径 vs 手写专属文案、手写 setStatus 在 delete 之前的方法顺序、$base/$data 变量名；menu normalizeByType/TYPE_* 永久 diff（PM 定不生成）。已知项：post 产物 deleteDoc 仍含「留 M3-C」原文（防污染硬门要求与 M3-A 逐字一致，文案更新留待 PM 定）；admin 自我保护 __currentUser 变体仅留备注未落地。

M3 后端收官 + M3-D 路线定案（2026-06-09，M3-C 后）：三项决策（daxing 全按 PM 建议）：① post 保真收尾（乙案）——post(岗位)补 deleteBindingGuards(bx_admin_post) + 清「留 M3-C」过时注释，使生成 post == 手写岗位 post 完整保真，并重锚防污染回归基线（此后 M3 系列防污染硬门以重锚后产物为准，非原始 M3-A 产物）；收尾后八个手写样板全部可被生成器完整复刻，护城河后端段闭环。② 前端不反序（ADR-6 铁律）——web 侧 CRUD 页此前为 PlaceholderView 占位，先手写前端黄金样板（M3-D0）再做前端生成器（M3-D1），复刻后端"先 M1-C 手写、再 M3 复刻"成功路径。③ M3-D1 产物边界——bx:make 前端产物 = 列表页 + 编辑表单 + 分配菜单树形勾选弹窗，消费 OpenAPI 自动生成的 API 调用文件，不接管 API 层生成（守 §3/§4 既定 OpenAPI 工具链分工）。M3-D0 因定形质量决定护城河前端段成色，PM 将先出设计稿待 daxing 拍板再下任务书。

M3-C 收尾·post 完整保真（2026-06-10，server 仓 dev，GitHub+Gitee 双推）：configs/post.php 增 deleteBindingGuards（bx_admin_post/post_id/管理员；中间表无软删直接 count，与手写 PostService::delete 计数路径一致，无 model 键）；清生成器全部阶段占位话术——deleteDoc 未声明 fallback「留 M3-C」→「关联护栏按需在 config 声明」（非树形/树形两分支）、树形未声明的 // TODO M3-C 锚点移除（能力已落地，未声明即不输出）。★保真：post Service delete 含绑定拒删与手写逐字范式一致（$bound 变量/消息文案为 role 同口径已知差异），doc 无占位 → post 完整保真；dept/menu/role 回归 0 漂移（其 delete doc 走声明分支组合文案，本就不含占位，优于任务书「仅 doc 一行变化」预期）；产物 grep M3-C/TODO 0 命中；二次重生成自洽（确定性 ✓）→ 新 bx_post 产物即此后防污染硬门基准（原 M3-A 基线作废）。实跑 5/5：绑定拒删 422 / 删管理员解绑后关联清空 / 解绑后可删 / 临时数据硬删零残留。清理项：runtime/generated/dict 为 M3-A 时代遗留过期产物（非范式标的、无 config），已删除。**M3 后端终态达成：post（纯 CRUD+绑定护栏）/ dept（树形 cte）/ menu（树形 memory+级联+可空唯一）/ role（授权链路）四范式标的全部完整保真**，下一步 M3-D0 前端黄金样板设计确认（PM 出设计稿）。

M3-D0 落地（2026-06-09，web 仓 commit b426438，GitHub+Gitee dev 双推，16 文件 +1614 行）：前端黄金样板。现状勘查：web 仓纯手写 axios（无 OpenAPI 工具链）、components/ 空、CRUD 页全 PlaceholderView 占位、M1-D 复用点（request 401 分流 / user store / v-permission / profile 动态路由）齐全零改造。XTable 配置化组件从零新建（权威 schema：src/components/XTable/types.ts，文档化 docs/CRUD-SCHEMA.md）：config = api 槽位{list,save,update,remove,status} + rowKey + tree（树形整树无分页）+ search（input/select/daterange）+ columns（text/dictTag/time/switch/slot）+ toolbar + rowActions（emit/perm/confirm/show 行级隐藏）；按钮全挂 v-permission、emit:remove 内建（确认→删→刷新→末行回退页码）、空搜索值不参与请求。编辑表单 ElDrawer（XFormDrawerConfig：entity/api/detail/items；控件 input/textarea/select/switch/number/radio/treeSelect；场景 open(create,preset)/open(update,row)，required 对接 sceneCreate、update 仅提交可见且非 disabledOnEdit 字段对接 $request->has()）。分配菜单弹窗（ElDialog+ElTree，check-strictly 独立勾选；Promise.all(GET menus/tree, GET roles/:id/menus)→setCheckedKeys 回显→getCheckedKeys→PUT 覆盖；与后端 profile 自动补祖先目录配合自洽）。树形 table（menu：tree 整树、行内"新增下级"预填 parent_id、父节点 treeSelect 虚拟根 id=0）。进阶范式用单钩子 visible(form,mode) 表达：role data_scope 五档（=5 时 dept_ids 部门树多选、仅编辑场景）、menu type 联动（目录/菜单/按钮字段显隐、隐藏不提交 + 后端 normalizeByType 兜底）。新建 api role/menu/dept/dict.ts + useDict 组合式（模块级内存缓存）。联调 25+ API 全绿（登录→profile→role CRUD+scope5+分配菜单 enforce 复验+整单回滚→menu 三型 CRUD+有子拒删 422+防成环 422），vue-tsc + 生产构建通过；浏览器点击留 daxing 复验（npm run dev + 8801 可直跑）。已知项：① role 分配菜单全不勾（menu_ids:[]）被后端 require 拦 422，清空授权需后端放宽口径（待定）；② data_scope=5 需建后再编辑配部门（sceneCreate 不收 dept_ids）；③ daterange 控件已实现但两页未用；④ main.ts 补 el-message-box/el-overlay 样式导入。M3-D1 元数据映射详表见 web 仓 docs/CRUD-SCHEMA.md §6。


M3-D1 落地（2026-06-12，server 仓 dev，GitHub+Gitee 双推）：前端产物生成器，M3 护城河收官——一条 bx:make 从表结构产出全栈产物（后端四件套+路由/seeder + 前端列表/表单/分配弹窗/api 薄壳）。新增 extend/generator/FrontendGenerator.php + stubs/frontend/{index.vue,api,assign-dialog.vue}.stub，Make.php 并入产物流（默认落 extend/generator/output/frontend/，不自动写 web 仓、人工 copy，已入 .gitignore）；ModuleMeta 增 front 透传（模块级/字段级/relationEndpoint 级，后端 Generator 不读取）。元数据映射落实 web 仓 docs/CRUD-SCHEMA.md §6：list→columns（非树自动 id/created_at 列、status→switch+perm、枚举→dictTag）、search→keyword 合并 input + status select dict、required→formItems.required、unique→disabledOnEdit、isTree/parentField→tree:true+父 treeSelect 虚拟根「顶级」+行内新增下级+treeLeafGuard 过滤叶子、perms 前缀→按钮 perm、relationEndpoints→Assign<Target>Dialog（GET 回显+PUT 覆盖）+api.relation 槽位、protectedRows→SUPER_CODE 常量+show 行级隐藏。手调项走 config 可选 front 声明（枚举含 tagType/列宽/tip/排序/手工槽——与后端 rule/messages 入 config 同构）；声明式联动 front.visibility 生成 visible(form) 钩子（menu isNav/isMenu/isButton 逐字），复杂联动留 TODO 手工槽（role data_scope=5 部门树、menu type 完整规整由后端 normalizeByType 兜底）。api 薄壳甲案：手写 axios 消费 @/utils/request，函数名/签名与 D0 逐字（list/get/create/update/delete/setStatus + getXxxYyyIds/assignXxxYyys），接口注释由元数据推导（删除护栏/级联、必填、防成环等）。排版确定性：config 条目含注释或显示宽度（CJK=2）>112 → 逐键多行，api 签名 >100 列参数换行（prettier printWidth 同口径）。★保真 diff（vs web 仓 D0 手写两页）：menu index.vue / menu api.ts / role AssignMenuDialog.vue 正文逐字一致（仅 @mission 文案+@date 头）；role index.vue 除头外仅 4 处可解释 diff（getDeptTree import、api 槽位注释第二行、assign show 注释文案、detail 注释文案，及 dept_ids 项→TODO 手工槽）；role api.ts 仅 dept_ids 相关 3 处（ADR-9 槽）。开箱即跑：产物 copy 进 web 仓临时目录 → vue-tsc + vite build 通过后清理。后端唯一行为修正（已知项①）：assignScenes 计算块与手写 RoleValidate 的 sceneAssignMenus menu_ids require→array（允许空数组=清空授权，覆盖式语义自洽）；实跑：分配→清空 [] code 0、role_menu/casbin 零残留、enforce 复验（临时 d1tester 授权态 posts 200 → 清空后 403000）、临时 role/admin 硬删零残留。防污染硬门：post/dept/menu 后端重生成与重锚基线逐字一致（仅 @date），role 仅差修正行 → 四标的重锚（runtime/generated/<m> 新增前端产物锚点），二次重生成自洽。web 仓同步：docs/CRUD-SCHEMA.md §4 已知边界（空数组清空已放开）与 §6 api 行（甲案）更新。**M3 全段闭环：后端四范式 + 前端全栈产物复刻完成，进入 M4（通用业务）**。

> M4-A 落地(2026-06-12,server aacd104 + 回炉① 8e5cbc5 / web fdd74c1,GitHub+Gitee dev 双推):内容模块吃狗粮首发。三表(bx_content_category 树 / bx_content / bx_banner,tenant_id+软删标配,content 带 create_by/create_dept、banner 带 create_by)全经 bx:make 生成。后端 `app/common/library/HtmlPurifier`(ezyang/htmlpurifier ^4.19 白名单)+ ContentService 落库前富文本净化。前端三黄金样板组件:**XEditor**(wangEditor v5.1.23 MIT)/ **XUpload**(单多图/预览/url 回填)/ **AuthImg**(受控 URL 鉴权取流);XFormDrawer 扩展 datetime/slot 控件。ContentSeeder 幂等:内容管理目录+三菜单+12 perms(46→62)+ 字典 sys_content_status。后端 33/33、前端 20+ API 联调、防污染硬门通过(回炉① 8e5cbc5 修树形产物 unused import lint,dept 按 M3-C 先例重锚)。**★吃狗粮裁定(ADR-15)**:7 项回炉并入 **M3-E**——richtext / image / search:daterange(单字段 between)/ exact 类型分流 / readonly / listOrder / seeder menuDir·menuPath;2 项定为永久手工槽——双字段区间交集查询、搜索 select 远程数据源/关联名称列(数据源函数无法声明)。已知项:① 本地存储驱动下富文本内嵌图/封面需鉴权取流,生产建议切 OSS 公网 URL(M2-D 驱动就绪)或为图片类开公开直链路由(待 PM 定);② wangEditor 须装 @wangeditor/editor-for-vue@next(Vue3 版);③ web 既有 multi-word 命名 lint 噪音(D0 起),构建以含 type-check 的 npm run build 为准。

> M3-E 落地(2026-06-12,server cb9a637,GitHub+Gitee dev 双推):吃狗粮回炉,7 项通用缺口进生成器,均「缺省不生成」(复刻 M3-C 缺省零影响)。① **richtext:true** → 后端 Service create/update 双注入 `HtmlPurifier::clean` + 前端 XEditor 槽、列表默认跳过;② **image:true** → 前端 XUpload 表单槽 + 列表 AuthImg 列(column=false 则不出列不 import);③ **search:'daterange'** → 前端 daterange + 后端单字段 between(起 00:00:00~止 23:59:59);④ **exact 类型分流** → 按反读 cast,整型 `(int)`、字符串等值不强转(修复字符串 exact 不可用);⑤ **readonly:true** → 排 FILLABLE(防越权写)+ 表单不渲染、列表仍显示;⑥ **listOrder** 模块级排序声明;⑦ **seeder menuDir/menuPath/menuIcon/menuSort** → 业务模块脱离 System 写死(父目录 find-or-create + 路径推导)。★保真:content/banner/category 重生成 == M4-A 手工样板(diff 仅 @date + 可解释项:category_id 跨模块槽、banner effective 双字段交集、注释措辞)。防污染硬门:八标的(post/role/menu/dept + admin/dict/config/file 首次入锚)0 漂移、exact 分流对老标的无影响(均无字符串 exact)、二次重生成自洽。安全:richtext 净化实跑剥离 / readonly 排 fillable 实测(PUT view_count 落库仍 0)/ daterange·exact 端到端 + SQL 注入探针参数化。**永久手工槽**:banner effective 双字段交集、content category_id 跨模块数据源(已入 CRUD-SCHEMA.md §7)。此后 M4-B 起业务模块直吃 richtext/image/daterange/readonly/listOrder/menuDir 红利。
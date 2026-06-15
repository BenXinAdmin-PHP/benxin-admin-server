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
| ADR-11 | 支付框架(M4-C) | `PayInterface` + 渠道 Provider + `PayManager`(复刻 StorageManager)+ 自建 `BxPay` 服务层(复刻 BxJwt)承载订单状态机/幂等/event。底层 SDK = **yansongda/pay v3**(MIT,PHP≥8.0,框架无关)。金额**整型分**;渠道密钥/证书 **ConfigCrypt 入库**(.env 持密钥);回调强制**验签+幂等(notify_log 去重)+金额二次校验+状态机合法迁移**,原文落库审计。**业务解耦**:底座只管支付,经 `biz_type/biz_id` 关联上层单据,支付/退款成功 fire `PaySuccessEvent`/`RefundSuccessEvent` 供上层闭源业务监听,**底座不含具体业务**(守 §1) |
| ADR-12 | 消息渠道(M4-D) | `MessageChannelInterface` + 短信 Provider(ali/tencent,**自建 HTTP 签名适配器**)+ `MessageManager`(复刻工厂);渠道切换走 bx_config `sms_channel`,AK/SK 经 ConfigCrypt 注入。**验证码统一服务**(Valkey `bx:sms:code:{scene}:{mobile}` TTL5min + think-throttle 多维限流:手机号 60s/1 条·天上限、IP 天上限,校验消费即删、错误锁定)供 M5 登录消费;短信模板配置化(bx_sms_template);bx_sms_log 手机号脱敏入库 |
| ADR-13 | 微信能力(M4-B) | 自建 `BxWechat` 服务承载核心能力:**access_token/jsapi_ticket 中心化缓存**(Valkey `bx:wechat:token:{type}:{appid}`,防并发刷新锁、多实例共享)、小程序 code2session、公众号 oauth(供 M5 懒登录消费),多账号(公众号/小程序/企业微信)。配置**复用 bx_config**(ConfigCrypt,§9),零新表。复杂能力(模板/订阅消息、素材、开放平台)**按需引 w7corp/easywechat 6.x**(MIT,PHP≥8.0.2),不全量引入 |
| ADR-14 | 内容模型(M4-A) | 通用 CMS 底座 = 内容分类(树)+ 内容 + 广告位,**不绑具体业务字段**(上层扩展);status 字典化(`sys_content_status`);**富文本 XSS 净化**——后端 `HtmlPurifier`(ezyang/htmlpurifier,LGPL 作依赖不传染)白名单过滤,前端 wangEditor(MIT)。内容标签**延后**(上层按需) |
| ADR-15 | M4 吃狗粮策略 | 真实业务 CRUD 全走 `bx:make`(反向检验生成器),框架/基础设施手写。生成缺口**三类归档**:① config 可声明即补;② 生成器范式缺失 → **M3 小版本回炉**(改 stub/计算块 + 重跑防污染硬门作回炉验收门,须与重锚基线逐字一致仅 @date 差);③ 一次性手工槽 → 文档化为 `front` 槽。每个 M4 阶段完成报告必附「**狗粮反馈**」小节 |
| ADR-16 | C 端用户模型(M5) | **`bx_user` 主表 + `bx_user_oauth` 关联表**——一个 user 对应多来源 openid(platform=mini/mp),靠 `unionid` 打通小程序/公众号同一微信用户。bx_user:mobile 唯一 + nickname/avatar/gender/unionid/status/last_login_at + tenant_id + 软删;bx_user_oauth:user_id + platform + openid(同 platform 唯一)+ unionid。C 端用 `user_id` 维度,**不挂 create_by、不接 Casbin**(C 端不做 RBAC)。M4-C `bx_pay_order.user_id` 此时正式指向 bx_user |
| ADR-17 | 懒登录 + 双端登录流(M5) | **懒登录**(ADR-3):token 可选携带 + 接口分级(浏览免登录/核心强制)+ 401 分流(核心跳登录、401003 单飞 refresh 续期)、「我的」游客/登录态。**双端登录流**:小程序 `wx.login`→code2session→openid + `getPhoneNumber` 新版 code 换手机号(免 session_key);H5(公众号内)oauth→openid + **短信验证码**(M4-D)。**登录即注册,openid+mobile 缺一不可**;配开放平台则 unionid 两端打通同一 user、否则两端独立(底座不强制开放平台);**H5 限微信环境**(非微信浏览器降级提示)。**api guard 复用 BxJwt + 独立 `JWT_API_SECRET`**、独立 Valkey 白黑名单 `bx:bxjwt:api:*`、access+refresh 双令牌、C 端无 Casbin。报文加密 ADR-4 默认关、M5 不启用 |

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
| 业务段 | 各模块分配 | 内容 11xxxx、支付 12xxxx、消息 13xxxx、微信 14xxxx、C 端用户/登录 15xxxx |
| 微信 14xxxx | 140001 配置缺失 / 140002 access_token / 140003 jsapi_ticket / 140004 JSSDK 签名 / 140005 code2session / 140006 oauth / 140099 微信接口通用(透传 errcode/errmsg) |
| 支付 12xxxx | 120001 配置缺失 / 120002 下单失败 / 120003 订单不存在 / 120004 状态非法迁移 / 120005 回调验签失败 / 120006 金额不一致 / 120007 退款失败 / 120008 退款超余额 / 120099 渠道通用 |
| 消息 13xxxx | 130001 配置缺失 / 130002 发送失败 / 130003 发送过频 / 130004 验证码错误 / 130005 不存在或过期 / 130006 错误超限锁定 / 130099 渠道通用 |
| C 端 15xxxx | 150001 新用户需补充手机号 / 150002 账号已被禁用或注销(status=0 或命中软删行) / 150099 登录失败通用。微信接口错误复用 14xxxx（140005 code2session / 140006 oauth / 140099 getPhoneNumber 透传）、短信验证码错误复用 13xxxx（130004/130005/130006），不重复造码 |

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
| M4-B | 微信配置 + BxWechat 能力(token/ticket/code2session/oauth,配置复用 bx_config) | ✅ 已完成 |
| M4-C | 支付框架(PayInterface + 渠道 + 订单/退款 + 回调状态机,yansongda/pay 底层) | ✅ 已完成 |
| M4-D | 消息(短信渠道 + 验证码 + 模板 + 系统公告,验证码为 M5 铺垫) | ✅ 已完成 |
| **M5-A** | C 端认证基建(bx_user + bx_user_oauth + api guard JWT + C 端前台内容接口) | ✅ 已完成 |
| M5-B | 登录闭环(小程序 code2session+getPhoneNumber / H5 oauth+短信验证码,登录即注册) | ✅ 已完成 |
| M5-C | 首页 + 我的 + 懒登录前端(uni.request token 携带 + 401 分流 + 游客/登录态) | ✅ 已完成 |
| M6 | （可选）官网 + 首页拖拽搭建 | ⚪ 未开始 |
| **发布准备** | 开源发布准备：收口 ✅ / README+基线迁移 ✅ / README 增补 ✅ / 后台视觉升级 ✅ / 生成器 M3-F：树形 treeSelect label 参数化（dept quirk 根治）✅ / 截图+终审+发布触发（daxing 主场，待） | 🔵 进行中 |

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
> M4-B 落地(2026-06-12,server B-1 19f4161 / B-2 a7844f4,GitHub+Gitee dev 双推):微信能力底座(纯自建,不引 easywechat)。配置复用 bx_config group=wechat 九项前缀 key 骨架(mp_/mini_/work_,敏感项 AES 假串),WechatConfigSeeder 幂等 + 与 M2-B 旧示例合并。**BxWechat**(app/common/library/wechat/,6 类+1 异常):WechatManager 工厂(mp/mini/work,缺配置 140001)+ WechatAccount 抽象(access_token 中心化:Valkey `bx:wechat:token:{type}:{appid}` TTL=expires_in−300,**★防并发刷新锁** SET NX EX 10s + Lua 校验释放 + 兜底强刷;callWithToken 对 40001/42001/40014 清缓存重试一次)+ MpAccount(jsapiTicket/jssdkSign/oauthUrl/oauthAccessToken/oauthUserInfo)+ MiniAccount(code2session,session_key 不缓存不日志)+ WorkAccount(预留)+ HttpClientInterface/CurlHttpClient(可注入 mock,强制 HTTPS+证书校验,传输错→140099)+ WechatException(继承 BusinessException)。对外:`GET /api/v1/wechat/jssdk`(api guard 懒登录,限流 60/m,缺 url 140004);code2session/oauth 作 service 留 M5;探针 `/_wechat_probe`(APP_DEBUG+JwtAuth)。错误码 14xxxx 固化(§6.2)。离线全绿 54+7:防并发锁双进程真实模拟(仅 1 次刷新、两端同 token)/ JSSDK 签名对照微信官方校验工具逐字 / 40001 重试序列无死循环 / session_key 不落 Valkey / 占位假串真实 API 40013→140002 透出。安全:密钥 AES+脱敏、session_key·网页 token 不落明文(LogSanitizer)、token·ticket 仅 Valkey、探针仅 DEBUG。**需真实凭据待验**(留 daxing 测试号,建议并入 M5 联调):真实 token/code2session/oauth/JSSDK 内验,配号后跑 _wechat_probe 四项转绿。已知项:① 企业微信仅占位 + WorkAccount 承载配置未实现(qyapi 语义不同,届时单独实现或挂 easywechat);② getByGroupKey 仍延后(前缀 key 规避,M4-C 支付沿用);③ WechatManager 进程内实例缓存,常驻进程切换需 flush()(与 Casbin 同列优化项);④ 微信 mock 测试脚本暂在 /tmp 未入库(建议移仓留档)。

> M4-C 落地(2026-06-13,server C-1 38234b8 / C-2 d35713d,GitHub+Gitee dev 双推):支付框架手写核心。三表(bx_pay_order:order_no/out_trade_no 唯一含软删 + transaction_id 索引 + biz_type/biz_id 解耦 + user_id 留 M5 + refunded_amount 累计退款额;bx_pay_refund:refund_no 唯一;bx_pay_notify_log 只增不软删,幂等唯一键 (channel,event_type,idem_no)——pay 取 out_trade_no、refund 取 out_refund_no)。配置 bx_config group=pay 九项前缀 key(wxpay_/alipay_,私钥/APIv3 敏感 AES 假串、不进仓库),微信支付 appid 复用 wechat 组。框架 app/common/library/pay/:PayInterface(prepay/query/refund/refundQuery/close/verifyNotify/ack)+ NotifyResult(金额统一分)+ WechatPayProvider/AlipayProvider + PayManager::channel()(复刻 StorageManager + fake() 注入);app/common/service/BxPay 核心收口(状态机唯一入口 transitionTo + 幂等 + 金额二次校验 + 退款余额 + event)。已实现 trade_type:微信 jsapi/native、支付宝 wap/page(h5/app 留扩展位)。金额整型分,支付宝元↔分换算无浮点误差。**★回调安全四件套**:验签(失败 verified=0+ackFail 不泄露)/ 幂等(notify_log find-or-create,processed=1 直接 ACK 不重复 fire)/ 金额二次校验(不符 120006 拒)/ 状态机(TRANSITIONS 表 canTransit,合法 0→1·1→2/3·3→2/3,非法 120004 拒)。ACK:微信 {code:SUCCESS}/支付宝 success。退款:支付宝同步 confirm、微信异步等退款回调;refund 余额校验 120008;confirmRefund 事务更新累计退款额判部分/全退 + fire。业务解耦:PaySuccessEvent/RefundSuccessEvent(biz_id 透传)+ 空示例 PayBizExampleListener(底座零业务,app/event.php 注册)+ 上层路由文档。对外:回调 POST /api/v1/pay/notify/{channel}(?type=refund 区分)、BxPay::prepay service(不暴露通用下单)、后台 pay-orders 列表/详情/退款(system:pay:list|refund + confirm=1 二次确认)、探针 _pay_probe。错误码 12xxxx 固化(§6.2)。离线 mock 全绿 C-1 38/38 + C-2 35/35(四件套/状态机/event/退款全路径),微信套件回归无回退。安全:密钥 AES 不进仓库、回调四件套 + notify_log 审计、退款 JwtAuth+Casbin+二次确认+操作日志、yansongda 内置 logger 关闭(私钥不落第三方日志)、底座零业务、探针 DEBUG。**★安全/依赖要点**:yansongda **锁 ^3.7.20**(修复 PKSA-8dgs-n4fh-5pd5「微信回调验签 localhost Host 头绕过」CVE,正中回调要害)+ 必需依赖 **hyperf/pimple ^2**(yansongda v3 在 ThinkPHP 下的 PSR 容器,缺则渠道初始化报错)。需真实商户号待验(留 daxing 商户/沙箱,建议单列支付真实联调或并入 M5):真实下单调起/支付回调验签/退款及退款回调;yansongda v3 证书模式(cert 模式 vs 公钥模式)config 形状待配真实证书时最终核定(当前按公钥/单证书占位映射,已到缺证书边界)。已知项:①「只读+退款」特殊形态本阶段手写,记为生成器「只读模块」吃狗粮候选;② getByGroupKey 仍延后(前缀 key 规避);③ 后台支付订单前端页未做(后端契约就绪,随前端排期);④ aliyun composer 镜像陈旧止于 3.7.16,装 3.7.20 需回落官方源(环境经验,镜像偶发滞后)。

> M4-D 落地(2026-06-13,server D-1 018c3f3 / D-2 42c052e、web D-2 a9691d9,GitHub+Gitee dev 三推核验):消息模块 + M4 收官。三表(bx_sms_template scene 唯一 withTrashed / bx_notice content 富文本+create_by / bx_sms_log 只增不软删手机号脱敏)。bx_config group=sms 八项前缀 key(ali_/tencent_,AK Secret/SecretKey 敏感 AES 假串)。**手写(D-1)**:SmsChannelInterface/SmsResult + SmsAliProvider(自建 RPC HMAC-SHA1)+ SmsTencentProvider(自建 TC3-HMAC-SHA256)+ MessageManager(缺省取 sms_channel,HTTP 可注入);**SmsCodeService**(多维限流 60s/手机号天 10/IP 天 20 + 防爆破 5 次锁定 + Valkey TTL5min 消费即删不落明文 + bx_sms_log 脱敏)。**bx:make 生成(D-2,★M3-E 红利首验)**:短信模板 + 系统公告 CRUD(menuDir=消息管理 find-or-create 不写死 System);**bx_notice richtext:true 自动效果实证**——生成 NoticeService 自动 HtmlPurifier::clean 双路径 + 前端自动 XEditor 槽,与 M4-A 手写逐字等效、零手工接线,**吃狗粮闭环(M4-A 手写→M3-E 回炉→D-2 自动复用)成立**;防污染硬门八标的 0 漂移。对外:POST /api/v1/sms/code(接口级 1/m + scene 白名单 login/bind/reset)+ GET /api/v1/notices[/:id](前台只读已发布)+ 后台模板/公告 CRUD + 短信日志只读 + 探针。错误码 13xxxx 固化(§6.2)。离线全绿 D-1 41/41 + D-2:阿里 RPC 签名==官方向量、腾讯 TC3 CanonicalRequest/StringToSign/签名链==官方中间值、验证码全路径、richtext 净化、scene 唯一含软删。安全:AK/SK AES+脱敏、手机号脱敏 + LogSanitizer 加 mobile/phone/sms_code/verify_code/captcha 黑名单、防轰炸(接口 1/m + 多维)+ 防爆破(5 次锁定)、验证码不落明文、探针 DEBUG。需真实短信凭据待验(留 daxing 或并入 M5):真实下发需审核签名+模板 ID(seeder 占位 template_code 待后台改真实值)。已知项:生成器小缺口(非阻断)——前端 view 输出固定落 views/system/,menuDir 仅作用 seeder component 路径,D-2 按 component 路径人工放置 view(沿用 M4-A 人工 copy);记为生成器未来增强候选(frontend 输出目录跟随 menuDir)。

> **M4 收官(2026-06-13,server+web dev)**:通用业务四块全闭环——内容(A,bx:make 吃狗粮首发)/ 微信能力(B,BxWechat 自建 token/code2session/oauth)/ 支付框架(C,BxPay+yansongda+回调四件套+event 解耦)/ 消息(D,短信渠道+验证码+模板+公告)。**新沉淀可复用基建**:BxWechat、BxPay+PaySuccessEvent(业务解耦)、MessageManager+SmsCodeService、HtmlPurifier+XEditor/XUpload/AuthImg。**吃狗粮闭环成立**:M4-A 手写富文本样板 → M3-E 回炉 → M4-D 系统公告零手工自动复刻,护城河「先手写样板、再生成器复刻」在业务层跑通。**M5 衔接点就绪**:SmsCodeService::verify(验证码登录)+ MiniAccount::code2session / MpAccount::oauth(微信登录)+ BxPay::prepay(C 端下单)。下一步 M5 进入设计评审(C 端 uni-app 懒登录)。

> **M5 启动约定(2026-06-13)**:M5 设计评审定案(daxing 全按 PM 建议)——C 端 uni-app 懒登录,从后端底座转向 C 端真实使用,消费 M4-B 微信能力 + M4-C 支付 + M4-D 验证码。定案:① C 端用户模型(ADR-16)bx_user 主表 + bx_user_oauth 关联表(多来源 openid,unionid 打通);② 懒登录+双端登录流(ADR-17)小程序 code2session+getPhoneNumber 新版 code 换 / H5 oauth+短信验证码,登录即注册 openid+mobile 缺一不可,H5 限微信环境;③ api guard 复用 BxJwt 独立密钥 JWT_API_SECRET、C 端无 Casbin;④ 报文加密 ADR-4 默认关不启用;⑤ unionid 配开放平台则两端打通否则独立(底座不强制)。M5 拆 A 认证基建 → B 登录闭环 → C 首页+我的+懒登录前端。M5-A 任务书已下发。

> M5-A 落地(2026-06-13,server A-1 f08ef58 / A-2 6695fd6,GitHub+Gitee dev 双推,ls-remote 三端核验 =6695fd6):C 端认证基建。两表:bx_user((tenant_id,mobile) 唯一含软删 uk_tenant_mobile + idx_unionid + gender/status/last_login_at + tenant_id + 软删,**不挂 create_by/create_dept、不接 Casbin**,承 BxModel)+ bx_user_oauth((platform,openid) 唯一 uk_platform_openid + idx_user_id,**直继承 think\Model 不软删**,关联随 user 生命周期)。bx_pay_order.user_id(M4-C 已留 bigint MUL 默认 0)逻辑指向 bx_user.id,无需改表。**api guard JWT**:独立 JWT_API_SECRET(.env ≥32B)+ config/jwt.php api guard 独立 TTL(access 2h / refresh 30d,C 端登录态更久,.env 可调);**BxJwt::issueForApi(userId,tenantId)** 先 refresh(写白名单)后 access(携 rjti)、sub=user_id(uid claim),供 M5-B 统一签发;Valkey 白黑名单 bx:bxjwt:api:wl:refresh:{jti} / bx:bxjwt:api:bl:{jti} 与 admin bx:bxjwt:admin:* 实测隔离;api JwtAuth 中间件(M0 占位→完整版)校验 api access+黑名单 → 取 bx_user(status=1)→ 注入 userId/userInfo/jwtClaims,C 端无 Casbin;refresh(不轮换,失效 401004)+ logout(拉黑 access jti + 撤 refresh 白名单)闭环。令牌闭环自测 11/11 绿 + 双向跨 guard 互拒 401(aud 不符)。**C 端前台内容接口**(懒登录、免登录、token 可选):GET /api/v1/contents(已发布 status=1+publish_at 过滤、列表字段白名单不含正文、category_id/keyword、置顶优先)+ /:id(含正文、浏览量原子 inc +1 防并发覆盖、草稿/未发布 404)+ GET /api/v1/banners(启用 + 生效区间 start_at≤now≤end_at + position 可选,sort 升序)。字段精简:contents/banners 均不外露 create_by/create_dept/tenant_id/deleted_at(实测无泄露)。安全:密钥隔离 + 白黑名单(refresh 白 + access jti 黑)+ 免登录只读不泄敏 + ORM 参数化 + 探针仅 DEBUG(_api_login_probe 在 isDebug() 内注册);php -l 全过(14 文件),web/uniapp 未动。已知项:① C 端令牌 access 2h/refresh 30d(.env JWT_API_ACCESS_TTL/JWT_API_REFRESH_TTL 可调);② ApiTesterSeeder 播种测试用户(id=1,mobile=19900000000,仅 DEBUG,M5-B 真实登录就位后清理);③ 真实微信 code2session/oauth/手机号登录闭环留 M5-B(需 daxing 测试号/小程序)。

> M5-B 落地(2026-06-13,server B-1 7bddcd5 / B-2 3f36eba,GitHub+Gitee dev 双推,ls-remote 三端核验 =3f36eba):C 端登录闭环。**补 M4-B 缺口**:WechatAccount 加 apiPost+callWithTokenPost(callWithToken 的 POST 变体,共用 access_token 中心化缓存 + 40001/42001/40014 失效清缓存重试一次);MiniAccount::getPhoneNumber(phoneCode) POST wxa/business/getuserphonenumber 免 session_key 换号、返回 purePhoneNumber、失败 140099。**UserAuthService::loginOrRegister**(platform,openid,?mobile,?unionid) 全程事务,四级定位(D3):① (platform,openid) 命中→老用户静默(忽略入参 mobile)② openid 未命中+unionid 非空→按 unionid 命中已注册 user 补建当前端 oauth(两端打通)③ 同手机号正常 user→复用+补 oauth(同手机号即同人)④ 全新→建 user+oauth;(tenant_id,mobile) withTrashed 前置检测命中软删行→150002(D4 不撞唯一键)、status≠1→150002、新建不写 create_by(ADR-16)、默认昵称「用户+手机后 4 位」。**登录端点**(均免登录 + 限流 10/m/IP,独立于 sms/code 1/m):POST /api/v1/login/mini {code,phone_code?}(code2session→openid 失败 140005;未注册无 phone_code→150001、有则 getPhoneNumber 换号;老用户静默)+ POST /api/v1/login/h5 {code,mobile?,sms_code?}(oauth→openid 失败 140006;未注册缺 mobile/sms_code→150001、齐则 SmsCodeService::verify 消费即删 失败透传 130004/5/6;老用户静默);成功统一 BxJwt::issueForApi 发双令牌、响应只回令牌(user 详情留 M5-C)。D5:后端靠 oauth 天然约束微信环境,UA 判断留 M5-C。**错误码 15xxxx 固化**(已合入 §6.2):150001 新用户需补手机号 / 150002 禁用或注销 / 150099 登录通用;微信复用 14xxxx、短信复用 13xxxx。自测 25/25(微信 mock 4 + UserAuthService 9 + Login 端到端 7 + 双 guard 隔离 2 + 硬删零残留 1)全绿,php -l 全过,web/uniapp 未动。**安全**:登录限流 + 凭据(code/phone_code/mobile/sms_code)LogSanitizer 全打码 + session_key 不落 + 验证码消费即删 + 新建不写 create_by + ORM 参数化 + 探针/mock 仅离线。已知项:① **限流命中为 HTTP 200 + body code=429000**(§6.1 业务码风格 A 应有之义,唯 401 走 HTTP 状态;前端 429 分流按 body code 判,PM 定不改 throttle);② 真实微信/短信凭据待 daxing 验(test 号/小程序/已审签名模板,占位 appid 下 code2session 实达微信返 140005 证明接线就绪);③ getPhoneNumber 当前取 purePhoneNumber(不含区号),需区号配真实号后核定;④ ApiTesterSeeder 保留作回归载体(DEBUG),mock 脚本入库 tests/m5b_login_mock.php(修正 M4-B /tmp 散落);⑤ unionid 打通需配开放平台,否则两端独立(底座不强制,ADR-17)。

> M5-C 落地(2026-06-14,server 3ade915(C-1a 补口) / uniapp d2f7d26(前端),GitHub+Gitee dev 双推,ls-remote 三端核验 server=3ade915 / uniapp=d2f7d26):懒登录前端,**C 端 uni-app 自 M0-C 后首次实质编码**。**后端补口**:GET /api/v1/login/wechat/oauth-url(免登录 60/m,复用 MpAccount::oauthUrl 生成网页授权 URL + 随机 state 写 Valkey 短 TTL 回传前端比对防 CSRF)+ GET /api/v1/user/profile(api JwtAuth,精简 {id,nickname,avatar,gender,mobile,last_login_at},不外露 openid/unionid/tenant_id/deleted_at/status,跨 guard 隔离回归)。**请求层 utils/request.ts**:token 可选携带(匿名浏览不报错)+ 业务码解包 + **401 单飞续期**(401003 并发复用同一 Promise 刷一次重放、refresh 不轮换;401001/401004 清会话 + SESSION_EXPIRED_EVENT 广播解耦 request↔store)+ **429 按 body code===429000 判**(不看 HTTP 状态,承 M5-B 已知项①)+ uni.setStorageSync 持久化两端通用。**stores/user.ts**:isLogin/userInfo/setLogin/fetchProfile/logout/resetLocal,启动从 storage 恢复(懒登录不预校验、首个受保护请求 401 兜底);ensureLogin 守卫置 utils/login.ts 避免循环依赖。**双端登录流(条件编译 + login.vue)**:小程序 uni.login→/login/mini 老用户静默,新用户 150001→原生 button open-type=getPhoneNumber 拿新版 phone code→重 uni.login 取新 code→/login/mini{code,phone_code} 注册;H5 isWechatEnv UA 判断(非微信降级提示 D5)→snsapi_base 静默 oauth(location.href 跳授权)→回调取 code/state→history.replaceState 清 URL + state 比对→老用户静默,新用户 150001→手机号+短信验证码(/sms/code+60s 倒计时)→/login/h5 注册(复用同轮 oauth code)。**首页**:home_top swiper 轮播 + 内容列表(onLoad/onPullDownRefresh/onReachBottom/finished,消费 M5-A 白名单字段无正文,全程免登录);**详情**:rich-text 渲染(后端 HtmlPurifier 净化 + rich-text 不执行脚本 双重防 XSS)+ 浏览量原子 +1;**我的**:游客态(登录入口)/ 登录态(avatar/nickname/手机脱敏/性别+登出)+ ensureLogin 守卫示例(ADR-3)。自测:vue-tsc 全过、双端构建 DONE、server 补口 curl 全绿、php -l 全过。安全:token XSS 标注(H5 storage 同 web M1-D 口径,小程序沙箱;C 端无服务端会话不引 httpOnly)+ oauth code 即清 + state 防 CSRF + 验证码 60s+后端 1/m 双重防刷+不本地持久化 + 报文加密未启用(VITE_ENABLE_ENCRYPT=false)+ 仅 wot-design-uni(MIT)+系统字体(§10)+ **manifest 真实 appid 不入库**。已知项:① **H5 hash 模式 oauth 回调可能丢 # 片段落根路由**,真机定夺(PM 推荐方案②固定非 hash 回调入口路由作 redirect_uri,登录逻辑不变);② 真实微信/短信凭据待 daxing 验(小程序+公众号测试号+回调域+已审签名模板);③ 本地驱动下封面/富文本内嵌图 C 端取流不通(M4-A 已知项①复现,发布前切 OSS 或开图片公开直链路由);④ profile 现返完整 mobile(本人数据,前端已脱敏,接口侧脱敏 PM 后续可定);⑤ ApiTesterSeeder/mock 保留作回归载体(DEBUG 限定生产不播种)。

> **M5 收官(2026-06-14,server+uniapp dev)**:C 端 uni-app 懒登录全链路闭环——C 端认证基建(A,bx_user + bx_user_oauth + api guard 独立 JWT + 前台内容接口)→ 登录闭环(B,小程序 code2session+getPhoneNumber / H5 oauth+短信验证码,登录即注册四级定位 + unionid 打通)→ 懒登录前端(C,uni.request token 可选携带 + 401 单飞/429 body code 分流 + 双端登录流 + 首页/详情/我的 游客·登录态)。**新沉淀**:UserAuthService(登录即注册范式)、api guard 独立密钥体系(与 admin 完全隔离)、uni 端请求层(401 单飞 + 会话广播解耦)、双端登录编排(条件编译)。**ADR-16/ADR-17 全部落地**。至此 **M0~M5 主线全部收官**:三端脚手架 → 认证+RBAC → 系统管理 → 代码生成器护城河 → 通用业务四块 → C 端懒登录。底座达「可运行 + C 端真实可用」。下一步进入**开源发布准备**(发布前收口 + README/Apache-2.0/演示部署/star 引流,守 §1 商业模式),M6(可选官网/首页拖拽搭建)按 §3 排最后、视引流效果再定。

> 开源发布前收口(2026-06-14,server 6049c1f / web 1b6e9ac / uniapp c7ccb2d,GitHub+Gitee dev 双推三端一致):三仓安全与卫生终检全绿。**★git 历史硬关卡过**:pickaxe 全历史(server 43/web 8/uniapp 4 commit)对私钥/阿里 LTAI/腾讯 AKID/真实 appid/GitHub·Slack·Stripe token **0 命中**,真实 JWT_API_SECRET/真实 mp appid 从未入库,历史干净无需重写。密钥:工作区 0 真实凭据(配置 Seeder 全 PLACEHOLDER_FAKE_* 假串),.env 未跟踪仅 .env.example 入库(全占位)、APP_DEBUG 改默认 false。后门:5 探针 + Tester/ApiTesterSeeder 全 APP_DEBUG 守门、生产实测不可达/不播种;**默认超管密码天然安全**(SUPER_ADMIN_INIT_PWD 空则跳过建超管,零默认弱口令)。协议:三仓 LICENSE=Apache-2.0 + composer/package license 字段齐(web/uniapp 补齐);依赖全可商用(无 AGPL/GPL/SSPL 传染,htmlpurifier LGPL 作依赖不传染,yansongda 3.7.20 CVE 已修);§10 无嵌入字体(系统字体栈)、图片仅框架默认。冷启动三仓通过(server migrate+登录 smoke / web build+type-check / uniapp 双端构建)。遗留决策(PM 裁定):① git 历史不清(0 命中)② 默认密码保持现状(README 强制文档化必设)③ **防污染基线迁 tracked tests/generator-baseline/**(护城河自证资产,并入 README 任务书任务 0)④ manifest 真实 appid 本地 skip-worktree 根治⑤ favicon/logo 框架默认列发布润色候选(非阻断)。未做:dev→main 合并 + tag(待 README 完成 + daxing 确认触发)。

> README 建设 + 防污染基线迁移(2026-06-14,server f60a3ad(基线)+354f243(README) / web dce680f / uniapp e9eacd1,GitHub+Gitee dev 双推三端一致):防污染基线八标的(65 文件)从 gitignored runtime/generated/ 迁 tracked tests/generator-baseline/,新增 verify.sh 保真回归脚本(重生成八标的逐字 diff 忽略 @date/@updated,POSIX/bash3.2 兼容修 macOS 无关联数组坑,实测 EXIT=0)+ 基线说明 README + Make.php docstring 指向;迁移前先验可复现性(post/role/menu/dept 走 --config、admin/dict/config/file 纯表结构推导,八者逐字一致)。clone 全仓仅凭仓内资源即可复现「生成==手写」保真回归(护城河自证资产到位)。三仓 README:server 主 README 九节(🏗️护城河+🍖吃狗粮卖点最前含 verify.sh、🔴安全必读块、开源边界、冷启动链路与收口一致、路线图 M0~M5✅),web/uniapp 简版(实装特性+起步+指向主仓,web 修残留 8000→8801、uniapp 补 manifest skip-worktree 提示),命名权威性无 binxin 误写、截图位三仓各 1 处预留。**开源主体就绪**,剩 daxing 三步:截图(4 处)+ 文案终审 + 发布触发(dev→main + tag v0.1.0 + 公开 + 引流)。真机联调(微信/短信真实凭据)可并行作背书。

> README 增补(2026-06-14,server 6480829 / web 52dc53b / uniapp 5bdb873,GitHub+Gitee dev 双推三端一致):README 四块增补落定即终稿。① 首次登录指引(快速开始补「首次登录后台」5 步:超管用户名=admin 取自 AuthSeeder.php:71-73 真实值、SUPER_ADMIN_INIT_PWD 不设则不建账号防弱口令、登录契约 §7、C 端懒登录无账号密码);② 全程 Vibe Coding 标识(server 标题区后独立段「AI 规划+编码、人类拍板验收,非无审查吐码——规范化基线+生成器+保真回归+安全基线自证」引向护城河/安全对冲顾虑,web/uniapp 各一句链主仓);③ 资源合规·可商用(措辞「未嵌商业付费字库/全开源可商用」非「非商业」,系统字体栈+OFL+MIT/Apache/ISC 图标+Apache-2.0+依赖零 AGPL 传染);④ 安全特性独立章节 11 项已落地(认证双 guard 隔离 / CSRF 天然免疫[Bearer+无 cookie+oauth state] / 授权 Casbin+数据权限五档 / 密钥 AES+git 历史 0 泄露 / 数据 ORM 参数化+白名单+软删唯一 / XSS 后端净化+前端转义双重 / 上传双重校验 / 日志脱敏 / 支付四件套 / 防刷 / 生产基线)+ 可折叠「生产加固建议」(安全响应头组合/密码策略/依赖审计/token 存储权衡/IDOR 提示,明标部署侧建议非已落地)。校验全过(超管名源码核实、措辞可商用、加固建议单列不混淆、无 binxin 误写、三端一致)。**README 终稿就绪**,发布前仅剩 daxing 三步:截图(4 处)+ 文案终审 + 发布触发(dev→main + tag v0.1.0 + 公开 + 引流)。
> 后台视觉升级 定案(2026-06-14,发布准备阶段,任务书已下发待 Claude Code 执行):daxing 本地起后台调试暴露两问题——① 后台除 role/menu 黄金样板外大面积 PlaceholderView 占位(M3-D1 生成器前端产物落 extend/generator/output/frontend/ 未 copy 进 web 仓);② 默认 Element Plus 朴素样式"不够高端"。经多轮 HTML 原型评审(A/B/C 标准商业 + D/E/F 高端 + G/H/I/J 深蓝 + K/L/M/N 全新流派 + 登录页两轮)daxing 拍板定稿。**后台主题=5 套可切换 + 明暗模式**:经典企业(默认,深蓝侧栏 #0a1f44+亮蓝 #2b6fff)/ 高级内敛(藏青 #0c1a30+金 #c9a24b)/ 科技蓝 / 政企蓝(#0a3a72 顶栏)/ Bento 仪表盘(暖白 #f4f2ee+墨黑+靛蓝 #4f6ef7)。**Bento 走方案丙**(daxing 初想全站乙、PM 析护城河翻倍代价后采纳丙):首页=Bento 数据卡片仪表盘 + 所有 CRUD 列表页内置「表格/卡片」视图切换(XTable 组件内部双渲染、非换布局骨架,主题切配色·视图切渲染两者正交)。**登录页=极光渐变 Aurora**(满屏 CSS 极光 + 居中玻璃卡片,背景可配置:默认 CSS 极光、部署者可换自有/CC0 图,不写死自家卖点保通用、不跟随主题切换)。**存储**:主题色/明暗/视图模式均 localStorage(零后端改动)。**★落地铁律(护城河红线)**:配色主题=纯 CSS 变量(design token,语义化 --bx-* + data-theme/data-mode 切换)不改任何单页 DOM → 生成器零回炉、防污染基线零重锚;「表格/卡片」视图=XTable 内部渲染模式开关(一份组件两种渲染、由 config 同时驱动)→ 生成器仅列表 stub 多渲卡片块、黄金样板/八标的基线不翻倍;XTable 升级后须重生成 role/menu 与黄金样板逐字一致、verify.sh 八标的 0 漂移,若会漂移须停下报告等 PM 裁定,不擅改生成器范式。**并修两 seeder 幂等 bug**(daxing 起项目实测暴露):① ConfigSeeder 非全新库重跑撞唯一键 Duplicate '0-site-site_name'(改幂等增量 find-or-create);② AuthSeeder 改 SUPER_ADMIN_INIT_PWD 重跑不更新密码致 401002(PM 裁定方案 A:超管已存在且提供非空 INIT_PWD 时打印明确提示不静默跳过,行为可预期)。任务书涵盖 token 体系/主题切换/骨架升级+细节精致化(过渡/骨架屏/缓动曲线)/Bento 丙/极光登录页/seeder 修复/护城河验收清单。**PM 备注**:占位页补全**已并入本次美化**(补占位本就要重生成各模块前端产物,正好用升级后 XTable+新主题一次成型避免返工;daxing 2026-06-14 拍板并入)。本任务完成 + daxing 浏览器复验观感满意(5 套主题+明暗+双视图+极光登录页+所有页无占位)后,即进入发布三步(截图 4 处/README 文案终审/dev→main+tag v0.1.0+公开+引流)。
> 后台视觉升级 落地（2026-06-14，web a4d207c / server 73186b2，GitHub+Gitee dev 双推三端一致 ls-remote 核验）：五阶段全交付。① Design Token——src/styles/theme.css 5 套(classic/elegant/tech/gov/bento)×明暗 语义 --bx-* + 联动 EP --el-*，useTheme(localStorage bx-theme/bx-mode)+ index.html 首屏防闪 + 顶栏 ThemePanel。② 骨架精致化——DefaultLayout 可收起侧栏/面包屑/头像下拉/卡片化/路由淡入 + MenuTree/MenuIcon，统一过渡/抽屉缓动/滚动条/圆角阴影跟随 token。③ Bento 方案丙——HomeView 仪表盘(真实客户端数据无假数，业务统计留 TODO)+ 列表「表格/卡片」双视图(XTable 一份 config 双渲染、卡片字段从 columns 推导、存 bx-list-view、树形强制表格)。④ 极光登录页——满屏纯 CSS 极光(零版权 §10)+ 玻璃卡片，背景层独立可换 CC0/后续读 bx_config、不跟主题、登录逻辑/token/401 未动、prefers-reduced-motion 关动画。⑤ 占位页补全(PM 口径：生成标准部分 + 手工槽)——16 菜单全真实页无 PlaceholderView 残留：post/dept 逐字 copy 基线产物、admin/dict/config/file + 只读模块(操作/登录/短信日志、支付订单退款 confirm=1 分↔元)按手工槽补全，新增 XDetailDialog、XTable 加可选 #toolbar 槽(均不进基线)。**★护城河 0 漂移**：配色纯 token/视图 XTable 内部/#toolbar 组件级，不改 stub 不改单页 DOM；verify.sh 八标的全程 0 漂移(仅 @date)复跑两次、role/menu 黄金样板未改。**seeder 修复**：ConfigSeeder 逐项 find-or-skip 幂等(连跑两次无撞 uk_tenant_group_key)、AuthSeeder 方案 A(超管已存在+非空 INIT_PWD 打印「未更新密码」提示不静默)。自测：逐阶段 npm run build(含 type-check) exit 0、server php -l 通过。**待 daxing**：浏览器复验观感(5 主题+明暗+双视图+极光页+16 页无占位，尤其 admin 角色岗位分配/dict 两级/file 上传预览/pay 退款冒烟)、全新库 seed:run 整链确认。**已知项**：① 生成器 dept treeSelect 未传 treeProps，默认 label='title' 而 dept 节点字段=name → 父级下拉标签空(Claude Code 未擅改 dept 页、停下报 PM，正确)，PM 裁定根治 → M3-F；② config value_type 自由文本(后端取值集未定，留约束)；③ pay status 按 model 常量 0-5 映射；④ admin 手工槽页已显式 treeProps:{label:'name'} 规避，dept.ts 覆盖为生成超集兼容 role 部门树。

> M3-F 落地（2026-06-14，server 0a2e50a / web 7a1f99a，GitHub+Gitee dev 双推三端 ls-remote 核验）：生成器微回炉，树形 treeSelect label 字段参数化，根治 dept 父级下拉空标签 quirk。**方案甲（零新增元数据键）**：勘查发现 FrontendGenerator 已有 treeLabelField() 推导链（front.treeLabel 显式 > 有 title 字段则 title > 否则 name），parentTreeData 虚拟根早已正确按它输出 name(dept)/title(menu)，唯一缺口是 formItemsBlock 父级 treeSelect 项未把它输出为 treeProps。修复 = 当 treeLabelField()≠'title' 时插入 `treeProps:{label:'<字段>'}`，='title'(menu) 不输出保逐字不变。改动精确两文件（FrontendGenerator +10/-2、dept 前端基线 +1 行）。**★防污染硬门**：重生成八标的 diff 精确等于预期——dept 前端仅 +`treeProps:{label:'name'}` 一行，dept 后端 + 其余七标的（post/role/menu/admin/dict/config/file）0 漂移；仅重锚 dept 前端基线一行，verify.sh 连跑两次 EXIT 0、二次重生成自洽。web 仅 copy dept 页 +1 行、build exit 0。dept 重为干净保真标的，生成器对任意「显示字段≠title」树形模块均正确。**已知项**：① content/category 同为树形,经核实其节点显示字段 = name(无 title 字段)、M4-A 时期 web 页确带同款空标签 bug,已定向补 treeProps:{label:'name'} 一行同步(web 21ab49f,无手工槽零冲突、verify.sh EXIT 0 自证未动基线)——至此 menu(title)/dept(name)/category(name) 三树形 web 页 label 全部正确,生成器对新树形模块自动正确；② dept/category 父级下拉选中显示名称留 daxing 浏览器复验。

> 路由切换白屏 hotfix（2026-06-14，web c437c81，GitHub+Gitee dev 双推三端一致）：后台视觉升级引入的客户端导航白屏回归根治。**根因**：阶段① 给 DefaultLayout 的 router-view 套了 `<transition mode="out-in">`，而所有 CRUD 页为多根 fragment（el-card 主体 + 编辑抽屉/弹窗并列），transition 只能 animate 单一根元素，遇多根 fragment 时 leave→enter 追踪失效 → 主区留白（刷新走首屏无 transition 循环故正常；单根 HomeView 不受影响）。Console 实证告警 `Component inside <Transition> renders non-element root node that cannot be animated`（daxing 浏览器坐实）。前两轮（229255e 加 Suspense+:key）未中——问题不在过渡参数/Suspense/路由注册，而在被包裹组件多根。**修复（方案甲，保留淡入淡出）**：transition 内套单根 `<div :key="route.fullPath" class="bx-page-wrap">`（width:100% block 无盒模型副作用），让 transition 动画该 div、内部多根组件随它整体进出；Suspense 保留作懒加载托底；:key 由 component 移到 div。改动精确仅 DefaultLayout.vue。卫生：verify.sh EXIT 0、八标的 0 漂移（不涉生成器/基线）、build exit 0。**daxing 浏览器复验通过（2026-06-14）**：/system/admin 等多根 CRUD 页连续点击即时渲染、无白屏、淡入淡出生效，Console `non-element root node` 告警已消失，单根 HomeView 正常。白屏回归彻底根治。**发布润色候选（非阻断）**：① AuthImg 收到空 src 时报 Invalid prop 告警（无头像账号边界，建议加空值兜底）；② ApiTesterSeeder 占位头像用了不存在的 a.com/*.png 致 Console 报 ERR_CONNECTION_CLOSED（建议换体面占位或留空）。**⚠️ 过程偏差（已完全回滚，记录立规）**：修复中一个 Bash 块 cd 到 server 仓后未切回 web，致 `git add -A && commit && push` 误将 PM 维护的未提交 docs/ARCHITECTURE.md 提交为 server cee726a 并双推；已 `reset --soft HEAD~1` + 取消暂存还原（WIP 内容零丢失）+ `push --force` 强推 server 两端回 0a2e50a，三端核验一致、工作区仅余 ARCHITECTURE.md 未提交态。无数据损失（本项目无并发协作者，强推目标干净）。**作业纪律（此后任务书固定带上）**：① Claude Code 绝不 commit docs/ARCHITECTURE.md（PM 维护铁律，本次撞线）；② 禁用 `git add -A`，改 `git add <显式文件清单>`；③ 跨仓操作后 git 前必须 `pwd` 显式确认当前仓；④ `push --force` 视为核选项，仅在确认无协作者依赖且 PM 知情时使用。
> 发布前冷启动验收通过（2026-06-15，daxing 本机）：server 全新库整链确认——docker compose down -v 清卷 → up -d 空库初始化 → migrate:run（29 迁移全绿）→ seed:run（19 Seeder 全绿，ConfigSeeder/PayConfigSeeder/SmsConfigSeeder/WechatConfigSeeder 幂等 find-or-skip 在全新库正常）→ php think run -p 8801 → 浏览器 admin 登录成功（Bento 首页 1 角色/58 权限点/17 可见菜单，系统信息卡显 v0.1.0）。web/uniapp build smoke 由 Claude Code 跑过 exit 0/DONE。**发布闸全过,可触发 dev→main + tag v0.1.0**。
> v0.1.0 发布后待办（2026-06-15 daxing 提出,均不阻断首发,留作发布后第一批迭代）：① 后台左上角标题文字「BenXinAdmin 管理后台」→「本心管理后台」——仅改后台界面左上角这一处显示文案,其余一切不动（登录页/系统信息卡/README/代码 BenXinAdmin·benxin·bx_ 标识全部保留,守 §13.1）；② 后台禁搜索引擎收录——index.html 加 `<meta name="robots" content="noindex,nofollow">` + 生产 Nginx X-Robots-Tag/robots.txt（属生产加固,可提前顺手做）；③ 媒体管理模块（图片/视频/音频:分类树 + 上传 + 删除 + 批量删 + 查询 + 记录）——基于 M2-D bx_file + StorageInterface + M4-A XUpload/AuthImg,走 bx:make 吃狗粮 + 手工槽（媒体预览/批量删）,需设计评审定:复用 bx_file 加 media_type/category_id vs 新建 bx_media+bx_media_category 树、批量删事务 + 物理 GC、视频音频建议走 OSS;为一个完整小迭代量；④ 非 Docker 安装方式写进 README——本地裸装 MySQL8+Valkey/Redis + .env 指向本地 + composer/migrate/seed/run,降低上手门槛利引流（生产本就裸机+宝塔+SafeLine,§12 已有实践,补文档即可）。优先级建议:②④①（快速）→ ③（中型,独立迭代）。
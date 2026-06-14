<div align="center">

# BenXinAdmin · 本心通用管理后台底座

**一套可运行的通用管理后台开源底座** —— 用户/权限/系统管理 + **可复刻的代码生成器**，供各类项目（打卡、商城、知识付费等）在其上叠加业务，免重复造公共轮子。

[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4.svg)
![ThinkPHP](https://img.shields.io/badge/ThinkPHP-8.1-1B5E20.svg)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1.svg)
![Vue](https://img.shields.io/badge/Vue-3-42b883.svg)
![uni-app](https://img.shields.io/badge/uni--app-MP%2BH5-2B9939.svg)

后端仓（本仓） · [后台前端 web](https://gitee.com/binxin-admin/binxin-admin-web) · [C 端 uniapp](https://gitee.com/binxin-admin/binxin-admin-uniapp)

</div>

<!-- 截图占位：登录页 / 菜单管理 / 代码生成器运行 / C 端小程序 -->

---

## ✨ 核心特性

### 🏗️ 代码生成器护城河
一条 `php think bx:make` 从**表结构**直出**全栈产物**：后端四件套（Model / Controller / Service / Validate）+ 路由 + 菜单权限 seeder + 前端列表页 / 编辑表单 / 分配弹窗 / API 薄壳。覆盖**四种范式**——纯 CRUD、树形（递归 CTE 或内存建树）、授权链路（角色分配菜单 + Casbin 同步 + 事务回滚）、数据权限。**「生成 == 手写黄金样板」逐字保真**，回归基线随仓可复现：`bash tests/generator-baseline/verify.sh`。

### 🍖 吃狗粮闭环
生成器不是玩具——真实业务（内容、系统公告等）就是 `bx:make` 生成的。「先手写黄金样板 → 暴露缺口回炉生成器 → 业务模块零手工自动复刻」闭环成立：例如富文本净化能力，手写内容模块沉淀 → 回炉进生成器 → 系统公告模块自动复用，零手工接线。

### 🔐 安全基线全程贯穿
- **JWT 双 guard 物理隔离**：后台 / C 端各自独立密钥 + `aud` 约束 + Valkey 独立命名空间（`bx:bxjwt:admin:*` / `bx:bxjwt:api:*`），跨端令牌互不可验。
- **Casbin RBAC**：`domain` 维度预留多租户；权限到接口/按钮。
- **数据权限五档**：全部 / 本部门 / 本部门及以下（递归 CTE 实时查子树）/ 仅本人 / 自定义，多角色取最宽。
- **三方密钥 AES 入库不进仓库**；操作日志脱敏红线；上传真实 MIME + 扩展名双重校验；支付回调四件套（验签 + 幂等 + 金额二次校验 + 状态机）。

### 📱 全栈三端
ThinkPHP 8 后端 + Vue 3 / Element Plus 后台 + uni-app C 端（**一套代码出微信小程序 + H5**）。C 端懒登录：用到核心业务前不强制登录，登录即注册，微信 + 手机号缺一不可。

### 🧩 通用业务底座
内容 CMS（分类树 / 内容 / 广告位 + 富文本净化）+ 微信能力（access_token 中心化缓存 / code2session / 网页 oauth）+ 支付框架（yansongda/pay，订单状态机 / 退款 / 事件解耦）+ 消息（短信渠道 / 验证码 / 系统公告）。**具体业务作闭源上层叠加，不进本仓**（守开源边界）。

---

## 🛠 技术栈

| 层 | 选型 | 说明 |
|---|---|---|
| 后端 | PHP **8.4** + ThinkPHP **8.1**（多应用） | Apache-2.0 |
| 数据库 | MySQL **8**（InnoDB / utf8mb4） | — |
| 缓存 | **Valkey**（Redis 协议兼容，BSD-3） | 替 Redis，规避 AGPL 争议 |
| 鉴权 | **lcobucci/jwt**（BSD-3）双令牌 + **php-casbin**（Apache-2.0） | 自建 BxJwt 服务层承双 guard / 白黑名单 |
| 支付 | **yansongda/pay** v3.7.20（MIT） | 锁定版修回调验签 CVE |
| 富文本 | ezyang/htmlpurifier（LGPL，作依赖不传染） | 后端白名单净化防 XSS |
| 迁移 / 限流 | think-migration + Seeder / think-throttle | 禁裸 SQL |
| 后台前端 | Vue 3.5 + Vite + Element Plus + UnoCSS + Pinia | MIT |
| C 端 | uni-app + Vue3 + TS + wot-design-uni（MIT） | 微信小程序 + H5 |

> 依赖协议全部可商用、无 AGPL/GPL 传染。

---

## 🗂 架构概览

三个**独立仓库**，均 Gitee + GitHub 双开双推：后端（本仓，含代码生成器）/ 后台前端 `benxin-admin-web` / C 端 `benxin-admin-uniapp`。

```
app/
├── common/            # 公共层：基类 / 中间件 / 异常 / 库
│   ├── base/          # BxController / BxModel / BxService / BxValidate（黄金样板四件套基类）
│   ├── middleware/    # RequestLog / Cors / JwtAuth / CasbinAuth / OperLog
│   ├── library/       # BxJwt / Result / ErrorCode / ConfigCrypt / BxCache / 微信·支付·短信
│   └── service/       # CasbinService / BxPay / SmsCodeService / UserAuthService
├── admin/             # 后台应用，对外前缀 /admin
└── api/               # C 端应用，对外前缀 /api
config/  ·  database/{migrations,seeds}  ·  public/
extend/generator/      # 代码生成器（stub + 元数据 → 全栈产物）
tests/generator-baseline/  # 生成器保真回归基线 + verify.sh
docs/ARCHITECTURE.md   # 架构基线与全部约定（权威文档）
docker-compose.yml     # 仅 MySQL + Valkey
```

统一返回（业务码风格 A）：`{ "code":0, "msg":"success", "data":null, "request_id":"uuid", "timestamp":… }`，`code=0` 成功；HTTP 默认 200，结果以 `code` 为准（鉴权类允许 401）。错误码段见 [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) §6.2。

---

## 🚀 快速开始

```bash
# 1. 安装依赖（生产 PHP 8.4）
composer install

# 2. 环境变量
cp .env.example .env
#   必填：SUPER_ADMIN_INIT_PWD（超管初始密码）
#         JWT_ADMIN_SECRET / JWT_API_SECRET（openssl rand -hex 32）
#         CONFIG_CRYPT_KEY（三方密钥 AES 入库密钥，openssl rand -hex 32）

# 3. 启动本地依赖（仅 MySQL:3308 + Valkey:6380，建议 Mac OrbStack）
docker compose up -d

# 4. 建表 + 业务种子（超管 / 菜单 / 字典 / Casbin / 各模块 perms）
php think migrate:run
php think seed:run

# 5. 起服务（独占 8801）
php think run -p 8801
curl http://127.0.0.1:8801/admin/v1/ping     # -> {"code":0,"msg":"pong",...}
```

> ### 🔴 安全必读
> 1. **`SUPER_ADMIN_INIT_PWD` 不设则不创建超管账号**——本底座**无任何默认弱口令**，部署者必须显式设置强密码（超管 `admin` 密码即此值，Argon2id 入库；首登后请立即改密）。
> 2. **生产务必 `APP_DEBUG=false`**（`.env.example` 已默认 false）——调试探针与测试种子均靠它守门，生产不暴露。
> 3. 三方密钥（支付 / 短信 / OSS）**AES 加密入库、不进仓库**；`.env` 已在 `.gitignore`，**严禁提交真实密钥**。

后台账号：用户名 `admin`，密码 = `.env` 的 `SUPER_ADMIN_INIT_PWD`。后台前端 / C 端起步见各自仓 README，`VITE_API_BASE(_URL)` 默认指向 `http://127.0.0.1:8801`。

<details>
<summary>本地环境提示（端口隔离 / PHP 版本妥协 / 数据卷）</summary>

- 依赖容器默认映射宿主机 **MySQL 3308 / Valkey 6380**（避开本机原生 3306 / 6379），容器内仍 3306/6379；无冲突可在 `.env` 改回。
- 本地若为 PHP 8.1~8.3，临时 `composer install --ignore-platform-req=php`（仅本地妥协，生产无此项）。
- 首次起容器后再改 `DB_PORT`/账号需 `docker compose down -v` 清卷重起，让 MySQL 按新 `.env` 重新初始化。

</details>

---

## ⚙️ 代码生成器

```bash
# 1) 建好目标表（migration）  2) 可选配模块元数据 extend/generator/configs/<m>.php
# 3) 生成（--output 指向独立目录便于 diff 验收；缺省落真实 app 路径，前端产物落 output/ 人工 copy）
php think bx:make bx_post --config=extend/generator/configs/post.php --output=runtime/generated/post --dry-run
php think bx:make bx_post --config=extend/generator/configs/post.php            # 落地（默认不覆盖，加 --force 覆盖）

# 保真回归（防污染硬门）：重生成八标的与基线逐字比对（仅 @date 差）
bash tests/generator-baseline/verify.sh        # 全 ✓ 即「生成 == 黄金样板」
```

产物 = 后端四件套 + 路由片段 + 菜单 perms seeder + 前端列表/表单/分配弹窗/api 薄壳。八个黄金样板标的（post/role/menu/dept/admin/dict/config/file）的保真基线在 [`tests/generator-baseline/`](tests/generator-baseline/)，clone 即可复现回归。

---

## 🧱 开源边界（请勿误解）

- **开源仓只含**：用户 / 权限 / 系统管理 + 基础代码生成器 + 通用业务**框架**（内容 / 支付 / 消息 / 微信配置）。
- **具体业务**（知识付费、商城等）作**独立闭源上层项目**叠加，**不在本仓**。
- 支付等三方密钥加密存储、不进仓库。

---

## 🗺 路线图

| 阶段 | 内容 | 状态 |
|---|---|---|
| M0 | 三端脚手架（后端 / 后台前端 / C 端） | ✅ |
| M1 | 认证基建 + Casbin RBAC + 管理员/角色/菜单/部门/岗位 + 数据权限 | ✅ |
| M2 | 系统管理（字典 / 参数 / 操作·登录日志 / 文件） | ✅ |
| M3 | 代码生成器（四范式保真 + 前端产物） | ✅ |
| M4 | 通用业务（内容 / 微信 / 支付 / 消息） | ✅ |
| M5 | C 端懒登录（认证 / 双端登录闭环 / 首页·我的） | ✅ |
| M6 | （可选）官网 + 首页拖拽搭建；高级生成器 / 企业模板（闭源） | ⚪ |

---

## 📄 协议与署名

[Apache-2.0](LICENSE)（带专利授权条款）。作者 仗键天涯(daxing)。架构基线与全部约定见 [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)。

> 仓库地址（代码内一律写 `BenXinAdmin`，仅 git 地址按实际）：
> Gitee（主）`https://gitee.com/binxin-admin/binxin-admin-server` · GitHub（镜像）`https://github.com/BenXinAdmin-PHP/benxin-admin-server`

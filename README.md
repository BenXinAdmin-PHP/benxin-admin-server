# BenXinAdmin · 后端（benxin-admin-server）

> 本心通用管理后台底座 —— 可运行的通用管理后台开源底座，供后续各类项目（打卡、商城、知识付费等）在其上叠加业务。
>
> 架构基线与全部约定见 [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)。开源协议：**Apache-2.0**。

## 技术栈（锁定）

| 组件 | 选型 |
|---|---|
| PHP | 8.4 |
| 框架 | ThinkPHP 8.1（多应用模式） |
| 数据库 | MySQL 8（InnoDB / utf8mb4） |
| 缓存 | Valkey（Redis 协议兼容，BSD-3） |
| 鉴权 | JWT 双令牌 + php-casbin（M1） |
| 迁移 | think-migration + Seeder |
| 限流 | think-throttle |

## 目录结构

```
app/
├── common/        # 公共层
│   ├── base/      # BxController / BxModel / BxService / BxValidate
│   ├── middleware/# RequestLog / Cors / JwtAuth / CasbinAuth / OperLog
│   ├── exception/ # 统一异常处理 Handle
│   └── library/   # Result(统一响应) / ErrorCode / Uuid
├── admin/         # 后台应用，对外前缀 /admin
└── api/           # C端应用，对外前缀 /api
config/            # 配置
database/
├── migrations/    # think-migration
└── seeds/         # 初始数据
public/            # 入口
docs/ARCHITECTURE.md
docker-compose.yml # 仅 MySQL + Valkey（本地依赖）
```

## 统一返回结构

```json
{ "code": 0, "msg": "success", "data": null, "request_id": "uuid", "timestamp": 1717000000 }
```

`code = 0` 表示成功，HTTP 默认 200，结果以 `code` 为准；鉴权类允许返回 HTTP 401。错误码段见架构文档 §6.2。

## 快速开始

```bash
# 1. 安装依赖（生产 PHP 8.4 直接 composer install）
composer install

# 2. 准备环境变量
cp .env.example .env   # 按本地实际填写，勿提交真实密钥
# 关键（M1 认证）：在 .env 配好 JWT 双密钥与超管初始密码（勿提交真实值）
#   JWT_ADMIN_SECRET / JWT_API_SECRET ← openssl rand -hex 32
#   SUPER_ADMIN_INIT_PWD             ← 自定义强密码，AuthSeeder 据此创建超管 admin

# 3. 启动本地依赖（MySQL + Valkey，建议 Mac OrbStack）
docker compose up -d

# 4. 建表 + 初始化数据
php think migrate:run
php think seed:run                 # 首次全量；AuthSeeder 幂等，可单独重跑：php think seed:run -s AuthSeeder

# 5. 本地启动（PHP-FPM 或内置服务器，独占 8801 避开本机其他项目占用的 8000）
php think run -p 8801
```

> 超管账号：用户名 `admin`，密码即 `.env` 的 `SUPER_ADMIN_INIT_PWD`（Argon2id 入库，种子不硬编码明文）。**首次登录后请立即在后台修改密码。**

前端联调：web 的 `VITE_API_BASE`、uniapp 的 `VITE_API_BASE_URL` 默认指向 `http://127.0.0.1:8801`。

自测：

```bash
curl http://127.0.0.1:8801/admin/v1/ping   # -> {"code":0,"msg":"pong",...}
curl http://127.0.0.1:8801/api/v1/ping      # -> {"code":0,"msg":"pong",...}

# 后台认证闭环（M1-A）：登录拿令牌 → 访问受保护接口 → 刷新 → 登出
curl -X POST http://127.0.0.1:8801/admin/v1/login -d 'username=admin&password=<SUPER_ADMIN_INIT_PWD>'
#   -> data: { access_token, refresh_token, token_type:"Bearer", expires_in, refresh_expires_in }
curl http://127.0.0.1:8801/admin/v1/profile -H 'Authorization: Bearer <access_token>'
curl -X POST http://127.0.0.1:8801/admin/v1/refresh -d 'refresh_token=<refresh_token>'
curl -X POST http://127.0.0.1:8801/admin/v1/logout  -H 'Authorization: Bearer <access_token>'
```

> 说明：本仓库锁定 PHP 8.4；若本地为 8.1~8.3，临时用 `composer install --ignore-platform-req=php` 安装（仅本地妥协，生产无此项）。
>
> 端口隔离：依赖容器默认映射到宿主机 **MySQL 3308 / Valkey 6380**（避开本机原生 MySQL 3306 / Redis 6379），容器内部仍是 3306/6379。如本机无端口冲突，可在 `.env` 自行改回 3306/6379。
>
> 首次起容器后若再改过 `DB_PORT`/账号等，需 `docker compose down -v` 清掉数据卷再 `docker compose up -d`，让 MySQL 按新 `.env` 重新初始化账号库（否则旧卷里的账号不会更新）。

## 开发约定

- 命名 / 注释头 / 安全基线严格遵循 [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) 与 `CLAUDE.md`。
- 所有响应经 `app\common\library\Result`，控制器继承 `BxController`，模型继承 `BxModel`。
- 表前缀 `bx_`，统一时间字段 `created_at/updated_at/deleted_at`，业务表预留 `tenant_id`。

## 仓库

双仓库同步（代码内一律写 `BenXinAdmin`，仅 git 地址按实际）：

- Gitee（主）：https://gitee.com/binxin-admin/binxin-admin-server
- GitHub（镜像）：https://github.com/BenXinAdmin-PHP/benxin-admin-server

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

# 3. 启动本地依赖（MySQL + Valkey，建议 Mac OrbStack）
docker compose up -d

# 4. 建表 + 初始化数据
php think migrate:run
php think seed:run

# 5. 本地启动（PHP-FPM 或内置服务器）
php think run -p 8800
```

自测：

```bash
curl http://127.0.0.1:8800/admin/v1/ping   # -> {"code":0,"msg":"pong",...}
curl http://127.0.0.1:8800/api/v1/ping      # -> {"code":0,"msg":"pong",...}
```

> 说明：本仓库锁定 PHP 8.4；若本地为 8.1~8.3，临时用 `composer install --ignore-platform-req=php` 安装（仅本地妥协，生产无此项）。
>
> 端口隔离：依赖容器默认映射到宿主机 **MySQL 3307 / Valkey 6380**（避开本机原生 MySQL 3306 / Redis 6379），容器内部仍是 3306/6379。如本机无端口冲突，可在 `.env` 自行改回 3306/6379。

## 开发约定

- 命名 / 注释头 / 安全基线严格遵循 [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) 与 `CLAUDE.md`。
- 所有响应经 `app\common\library\Result`，控制器继承 `BxController`，模型继承 `BxModel`。
- 表前缀 `bx_`，统一时间字段 `created_at/updated_at/deleted_at`，业务表预留 `tenant_id`。

## 仓库

双仓库同步（代码内一律写 `BenXinAdmin`，仅 git 地址按实际）：

- Gitee（主）：https://gitee.com/binxin-admin/binxin-admin-server
- GitHub（镜像）：https://github.com/BenXinAdmin-PHP/benxin-admin-server

# CLAUDE.md · benxin-admin-server（后端）

> 本文件是 Claude Code 在本仓库的执行铁律。完整背景见 `docs/ARCHITECTURE.md`（架构基线与约定），冲突时以基线文档为准；本文件冲突处先停下来问 daxing。

## 你的职责边界
- 你负责：编码、API 自测、Git 提交与双推、回填可复制 Markdown 完成报告。
- 你不负责：架构决策（由 PM/架构师对话给出任务书）。任务书没覆盖到的设计点，先问，不要自行拍板。
- 全程中文沟通与注释。

## 项目命名（唯一权威拼写）
- 代码、文档、注释、包名、README 一律用 **`BenXinAdmin`**。
- ⚠️ **仓库名是例外**：Gitee 用 `binxin`、GitHub 用 `benxin`，两边仓库名不一致。**只有 `git remote` 地址按实际值，其余任何地方都写 `BenXinAdmin`。**

## 技术栈（锁定）
- PHP 8.4 + ThinkPHP 8.1.4（多应用模式）；MySQL 8；Valkey（Redis 协议兼容）。
- 应用划分：`app/admin`（前缀 `/admin`）、`app/api`（前缀 `/api`）、`app/common`（公共）；版本路由分组 `v1`。
- 迁移用 think-migration + Seeder，**禁止散落裸 SQL**；初始数据走 Seeder。
- 限流用 think-throttle。

## 命名约定
- 表前缀 `bx_`，表名 snake_case 单数（`bx_admin`/`bx_role`/`bx_menu`/`bx_config`/`bx_oper_log` …，Casbin 表 `bx_casbin_rule`）。
- 统一时间字段 `created_at`/`updated_at`/`deleted_at`（datetime，软删除走 `deleted_at`）。
- 业务表统一预留 `tenant_id`（unsigned bigint 默认 0；单租户恒为 0）。
- 类：`XxxController`/`Xxx`(Model)/`XxxService`/`XxxValidate`；基类前缀 `Bx`（`BxController`/`BxModel`/`BxService`/`BxValidate`）。

## 文件注释头（每个新建/修改的 PHP 文件强制）
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

## API 规范（业务码风格 A）
- 统一返回：`{ "code":0, "msg":"success", "data":null, "request_id":"uuid", "timestamp":1717000000 }`，`code=0` 成功。
- HTTP 默认 200，结果以 `code` 为准；鉴权类（未登录/过期）允许返回 HTTP 401 便于前端拦截。
- 错误码段：400xxx 参数 / 401xxx 认证 / 403xxx 权限 / 404xxx 不存在 / 422xxx 校验 / 429xxx 限流 / 500xxx 服务端；业务段各模块分配。
- 分页：`data:{ list:[], total, page, page_size }`。
- 路由顺序：具体 action > `/:id` > 集合；更新用 `$request->has()` 判断字段是否参与。

## 认证与权限
- 后台、C 端各自独立 guard + 独立 JWT 密钥；access 短期 + refresh 长期；refresh 白名单 / 登出黑名单（jti）存 Valkey。
- Casbin RBAC，model 预留 domain（=tenant_id）维度，单租户用统一 domain。
- C 端懒登录；登录即注册，微信 + 手机号缺一不可。

## 安全红线（每个模块必须满足，作为自测项）
- 全程 ORM/查询构造器参数化，**禁止拼接 SQL**。
- 字段白名单防批量赋值；输出转义防 XSS。
- 上传：真实 MIME + 扩展名白名单 + 重命名 + 存非 Web 可执行目录。
- 敏感接口（登录/短信等）单独限流；密码用 `password_hash`（Argon2id/bcrypt）。
- 三方密钥（支付/短信/OSS）**AES 加密入库**，密钥取自 `.env` 的 `CONFIG_CRYPT_KEY`，展示脱敏。
- **任何密钥/密码不得硬编码、不得入库到 git**；`.env` 必须在 `.gitignore`。
- 生产 `APP_DEBUG=false`，统一异常不泄露堆栈。

## 资源合规
- 只用开源可商用字体/图标/图片：字体优先系统栈或 HarmonyOS Sans/MiSans/思源黑体(OFL)、Inter；图标 Element Plus Icons/Remix/Tabler/Lucide。禁用方正/汉仪等商用字库与来源不明素材。

## Git 双推（Gitee 主、GitHub 镜像）
首次初始化（在仓库根目录执行一次）：
```bash
git init
git branch -M main
git remote add origin https://gitee.com/binxin-admin/binxin-admin-server.git
git remote set-url --add --push origin https://gitee.com/binxin-admin/binxin-admin-server.git
git remote set-url --add --push origin https://github.com/BenXinAdmin-PHP/benxin-admin-server.git
git checkout -b dev   # 日常开发在 dev
```
之后每次提交 + 双推（一条 push 同时推 Gitee 和 GitHub）：
```bash
git add -A
git commit -m "feat: xxx"   # 遵循 Conventional Commits：feat/fix/docs/refactor/chore/test/build
git push origin dev         # 会同时推送到 Gitee 与 GitHub
```
- 验证双推：`git remote -v` 应看到 origin 有两个 push 地址。
- fetch 默认从 Gitee。稳定后再 `main` 合并并 `git push origin main`。

## 完成任务后
回填任务书里的「完成报告」模板（可复制 Markdown），如实记录实际版本号、验收逐条结果、偏差与待 daxing 确认项。

# BenXinAdmin v0.1.0 🎉

**一套可运行的通用管理后台开源底座** —— 用户/权限/系统管理 + 可复刻的代码生成器，供各类项目（打卡、商城、知识付费等）在其上叠加业务，免重复造公共轮子。Apache-2.0。

> 🤖 全程 Vibe Coding 实践：AI 规划 + 编码、人类拍板验收。非无审查吐码 —— 规范化基线 + 生成器 + 保真回归 + 安全基线自证。

## ✨ 核心特性

### 🏗️ 代码生成器（护城河）
一条 `php think bx:make` 从表结构 + 元数据产出全栈产物：后端 Model/Controller/Service/Validate + 路由 + 菜单权限 Seeder + 前端列表页/编辑表单/分配弹窗 + API 薄壳。覆盖纯 CRUD、树形、授权链路三类范式。

### 🍖 吃狗粮闭环 + 保真回归
生成产物与手写黄金样板逐字一致（仅 @date 差）。仓内 `tests/generator-baseline/verify.sh` 一键自证「生成 == 黄金样板」八标的零漂移 —— clone 即可复现。

### 🔐 安全基线全程贯穿
JWT 双 guard 隔离（admin/api 独立密钥）· Casbin RBAC + 五档数据权限 · 三方密钥 AES 入库（git 历史 0 泄露）· ORM 参数化 + 字段白名单 · XSS 后端净化 + 前端转义 · 上传双重校验 · 日志脱敏 · 支付回调四件套。

### 🎨 后台视觉
5 套可切换主题（经典企业/高级内敛/科技蓝/政企蓝/Bento）+ 明暗模式 + 极光登录页 + 列表「表格/卡片」双视图。纯系统字体栈 + 开源图标，全可商用。

### 📱 全栈三端
后台前端（Vue 3.5 + Element Plus）· C 端（uni-app 微信小程序 + H5 懒登录）· 后端（ThinkPHP 8）。

### 🧩 通用业务底座
内容（CMS）· 微信能力（token/code2session/oauth 自建）· 支付框架（回调状态机 + event 解耦）· 消息（短信渠道 + 验证码 + 公告）。

## 🛠️ 技术栈
- **后端**：PHP 8.4 · ThinkPHP 8.1 · MySQL 8 · Valkey · php-casbin · lcobucci/jwt
- **后台前端**：Vue 3.5 · Vite · Element Plus · TypeScript · UnoCSS · Pinia
- **C 端**：uni-app · Vue 3 · wot-design-uni

## 📦 三仓
- 后端（本仓）：benxin-admin-server
- 后台前端：benxin-admin-web
- C 端：benxin-admin-uniapp

## 🚀 快速开始
见仓库 README「快速开始」与「首次登录后台」。

> ⚠️ **安全提醒**：首次部署必须自行设置 `SUPER_ADMIN_INIT_PWD`（不设则不创建超管账号，杜绝默认弱口令）；生产请关闭 `APP_DEBUG`、配置 `.env` 真实密钥（AES 入库）。

## 🗺️ 里程碑
M0 三端脚手架 → M1 认证 + RBAC → M2 系统管理 → M3 代码生成器 → M4 通用业务 → M5 C 端懒登录 → 视觉升级 + 发布。

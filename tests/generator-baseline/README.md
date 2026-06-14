# 代码生成器保真回归基线（防污染硬门）

> 这是 BenXinAdmin **代码生成器护城河的自证资产**——证明「`bx:make` 生成的代码 == 手写的黄金样板」。

## 这是什么

`tests/generator-baseline/<标的>/` 存放八个黄金样板模块经 `php think bx:make` 生成的**重锚基线产物**（后端四件套 + 路由/种子片段 + 前端列表/表单/分配弹窗/api 薄壳）。任何对生成器（`extend/generator/`：模板 stub / 计算块 / 元数据）的改动，都必须保证重生成结果与本基线**逐字一致**（仅文件头 `@date`/`@updated` 时间戳允许不同），否则视为「污染了既有样板」。

## 八标的与覆盖范式

| 标的 | 范式 | 生成方式 |
|---|---|---|
| `post` | 纯 CRUD + 绑定护栏 | `--config=configs/post.php` |
| `dept` | 树形（递归 CTE 子树） | `--config=configs/dept.php` |
| `menu` | 树形（内存建树）+ 级联清理 + 可空唯一 | `--config=configs/menu.php` |
| `role` | 授权链路（分配菜单 + Casbin 同步 + 事务回滚） | `--config=configs/role.php` |
| `admin` / `dict` / `config` / `file` | 系统表，纯表结构反读推导 | 无 config |

## 如何跑回归

```bash
bash tests/generator-baseline/verify.sh
```

脚本会用仓内生成器重生成八标的到临时目录（`runtime/generated/_verify`，被 .gitignore 忽略），与本基线逐字 diff（忽略 `@date`/`@updated`）。全部一致退出 0、打印 `✅`；任一漂移退出 1、打印差异。

> 仅依赖仓内资源（生成器源码 + stub + configs + 本基线），clone 全仓即可复现，无需任何运行期产物。

## 注意

- 本目录是**只读基线**，请勿手工编辑。生成器范式确有变更时，按既定流程「重锚」后再整体更新本目录（并复跑 `verify.sh` 自洽）。
- `runtime/generated/` 是运行期/临时产物目录（.gitignore 忽略），**不是**基线；基线只在本 tracked 目录。

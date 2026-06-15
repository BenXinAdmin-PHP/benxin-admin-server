#!/usr/bin/env bash
# +----------------------------------------------------------------------
# | @project   BenXinAdmin
# | @mission   代码生成器保真回归 — 重生成八标的与基线逐字 diff（防污染硬门）
# | @author    仗键天涯(daxing)
# | @email     3442535897@qq.com
# | @date      2026-06-14
# +----------------------------------------------------------------------
#
# 用法（仓库根目录或任意位置均可）：bash tests/generator-baseline/verify.sh
# 作用：用仓内生成器（extend/generator）+ 配置重生成八个黄金样板标的，
#       与 tests/generator-baseline/<标的> 逐字对比（忽略 @date/@updated 时间戳行）。
# 退出码：0 全部逐字一致（保真）；1 出现漂移（打印差异）。
# 兼容：纯 POSIX/bash 3.2（不用关联数组，macOS 自带 bash 亦可跑）。
#
# 八标的覆盖四范式：纯 CRUD(post) / 树形 cte(dept) / 树形 memory+级联(menu) /
# 授权链路(role) + 系统表纯推导(admin/dict/config/file，无 config，靠表结构反读)。

cd "$(dirname "$0")/../.." || exit 2   # → 仓库根目录

BASE="tests/generator-baseline"
TMP="runtime/generated/_verify"        # 临时重生成目录（runtime/ 被 .gitignore 忽略）
rm -rf "$TMP"

fail=0
for m in post role menu dept admin dict config file; do
  # 有 config 的标的传 --config，系统表纯表结构推导（无 config）
  case "$m" in
    post|role|menu|dept) opt="--config=extend/generator/configs/$m.php" ;;
    *)                   opt="" ;;
  esac

  php think bx:make "bx_$m" $opt --output="$TMP/$m" --force >/dev/null 2>&1

  if diff -rq -I '@date' -I '@updated' "$BASE/$m" "$TMP/$m" >/dev/null 2>&1; then
    echo "  ✓ $m"
  else
    echo "  ✗ $m 漂移："
    diff -r -I '@date' -I '@updated' "$BASE/$m" "$TMP/$m" 2>&1 | head -40
    fail=1
  fi
done

rm -rf "$TMP"
echo "----------------------------------------"
if [ "$fail" -eq 0 ]; then
  echo "保真回归通过 ✅（生成 == 黄金样板，仅 @date 差）"
else
  echo "保真回归失败 ❌（生成器改动污染了样板，请核对上方差异）"
fi
exit "$fail"

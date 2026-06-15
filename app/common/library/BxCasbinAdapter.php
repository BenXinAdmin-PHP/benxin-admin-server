<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   Casbin 适配器 — 读写 bx_casbin_rule
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-09 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\library;

use Casbin\Model\Model;
use Casbin\Persist\Adapter;
use Casbin\Persist\AdapterHelper;
use think\facade\Db;

/**
 * 自建 Casbin 持久化适配器（ADR-8 哲学：薄、可控、可被 M3 生成器复刻）。
 * 直读写标准表 bx_casbin_rule（ptype + v0..v5），全程 ORM 参数化，无拼接 SQL。
 *
 * 字段映射（与 M1-B 任务书 §2 固化一致）：
 *   p 策略：v0=sub(角色code) v1=dom(tenant_id) v2=obj(perms串) v3=act(动作，超管为*)
 *   g 关系：v0=子 v1=父 v2=dom（本步不灌）
 */
class BxCasbinAdapter implements Adapter
{
    use AdapterHelper;

    /** 规则表名（不含前缀，Db::name 自动加 bx_） */
    protected string $table = 'casbin_rule';

    /** v0..v5 列名 */
    protected array $columns = ['v0', 'v1', 'v2', 'v3', 'v4', 'v5'];

    /**
     * 从 bx_casbin_rule 装载全部规则进 model。
     */
    public function loadPolicy($model): void
    {
        $rows = Db::name($this->table)->select();
        foreach ($rows as $row) {
            $this->loadPolicyLine($this->rowToLine($row), $model);
        }
    }

    /**
     * 全量落盘：清空后按 model 内 p / g 策略重写。
     */
    public function savePolicy($model): void
    {
        Db::name($this->table)->whereRaw('1=1')->delete();

        foreach (['p', 'g'] as $sec) {
            if (!isset($model->model[$sec])) {
                continue;
            }
            foreach ($model->model[$sec] as $ptype => $ast) {
                foreach ($ast->policy as $rule) {
                    Db::name($this->table)->insert($this->ruleToRow($ptype, $rule));
                }
            }
        }
    }

    /**
     * 新增一条规则。
     *
     * @param string             $sec   节（p / g）
     * @param string             $ptype 策略类型
     * @param array<int,string>  $rule  规则值（按 v0..v5 顺序）
     */
    public function addPolicy($sec, $ptype, $rule): void
    {
        Db::name($this->table)->insert($this->ruleToRow($ptype, $rule));
    }

    /**
     * 删除一条规则（按 ptype + 各 v 列精确匹配）。
     *
     * @param array<int,string> $rule
     */
    public function removePolicy($sec, $ptype, $rule): void
    {
        $query = Db::name($this->table)->where('ptype', $ptype);
        foreach (array_values($rule) as $i => $value) {
            if (isset($this->columns[$i])) {
                $query->where($this->columns[$i], $value);
            }
        }
        $query->delete();
    }

    /**
     * 按字段偏移过滤删除（fieldIndex 起，逐个匹配非空值）。
     *
     * @param int    $fieldIndex
     * @param string ...$fieldValues
     */
    public function removeFilteredPolicy($sec, $ptype, $fieldIndex, ...$fieldValues): void
    {
        $query = Db::name($this->table)->where('ptype', $ptype);
        foreach ($fieldValues as $offset => $value) {
            $col = $this->columns[$fieldIndex + $offset] ?? null;
            if ($col !== null && $value !== '') {
                $query->where($col, $value);
            }
        }
        $query->delete();
    }

    /**
     * 数据行 → Casbin 策略行文本（"ptype, v0, v1, ..."，跳过 null）。
     *
     * @param array<string,mixed> $row
     */
    protected function rowToLine(array $row): string
    {
        $tokens = [(string) $row['ptype']];
        foreach ($this->columns as $col) {
            if ($row[$col] === null) {
                break; // 标准表各值连续填充，遇 null 即止
            }
            $tokens[] = (string) $row[$col];
        }

        return implode(', ', $tokens);
    }

    /**
     * 策略规则 → 数据行（ptype + v0.. 依次填充）。
     *
     * @param array<int,string> $rule
     * @return array<string,mixed>
     */
    protected function ruleToRow(string $ptype, array $rule): array
    {
        $row = ['ptype' => $ptype];
        foreach (array_values($rule) as $i => $value) {
            if (isset($this->columns[$i])) {
                $row[$this->columns[$i]] = (string) $value;
            }
        }

        return $row;
    }
}

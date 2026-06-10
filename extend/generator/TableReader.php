<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 表结构反读（information_schema 参数化）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace generator;

use think\facade\Db;

/**
 * 表结构反读：从 information_schema 读字段/索引（全程 `?` 占位参数化，禁拼接 SQL）。
 * - 自动识别并排除公共字段（不计入业务字段）。
 * - 提供 MySQL 类型 → PHP/cast/校验 的映射。
 * - 识别唯一索引业务字段（排除 tenant_id），供 withTrashed 唯一校验。
 */
class TableReader
{
    /**
     * 公共字段：不计入业务字段（§任务书 2）。
     *
     * @var array<int,string>
     */
    public const COMMON_FIELDS = [
        'id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at', 'create_by', 'create_dept',
    ];

    private string $database;

    private string $table;

    public function __construct(string $table)
    {
        $this->table    = $table;
        $this->database = (string) Db::connect()->getConfig('database');
    }

    /**
     * 表是否存在。
     */
    public function exists(): bool
    {
        $rows = Db::query(
            'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->database, $this->table]
        );

        return (int) ($rows[0]['c'] ?? 0) > 0;
    }

    /**
     * 表注释（用于推断模块中文名）。
     */
    public function tableComment(): string
    {
        $rows = Db::query(
            'SELECT TABLE_COMMENT AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->database, $this->table]
        );

        return trim((string) ($rows[0]['c'] ?? ''));
    }

    /**
     * 全部字段元信息（按表内顺序）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function columns(): array
    {
        $rows = Db::query(
            'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, '
            . 'COLUMN_KEY, COLUMN_COMMENT, EXTRA '
            . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$this->database, $this->table]
        );

        $uniqueCols = $this->uniqueBusinessColumns();

        $columns = [];
        foreach ($rows as $r) {
            $name = (string) $r['COLUMN_NAME'];
            $type = strtolower((string) $r['DATA_TYPE']);
            $map  = self::mapType($type, $name);

            $columns[] = [
                'name'       => $name,
                'data_type'  => $type,
                'length'     => $r['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int) $r['CHARACTER_MAXIMUM_LENGTH'] : null,
                'nullable'   => strtoupper((string) $r['IS_NULLABLE']) === 'YES',
                'default'    => $r['COLUMN_DEFAULT'],
                'comment'    => trim((string) $r['COLUMN_COMMENT']),
                'is_primary' => strtoupper((string) $r['COLUMN_KEY']) === 'PRI',
                'is_unique'  => in_array($name, $uniqueCols, true),
                'is_common'  => in_array($name, self::COMMON_FIELDS, true),
                'php_type'   => $map['php'],
                'cast'       => $map['cast'],
                'validate'   => $map['validate'],
            ];
        }

        return $columns;
    }

    /**
     * 业务字段（排除公共字段）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function businessColumns(): array
    {
        return array_values(array_filter($this->columns(), static fn ($c) => !$c['is_common']));
    }

    /**
     * 唯一索引覆盖的业务字段名（排除 tenant_id；联合唯一里取非 tenant_id 列）。
     *
     * @return array<int,string>
     */
    public function uniqueBusinessColumns(): array
    {
        $rows = Db::query(
            'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$this->database, $this->table]
        );

        $byIndex = [];
        foreach ($rows as $r) {
            if ((int) $r['NON_UNIQUE'] === 0 && strtoupper((string) $r['INDEX_NAME']) !== 'PRIMARY') {
                $byIndex[(string) $r['INDEX_NAME']][] = (string) $r['COLUMN_NAME'];
            }
        }

        $cols = [];
        foreach ($byIndex as $columns) {
            foreach ($columns as $c) {
                if ($c !== 'tenant_id' && !in_array($c, $cols, true)) {
                    $cols[] = $c;
                }
            }
        }

        return $cols;
    }

    /**
     * 自引用父字段检测：列名 ∈ {parent_id, pid} 且为整型，优先 parent_id。
     * 命中返回列名（树形模块标志），否则 null。
     */
    public function selfRefColumn(): ?string
    {
        $intCols = [];
        foreach ($this->columns() as $c) {
            if ($c['php_type'] === 'int') {
                $intCols[$c['name']] = true;
            }
        }
        foreach (['parent_id', 'pid'] as $candidate) {
            if (isset($intCols[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * 是否存在指定列。
     */
    public function hasColumn(string $name): bool
    {
        foreach ($this->columns() as $c) {
            if ($c['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * MySQL 数据类型 → PHP 标量 / 模型 cast / 校验类型 的映射表。
     *
     * @return array{php:string,cast:?string,validate:string}
     */
    public static function mapType(string $dataType, string $column = ''): array
    {
        // tinyint 多为状态/布尔，归整数
        return match ($dataType) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'
                => ['php' => 'int', 'cast' => 'integer', 'validate' => 'integer'],
            'decimal', 'float', 'double'
                => ['php' => 'float', 'cast' => 'float', 'validate' => 'float'],
            'date', 'datetime', 'timestamp'
                => ['php' => 'string', 'cast' => null, 'validate' => 'date'],
            'char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'json'
                => ['php' => 'string', 'cast' => null, 'validate' => 'max'],
            default
                => ['php' => 'string', 'cast' => null, 'validate' => 'max'],
        };
    }
}

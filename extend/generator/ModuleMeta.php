<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 模块元数据收集（表结构 + 配置/选项合并）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace generator;

/**
 * 模块元数据：合并「表结构反读默认值」与「配置数组 / CLI 选项覆盖」。
 *
 * 模块级：ModuleName(Post) / moduleName(post) / modulePlural(posts) / moduleCn(岗位) / permPrefix(system:post)。
 * 字段级（逐业务字段）：是否列表显示 / 查询条件(none|keyword|exact) / 新增必填 / 更新可改 / 是否敏感 /
 *                       校验规则(rule，缺省按类型推导) / 自定义消息(messages)。
 */
class ModuleMeta
{
    public string $table;        // 物理表名 bx_post
    public string $tableName;    // 去前缀模型名 post
    public string $moduleName;   // post（lcfirst）
    public string $ModuleName;   // Post（PascalCase）
    public string $modulePlural; // posts
    public string $moduleCn;     // 岗位
    public string $permPrefix;   // system:post

    /** @var array<int,array<string,mixed>> 业务字段（已合并属性） */
    public array $fields = [];

    /** @var array<int,array<string,mixed>> 全部字段（含公共字段，供模型 cast 用） */
    public array $allColumns = [];

    public ?string $uniqueField = null; // code（唯一索引业务字段，单字段唯一）
    public bool $hasStatus = false;      // 是否含 status 业务字段

    /** @var array<int,string> 敏感字段名 */
    public array $sensitiveFields = [];

    /**
     * @param array<string,mixed> $config 配置数组（来自 --config 文件或直接传入）
     * @param array<string,string> $options CLI 选项覆盖（name/plural/cn/perm）
     */
    public static function build(TableReader $reader, string $table, array $config = [], array $options = []): self
    {
        $m             = new self();
        $m->table      = $table;
        $prefix        = (string) (config('database.connections.' . config('database.default') . '.prefix') ?? '');
        $m->tableName  = $prefix !== '' && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;

        // 模块名：配置 > 选项 > 表名推导
        $studly         = self::studly($m->tableName);
        $m->ModuleName  = (string) ($config['name'] ?? $options['name'] ?? $studly);
        $m->moduleName  = lcfirst($m->ModuleName);
        $m->modulePlural = (string) ($config['plural'] ?? $options['plural'] ?? ($m->moduleName . 's'));
        $m->moduleCn    = (string) ($config['cn'] ?? $options['cn'] ?? ($reader->tableComment() ?: $m->ModuleName));
        $m->permPrefix  = (string) ($config['perm'] ?? $options['perm'] ?? ('system:' . $m->moduleName));

        $m->allColumns  = $reader->columns();
        $unique         = $reader->uniqueBusinessColumns();
        $m->uniqueField = $unique[0] ?? null;

        $fieldCfg = (array) ($config['fields'] ?? []);
        foreach ($reader->businessColumns() as $col) {
            $name = $col['name'];
            $cfg  = (array) ($fieldCfg[$name] ?? []);

            $isStatus = $name === 'status';
            if ($isStatus) {
                $m->hasStatus = true;
            }

            $search = (string) ($cfg['search'] ?? ($isStatus ? 'exact' : 'none'));
            $sensitive = (bool) ($cfg['sensitive'] ?? false);
            if ($sensitive) {
                $m->sensitiveFields[] = $name;
            }

            $m->fields[] = [
                'name'            => $name,
                'comment'         => $col['comment'],
                'data_type'       => $col['data_type'],
                'length'          => $col['length'],
                'cast'            => $col['cast'],
                'validate'        => $col['validate'],
                'is_unique'       => $col['is_unique'],
                'list'            => (bool) ($cfg['list'] ?? true),
                'search'          => $search,
                'create_required' => (bool) ($cfg['create_required'] ?? false),
                'update_editable' => (bool) ($cfg['update_editable'] ?? true),
                'sensitive'       => $sensitive,
                'rule'            => $cfg['rule'] ?? null,
                'messages'        => (array) ($cfg['messages'] ?? []),
            ];
        }

        return $m;
    }

    /**
     * @return array<int,array<string,mixed>> 列表展示字段
     */
    public function listFields(): array
    {
        return array_values(array_filter($this->fields, static fn ($f) => $f['list']));
    }

    /**
     * @return array<int,array<string,mixed>> keyword 模糊查询字段
     */
    public function keywordFields(): array
    {
        return array_values(array_filter($this->fields, static fn ($f) => $f['search'] === 'keyword'));
    }

    /**
     * @return array<int,array<string,mixed>> 精确查询字段
     */
    public function exactFields(): array
    {
        return array_values(array_filter($this->fields, static fn ($f) => $f['search'] === 'exact'));
    }

    /**
     * @return array<int,array<string,mixed>> 新增必填字段
     */
    public function requiredFields(): array
    {
        return array_values(array_filter($this->fields, static fn ($f) => $f['create_required']));
    }

    /**
     * bx_post → Post。
     */
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}

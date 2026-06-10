<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 模块元数据收集（表结构 + 配置/选项合并）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// | @updated   2026-06-10 16:00:00
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

    // ---- 树形元数据（M3-B）----
    public bool $isTree = false;             // 是否树形模块（自引用 parent_id）
    public string $parentField = 'parent_id'; // 父字段列名
    public string $sortField = 'sort';        // 树排序字段（缺列回退 id）
    public string $subtreeStrategy = 'memory'; // memory（内存遍历）/ cte（递归 CTE）
    public string $treeDeleteGuard = 'reject'; // reject（有子拒删）/ cascade（占位，本步不实装）

    // ---- 授权链路元数据（M3-C）：缺省均为空 = 不生成对应块，纯 CRUD/树形模块零影响 ----
    public string $tablePrefix = '';

    /** @var array<int,array<string,mixed>> 绑定拒删护栏：table/fk/cn/model（软删表给 Model 名，计数不含已删行） */
    public array $deleteBindingGuards = [];

    /** @var array<int,array<string,mixed>> 删除级联清理：relationTable/fk/cn/casbin（removeByPerm 或 removeAllForRole 变体） */
    public array $deleteCascade = [];

    /** @var array<int,array<string,mixed>> 分配关系接口：GET 回显 + PUT 覆盖式分配 + casbinSync */
    public array $relationEndpoints = [];

    /** @var array<int,array<string,mixed>> 受保护行护栏：matchField/matchValue/denyActions/cn */
    public array $protectedRows = [];

    /** @var array<int,array<string,mixed>> 可空唯一字段（非空才校验、不含 withTrashed；区别于 uniqueField 强唯一） */
    public array $nullableUniqueFields = [];

    /**
     * @param array<string,mixed> $config 配置数组（来自 --config 文件或直接传入）
     * @param array<string,string> $options CLI 选项覆盖（name/plural/cn/perm）
     */
    public static function build(TableReader $reader, string $table, array $config = [], array $options = []): self
    {
        $m              = new self();
        $m->table       = $table;
        $prefix         = (string) (config('database.connections.' . config('database.default') . '.prefix') ?? '');
        $m->tablePrefix = $prefix;
        $m->tableName   = self::stripPrefix($table, $prefix);

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

        // 树形识别（自引用列推导，config 显式优先）
        $detectedParent  = $reader->selfRefColumn();
        $m->parentField  = (string) ($config['parentField'] ?? $detectedParent ?? 'parent_id');
        $m->isTree       = array_key_exists('tree', $config)
            ? (bool) $config['tree']
            : ($detectedParent !== null);
        $m->sortField    = (string) ($config['sortField'] ?? ($reader->hasColumn('sort') ? 'sort' : 'id'));
        $m->subtreeStrategy = (string) ($config['subtreeStrategy'] ?? 'memory');
        $m->treeDeleteGuard = (string) ($config['treeDeleteGuard'] ?? 'reject');

        // 授权链路声明（M3-C）：均为可选键，逐条规范化补默认值
        foreach ((array) ($config['deleteBindingGuards'] ?? []) as $g) {
            $m->deleteBindingGuards[] = [
                'table' => (string) $g['table'],
                'fk'    => (string) $g['fk'],
                'cn'    => (string) ($g['cn'] ?? '关联数据'),
                'model' => isset($g['model']) ? (string) $g['model'] : null,
            ];
        }
        foreach ((array) ($config['deleteCascade'] ?? []) as $c) {
            $m->deleteCascade[] = [
                'relationTable' => (string) $c['relationTable'],
                'fk'            => (string) $c['fk'],
                'cn'            => (string) ($c['cn'] ?? self::stripPrefix((string) $c['relationTable'], $prefix) . ' 关联'),
                'casbin'        => isset($c['casbin']) ? (array) $c['casbin'] : null,
            ];
        }
        foreach ((array) ($config['relationEndpoints'] ?? []) as $e) {
            $sync = (array) ($e['casbinSync'] ?? []);
            $m->relationEndpoints[] = [
                'name'          => (string) $e['name'],
                'cn'            => (string) ($e['cn'] ?? $e['name']),
                'relationTable' => (string) $e['relationTable'],
                'selfFk'        => (string) $e['selfFk'],
                'targetTable'   => (string) $e['targetTable'],
                'targetModel'   => (string) ($e['targetModel'] ?? self::studly(self::stripPrefix((string) $e['targetTable'], $prefix))),
                'targetFk'      => (string) $e['targetFk'],
                'perm'          => (string) $e['perm'],
                'casbinSync'    => [
                    'enabled'     => (bool) ($sync['enabled'] ?? false),
                    'subField'    => (string) ($sync['subField'] ?? 'code'),
                    'permSource'  => (string) ($sync['permSource'] ?? 'perms'),
                    'act'         => (string) ($sync['act'] ?? 'do'),
                    'domainField' => (string) ($sync['domainField'] ?? 'tenant_id'),
                ],
            ];
        }
        foreach ((array) ($config['protectedRows'] ?? []) as $p) {
            $m->protectedRows[] = [
                'matchField'  => (string) $p['matchField'],
                'matchValue'  => (string) $p['matchValue'],
                'denyActions' => array_map('strval', (array) ($p['denyActions'] ?? [])),
                'cn'          => (string) ($p['cn'] ?? $p['matchValue']),
            ];
        }

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

            // 可空唯一变体（M3-C）：unique + nullable + uniqueScope=active（非空才校验、不含 withTrashed）
            if ((bool) ($cfg['unique'] ?? false) && (bool) ($cfg['nullable'] ?? false)
                && (string) ($cfg['uniqueScope'] ?? '') === 'active' && $name !== $m->uniqueField) {
                $m->nullableUniqueFields[] = [
                    'name'  => $name,
                    'label' => (string) ($cfg['label'] ?? ''),
                ];
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
     * 是否声明了授权链路（任一 M3-C 键非空）。
     */
    public function hasAuthChain(): bool
    {
        return $this->deleteBindingGuards !== [] || $this->deleteCascade !== []
            || $this->relationEndpoints !== [] || $this->protectedRows !== [];
    }

    /**
     * 命中某动作的受保护行声明（delete/disable/changeCode/assign），无则 null。
     *
     * @return array<string,mixed>|null
     */
    public function protectedRowFor(string $action): ?array
    {
        foreach ($this->protectedRows as $p) {
            if (in_array($action, $p['denyActions'], true)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * bx_post → Post。
     */
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * 去掉表前缀：bx_role_menu → role_menu（供 Db::name() 用）。
     */
    public static function stripPrefix(string $table, string $prefix): string
    {
        return $prefix !== '' && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
    }
}

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 渲染编排 + 落地（防覆盖 / --force / --dry-run）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace generator;

/**
 * 生成编排：根据 ModuleMeta 计算各占位符内容 → 渲染四件套 + 路由片段 + seeder 片段。
 * 不直接写盘，返回「相对路径 => 内容」清单，由命令层按防覆盖策略落地。
 */
class Generator
{
    private ModuleMeta $meta;

    private StubRenderer $renderer;

    private string $date;

    public function __construct(ModuleMeta $meta, StubRenderer $renderer, string $date)
    {
        $this->meta     = $meta;
        $this->renderer = $renderer;
        $this->date     = $date;
    }

    /**
     * 生成全部产物。
     *
     * @return array<string,string> 相对路径 => 文件内容
     */
    public function generate(): array
    {
        return [
            'app/common/model/' . $this->meta->ModuleName . '.php'              => $this->model(),
            'app/admin/controller/' . $this->meta->ModuleName . '.php'         => $this->controller(),
            'app/admin/service/' . $this->meta->ModuleName . 'Service.php'     => $this->service(),
            'app/admin/validate/' . $this->meta->ModuleName . 'Validate.php'   => $this->validate(),
            'route/' . $this->meta->moduleName . '.route.php'                  => $this->route(),
            'seeder/' . $this->meta->ModuleName . 'MenuSeeder.php'            => $this->seeder(),
        ];
    }

    // ============================== 四件套 ==============================

    private function model(): string
    {
        return $this->renderer->render('model', $this->baseVars() + [
            'hidden'    => $this->hidden(),
            'typeCasts' => $this->typeCasts(),
        ]);
    }

    private function controller(): string
    {
        $hasStatus = $this->meta->hasStatus;

        return $this->renderer->render('controller', $this->baseVars() + [
            'statusPathHint'    => $hasStatus ? '|/:id/status' : '',
            'listFilterHint'    => $this->listFilterHint(),
            'controllerFilters' => $this->controllerFilters(),
            'statusController'  => $hasStatus ? $this->statusController() : '',
        ]);
    }

    private function service(): string
    {
        $u = $this->meta->uniqueField;

        return $this->renderer->render('service', $this->baseVars() + [
            'uniqueDoc'         => $u !== null ? " * {$u} 唯一含软删（§5.1）。\n" : '',
            'listDoc'           => $this->listDoc(),
            'fillable'          => $this->fillable(),
            'listOrder'         => $this->listOrder(),
            'searchWhere'       => $this->searchWhere(),
            'uniqueGuardCreate' => $u !== null ? "        \$this->assert" . ModuleMeta::studly($u) . "Unique((string) \$data['{$u}'], null);\n" : '',
            'uniqueGuardUpdate' => $u !== null ? $this->uniqueGuardUpdate($u) : '',
            'statusMethod'      => $this->meta->hasStatus ? $this->statusMethod() : '',
            'uniqueGuardMethod' => $u !== null ? $this->uniqueGuardMethod($u) : '',
        ]);
    }

    private function validate(): string
    {
        $u = $this->meta->uniqueField;

        return $this->renderer->render('validate', $this->baseVars() + [
            'statusSceneHint'    => $this->meta->hasStatus ? '/status' : '',
            'uniqueValidateDoc'  => $u !== null ? "{$u} 唯一（含软删）校验在 {$this->meta->ModuleName}Service。" : '',
            'rules'              => $this->rules(),
            'messageBlock'       => $this->messageBlock(),
            'sceneFields'        => $this->sceneFields(),
            'updateRemove'       => $this->updateRemove(),
            'statusScene'        => $this->meta->hasStatus ? $this->statusScene() : '',
        ]);
    }

    // ============================ 路由 / 种子 ============================

    private function route(): string
    {
        return $this->renderer->render('route', $this->baseVars() + [
            'routeLines' => $this->routeLines(),
        ]);
    }

    private function seeder(): string
    {
        return $this->renderer->render('seeder', $this->baseVars());
    }

    // ============================== 占位符构建 ==============================

    /**
     * @return array<string,string>
     */
    private function baseVars(): array
    {
        return [
            'ModuleName'   => $this->meta->ModuleName,
            'moduleName'   => $this->meta->moduleName,
            'modulePlural' => $this->meta->modulePlural,
            'moduleCn'     => $this->meta->moduleCn,
            'permPrefix'   => $this->meta->permPrefix,
            'table'        => $this->meta->table,
            'tableName'    => $this->meta->tableName,
            'date'         => $this->date,
        ];
    }

    private function hidden(): string
    {
        $parts = ["'deleted_at'"];
        foreach ($this->meta->sensitiveFields as $f) {
            $parts[] = "'{$f}'";
        }

        return implode(', ', $parts);
    }

    private function typeCasts(): string
    {
        $pairs = ['id' => 'integer', 'tenant_id' => 'integer'];
        foreach ($this->meta->fields as $f) {
            if ($f['cast'] !== null) {
                $pairs[$f['name']] = $f['cast'];
            }
        }

        return $this->alignedAssoc($pairs, 8, static fn ($v) => "'{$v}'");
    }

    private function fillable(): string
    {
        $names = array_map(static fn ($f) => "'{$f['name']}'", $this->meta->fields);

        return implode(', ', $names);
    }

    private function listOrder(): string
    {
        foreach ($this->meta->fields as $f) {
            if ($f['name'] === 'sort') {
                return "order('sort', 'asc')->order('id', 'asc')";
            }
        }

        return "order('id', 'asc')";
    }

    private function listDoc(): string
    {
        $parts = [];
        $kw    = $this->meta->keywordFields();
        if ($kw !== []) {
            $parts[] = 'keyword: ' . implode('/', array_map(static fn ($f) => $f['name'], $kw));
        }
        foreach ($this->meta->exactFields() as $f) {
            $parts[] = $f['name'] . ' 精确';
        }

        return $parts === [] ? '' : '（' . implode('；', $parts) . '）';
    }

    private function searchWhere(): string
    {
        $kw    = $this->meta->keywordFields();
        $exact = $this->meta->exactFields();
        if ($kw === [] && $exact === []) {
            return '';
        }

        $out = "\n";
        if ($kw !== []) {
            $chain = [];
            foreach ($kw as $i => $f) {
                $chain[] = $i === 0
                    ? "\$q->whereLike('{$f['name']}', \"%{\$keyword}%\")"
                    : "->whereOr('{$f['name']}', 'like', \"%{\$keyword}%\")";
            }
            $out .= "        \$keyword = trim((string) (\$filters['keyword'] ?? ''));\n";
            $out .= "        if (\$keyword !== '') {\n";
            $out .= "            \$query->where(function (\$q) use (\$keyword) {\n";
            $out .= "                " . implode('', $chain) . ";\n";
            $out .= "            });\n";
            $out .= "        }\n";
        }
        foreach ($exact as $f) {
            $n = $f['name'];
            $out .= "        if ((\$filters['{$n}'] ?? '') !== '') {\n";
            $out .= "            \$query->where('{$n}', (int) \$filters['{$n}']);\n";
            $out .= "        }\n";
        }

        return $out;
    }

    private function uniqueGuardUpdate(string $u): string
    {
        $method = 'assert' . ModuleMeta::studly($u) . 'Unique';

        return "\n        if (array_key_exists('{$u}', \$data)) {\n"
            . "            \$this->{$method}((string) \$data['{$u}'], \$id);\n"
            . "        }\n";
    }

    private function uniqueGuardMethod(string $u): string
    {
        $module  = $this->meta->ModuleName;
        $method  = 'assert' . ModuleMeta::studly($u) . 'Unique';
        $comment = $this->fieldComment($u) ?: $u;

        return "\n    /**\n"
            . "     * {$u} 全局唯一（含 withTrashed，已删 {$u} 不可复用，§5.1）。\n"
            . "     */\n"
            . "    protected function {$method}(string \${$u}, ?int \$exceptId): void\n"
            . "    {\n"
            . "        \$query = {$module}::withTrashed()->where('{$u}', \${$u});\n"
            . "        if (\$exceptId !== null) {\n"
            . "            \$query->where('id', '<>', \$exceptId);\n"
            . "        }\n"
            . "        if (\$query->count() > 0) {\n"
            . "            throw new BusinessException('{$comment}已存在：' . \${$u});\n"
            . "        }\n"
            . "    }\n";
    }

    private function statusMethod(): string
    {
        $module = $this->meta->ModuleName;
        $var    = '$' . $this->meta->moduleName;
        $sVar   = $var . '->status';
        $pad    = str_repeat(' ', strlen($sVar) - strlen($var));

        return "\n    public function setStatus(int \$id, int \$status): {$module}\n"
            . "    {\n"
            . "        {$var}{$pad} = {$module}::findOrFail(\$id);\n"
            . "        {$sVar} = \$status;\n"
            . "        {$var}->save();\n\n"
            . "        return {$var};\n"
            . "    }\n";
    }

    private function statusController(): string
    {
        $module = $this->meta->ModuleName;
        $var    = '$' . $this->meta->moduleName;
        $plural = $this->meta->modulePlural;

        return "\n    /**\n"
            . "     * 启停。\n"
            . "     * PUT /admin/v1/{$plural}/:id/status\n"
            . "     */\n"
            . "    public function status(int \$id): Response\n"
            . "    {\n"
            . "        validate({$module}Validate::class)->scene('status')->check(\$this->request->param());\n\n"
            . "        {$var} = (new {$module}Service(\$this->app))->setStatus(\$id, (int) \$this->request->param('status'));\n\n"
            . "        return \$this->success({$var}, '状态更新成功');\n"
            . "    }\n";
    }

    private function listFilterHint(): string
    {
        $keys = $this->filterKeys();

        return $keys === [] ? '' : ' + ' . implode('/', $keys) . ' 筛选';
    }

    private function controllerFilters(): string
    {
        $keys = $this->filterKeys();
        if ($keys === []) {
            return '';
        }

        $pairs = [];
        foreach ($keys as $k) {
            $pairs[$k] = "\$this->request->param('{$k}', '')";
        }

        return $this->alignedAssoc($pairs, 12, static fn ($v) => $v);
    }

    /**
     * @return array<int,string>
     */
    private function filterKeys(): array
    {
        $keys = [];
        if ($this->meta->keywordFields() !== []) {
            $keys[] = 'keyword';
        }
        foreach ($this->meta->exactFields() as $f) {
            $keys[] = $f['name'];
        }

        return $keys;
    }

    private function rules(): string
    {
        $pairs = [];
        foreach ($this->meta->fields as $f) {
            $pairs[$f['name']] = $f['rule'] ?? $this->deriveRule($f);
        }

        return $this->alignedAssoc($pairs, 8, static fn ($v) => "'{$v}'");
    }

    /**
     * @param array<string,mixed> $f
     */
    private function deriveRule(array $f): string
    {
        $parts = [];
        if ($f['create_required']) {
            $parts[] = 'require';
        }
        $parts[] = match ($f['validate']) {
            'max'     => 'max:' . ($f['length'] ?? 255),
            'integer' => 'integer',
            'float'   => 'float',
            'date'    => 'date',
            default   => 'max:255',
        };

        return implode('|', $parts);
    }

    private function messageBlock(): string
    {
        $pairs = [];
        foreach ($this->meta->fields as $f) {
            foreach ($f['messages'] as $ruleKey => $msg) {
                $pairs[$f['name'] . '.' . $ruleKey] = $msg;
            }
        }
        if ($pairs === []) {
            return '    protected $message = [];';
        }

        return "    protected \$message = [\n"
            . $this->alignedAssoc($pairs, 8, static fn ($v) => "'{$v}'") . "\n"
            . '    ];';
    }

    private function sceneFields(): string
    {
        return implode(', ', array_map(static fn ($f) => "'{$f['name']}'", $this->meta->fields));
    }

    private function updateRemove(): string
    {
        $out = '';
        foreach ($this->meta->requiredFields() as $f) {
            $out .= "\n            ->remove('{$f['name']}', 'require')";
        }

        return $out;
    }

    private function statusScene(): string
    {
        return "\n    public function sceneStatus(): static\n"
            . "    {\n"
            . "        return \$this->only(['status'])->append('status', 'require');\n"
            . "    }\n";
    }

    private function routeLines(): string
    {
        $p      = $this->meta->modulePlural;
        $module = $this->meta->ModuleName;
        $perm   = $this->meta->permPrefix;
        $pat    = "->pattern(['id' => '\\d+'])";

        $lines = [];
        if ($this->meta->hasStatus) {
            $lines[] = "        Route::put('{$p}/:id/status', '{$module}/status')->middleware(CasbinAuth::class, '{$perm}:update'){$pat};";
        }
        $lines[] = "        Route::get('{$p}/:id', '{$module}/read')->middleware(CasbinAuth::class, '{$perm}:list'){$pat};";
        $lines[] = "        Route::put('{$p}/:id', '{$module}/update')->middleware(CasbinAuth::class, '{$perm}:update'){$pat};";
        $lines[] = "        Route::delete('{$p}/:id', '{$module}/delete')->middleware(CasbinAuth::class, '{$perm}:delete'){$pat};";
        $lines[] = "        Route::get('{$p}', '{$module}/index')->middleware(CasbinAuth::class, '{$perm}:list');";
        $lines[] = "        Route::post('{$p}', '{$module}/save')->middleware(CasbinAuth::class, '{$perm}:create');";

        return implode("\n", $lines);
    }

    // ============================== 工具 ==============================

    private function fieldComment(string $name): string
    {
        foreach ($this->meta->fields as $f) {
            if ($f['name'] === $name) {
                return (string) $f['comment'];
            }
        }

        return '';
    }

    /**
     * 对齐渲染关联数组：`'key'<pad> => <fmt(value)>,`。
     *
     * @param array<string,string> $pairs
     * @param callable(string):string $fmt
     */
    private function alignedAssoc(array $pairs, int $indent, callable $fmt): string
    {
        $quotedKeys = [];
        foreach (array_keys($pairs) as $k) {
            $quotedKeys[$k] = "'{$k}'";
        }
        $max = 0;
        foreach ($quotedKeys as $qk) {
            $max = max($max, strlen($qk));
        }

        $pad   = str_repeat(' ', $indent);
        $lines = [];
        foreach ($pairs as $k => $v) {
            $qk      = $quotedKeys[$k];
            $lines[] = $pad . $qk . str_repeat(' ', $max - strlen($qk)) . ' => ' . $fmt($v) . ',';
        }

        return implode("\n", $lines);
    }
}

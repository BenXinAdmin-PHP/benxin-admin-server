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
        $isTree    = $this->meta->isTree;
        $src       = $isTree ? 'dept/menu' : 'post';
        $doc       = $isTree
            ? "{$this->meta->moduleCn} CRUD（生成器复刻 dept/menu 树形母版）。"
            : "{$this->meta->moduleCn} CRUD（生成器复刻 post 纯 CRUD 母版）。";

        return $this->renderer->render('controller', $this->baseVars() + [
            'controllerClassDoc' => $doc,
            'fidelitySrc'        => $src,
            'treePathHint'       => $isTree ? '/tree|' : '',
            'statusPathHint'     => $hasStatus ? '|/:id/status' : '',
            'collectionAction'   => $isTree ? $this->treeAction() : $this->indexAction(),
            'statusController'   => $hasStatus ? $this->statusController() : '',
        ]);
    }

    private function service(): string
    {
        $u      = $this->meta->uniqueField;
        $isTree = $this->meta->isTree;
        $cte    = $isTree && $this->meta->subtreeStrategy === 'cte';

        $summary = $isTree
            ? "{$this->meta->moduleCn}服务：树构建 + CRUD（生成器复刻 dept/menu 树形母版）。"
            : "{$this->meta->moduleCn}服务：标准 CRUD（生成器复刻 post 母版）。";
        $mission = $isTree
            ? "服务 — {$this->meta->moduleCn} 树形 CRUD（生成器复刻 dept/menu 母版）"
            : "服务 — {$this->meta->moduleCn} CRUD（生成器复刻 post 母版）";
        $deleteDoc = $isTree
            ? '删除：有子节点拒绝；关联绑定护栏留 M3-C。'
            : '删除（纯 CRUD 软删；关联护栏属授权/关系范畴，留 M3-C）。';

        return $this->renderer->render('service', $this->baseVars() + [
            'serviceMission'    => $mission,
            'serviceSummary'    => $summary,
            'dbImport'          => $cte ? "use think\\facade\\Db;\n" : '',
            'uniqueDoc'         => $u !== null ? " * {$u} 唯一含软删（§5.1）。\n" : '',
            'fillable'          => $this->fillable(),
            'collectionMethods' => $isTree ? $this->treeMethods() : $this->listMethod(),
            'createParentGuard' => $isTree ? "        \$this->assertParent((int) (\$data['{$this->meta->parentField}'] ?? 0));\n" : '',
            'uniqueGuardCreate' => $u !== null ? "        \$this->assert" . ModuleMeta::studly($u) . "Unique((string) \$data['{$u}'], null);\n" : '',
            'updateGuards'      => $this->updateGuards($u),
            'deleteDoc'         => $deleteDoc,
            'deleteGuard'       => $isTree ? $this->treeDeleteGuard() : '',
            'statusMethod'      => $this->meta->hasStatus ? $this->statusMethod() : '',
            'publicTreeExtras'  => $cte ? $this->descendantIdsMethod() : '',
            'treeHelperMethods' => $isTree ? $this->treeHelperMethods() : '',
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

    /**
     * update 护栏块：树形 → 改父防自指/成环；普通 → 唯一校验。两者皆有则父级在前。
     */
    private function updateGuards(?string $u): string
    {
        $out = '';
        if ($this->meta->isTree) {
            $pf   = $this->meta->parentField;
            $out .= "\n        if (array_key_exists('{$pf}', \$data)) {\n"
                . "            \$newParent = (int) \$data['{$pf}'];\n"
                . "            \$this->assertParent(\$newParent);\n"
                . "            \$this->assertNotCycle(\$id, \$newParent);\n"
                . "        }\n";
        }
        if ($u !== null) {
            $out .= $this->uniqueGuardUpdate($u);
        }

        return $out;
    }

    // ---------------------- 集合方法（list / tree 二选一） ----------------------

    /**
     * 控制器 index() 动作（普通模块分页列表）。
     */
    private function indexAction(): string
    {
        $module = $this->meta->ModuleName;
        $plural = $this->meta->modulePlural;

        return "    /**\n"
            . "     * 列表（分页{$this->listFilterHint()}）。\n"
            . "     * GET /admin/v1/{$plural}\n"
            . "     */\n"
            . "    public function index(): Response\n"
            . "    {\n"
            . "        [\$page, \$size] = \$this->pageParam();\n"
            . "        \$result = (new {$module}Service(\$this->app))->list([\n"
            . $this->controllerFilters() . "\n"
            . "        ], \$page, \$size);\n\n"
            . "        return \$this->paginate(\$result['list'], \$result['total'], \$page, \$size);\n"
            . "    }";
    }

    /**
     * 控制器 tree() 动作（树形模块，取代分页列表）。
     */
    private function treeAction(): string
    {
        $module = $this->meta->ModuleName;
        $plural = $this->meta->modulePlural;

        return "    /**\n"
            . "     * 完整{$this->meta->moduleCn}树。\n"
            . "     * GET /admin/v1/{$plural}/tree\n"
            . "     */\n"
            . "    public function tree(): Response\n"
            . "    {\n"
            . "        return \$this->success((new {$module}Service(\$this->app))->tree());\n"
            . "    }";
    }

    /**
     * 服务 list() 方法（普通模块分页列表）。
     */
    private function listMethod(): string
    {
        $module = $this->meta->ModuleName;

        return "    /**\n"
            . "     * 分页列表{$this->listDoc()}。\n"
            . "     *\n"
            . "     * @param array<string,mixed> \$filters\n"
            . "     * @return array{list:array<int,mixed>,total:int}\n"
            . "     */\n"
            . "    public function list(array \$filters, int \$page, int \$pageSize): array\n"
            . "    {\n"
            . "        \$query = {$module}::{$this->listOrder()};\n"
            . $this->searchWhere() . "\n"
            . "        \$total = \$query->count();\n"
            . "        \$list  = \$query->page(\$page, \$pageSize)->select()->toArray();\n\n"
            . "        return ['list' => \$list, 'total' => \$total];\n"
            . "    }";
    }

    /**
     * 服务 tree() + buildTree() 方法（树形模块，内存建树）。
     */
    private function treeMethods(): string
    {
        $module = $this->meta->ModuleName;
        $pf     = $this->meta->parentField;
        $sf     = $this->meta->sortField;
        $order  = $sf === 'id' ? "order('id', 'asc')" : "order('{$sf}', 'asc')->order('id', 'asc')";

        return "    /**\n"
            . "     * 完整{$this->meta->moduleCn}树（按 {$sf} 升序）。\n"
            . "     *\n"
            . "     * @return array<int,array>\n"
            . "     */\n"
            . "    public function tree(): array\n"
            . "    {\n"
            . "        \$list = {$module}::{$order}->select()->toArray();\n\n"
            . "        return \$this->buildTree(\$list, 0);\n"
            . "    }\n\n"
            . "    /**\n"
            . "     * 内存建树。\n"
            . "     *\n"
            . "     * @param array<int,array> \$list\n"
            . "     * @return array<int,array>\n"
            . "     */\n"
            . "    public function buildTree(array \$list, int \$parentId = 0): array\n"
            . "    {\n"
            . "        \$tree = [];\n"
            . "        foreach (\$list as \$node) {\n"
            . "            if ((int) \$node['{$pf}'] === \$parentId) {\n"
            . "                \$children = \$this->buildTree(\$list, (int) \$node['id']);\n"
            . "                if (\$children !== []) {\n"
            . "                    \$node['children'] = \$children;\n"
            . "                }\n"
            . "                \$tree[] = \$node;\n"
            . "            }\n"
            . "        }\n\n"
            . "        return \$tree;\n"
            . "    }";
    }

    /**
     * 树形 delete 护栏：有子节点拒删 + 关联护栏 M3-C 锚点注释。
     */
    private function treeDeleteGuard(): string
    {
        $module = $this->meta->ModuleName;
        $pf     = $this->meta->parentField;

        return "\n        if ({$module}::where('{$pf}', \$id)->count() > 0) {\n"
            . "            throw new BusinessException('该{$this->meta->moduleCn}存在子节点，请先删除子节点');\n"
            . "        }\n\n"
            . "        // TODO M3-C: 关联绑定计数拒删（admin 挂靠 / role_menu + casbin reload）\n";
    }

    /**
     * 树形辅助方法：assertParent + assertNotCycle + 子树取法（memory: collectDescendants / cte: descendantIds）。
     */
    private function treeHelperMethods(): string
    {
        $module = $this->meta->ModuleName;
        $cn     = $this->meta->moduleCn;
        $pf     = $this->meta->parentField;
        $cte    = $this->meta->subtreeStrategy === 'cte';

        $out = "\n    protected function assertParent(int \$parentId): void\n"
            . "    {\n"
            . "        if (\$parentId === 0) {\n"
            . "            return;\n"
            . "        }\n"
            . "        if ({$module}::where('id', \$parentId)->count() === 0) {\n"
            . "            throw new BusinessException('父级{$cn}不存在');\n"
            . "        }\n"
            . "    }\n";

        if ($cte) {
            $out .= "\n    protected function assertNotCycle(int \$id, int \$newParentId): void\n"
                . "    {\n"
                . "        if (\$newParentId === 0) {\n"
                . "            return;\n"
                . "        }\n"
                . "        if (\$newParentId === \$id) {\n"
                . "            throw new BusinessException('父级不能选择自身');\n"
                . "        }\n"
                . "        if (in_array(\$newParentId, \$this->descendantIds(\$id), true)) {\n"
                . "            throw new BusinessException('父级不能选择自身的子节点');\n"
                . "        }\n"
                . "    }\n";
            // descendantIds 为 public（数据权限复用），作为公共方法在 separator 之前注入（publicTreeExtras）
        } else {
            $out .= "\n    protected function assertNotCycle(int \$id, int \$newParentId): void\n"
                . "    {\n"
                . "        if (\$newParentId === 0) {\n"
                . "            return;\n"
                . "        }\n"
                . "        if (\$newParentId === \$id) {\n"
                . "            throw new BusinessException('父级不能选择自身');\n"
                . "        }\n\n"
                . "        \$all         = {$module}::field('id,{$pf}')->select()->toArray();\n"
                . "        \$descendants = \$this->collectDescendants(\$all, \$id);\n"
                . "        if (in_array(\$newParentId, \$descendants, true)) {\n"
                . "            throw new BusinessException('父级不能选择自身的子节点');\n"
                . "        }\n"
                . "    }\n";
            $out .= "\n    /**\n"
                . "     * 收集某节点的全部子孙 id（内存遍历）。\n"
                . "     *\n"
                . "     * @param array<int,array> \$all\n"
                . "     * @return array<int,int>\n"
                . "     */\n"
                . "    protected function collectDescendants(array \$all, int \$id): array\n"
                . "    {\n"
                . "        \$result = [];\n"
                . "        foreach (\$all as \$node) {\n"
                . "            if ((int) \$node['{$pf}'] === \$id) {\n"
                . "                \$childId  = (int) \$node['id'];\n"
                . "                \$result[] = \$childId;\n"
                . "                \$result   = array_merge(\$result, \$this->collectDescendants(\$all, \$childId));\n"
                . "            }\n"
                . "        }\n\n"
                . "        return \$result;\n"
                . "    }\n";
        }

        return $out;
    }

    /**
     * 子树 id 递归 CTE 方法（MySQL8 WITH RECURSIVE，全程参数化）。
     */
    private function descendantIdsMethod(): string
    {
        $tableName = $this->meta->tableName;
        $pf        = $this->meta->parentField;
        $cn        = $this->meta->moduleCn;
        $idVar     = '$' . $this->meta->moduleName . 'Id';
        $cte       = $this->meta->moduleName . '_cte';

        return "\n    /**\n"
            . "     * 子树 id 集合（含自身 + 全部后代），MySQL8 递归 CTE（参数化）。\n"
            . "     * 供数据权限“本{$cn}及以下”使用（ADR-9）。\n"
            . "     *\n"
            . "     * @return array<int,int>\n"
            . "     */\n"
            . "    public function descendantIds(int {$idVar}): array\n"
            . "    {\n"
            . "        if ({$idVar} <= 0) {\n"
            . "            return [];\n"
            . "        }\n\n"
            . "        \$prefix = config('database.connections.mysql.prefix', 'bx_');\n"
            . "        \$table  = \$prefix . '{$tableName}';\n"
            . "        \$sql    = \"WITH RECURSIVE {$cte} AS (\"\n"
            . "            . \"SELECT id FROM {\$table} WHERE id = ? AND deleted_at IS NULL \"\n"
            . "            . \"UNION ALL \"\n"
            . "            . \"SELECT d.id FROM {\$table} d INNER JOIN {$cte} c ON d.{$pf} = c.id WHERE d.deleted_at IS NULL\"\n"
            . "            . \") SELECT id FROM {$cte}\";\n\n"
            . "        \$rows = Db::query(\$sql, [{$idVar}]);\n\n"
            . "        return array_map(static fn (\$r) => (int) \$r['id'], \$rows);\n"
            . "    }\n";
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
        // 树形：/tree 取数（list perm），排在 /:id 之前；树形无 index 集合路由
        if ($this->meta->isTree) {
            $lines[] = "        Route::get('{$p}/tree', '{$module}/tree')->middleware(CasbinAuth::class, '{$perm}:list');";
        }
        if ($this->meta->hasStatus) {
            $lines[] = "        Route::put('{$p}/:id/status', '{$module}/status')->middleware(CasbinAuth::class, '{$perm}:update'){$pat};";
        }
        $lines[] = "        Route::get('{$p}/:id', '{$module}/read')->middleware(CasbinAuth::class, '{$perm}:list'){$pat};";
        $lines[] = "        Route::put('{$p}/:id', '{$module}/update')->middleware(CasbinAuth::class, '{$perm}:update'){$pat};";
        $lines[] = "        Route::delete('{$p}/:id', '{$module}/delete')->middleware(CasbinAuth::class, '{$perm}:delete'){$pat};";
        if (!$this->meta->isTree) {
            $lines[] = "        Route::get('{$p}', '{$module}/index')->middleware(CasbinAuth::class, '{$perm}:list');";
        }
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

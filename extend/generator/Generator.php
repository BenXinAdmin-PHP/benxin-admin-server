<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 渲染编排 + 落地（防覆盖 / --force / --dry-run）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// | @updated   2026-06-12 10:00:00
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
        $endpoints = $this->meta->relationEndpoints;
        $src       = $isTree ? 'dept/menu' : 'post';
        if ($endpoints !== []) {
            $rels = implode('/', array_map(static fn ($e) => '分配' . $e['cn'], $endpoints));
            $doc  = "{$this->meta->moduleCn} CRUD + {$rels}（生成器复刻 role 授权链路母版）。";
        } else {
            $doc = $isTree
                ? "{$this->meta->moduleCn} CRUD（生成器复刻 dept/menu 树形母版）。"
                : "{$this->meta->moduleCn} CRUD（生成器复刻 post 纯 CRUD 母版）。";
        }

        return $this->renderer->render('controller', $this->baseVars() + [
            'controllerClassDoc'    => $doc,
            'fidelitySrc'           => $src,
            'treePathHint'          => $isTree ? '/tree|' : '',
            'statusPathHint'        => $hasStatus ? '|/:id/status' : '',
            'relationPathHint'      => implode('', array_map(static fn ($e) => "|/:id/{$e['name']}", $endpoints)),
            'collectionAction'      => $isTree ? $this->treeAction() : $this->indexAction(),
            'statusController'      => $hasStatus ? $this->statusController() : '',
            'relationReadActions'   => $this->relationReadActions(),
            'relationAssignActions' => $this->relationAssignActions(),
        ]);
    }

    private function service(): string
    {
        $u      = $this->meta->uniqueField;
        $isTree = $this->meta->isTree;
        $cte    = $isTree && $this->meta->subtreeStrategy === 'cte';

        $uniqueGuardCreate = $u !== null
            ? "        \$this->assert" . ModuleMeta::studly($u) . "Unique((string) \$data['{$u}'], null);\n"
            : '';
        foreach ($this->meta->nullableUniqueFields as $f) {
            // 可空唯一：值缺省按空串跳过校验（复刻手写 menu.perms）
            $uniqueGuardCreate .= '        $this->assert' . ModuleMeta::studly($f['name'])
                . "Unique(\$data['{$f['name']}'] ?? '', null);\n";
        }

        $uniqueGuardMethods = $u !== null ? $this->uniqueGuardMethod($u) : '';
        foreach ($this->meta->nullableUniqueFields as $f) {
            $uniqueGuardMethods .= $this->nullableUniqueGuardMethod($f);
        }

        return $this->renderer->render('service', $this->baseVars() + [
            'serviceMission'        => $this->serviceMission(),
            'serviceSummary'        => $this->serviceSummary(),
            'modelImports'          => $this->modelImports(),
            'serviceImports'        => $this->casbinTouched() ? "use app\\common\\service\\CasbinService;\n" : '',
            'dbImport'              => $this->needsDb($cte) ? "use think\\facade\\Db;\n" : '',
            'uniqueDoc'             => $u !== null ? " * {$u} 唯一含软删（§5.1）。\n" : '',
            'fillable'              => $this->fillable(),
            'collectionMethods'     => $isTree ? $this->treeMethods() : $this->listMethod(),
            'relationIdMethods'     => $this->relationIdMethods(),
            'createParentGuard'     => $isTree ? "        \$this->assertParent((int) (\$data['{$this->meta->parentField}'] ?? 0));\n" : '',
            'uniqueGuardCreate'     => $uniqueGuardCreate,
            'updateGuards'          => $this->updateGuards($u),
            'deleteDoc'             => $this->deleteDoc(),
            'deleteGuard'           => $this->deleteGuards(),
            'deleteAction'          => $this->deleteAction(),
            'statusMethod'          => $this->meta->hasStatus ? $this->statusMethod() : '',
            'relationAssignMethods' => $this->relationAssignMethods(),
            'publicTreeExtras'      => $cte ? $this->descendantIdsMethod() : '',
            'treeHelperMethods'     => $isTree ? $this->treeHelperMethods() : '',
            'uniqueGuardMethod'     => $uniqueGuardMethods,
        ]);
    }

    private function validate(): string
    {
        $u = $this->meta->uniqueField;

        if ($u !== null && $this->meta->protectedRows !== []) {
            $mv        = $this->meta->protectedRows[0]['matchValue'];
            $uniqueDoc = "{$u} 唯一性、{$mv} 保护等业务规则在 {$this->meta->ModuleName}Service。";
        } else {
            $uniqueDoc = $u !== null ? "{$u} 唯一（含软删）校验在 {$this->meta->ModuleName}Service。" : '';
        }

        return $this->renderer->render('validate', $this->baseVars() + [
            'statusSceneHint'    => $this->meta->hasStatus ? '/status' : '',
            'assignSceneHint'    => implode('', array_map(
                static fn ($e) => '/assign' . ModuleMeta::studly($e['name']),
                $this->meta->relationEndpoints,
            )),
            'uniqueValidateDoc'  => $uniqueDoc,
            'rules'              => $this->rules(),
            'messageBlock'       => $this->messageBlock(),
            'sceneFields'        => $this->sceneFields(),
            'updateRemove'       => $this->updateRemove(),
            'statusScene'        => $this->meta->hasStatus ? $this->statusScene() : '',
            'assignScenes'       => $this->assignScenes(),
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
        // 标准四动作 + 分配接口的非标 act（如 assign；role 复用 update 则不追加，与路由一一对应）
        $acts  = ['list', 'create', 'update', 'delete'];
        $items = "['list', '查询', 1], ['create', '新增', 2], ['update', '修改', 3], ['delete', '删除', 4]";
        $sort  = 5;
        foreach ($this->meta->relationEndpoints as $e) {
            $prefix = $this->meta->permPrefix . ':';
            if (!str_starts_with($e['perm'], $prefix)) {
                continue; // 跨模块 perm 不入本模块 seeder（报告中说明）
            }
            $act = substr($e['perm'], strlen($prefix));
            if (in_array($act, $acts, true)) {
                continue;
            }
            $acts[] = $act;
            $items .= ", ['{$act}', '分配{$e['cn']}', {$sort}]";
            $sort++;
        }

        return $this->renderer->render('seeder', $this->baseVars() + [
            'seederPermActs'  => implode('|', $acts),
            'seederPermItems' => $items,
        ]);
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
     * update 护栏块：树形改父防自指/成环 → 受保护行（禁改标识/禁停用）→ 唯一校验（强唯一 + 可空唯一）。
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
        $out .= $this->protectedUpdateGuard();
        if ($u !== null) {
            $out .= $this->uniqueGuardUpdate($u);
        }
        foreach ($this->meta->nullableUniqueFields as $f) {
            $out .= $this->uniqueGuardUpdate($f['name']);
        }

        return $out;
    }

    /**
     * update 入口受保护行护栏（复刻手写 role：super_admin 不可改 code / 不可停用）。
     */
    private function protectedUpdateGuard(): string
    {
        $changeCode = $this->meta->protectedRowFor('changeCode');
        $disable    = $this->meta->protectedRowFor('disable');
        $p          = $changeCode ?? $disable;
        if ($p === null) {
            return '';
        }

        $var   = '$' . $this->meta->moduleName;
        $mf    = $p['matchField'];
        $mv    = $p['matchValue'];
        $hints = [];
        if ($changeCode !== null) {
            $hints[] = "不可改 {$mf}";
        }
        if ($disable !== null) {
            $hints[] = '不可停用';
        }

        $out = "\n        // {$mv} 保护：" . implode('、', $hints) . "\n"
            . "        if ({$var}->{$mf} === '{$mv}') {\n";
        if ($changeCode !== null) {
            $out .= "            if (array_key_exists('{$mf}', \$data) && \$data['{$mf}'] !== '{$mv}') {\n"
                . "                throw new BusinessException('{$p['cn']}为内置保护数据，不可修改 {$mf}');\n"
                . "            }\n";
        }
        if ($disable !== null) {
            $out .= "            if (array_key_exists('status', \$data) && (int) \$data['status'] !== 1) {\n"
                . "                throw new BusinessException('{$p['cn']}为内置保护数据，不可停用');\n"
                . "            }\n";
        }

        return $out . "        }\n";
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

    // ====================== 删除链路（M3-C：护栏 + 级联） ======================

    /**
     * delete 护栏块：受保护行（紧跟 findOrFail，复刻手写 role）→ 子节点拒删 → 绑定拒删。
     * 关联护栏均按 config 声明生成，未声明即不输出（无占位注释）。
     */
    private function deleteGuards(): string
    {
        $cn  = $this->meta->moduleCn;
        $var = '$' . $this->meta->moduleName;
        $out = '';

        if (($p = $this->meta->protectedRowFor('delete')) !== null) {
            $out .= "        if ({$var}->{$p['matchField']} === '{$p['matchValue']}') {\n"
                . "            throw new BusinessException('{$p['cn']}为内置保护数据，不可删除');\n"
                . "        }\n";
        }

        $hasChildGuard = $this->meta->isTree;
        if ($hasChildGuard) {
            $module = $this->meta->ModuleName;
            $pf     = $this->meta->parentField;
            $out .= "\n        if ({$module}::where('{$pf}', \$id)->count() > 0) {\n"
                . "            throw new BusinessException('该{$cn}存在子节点，请先删除子节点');\n"
                . "        }\n";
        }

        // 绑定拒删：树形紧贴子节点护栏（复刻手写 dept）；否则与上一段空行分隔（复刻手写 role）
        $lead = $hasChildGuard ? '' : "\n";
        foreach ($this->meta->deleteBindingGuards as $g) {
            $cond = $g['model'] !== null
                ? "{$g['model']}::where('{$g['fk']}', \$id)->count() > 0"
                : "Db::name('" . ModuleMeta::stripPrefix($g['table'], $this->meta->tablePrefix) . "')->where('{$g['fk']}', \$id)->count() > 0";
            $out .= $lead
                . "        if ({$cond}) {\n"
                . "            throw new BusinessException('该{$cn}已被{$g['cn']}绑定，无法删除');\n"
                . "        }\n";
            $lead = '';
        }

        return $out;
    }

    /**
     * delete 执行块：无级联 → 直接软删；声明 deleteCascade → 事务内清关系表 + casbin，
     * removeByPerm 变体复刻手写 menu（条件 reload），removeAllForRole 变体复刻手写 role（finally reload）。
     */
    private function deleteAction(): string
    {
        $var     = '$' . $this->meta->moduleName;
        $cascade = $this->meta->deleteCascade;
        $guards  = $this->meta->hasAuthChain() || $this->meta->isTree;

        if ($cascade === []) {
            // 有任一护栏（受保护行/子节点/绑定）时与护栏块空行分隔；纯 CRUD 紧跟 findOrFail
            $lead = ($this->meta->isTree || $this->meta->deleteBindingGuards !== [] || $this->meta->protectedRowFor('delete') !== null)
                ? "\n" : '';

            return $lead . "        {$var}->delete();\n";
        }

        $casbin = $this->cascadeCasbin();
        if ($casbin !== null && !empty($casbin['removeAllForRole'])) {
            return $this->cascadeBySub($casbin);
        }

        return $this->cascadeByPerm($casbin);
    }

    /**
     * 级联清理（removeAllForRole 变体）：本行 subField 作 casbin sub 清策略，finally reload。
     */
    private function cascadeBySub(array $casbin): string
    {
        $var    = '$' . $this->meta->moduleName;
        $sub    = '$' . (string) ($casbin['subField'] ?? 'code');
        $domPad = str_repeat(' ', max(strlen($sub), 4) - 4);
        $subPad = str_repeat(' ', max(strlen($sub), 4) - strlen($sub));

        $out = "\n        {$sub}{$subPad} = (string) {$var}->" . ($casbin['subField'] ?? 'code') . ";\n"
            . "        \$dom{$domPad} = (int) {$var}->" . ($casbin['domainField'] ?? 'tenant_id') . ";\n\n"
            . "        try {\n"
            . "            Db::transaction(function () use (\$id, {$var}, {$sub}, \$dom) {\n";
        foreach ($this->meta->deleteCascade as $c) {
            $rel  = ModuleMeta::stripPrefix($c['relationTable'], $this->meta->tablePrefix);
            $out .= "                Db::name('{$rel}')->where('{$c['fk']}', \$id)->delete();\n";
        }
        $out .= "                CasbinService::removeAllForRole({$sub}, \$dom);\n"
            . "                {$var}->delete();\n"
            . "            });\n"
            . "        } finally {\n"
            . "            // 无论提交/回滚，重载使内存策略与库一致\n"
            . "            CasbinService::reload();\n"
            . "        }\n";

        return $out;
    }

    /**
     * 级联清理（removeByPerm 变体 / 无 casbin）：清关系表 +（按本行 perm 串删策略），事务外条件 reload。
     */
    private function cascadeByPerm(?array $casbin): string
    {
        $var       = '$' . $this->meta->moduleName;
        $cn        = $this->meta->moduleCn;
        $permField = (string) ($casbin['permField'] ?? 'perms');
        $permVar   = '$' . $permField;
        $hasCasbin = $casbin !== null && !empty($casbin['removeByPerm']);

        $out  = $hasCasbin ? "\n        {$permVar} = (string) {$var}->{$permField};\n\n" : "\n";
        $out .= '        Db::transaction(function () use ($id, ' . $var . ($hasCasbin ? ", {$permVar}" : '') . ") {\n";
        foreach ($this->meta->deleteCascade as $c) {
            $rel  = ModuleMeta::stripPrefix($c['relationTable'], $this->meta->tablePrefix);
            $out .= "            // 清理{$c['cn']}\n"
                . "            Db::name('{$rel}')->where('{$c['fk']}', \$id)->delete();\n\n";
        }
        if ($hasCasbin) {
            $out .= "            // 清理引用该 perm 的 casbin 授权（避免悬空策略）\n"
                . "            if ({$permVar} !== '') {\n"
                . "                CasbinService::removePolicyByPerm({$permVar});\n"
                . "            }\n\n";
        }
        $out .= "            // 软删除{$cn}\n"
            . "            {$var}->delete();\n"
            . "        });\n";
        if ($hasCasbin) {
            $out .= "\n        if ({$permVar} !== '') {\n"
                . "            CasbinService::reload();\n"
                . "        }\n";
        }

        return $out;
    }

    /**
     * deleteCascade 各条目中第一个 casbin 声明（removeByPerm / removeAllForRole 二选一）。
     *
     * @return array<string,mixed>|null
     */
    private function cascadeCasbin(): ?array
    {
        foreach ($this->meta->deleteCascade as $c) {
            if ($c['casbin'] !== null) {
                return $c['casbin'];
            }
        }

        return null;
    }

    /**
     * delete 方法 doc：按护栏/级联声明组合（无任何声明保持 M3-A/B 原文，保证 post 回归）。
     */
    private function deleteDoc(): string
    {
        $cn      = $this->meta->moduleCn;
        $isTree  = $this->meta->isTree;
        $cascade = $this->meta->deleteCascade;

        if (!$this->meta->hasAuthChain()) {
            return $isTree
                ? '删除：有子节点拒绝；关联绑定护栏按需在 config 声明。'
                : '删除（纯 CRUD 软删；关联护栏按需在 config 声明）。';
        }

        $parts = [];
        if (($p = $this->meta->protectedRowFor('delete')) !== null) {
            $parts[] = "{$p['matchValue']} 拒绝";
        }
        if ($isTree) {
            $parts[] = '有子节点拒绝';
        }
        foreach ($this->meta->deleteBindingGuards as $g) {
            $parts[] = $cascade === []
                ? "有{$g['cn']}绑定（{$g['table']}.{$g['fk']}）拒绝"
                : "仍有{$g['cn']}绑定拒绝";
        }
        $head = "删除{$cn}：" . implode('；', $parts);

        if ($cascade === []) {
            return $head . '；否则软删。';
        }

        $casbin = $this->cascadeCasbin();
        if ($casbin !== null && !empty($casbin['removeAllForRole'])) {
            $rels = implode('/', array_map(
                fn ($c) => ModuleMeta::stripPrefix($c['relationTable'], $this->meta->tablePrefix),
                $cascade,
            ));

            return $head . "；\n     * 否则事务软删 + 清 {$rels} + 清该{$cn} casbin 策略 + reload。";
        }

        $rels = implode(' 与 ', array_map(static fn ($c) => $c['relationTable'], $cascade));

        return $casbin !== null
            ? $head . "；级联清理 {$rels} 与 casbin {$casbin['permField']}。"
            : $head . "；级联清理 {$rels}。";
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
        $comment = $this->fieldLabel($u);

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

        // 受保护行禁停用：护栏插在 findOrFail 与赋值之间（复刻手写 role，无对齐补位）
        if (($p = $this->meta->protectedRowFor('disable')) !== null) {
            return "\n    public function setStatus(int \$id, int \$status): {$module}\n"
                . "    {\n"
                . "        {$var} = {$module}::findOrFail(\$id);\n"
                . "        if ({$var}->{$p['matchField']} === '{$p['matchValue']}' && \$status !== 1) {\n"
                . "            throw new BusinessException('{$p['cn']}为内置保护数据，不可停用');\n"
                . "        }\n"
                . "        {$sVar} = \$status;\n"
                . "        {$var}->save();\n\n"
                . "        return {$var};\n"
                . "    }\n";
        }

        $pad = str_repeat(' ', strlen($sVar) - strlen($var));

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
        foreach ($this->meta->relationEndpoints as $e) {
            $pairs[$e['targetFk'] . 's'] = 'array';
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
        foreach ($this->meta->relationEndpoints as $e) {
            $key                    = $e['targetFk'] . 's';
            $pairs[$key . '.array'] = "{$key} 必须为数组";
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
        // 分配关系子资源（/:id/xxx）：GET 回显（list perm）+ PUT 覆盖式分配（声明 perm），排在 /:id/status 之前（复刻手写 role）
        foreach ($this->meta->relationEndpoints as $e) {
            $assign  = 'assign' . ModuleMeta::studly($e['name']);
            $lines[] = "        Route::get('{$p}/:id/{$e['name']}', '{$module}/{$e['name']}')->middleware(CasbinAuth::class, '{$perm}:list'){$pat};";
            $lines[] = "        Route::put('{$p}/:id/{$e['name']}', '{$module}/{$assign}')->middleware(CasbinAuth::class, '{$e['perm']}'){$pat};";
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

    // ====================== 分配关系接口（M3-C） ======================

    /**
     * 服务层：每个 relationEndpoint 的已分配 id 列表方法（回显，复刻手写 menuIds）。
     */
    private function relationIdMethods(): string
    {
        $out = '';
        foreach ($this->meta->relationEndpoints as $e) {
            $idsMethod = lcfirst(ModuleMeta::studly($e['targetFk'])) . 's';
            $rel       = ModuleMeta::stripPrefix($e['relationTable'], $this->meta->tablePrefix);
            $out .= "\n    /**\n"
                . "     * 该{$this->meta->moduleCn}已分配{$e['cn']} id 列表。\n"
                . "     *\n"
                . "     * @return array<int,int>\n"
                . "     */\n"
                . "    public function {$idsMethod}(int \$id): array\n"
                . "    {\n"
                . "        return array_map('intval', Db::name('{$rel}')->where('{$e['selfFk']}', \$id)->column('{$e['targetFk']}'));\n"
                . "    }\n";
        }

        return $out;
    }

    /**
     * 服务层：覆盖式分配方法（★ 核心授权链路）。事务内覆盖写关系表 + casbin 重建，
     * 非法 targetId 抛异常整单回滚；reload 放 finally（复刻 M1-C 手写 assignMenus）。
     */
    private function relationAssignMethods(): string
    {
        $out    = '';
        $module = $this->meta->ModuleName;
        $var    = '$' . $this->meta->moduleName;
        $cn     = $this->meta->moduleCn;
        $deny   = $this->meta->protectedRowFor('assign');

        foreach ($this->meta->relationEndpoints as $e) {
            $method   = 'assign' . ModuleMeta::studly($e['name']);
            $idsVar   = '$' . lcfirst(ModuleMeta::studly($e['targetFk'])) . 's';
            $rel      = ModuleMeta::stripPrefix($e['relationTable'], $this->meta->tablePrefix);
            $target   = $e['targetModel'];
            $sync     = $e['casbinSync'];
            $rowVar   = $this->rowVarName($e['targetFk']);
            $w        = max(strlen($idsVar), 6);
            $idsPad   = str_repeat(' ', $w - strlen($idsVar));
            $validPad = str_repeat(' ', $w - 6);
            $doc      = $sync['enabled']
                ? "覆盖式分配{$e['cn']} + 同步 Casbin（★ 核心授权链路，事务）。"
                : "覆盖式分配{$e['cn']}（覆盖写关系表，事务）。";

            $out .= "\n    /**\n"
                . "     * {$doc}\n"
                . "     *\n"
                . "     * @param array<int,int> {$idsVar}\n"
                . "     */\n"
                . "    public function {$method}(int \$id, array {$idsVar}): void\n"
                . "    {\n"
                . "        {$var} = {$module}::findOrFail(\$id);\n";
            if ($deny !== null) {
                $out .= "        if ({$var}->{$deny['matchField']} === '{$deny['matchValue']}') {\n"
                    . "            throw new BusinessException('{$deny['cn']}为内置保护数据，不可分配{$e['cn']}');\n"
                    . "        }\n";
            }
            $out .= "\n        // 仅保留真实存在的{$e['cn']} id\n"
                . "        {$idsVar}{$idsPad} = array_values(array_unique(array_map('intval', {$idsVar})));\n"
                . "        \$valid{$validPad} = {$idsVar} === []\n"
                . "            ? []\n"
                . "            : array_map('intval', {$target}::whereIn('id', {$idsVar})->column('id'));\n"
                . "        if (count(\$valid) !== count({$idsVar})) {\n"
                . "            throw new BusinessException('提交的{$e['cn']}中存在不存在的项');\n"
                . "        }\n\n";

            $out .= $sync['enabled']
                ? $this->assignSyncBody($e, $rel, $rowVar)
                : $this->assignPlainBody($e, $rel, $rowVar);
            $out .= "    }\n";
        }

        return $out;
    }

    /**
     * 分配方法体（casbinSync 开）：取 perm 串 → 事务覆盖写 + removeAllForRole + 逐 perm 重建 → finally reload。
     *
     * @param array<string,mixed> $e
     */
    private function assignSyncBody(array $e, string $rel, string $rowVar): string
    {
        $var      = '$' . $this->meta->moduleName;
        $cn       = $this->meta->moduleCn;
        $sync     = $e['casbinSync'];
        $permsVar = '$' . $sync['permSource'];
        $subVar   = '$' . $sync['subField'];
        $w        = max(strlen($subVar), 4);
        $subPad   = str_repeat(' ', $w - strlen($subVar));
        $domPad   = str_repeat(' ', $w - 4);
        $actArg   = $sync['act'] === 'do' ? '' : ", '{$sync['act']}'";

        return "        // 取选中{$e['cn']}的非空 {$sync['permSource']}（按钮/菜单级），去重\n"
            . "        {$permsVar} = \$valid === []\n"
            . "            ? []\n"
            . "            : array_values(array_unique(array_filter(\n"
            . "                {$e['targetModel']}::whereIn('id', \$valid)->column('{$sync['permSource']}'),\n"
            . "                static fn (\$p) => trim((string) \$p) !== '',\n"
            . "            )));\n\n"
            . "        {$subVar}{$subPad} = (string) {$var}->{$sync['subField']};\n"
            . "        \$dom{$domPad} = (int) {$var}->{$sync['domainField']};\n\n"
            . "        try {\n"
            . "            Db::transaction(function () use (\$id, \$valid, {$permsVar}, {$subVar}, \$dom) {\n"
            . "                // 覆盖写 {$rel}\n"
            . "                Db::name('{$rel}')->where('{$e['selfFk']}', \$id)->delete();\n"
            . "                if (\$valid !== []) {\n"
            . "                    \$now  = date('Y-m-d H:i:s');\n"
            . "                    \$rows = array_map(static fn ({$rowVar}) => [\n"
            . $this->alignedAssoc([
                $e['selfFk']   => '$id',
                $e['targetFk'] => $rowVar,
                'created_at'   => '$now',
            ], 24, static fn ($v) => $v) . "\n"
            . "                    ], \$valid);\n"
            . "                    Db::name('{$rel}')->insertAll(\$rows);\n"
            . "                }\n\n"
            . "                // 覆盖同步 casbin：先清该{$cn}旧策略，再按新 {$sync['permSource']} 重建\n"
            . "                CasbinService::removeAllForRole({$subVar}, \$dom);\n"
            . "                foreach ({$permsVar} as \$perm) {\n"
            . "                    CasbinService::addPolicyForRole({$subVar}, \$dom, \$perm{$actArg});\n"
            . "                }\n"
            . "            });\n"
            . "        } finally {\n"
            . "            CasbinService::reload();\n"
            . "        }\n";
    }

    /**
     * 分配方法体（casbinSync 关）：仅事务覆盖写关系表。
     *
     * @param array<string,mixed> $e
     */
    private function assignPlainBody(array $e, string $rel, string $rowVar): string
    {
        return "        Db::transaction(function () use (\$id, \$valid) {\n"
            . "            // 覆盖写 {$rel}\n"
            . "            Db::name('{$rel}')->where('{$e['selfFk']}', \$id)->delete();\n"
            . "            if (\$valid !== []) {\n"
            . "                \$now  = date('Y-m-d H:i:s');\n"
            . "                \$rows = array_map(static fn ({$rowVar}) => [\n"
            . $this->alignedAssoc([
                $e['selfFk']   => '$id',
                $e['targetFk'] => $rowVar,
                'created_at'   => '$now',
            ], 20, static fn ($v) => $v) . "\n"
            . "                ], \$valid);\n"
            . "                Db::name('{$rel}')->insertAll(\$rows);\n"
            . "            }\n"
            . "        });\n";
    }

    /**
     * 控制器：已分配 id 列表回显 action（GET /:id/<relation>，复刻手写 Role::menus）。
     */
    private function relationReadActions(): string
    {
        $out    = '';
        $module = $this->meta->ModuleName;
        $plural = $this->meta->modulePlural;
        foreach ($this->meta->relationEndpoints as $e) {
            $idsMethod = lcfirst(ModuleMeta::studly($e['targetFk'])) . 's';
            $out .= "\n    /**\n"
                . "     * 已分配{$e['cn']} id 列表（回显勾选）。\n"
                . "     * GET /admin/v1/{$plural}/:id/{$e['name']}\n"
                . "     */\n"
                . "    public function {$e['name']}(int \$id): Response\n"
                . "    {\n"
                . "        return \$this->success((new {$module}Service(\$this->app))->{$idsMethod}(\$id));\n"
                . "    }\n";
        }

        return $out;
    }

    /**
     * 控制器：覆盖式分配 action（PUT /:id/<relation>，复刻手写 Role::assignMenus）。
     */
    private function relationAssignActions(): string
    {
        $out    = '';
        $module = $this->meta->ModuleName;
        $plural = $this->meta->modulePlural;
        foreach ($this->meta->relationEndpoints as $e) {
            $method = 'assign' . ModuleMeta::studly($e['name']);
            $key    = $e['targetFk'] . 's';
            $out .= "\n    /**\n"
                . "     * 分配{$e['cn']}（全量覆盖式 {$key}[]，同步 Casbin）。\n"
                . "     * PUT /admin/v1/{$plural}/:id/{$e['name']}\n"
                . "     */\n"
                . "    public function {$method}(int \$id): Response\n"
                . "    {\n"
                . "        validate({$module}Validate::class)->scene('{$method}')->check(\$this->request->param());\n\n"
                . "        (new {$module}Service(\$this->app))->{$method}(\$id, (array) \$this->request->param('{$key}', []));\n\n"
                . "        return \$this->success(null, '分配成功');\n"
                . "    }\n";
        }

        return $out;
    }

    /**
     * 校验器：分配场景（sceneAssignXxx，复刻手写 sceneAssignMenus）。
     * append array 而非 require：允许空数组 = 清空授权（M3-D1 修正，覆盖式语义自洽）。
     */
    private function assignScenes(): string
    {
        $out = '';
        foreach ($this->meta->relationEndpoints as $e) {
            $method = 'assign' . ModuleMeta::studly($e['name']);
            $key    = $e['targetFk'] . 's';
            $out .= "\n    public function scene" . ucfirst($method) . "(): static\n"
                . "    {\n"
                . "        // append array 而非 require：允许空数组 = 清空授权（覆盖式语义自洽）\n"
                . "        return \$this->only(['{$key}'])->append('{$key}', 'array');\n"
                . "    }\n";
        }

        return $out;
    }

    // ====================== 导入 / 文案（M3-C） ======================

    /**
     * 服务层模型导入：本模块 + 绑定护栏 Model + 关系目标 Model，去重按字母序。
     */
    private function modelImports(): string
    {
        $names = [$this->meta->ModuleName];
        foreach ($this->meta->deleteBindingGuards as $g) {
            if ($g['model'] !== null) {
                $names[] = $g['model'];
            }
        }
        foreach ($this->meta->relationEndpoints as $e) {
            $names[] = $e['targetModel'];
        }
        $names = array_unique($names);
        sort($names);

        $out = '';
        foreach ($names as $n) {
            $out .= "use app\\common\\model\\{$n};\n";
        }

        return $out;
    }

    /**
     * 是否触及 casbin（级联清理含 casbin 声明，或任一分配接口开 casbinSync）。
     */
    private function casbinTouched(): bool
    {
        if ($this->cascadeCasbin() !== null) {
            return true;
        }
        foreach ($this->meta->relationEndpoints as $e) {
            if ($e['casbinSync']['enabled']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 服务层是否需要 Db 门面（CTE / 级联 / 分配接口 / 无 Model 的绑定护栏走 Db::name）。
     */
    private function needsDb(bool $cte): bool
    {
        if ($cte || $this->meta->deleteCascade !== [] || $this->meta->relationEndpoints !== []) {
            return true;
        }
        foreach ($this->meta->deleteBindingGuards as $g) {
            if ($g['model'] === null) {
                return true;
            }
        }

        return false;
    }

    private function serviceMission(): string
    {
        $cn = $this->meta->moduleCn;
        if ($this->meta->relationEndpoints !== []) {
            $rels = implode('/', array_map(static fn ($e) => '分配' . $e['cn'], $this->meta->relationEndpoints));

            return "服务 — {$cn} CRUD + {$rels}（同步 Casbin，生成器复刻 role 授权链路母版）";
        }

        return $this->meta->isTree
            ? "服务 — {$cn} 树形 CRUD（生成器复刻 dept/menu 母版）"
            : "服务 — {$cn} CRUD（生成器复刻 post 母版）";
    }

    private function serviceSummary(): string
    {
        $cn = $this->meta->moduleCn;
        if ($this->meta->relationEndpoints !== []) {
            $rels    = implode('/', array_map(static fn ($e) => '分配' . $e['cn'], $this->meta->relationEndpoints));
            $summary = "{$cn}服务：CRUD + 覆盖式{$rels}并同步 Casbin（生成器复刻 role 授权链路母版）。";
        } else {
            $summary = $this->meta->isTree
                ? "{$cn}服务：树构建 + CRUD（生成器复刻 dept/menu 树形母版）。"
                : "{$cn}服务：标准 CRUD（生成器复刻 post 母版）。";
        }
        if ($this->meta->protectedRows !== []) {
            $p     = $this->meta->protectedRows[0];
            $verbs = [];
            foreach ($p['denyActions'] as $a) {
                $verbs[] = match ($a) {
                    'delete'     => '删',
                    'disable'    => '停',
                    'changeCode' => '改 ' . $p['matchField'],
                    'assign'     => '分配' . ($this->meta->relationEndpoints[0]['cn'] ?? '关系'),
                    default      => $a,
                };
            }
            $summary .= "\n * {$p['matchValue']} 为内置受保护行：不可" . implode('/', $verbs) . '。';
        }

        return $summary;
    }

    /**
     * 可空唯一校验方法（非空才校验、不含 withTrashed；复刻手写 menu.assertPermsUnique）。
     *
     * @param array<string,mixed> $f
     */
    private function nullableUniqueGuardMethod(array $f): string
    {
        $module = $this->meta->ModuleName;
        $name   = $f['name'];
        $method = 'assert' . ModuleMeta::studly($name) . 'Unique';
        $label  = $this->fieldLabel($name, $f['label']);

        return "\n    /**\n"
            . "     * {$name} 非空时全局唯一（同租户，排除自身）。\n"
            . "     */\n"
            . "    protected function {$method}(string \${$name}, ?int \$exceptId): void\n"
            . "    {\n"
            . "        if (trim(\${$name}) === '') {\n"
            . "            return;\n"
            . "        }\n"
            . "        \$query = {$module}::where('{$name}', \${$name});\n"
            . "        if (\$exceptId !== null) {\n"
            . "            \$query->where('id', '<>', \$exceptId);\n"
            . "        }\n"
            . "        if (\$query->count() > 0) {\n"
            . "            throw new BusinessException('{$label} {$name} 已存在：' . \${$name});\n"
            . "        }\n"
            . "    }\n";
    }

    /**
     * 关系行闭包变量名：menu_id → $mid、dept_id → $did（末段 id 保留，其余取首字母，复刻手写）。
     */
    private function rowVarName(string $targetFk): string
    {
        $parts = explode('_', $targetFk);
        $last  = array_pop($parts);
        if ($last !== 'id') {
            $parts[] = $last;
            $last    = '';
        }

        return '$' . implode('', array_map(static fn ($w) => substr($w, 0, 1), $parts)) . $last;
    }

    /**
     * 字段中文标签：配置覆盖 > 列注释截断（到第一个标点/空格）> 字段名。
     */
    private function fieldLabel(string $name, string $override = ''): string
    {
        if ($override !== '') {
            return $override;
        }
        $comment = $this->fieldComment($name);
        if ($comment === '') {
            return $name;
        }
        $cut = mb_strlen($comment);
        foreach (['，', '。', '：', '（', ',', '(', ':', ' '] as $sep) {
            $pos = mb_strpos($comment, $sep);
            if ($pos !== false && $pos < $cut) {
                $cut = $pos;
            }
        }

        return mb_substr($comment, 0, $cut);
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

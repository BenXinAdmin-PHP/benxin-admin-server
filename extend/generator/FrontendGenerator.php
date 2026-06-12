<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — 前端产物（列表页 + 编辑表单 + 分配弹窗 + api 薄壳，M3-D1）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-12 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace generator;

/**
 * 前端产物生成：按 ModuleMeta（后端元数据 + 可选 front 声明）复刻 M3-D0 黄金样板
 * （web 仓 role/menu 两页，范式权威：docs/CRUD-SCHEMA.md + XTable/XFormDrawer types）。
 *
 * 产物（相对路径，默认由命令层落 extend/generator/output/frontend/，不自动写 web 仓）：
 * - frontend/src/api/<module>.ts                     —— 手写 axios 薄壳（甲案，消费 @/utils/request）
 * - frontend/src/views/<dir>/index.vue               —— XTable config + XFormDrawer config 三段式
 * - frontend/src/views/<dir>/Assign<Target>Dialog.vue —— 有 relationEndpoints 才生成
 *
 * 联动边界：声明式可推导的（front.visibility 钩子、仅 update 可见等）生成 visible(form, mode)；
 * 复杂业务联动（data_scope=5 部门树、menu type 完整规整）走 front.formManualSlots 留 TODO 手工槽。
 *
 * 排版确定性（对齐 D0 手写排版，diff 验真）：
 * - config 条目单行/多行：含注释或 tip、或显示宽度（CJK 记 2）> 112 → 逐键多行；
 * - api 函数签名：> 100 列 → 参数逐行换行（prettier printWidth=100 同口径）。
 */
class FrontendGenerator
{
    private ModuleMeta $meta;

    private StubRenderer $renderer;

    private string $dateDay;

    public function __construct(ModuleMeta $meta, StubRenderer $renderer, string $date)
    {
        $this->meta     = $meta;
        $this->renderer = $renderer;
        $this->dateDay  = substr($date, 0, 10);
    }

    /**
     * @return array<string,string> 相对路径 => 文件内容
     */
    public function generate(): array
    {
        $dir   = $this->viewDir();
        $files = [
            'frontend/src/api/' . $this->meta->moduleName . '.ts' => $this->apiFile(),
            'frontend/src/views/' . $dir . '/index.vue'           => $this->indexVue(),
        ];
        foreach ($this->meta->relationEndpoints as $e) {
            $files['frontend/src/views/' . $dir . '/' . $this->dialogName($e) . '.vue'] = $this->assignDialog($e);
        }

        return $files;
    }

    // ============================== api 薄壳 ==============================

    private function apiFile(): string
    {
        $isTree = $this->meta->isTree;

        return $this->renderer->render('frontend/api', [
            'apiMission'         => $this->apiMission(),
            'dateDay'            => $this->dateDay,
            'requestTypeImports' => $isTree ? ', type ApiEnvelope' : ', type ApiEnvelope, type PageResult',
            'itemInterface'      => $this->itemInterface(),
            'detailInterface'    => $this->detailInterface(),
            'apiFunctions'       => implode("\n\n", $this->apiFunctions()),
        ]);
    }

    private function apiMission(): string
    {
        $parts = [];
        if ($this->meta->isTree) {
            $parts[] = '树';
        }
        $parts[] = 'CRUD';
        if ($this->meta->hasStatus) {
            $parts[] = '状态';
        }
        foreach ($this->meta->relationEndpoints as $e) {
            $parts[] = '分配' . $e['cn'];
        }

        return $this->meta->moduleCn . '接口（' . implode(' + ', $parts)
            . ' — /admin/v1/' . $this->meta->modulePlural . '）';
    }

    /**
     * 行/树节点接口：全部列（除 deleted_at），业务字段可经 front.tsDoc 注释。
     */
    private function itemInterface(): string
    {
        $cn   = $this->meta->moduleCn;
        $item = $this->itemType();

        if ($this->meta->isTree) {
            $doc = "/** {$cn}树节点（管理端全字段；children 仅在有子节点时存在） */";
        } else {
            $extra = $this->relationFkList();
            $doc   = $extra === ''
                ? "/** {$cn}行（列表/详情共用） */"
                : "/** {$cn}行（列表/详情共用；详情额外带 {$extra}） */";
        }

        $out = $doc . "\nexport interface {$item} {\n";
        foreach ($this->meta->allColumns as $col) {
            if ($col['name'] === 'deleted_at') {
                continue;
            }
            $tsDoc = $this->tsDocFor($col['name']);
            if ($tsDoc !== null) {
                $out .= "  /** {$tsDoc} */\n";
            }
            $out .= "  {$col['name']}: " . $this->tsType($col) . "\n";
        }
        if ($this->meta->isTree) {
            $out .= "  children?: {$item}[]\n";
        }

        return $out . "}\n\n";
    }

    /**
     * 详情聚合接口（有 relationEndpoints 才生成）：Item + 各关系 id 集合。
     */
    private function detailInterface(): string
    {
        if ($this->meta->relationEndpoints === []) {
            return '';
        }
        $cns  = implode('/', array_map(static fn ($e) => $e['cn'], $this->meta->relationEndpoints));
        $item = $this->itemType();

        $out = "/** 详情聚合：含已分配{$cns} id */\n"
            . "export interface {$this->meta->ModuleName}Detail extends {$item} {\n";
        foreach ($this->meta->relationEndpoints as $e) {
            $out .= "  {$e['targetFk']}s: number[]\n";
        }

        return $out . "}\n\n";
    }

    /**
     * @return array<int,string>
     */
    private function apiFunctions(): array
    {
        $m      = $this->meta;
        $item   = $this->itemType();
        $plural = $m->modulePlural;
        $base   = "/admin/v1/{$plural}";
        $fns    = [];

        if ($m->isTree) {
            $fns[] = $this->tsFn(
                "/** GET {$base}/tree —— 完整{$m->moduleCn}树（无分页） */",
                $this->fn('tree'),
                [],
                "{$item}[]",
                "  return request<{$item}[]>({ url: '/v1/{$plural}/tree', method: 'get' })",
            );
        } else {
            $fns[] = $this->tsFn(
                "/** GET {$base} —— 分页列表{$this->listDocSuffix()} */",
                $this->fn('list'),
                ['params: Record<string, unknown>'],
                "PageResult<{$item}>",
                "  return request<PageResult<{$item}>>({ url: '/v1/{$plural}', method: 'get', params })",
            );
        }

        $detail    = $m->relationEndpoints !== [] ? "{$m->ModuleName}Detail" : $item;
        $detailDoc = $m->relationEndpoints !== [] ? "详情（含 {$this->relationFkList()}）" : '详情';
        $fns[]     = $this->tsFn(
            "/** GET {$base}/:id —— {$detailDoc} */",
            $this->fn('get'),
            ['id: number'],
            $detail,
            '  return request<' . $detail . '>({ url: `/v1/' . $plural . '/${id}`, method: \'get\' })',
        );

        $required = implode('/', array_map(static fn ($f) => $f['name'], $m->requiredFields()));
        $fns[]    = $this->tsFn(
            "/** POST {$base} —— 新增" . ($required !== '' ? "（sceneCreate：{$required} 必填）" : '') . ' */',
            $this->fn('create'),
            ['data: Record<string, unknown>'],
            $item,
            "  return request<{$item}>({ url: '/v1/{$plural}', method: 'post', data })",
        );

        $updateDoc = $m->isTree ? '更新（选择性字段；防自指/成环在后端）' : '更新（选择性字段）';
        $fns[]     = $this->tsFn(
            "/** PUT {$base}/:id —— {$updateDoc} */",
            $this->fn('update'),
            ['id: number', 'data: Record<string, unknown>'],
            $item,
            '  return request<' . $item . '>({ url: `/v1/' . $plural . '/${id}`, method: \'put\', data })',
        );

        $fns[] = $this->tsFn(
            "/** DELETE {$base}/:id —— 删除{$this->deleteDocSuffix()} */",
            $this->fn('delete'),
            ['id: number'],
            'null',
            '  return request<null>({ url: `/v1/' . $plural . '/${id}`, method: \'delete\' })',
        );

        if ($m->hasStatus) {
            $fns[] = $this->tsFn(
                "/** PUT {$base}/:id/status —— 启停 */",
                $this->fn('status'),
                ['id: number', 'status: number'],
                $item,
                '  return request<' . $item . '>({ url: `/v1/' . $plural . '/${id}/status`, method: \'put\', data: { status } })',
            );
        }

        foreach ($m->relationEndpoints as $e) {
            $sync  = $e['casbinSync']['enabled'] ? '（同步 Casbin）' : '';
            $fns[] = $this->tsFn(
                "/** GET {$base}/:id/{$e['name']} —— 已分配{$e['cn']} id 列表（分配弹窗回显） */",
                $this->relReadFn($e),
                ['id: number'],
                'number[]',
                '  return request<number[]>({ url: `/v1/' . $plural . '/${id}/' . $e['name'] . '`, method: \'get\' })',
            );
            $fns[] = $this->tsFn(
                "/** PUT {$base}/:id/{$e['name']} —— 覆盖式分配{$e['cn']}{$sync} */",
                $this->relWriteFn($e),
                ['id: number', "{$e['targetFk']}s: number[]"],
                'null',
                '  return request<null>({ url: `/v1/' . $plural . '/${id}/' . $e['name'] . '`, method: \'put\', data: { ' . $e['targetFk'] . 's } })',
            );
        }

        return $fns;
    }

    private function listDocSuffix(): string
    {
        $parts = [];
        $kw    = $this->meta->keywordFields();
        if ($kw !== []) {
            $parts[] = 'keyword 模糊 ' . implode('/', array_map(static fn ($f) => $f['name'], $kw));
        }
        foreach ($this->meta->exactFields() as $f) {
            $parts[] = $f['name'] . ' 精确';
        }

        return $parts === [] ? '' : '（' . implode('，', $parts) . '）';
    }

    private function deleteDocSuffix(): string
    {
        $parts = [];
        if (($p = $this->meta->protectedRowFor('delete')) !== null) {
            $parts[] = $p['matchValue'];
        }
        if ($this->meta->isTree) {
            $head = '有子节点 → 422';
            if ($this->meta->deleteCascade !== []) {
                $head .= '；级联清 ' . $this->cascadeBrief();
            }

            return '（' . $head . '）';
        }
        foreach ($this->meta->deleteBindingGuards as $g) {
            $parts[] = '有' . $g['cn'] . '绑定';
        }

        return $parts === [] ? '' : '（' . implode('/', $parts) . ' → 422）';
    }

    private function cascadeBrief(): string
    {
        $rels = array_map(
            fn ($c) => ModuleMeta::stripPrefix($c['relationTable'], $this->meta->tablePrefix),
            $this->meta->deleteCascade,
        );
        $hasCasbin = false;
        foreach ($this->meta->deleteCascade as $c) {
            if ($c['casbin'] !== null) {
                $hasCasbin = true;
            }
        }

        return implode(' + ', $rels) . ($hasCasbin ? ' + casbin' : '');
    }

    /**
     * 函数渲染：签名超 100 列（printWidth）→ 参数逐行换行。
     *
     * @param array<int,string> $params
     */
    private function tsFn(string $doc, string $name, array $params, string $retData, string $body): string
    {
        $ret    = "Promise<ApiEnvelope<{$retData}>>";
        $inline = "export function {$name}(" . implode(', ', $params) . "): {$ret} {";
        if (mb_strwidth($inline, 'UTF-8') <= 100) {
            return "{$doc}\n{$inline}\n{$body}\n}";
        }

        $lines = "export function {$name}(\n";
        foreach ($params as $p) {
            $lines .= "  {$p},\n";
        }

        return "{$doc}\n{$lines}): {$ret} {\n{$body}\n}";
    }

    // ============================== 列表页 index.vue ==============================

    private function indexVue(): string
    {
        $m   = $this->meta;
        $rel = $m->relationEndpoints[0] ?? null;

        return $this->renderer->render('frontend/index.vue', [
            'pageMission'        => $this->pageMission(),
            'dateDay'            => $this->dateDay,
            'assignDialogImport' => $this->assignDialogImport(),
            'apiImports'         => $this->apiImports(),
            'moduleName'         => $m->moduleName,
            'tableTypeImports'   => ($this->enums() !== [] ? 'OptionItem, ' : '') . 'Row, XTableConfig',
            'constsBlock'        => $this->constsBlock(),
            'apiSlotComment'     => $rel !== null
                ? "\n// 模块 API 槽位：列表/新增/更新/删除/状态 + 分配关系（relationEndpoints）\n"
                : "\n",
            'apiSlots'           => $this->apiSlots(),
            'treeFlags'          => $this->treeFlags(),
            'searchBlock'        => $this->searchBlock(),
            'columns'            => $this->columnsBlock(),
            'permPrefix'         => $m->permPrefix,
            'moduleCn'           => $m->moduleCn,
            'actionsWidth'       => isset($m->front['actionsWidth'])
                ? '  actionsWidth: ' . (int) $m->front['actionsWidth'] . ",\n"
                : '',
            'rowActions'         => $this->rowActionsBlock(),
            'helperBlocks'       => $this->helperBlocks(),
            'entity'             => (string) ($m->front['entity'] ?? $m->moduleCn),
            'detailLine'         => $this->detailLine(),
            'formItems'          => $this->formItemsBlock(),
            'assignState'        => $this->assignState(),
            'onActionBranches'   => $this->onActionBranches(),
            'assignDialogTag'    => $this->assignDialogTag(),
        ]);
    }

    private function pageMission(): string
    {
        $m     = $this->meta;
        $parts = $m->isTree ? '树形 XTable 整树无分页' : 'XTable 配置化列表';
        $parts .= ' + 编辑抽屉';
        foreach ($m->relationEndpoints as $e) {
            $parts .= ' + 分配' . $e['cn'] . '弹窗';
        }

        return "{$m->moduleCn}管理（bx:make 生成：{$parts}）";
    }

    private function assignDialogImport(): string
    {
        $out = '';
        foreach ($this->meta->relationEndpoints as $e) {
            $name = $this->dialogName($e);
            $out .= "import {$name} from './{$name}.vue'\n";
        }

        return $out;
    }

    private function apiImports(): string
    {
        $names = [$this->fn($this->meta->isTree ? 'tree' : 'list'), $this->fn('create'), $this->fn('update'), $this->fn('delete')];
        if ($this->meta->hasStatus) {
            $names[] = $this->fn('status');
        }
        if ($this->meta->relationEndpoints !== []) {
            $names[] = $this->fn('get'); // detail 聚合回显
            foreach ($this->meta->relationEndpoints as $e) {
                $names[] = $this->relReadFn($e);
                $names[] = $this->relWriteFn($e);
            }
        }
        sort($names);

        $out = implode("\n", array_map(static fn ($n) => "  {$n},", $names));
        if ($this->meta->isTree) {
            $out .= "\n  type " . $this->itemType() . ',';
        }

        return $out;
    }

    /**
     * 受保护行常量 + 静态枚举常量（front.enums 声明，含 tagType）。
     */
    private function constsBlock(): string
    {
        $out = '';
        if ($this->protectedConst() !== null) {
            [$name, $value] = $this->protectedConst();
            $out .= "\nconst {$name} = '{$value}'\n";
        }
        foreach ($this->enums() as $enum => $def) {
            $out .= "\n/** {$def['doc']} */\nconst {$enum}: OptionItem[] = [\n";
            foreach ((array) $def['options'] as $opt) {
                $value = is_string($opt['value']) ? "'{$opt['value']}'" : $opt['value'];
                $line  = "  { label: '{$opt['label']}', value: {$value}";
                if (isset($opt['tagType'])) {
                    $line .= ", tagType: '{$opt['tagType']}'";
                }
                $out .= $line . " },\n";
            }
            $out .= "]\n";
        }

        return $out === '' ? '' : rtrim($out, "\n");
    }

    private function apiSlots(): string
    {
        $lines = [
            '  list: ' . $this->fn($this->meta->isTree ? 'tree' : 'list') . ',',
            '  save: ' . $this->fn('create') . ',',
            '  update: ' . $this->fn('update') . ',',
            '  remove: ' . $this->fn('delete') . ',',
        ];
        if ($this->meta->hasStatus) {
            $lines[] = '  status: ' . $this->fn('status') . ',';
        }
        if (($e = $this->meta->relationEndpoints[0] ?? null) !== null) {
            $lines[] = '  relation: { read: ' . $this->relReadFn($e) . ', write: ' . $this->relWriteFn($e) . ' },';
        }

        return implode("\n", $lines);
    }

    private function treeFlags(): string
    {
        if (!$this->meta->isTree) {
            return '';
        }

        return "  // ★ 树形范式：取 GET {$this->meta->modulePlural}/tree 整树、无分页，row-key + tree-props 缩进展开\n"
            . "  tree: true,\n"
            . "  defaultExpandAll: true,\n";
    }

    /**
     * 顶部搜索（树形整树无搜索）：keyword 合并一项 + exact 字段逐项。
     */
    private function searchBlock(): string
    {
        if ($this->meta->isTree) {
            return '';
        }
        $items = [];
        $kw    = $this->meta->keywordFields();
        if ($kw !== []) {
            $labels      = implode('/', array_map(fn ($f) => $this->label($f), $kw));
            $placeholder = (string) ($this->meta->front['keywordPlaceholder'] ?? "{$labels}模糊查询");
            $items[]     = "    { prop: 'keyword', label: '关键词', type: 'input', placeholder: '{$placeholder}' },";
        }
        foreach ($this->meta->exactFields() as $f) {
            $hint = $this->fieldFront($f, 'search');
            if ($f['name'] === 'status') {
                $dict    = (string) ($hint['dict'] ?? 'sys_normal_disable');
                $width   = (int) ($hint['width'] ?? 160);
                $items[] = "    { prop: 'status', label: '{$this->label($f)}', type: 'select', dict: '{$dict}', width: {$width} },";
            } elseif (isset($hint['enum'])) {
                $items[] = "    { prop: '{$f['name']}', label: '{$this->label($f)}', type: 'select', options: {$hint['enum']} },";
            } else {
                $items[] = "    { prop: '{$f['name']}', label: '{$this->label($f)}', type: 'input' },";
            }
        }
        if ($items === []) {
            return '';
        }

        return "  search: [\n" . implode("\n", $items) . "\n  ],\n";
    }

    /**
     * 列：非树自动补 id 首列 + created_at 时间尾列；字段列按 front.column 合并约定。
     */
    private function columnsBlock(): string
    {
        $m       = $this->meta;
        $entries = [];
        if (!$m->isTree) {
            $entries[] = $this->entry([['prop', "'id'"], ['label', "'ID'"], ['width', '70']]);
        }
        foreach ($this->orderedFields((array) ($m->front['columnOrder'] ?? [])) as $f) {
            $hint = $f['front']['column'] ?? [];
            if ($hint === false || !$f['list']) {
                continue;
            }
            $hint  = (array) $hint;
            $pairs = [['prop', "'{$f['name']}'"], ['label', "'" . ($hint['label'] ?? $this->label($f)) . "'"]];

            $type = $hint['type'] ?? ($f['name'] === 'status' ? 'switch' : null);
            if ($type !== null) {
                $pairs[] = ['type', "'{$type}'"];
            }
            if (isset($hint['dict'])) {
                $pairs[] = ['dict', "'{$hint['dict']}'"];
            }
            if (isset($hint['enum'])) {
                $pairs[] = ['options', (string) $hint['enum']];
            }
            if ($type === 'switch') {
                $pairs[] = ['perm', "'{$m->permPrefix}:update'"];
                $pairs[] = ['width', (string) ($hint['width'] ?? 80)];
            } else {
                foreach (['sortable', 'width', 'minWidth', 'align', 'showOverflowTooltip'] as $k) {
                    if (isset($hint[$k])) {
                        $pairs[] = [$k, $this->tsScalar($hint[$k])];
                    }
                }
            }
            $entries[] = $this->entry($pairs);
        }
        if (!$m->isTree) {
            $entries[] = $this->entry([
                ['prop', "'created_at'"], ['label', "'创建时间'"], ['type', "'time'"],
                ['sortable', 'true'], ['width', '180'],
            ]);
        }

        return implode("\n", $entries);
    }

    private function rowActionsBlock(): string
    {
        $m       = $this->meta;
        $perm    = $m->permPrefix;
        $entries = [];

        if ($m->isTree) {
            $guard = (array) ($m->front['treeLeafGuard'] ?? []);
            $pairs = [['label', "'新增下级'"], ['emit', "'addChild'"], ['perm', "'{$perm}:create'"]];
            if ($guard !== []) {
                $pairs[] = [
                    'show', "(row) => row.{$guard['field']} !== {$this->tsScalar($guard['value'])}",
                    'comment', "// {$guard['cn']}节点是叶子，不可挂子级",
                ];
            }
            $entries[] = $this->entry($pairs);
        }

        $entries[] = $this->entry([['label', "'编辑'"], ['emit', "'edit'"], ['perm', "'{$perm}:update'"]]);

        foreach ($m->relationEndpoints as $i => $e) {
            $pairs = [
                ['label', "'分配{$e['cn']}'"],
                ['emit', "'" . $this->assignEmit($e, $i) . "'"],
                ['perm', "'{$e['perm']}'"],
            ];
            if (($p = $m->protectedRowFor('assign')) !== null) {
                $pairs[] = [
                    'show', $this->protectedShow($p),
                    'comment', "// {$p['matchValue']} 为内置保护行，后端拒绝分配（422），前端直接隐藏入口",
                ];
            }
            $entries[] = $this->entry($pairs);
        }

        $pairs = [
            ['label', "'删除'"], ['emit', "'remove'"], ['perm', "'{$perm}:delete'"],
            ['type', "'danger'"],
        ];
        $confirm = ['confirm', 'true'];
        if ($m->isTree) {
            $brief      = $m->deleteCascade !== [] ? '；删除级联清 ' . $this->cascadeBrief() . ' 策略' : '';
            $confirm[2] = 'inline';
            $confirm[3] = "// 有子节点后端拒删 422{$brief}";
        }
        $pairs[] = $confirm;
        if (($p = $m->protectedRowFor('delete')) !== null) {
            $pairs[] = ['show', $this->protectedShow($p)];
        }
        $entries[] = $this->entry($pairs);

        return implode("\n", $entries);
    }

    /**
     * 树形辅助（父级树取数）+ 声明式联动钩子（front.visibility）。
     */
    private function helperBlocks(): string
    {
        $m   = $this->meta;
        $out = '';

        if ($m->isTree) {
            $cn    = $m->moduleCn;
            $item  = $this->itemType();
            $fn    = $this->fn('tree');
            $label = $this->treeLabelField();
            $guard = (array) ($m->front['treeLeafGuard'] ?? []);
            if ($guard !== []) {
                $out .= "\n/** 父级{$cn}树：虚拟根「顶级」(id=0) + 过滤{$guard['cn']}节点（{$guard['cn']}不可作父级） */\n"
                    . "async function parentTreeData(): Promise<Row[]> {\n"
                    . "  const { data } = await {$fn}()\n"
                    . "  const strip = (nodes: {$item}[]): {$item}[] =>\n"
                    . "    nodes\n"
                    . "      .filter((n) => n.{$guard['field']} !== {$this->tsScalar($guard['value'])})\n"
                    . "      .map((n) => ({ ...n, children: n.children ? strip(n.children) : undefined }))\n"
                    . "  return [{ id: 0, {$label}: '顶级', children: strip(data) }]\n"
                    . "}\n";
            } else {
                $out .= "\n/** 父级{$cn}树：虚拟根「顶级」(id=0) */\n"
                    . "async function parentTreeData(): Promise<Row[]> {\n"
                    . "  const { data } = await {$fn}()\n"
                    . "  return [{ id: 0, {$label}: '顶级', children: data }]\n"
                    . "}\n";
            }
        }

        $vis = (array) ($m->front['visibility'] ?? []);
        if (($vis['hooks'] ?? []) !== []) {
            $out .= "\n";
            foreach ((array) ($vis['doc'] ?? []) as $line) {
                $out .= "// {$line}\n";
            }
            foreach ((array) $vis['hooks'] as $name => $h) {
                $tail = isset($h['cn']) ? " // {$h['cn']}" : '';
                $out .= "const {$name} = (form: Row) => Number(form.{$h['field']}) {$h['op']} {$this->tsScalar($h['value'])}{$tail}\n";
            }
        }

        return $out;
    }

    private function detailLine(): string
    {
        if ($this->meta->relationEndpoints === []) {
            return '';
        }

        return "  // 编辑回显走 detail 聚合：行数据没有 {$this->relationFkList()}（分配关系回显需要）\n"
            . '  detail: ' . $this->fn('get') . ",\n";
    }

    /**
     * 编辑表单项：约定推导 + front.form 覆盖；front.formManualSlots 注入 TODO 手工槽。
     */
    private function formItemsBlock(): string
    {
        $m       = $this->meta;
        $slots   = [];
        foreach ((array) ($m->front['formManualSlots'] ?? []) as $s) {
            $slots[(string) $s['after']][] = (string) $s['note'];
        }

        $entries = [];
        if ($m->isTree) {
            // 父级 treeSelect（独立勾选 + 虚拟根，复刻 D0 menu）
            $entries[] = $this->entry([
                ['prop', "'{$m->parentField}'"], ['label', "'父级{$m->moduleCn}'"], ['type', "'treeSelect'"],
                ['checkStrictly', 'true'], ['treeData', 'parentTreeData'], ['defaultValue', '0'],
            ]);
        }

        foreach ($this->orderedFields((array) ($m->front['formOrder'] ?? [])) as $f) {
            if ($f['name'] === $m->parentField && $m->isTree) {
                continue; // 已按树形约定生成
            }
            $hint = $f['front']['form'] ?? [];
            if ($hint === false) {
                continue;
            }
            $hint  = (array) $hint;
            $type  = (string) ($hint['type'] ?? $this->deriveFormType($f));
            $pairs = [
                ['prop', "'{$f['name']}'"],
                ['label', "'" . ($hint['label'] ?? $this->label($f)) . "'"],
                ['type', "'{$type}'"],
            ];
            if (isset($hint['dict'])) {
                $pairs[] = ['dict', "'{$hint['dict']}'"];
            }
            if (isset($hint['enum'])) {
                $pairs[] = ['options', (string) $hint['enum']];
            }
            if ((bool) ($hint['required'] ?? $f['create_required'])) {
                $pairs[] = ['required', 'true'];
            }
            if ($f['name'] === $m->uniqueField) {
                $pairs[] = ['disabledOnEdit', 'true'];
            }
            if ($type === 'switch') {
                $pairs[] = ['activeValue', '1'];
                $pairs[] = ['inactiveValue', '0'];
            }
            if ($type === 'number') {
                $pairs[] = ['min', (string) ($hint['min'] ?? 0)];
            }
            if (isset($hint['defaultValue']) || $type === 'number') {
                $pairs[] = ['defaultValue', $this->tsScalar($hint['defaultValue'] ?? 0)];
            }
            if (isset($hint['visible'])) {
                $pairs[] = ['visible', (string) $hint['visible']];
            }
            if (isset($hint['tip'])) {
                $pairs[] = ['tip', "'{$hint['tip']}'"];
            }
            $entries[] = $this->entry($pairs);

            foreach ($slots[$f['name']] ?? [] as $note) {
                $entries[] = "    // TODO 手工补充复杂联动（生成器留槽）：{$note}";
            }
        }

        return implode("\n", $entries);
    }

    /**
     * 表单控件类型约定：status→switch、sort→number、长文本→textarea、其余 input。
     */
    private function deriveFormType(array $f): string
    {
        if ($f['name'] === 'status') {
            return 'switch';
        }
        if ($f['name'] === 'sort') {
            return 'number';
        }
        if (($f['length'] ?? 0) >= 255) {
            return 'textarea';
        }

        return 'input';
    }

    private function assignState(): string
    {
        if ($this->meta->relationEndpoints === []) {
            return '';
        }
        $M = $this->meta->ModuleName;

        return "\nconst assignVisible = ref(false)\n"
            . "const assign{$M}Id = ref(0)\n"
            . "const assign{$M}Name = ref('')\n";
    }

    private function onActionBranches(): string
    {
        $m   = $this->meta;
        $out = '';
        if ($m->isTree) {
            $out .= "  } else if (name === 'addChild' && row) {\n"
                . "    // 行内「新增下级」：预置 {$m->parentField} 的 create 场景\n"
                . "    drawerRef.value?.open('create', { {$m->parentField}: row.id })\n";
        }
        $out .= "  } else if (name === 'edit' && row) {\n"
            . "    drawerRef.value?.open('update', row)\n";
        foreach ($m->relationEndpoints as $i => $e) {
            $M       = $m->ModuleName;
            $display = (string) ($m->front['displayField'] ?? 'name');
            $out .= "  } else if (name === '{$this->assignEmit($e, $i)}' && row) {\n"
                . "    assign{$M}Id.value = Number(row.id)\n"
                . "    assign{$M}Name.value = String(row.{$display})\n"
                . "    assignVisible.value = true\n";
        }

        return $out;
    }

    private function assignDialogTag(): string
    {
        $out = '';
        foreach ($this->meta->relationEndpoints as $e) {
            $kebab = $this->kebab($this->meta->moduleName);
            $out .= "\n  <{$this->dialogName($e)} v-model=\"assignVisible\""
                . " :{$kebab}-id=\"assign{$this->meta->ModuleName}Id\""
                . " :{$kebab}-name=\"assign{$this->meta->ModuleName}Name\" />\n";
        }

        return $out;
    }

    // ============================== 分配弹窗 ==============================

    /**
     * @param array<string,mixed> $e
     */
    private function assignDialog(array $e): string
    {
        $m       = $this->meta;
        $casbin  = (bool) $e['casbinSync']['enabled'];
        $target  = (string) $e['targetModel'];
        $fkCamel = lcfirst(ModuleMeta::studly($e['targetFk']));
        $deny    = $m->protectedRowFor('assign');

        $watchDoc = "// 打开时拉全量{$e['cn']}树 + 已分配 {$fkCamel}s 回显勾选。\n"
            . "// check-strictly 独立勾选语义：提交精确 id 集合、不做父子级联；";
        if ($casbin) {
            $watchDoc .= "\n// 后端 profile 取数时自动补全祖先目录保证树连通（基线 §7），二者配合自洽。";
        }

        $catch = $deny !== null
            ? "// 非法 {$e['targetFk']} / {$deny['matchValue']} 等 422 已由拦截器提示，后端整单回滚不留半套策略"
            : "// 非法 {$e['targetFk']} 422 已由拦截器提示，后端整单回滚不留半套策略";

        $relImports = [$this->relReadFn($e), $this->relWriteFn($e)];
        sort($relImports);

        return $this->renderer->render('frontend/assign-dialog.vue', [
            'dialogMission'     => "分配{$e['cn']}弹窗（bx:make 生成：ElTree 独立勾选，GET 回显 + PUT 覆盖提交）",
            'dateDay'           => $this->dateDay,
            'targetTreeFn'      => 'get' . $target . 'Tree',
            'targetItemType'    => $target . 'Item',
            'targetApiModule'   => lcfirst($target),
            'relationFnImports' => implode(', ', $relImports),
            'moduleName'        => $m->moduleName,
            'successDoc'        => $casbin
                ? '/** 分配成功（Casbin 已同步，换登录可验 enforce 变化） */'
                : '/** 分配成功 */',
            'targetTreeVar'     => lcfirst($target) . 'Tree',
            'watchDoc'          => $watchDoc,
            'readFn'            => $this->relReadFn($e),
            'writeFn'           => $this->relWriteFn($e),
            'catchComment'      => $catch,
            'relationCn'        => $e['cn'],
            'treeLabel'         => (string) (($e['front']['treeLabel'] ?? null) ?: 'title'),
        ]);
    }

    // ============================== 工具 ==============================

    /**
     * config 条目渲染：含注释或显示宽度（CJK 记 2）> 112 → 逐键多行（对齐 D0 手写排版）。
     *
     * @param array<int,array> $pairs 每项 [key, value] 或 [key, value, 'comment'|'inline', text]
     */
    private function entry(array $pairs, int $indent = 4): string
    {
        $pad        = str_repeat(' ', $indent);
        $hasComment = false;
        $inlineKv   = [];
        foreach ($pairs as $p) {
            if (($p[2] ?? '') !== '') {
                $hasComment = true;
            }
            $inlineKv[] = "{$p[0]}: {$p[1]}";
        }
        $single = $pad . '{ ' . implode(', ', $inlineKv) . ' },';
        if (!$hasComment && mb_strwidth($single, 'UTF-8') <= 112) {
            return $single;
        }

        $out = $pad . "{\n";
        foreach ($pairs as $p) {
            if (($p[2] ?? '') === 'comment') {
                $out .= "{$pad}  {$p[3]}\n";
            }
            $out .= "{$pad}  {$p[0]}: {$p[1]},";
            if (($p[2] ?? '') === 'inline') {
                $out .= " {$p[3]}";
            }
            $out .= "\n";
        }

        return $out . $pad . '},';
    }

    /**
     * 字段中文标签：front.label > 列注释截断（到第一个标点/空格）> 字段名。
     */
    private function label(array $f): string
    {
        $front = (array) ($f['front'] ?? []);
        if (isset($front['label'])) {
            return (string) $front['label'];
        }
        $comment = (string) $f['comment'];
        if ($comment === '') {
            return $f['name'];
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

    /**
     * @return array<string,mixed>|null [常量名, 值]（受保护行用于 show 闭包时才生成）
     */
    private function protectedConst(): ?array
    {
        $p = $this->meta->protectedRowFor('delete') ?? $this->meta->protectedRowFor('assign');
        if ($p === null) {
            return null;
        }
        $head = strtoupper(explode('_', (string) $p['matchValue'])[0]);

        return [$head . '_' . strtoupper((string) $p['matchField']), (string) $p['matchValue']];
    }

    private function protectedShow(array $p): string
    {
        [$const] = $this->protectedConst();

        return "(row) => row.{$p['matchField']} !== {$const}";
    }

    /**
     * @return array<string,mixed>
     */
    private function enums(): array
    {
        return (array) ($this->meta->front['enums'] ?? []);
    }

    /**
     * 字段按声明顺序重排（front.columnOrder / formOrder），未列出的字段按表序追加在后。
     *
     * @return array<int,array<string,mixed>>
     */
    private function orderedFields(array $order): array
    {
        if ($order === []) {
            return $this->meta->fields;
        }
        $byName = [];
        foreach ($this->meta->fields as $f) {
            $byName[$f['name']] = $f;
        }
        $out = [];
        foreach ($order as $name) {
            if (isset($byName[$name])) {
                $out[] = $byName[$name];
                unset($byName[$name]);
            }
        }
        foreach ($byName as $f) {
            $out[] = $f;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|array{} 字段级 front 子段（false 原样返回由调用方判跳过）
     */
    private function fieldFront(array $f, string $section)
    {
        return ($f['front'] ?? [])[$section] ?? [];
    }

    private function tsDocFor(string $name): ?string
    {
        foreach ($this->meta->fields as $f) {
            if ($f['name'] !== $name) {
                continue;
            }
            $doc = ($f['front'] ?? [])['tsDoc'] ?? null;
            if ($doc === true) {
                return (string) $f['comment'];
            }

            return $doc !== null ? (string) $doc : null;
        }

        return null;
    }

    private function tsType(array $col): string
    {
        if (in_array($col['data_type'], ['date', 'datetime', 'timestamp'], true)) {
            return $col['nullable'] ? 'string | null' : 'string';
        }

        return $col['php_type'] === 'string' ? 'string' : 'number';
    }

    private function tsScalar($v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_string($v)) {
            return "'{$v}'";
        }

        return (string) $v;
    }

    private function itemType(): string
    {
        return $this->meta->ModuleName . 'Item';
    }

    private function viewDir(): string
    {
        return (string) ($this->meta->front['viewDir'] ?? str_replace(':', '/', $this->meta->permPrefix));
    }

    /**
     * api 函数名约定（与 D0 手写 api 文件逐字对齐）。
     */
    private function fn(string $kind): string
    {
        $M = $this->meta->ModuleName;

        return match ($kind) {
            'list'   => 'list' . ModuleMeta::studly($this->meta->modulePlural),
            'tree'   => "get{$M}Tree",
            'get'    => "get{$M}",
            'create' => "create{$M}",
            'update' => "update{$M}",
            'delete' => "delete{$M}",
            'status' => "set{$M}Status",
        };
    }

    private function relReadFn(array $e): string
    {
        return 'get' . $this->meta->ModuleName . $e['targetModel'] . 'Ids';
    }

    private function relWriteFn(array $e): string
    {
        return 'assign' . $this->meta->ModuleName . ModuleMeta::studly($e['name']);
    }

    private function assignEmit(array $e, int $index): string
    {
        return count($this->meta->relationEndpoints) === 1 || $index === 0
            ? 'assign'
            : 'assign' . ModuleMeta::studly($e['name']);
    }

    private function dialogName(array $e): string
    {
        return 'Assign' . $e['targetModel'] . 'Dialog';
    }

    private function relationFkList(): string
    {
        return implode('/', array_map(static fn ($e) => $e['targetFk'] . 's', $this->meta->relationEndpoints));
    }

    private function treeLabelField(): string
    {
        if (isset($this->meta->front['treeLabel'])) {
            return (string) $this->meta->front['treeLabel'];
        }
        foreach ($this->meta->fields as $f) {
            if ($f['name'] === 'title') {
                return 'title';
            }
        }

        return 'name';
    }

    private function kebab(string $name): string
    {
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name));
    }
}

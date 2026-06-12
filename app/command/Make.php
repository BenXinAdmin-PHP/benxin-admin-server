<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — CLI 入口（php think bx:make）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// | @updated   2026-06-12 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\command;

use generator\FrontendGenerator;
use generator\Generator;
use generator\ModuleMeta;
use generator\StubRenderer;
use generator\TableReader;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 代码生成器命令：依据表结构 + 模块元数据，复刻后端四件套 + 路由/种子片段，
 * 以及前端产物（M3-D1：列表页 + 编辑表单 + 分配弹窗 + api 薄壳，复刻 web 仓 D0 黄金样板）。
 *
 *   php think bx:make bx_post --config=extend/generator/configs/post.php --output=runtime/generated/post --dry-run
 *
 * 选项：
 *   --config  模块元数据 PHP 文件（return array）；缺省按表结构推导
 *   --output  输出根目录（验收时指向独立目录做 diff）；缺省落地到真实 app 路径
 *   --force   覆盖已存在文件（缺省跳过并提示）
 *   --dry-run 只打印将生成的文件清单，不落地
 */
class Make extends Command
{
    protected function configure(): void
    {
        $this->setName('bx:make')
            ->addArgument('table', Argument::REQUIRED, '目标表名（如 bx_post），或带前缀/不带前缀均可')
            ->addOption('config', null, Option::VALUE_REQUIRED, '模块元数据 PHP 文件路径（return array）')
            ->addOption('output', null, Option::VALUE_REQUIRED, '输出根目录（验收做 diff 用）')
            ->addOption('name', null, Option::VALUE_REQUIRED, '模块名 PascalCase（覆盖推导，如 Post）')
            ->addOption('cn', null, Option::VALUE_REQUIRED, '模块中文名（覆盖表注释推导）')
            ->addOption('perm', null, Option::VALUE_REQUIRED, 'perm 前缀（如 system:post）')
            ->addOption('force', null, Option::VALUE_NONE, '强制覆盖已存在文件')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只列清单不落地')
            ->setDescription('BenXinAdmin 代码生成器：表结构 → 后端四件套 + 路由/种子片段 + 前端列表/表单/分配弹窗/api 薄壳');
    }

    protected function execute(Input $input, Output $output): int
    {
        $table = $this->normalizeTable((string) $input->getArgument('table'));

        $reader = new TableReader($table);
        if (!$reader->exists()) {
            $output->error("表不存在：{$table}（库：" . Db::connect()->getConfig('database') . '）');
            return 1;
        }

        // 元数据：配置文件 + CLI 选项覆盖
        $config = [];
        if ($configPath = $input->getOption('config')) {
            $abs = $this->absPath((string) $configPath);
            if (!is_file($abs)) {
                $output->error("配置文件不存在：{$abs}");
                return 1;
            }
            $config = (array) require $abs;
        }
        $options = array_filter([
            'name' => $input->getOption('name'),
            'cn'   => $input->getOption('cn'),
            'perm' => $input->getOption('perm'),
        ], static fn ($v) => $v !== null && $v !== '');

        $meta = ModuleMeta::build($reader, $table, $config, $options);

        $output->info("模块：{$meta->ModuleName}（{$meta->moduleCn}）｜表：{$meta->table}｜perm：{$meta->permPrefix}");
        $output->writeln('业务字段：' . implode(', ', array_map(static fn ($f) => $f['name'], $meta->fields)));
        $output->writeln('唯一字段：' . ($meta->uniqueField ?? '无') . '｜含 status：' . ($meta->hasStatus ? '是' : '否'));
        $output->writeln('树形：' . ($meta->isTree
            ? "是（parentField={$meta->parentField}｜sortField={$meta->sortField}｜子树策略={$meta->subtreeStrategy}）"
            : '否'));

        // 授权链路（M3-C）：分配接口 / 绑定拒删 / 级联清理 / 受保护行
        if ($meta->hasAuthChain()) {
            $parts = [];
            foreach ($meta->relationEndpoints as $e) {
                $assign  = 'assign' . ModuleMeta::studly($e['name']);
                $parts[] = "分配接口 GET|PUT {$meta->modulePlural}/:id/{$e['name']}（{$assign}，perm={$e['perm']}）";
            }
            if ($meta->deleteBindingGuards !== []) {
                $parts[] = '绑定拒删 ' . count($meta->deleteBindingGuards) . ' 条';
            }
            if ($meta->deleteCascade !== []) {
                $parts[] = '删除级联 ' . count($meta->deleteCascade) . ' 表';
            }
            if ($meta->protectedRows !== []) {
                $parts[] = '受保护行 ' . count($meta->protectedRows) . ' 条';
            }
            $output->writeln('授权链路：' . implode('｜', $parts));
        } else {
            $output->writeln('授权链路：无');
        }

        // 渲染：后端四件套 + 路由/种子片段 + 前端产物（M3-D1：列表页/编辑表单/分配弹窗/api 薄壳）
        $renderer  = new StubRenderer($this->app->getRootPath() . 'extend/generator/stubs');
        $now       = date('Y-m-d H:i:s');
        $generator = new Generator($meta, $renderer, $now);
        $frontend  = new FrontendGenerator($meta, $renderer, $now);
        $files     = $this->resolvePaths(
            $generator->generate() + $frontend->generate(),
            (string) ($input->getOption('output') ?? ''),
        );

        // dry-run：只列清单
        if ($input->getOption('dry-run')) {
            $output->writeln('');
            $output->comment('[dry-run] 将生成以下文件（不落地）：');
            foreach ($files as $rel => $_) {
                $output->writeln('  - ' . $rel);
            }
            return 0;
        }

        // 落地（防覆盖）
        $force = (bool) $input->getOption('force');
        $output->writeln('');
        foreach ($files as $rel => $content) {
            $abs = $this->app->getRootPath() . $rel;
            if (is_file($abs) && !$force) {
                $output->warning("跳过（已存在，加 --force 覆盖）：{$rel}");
                continue;
            }
            $dir = dirname($abs);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($abs, $content);
            $output->writeln('  <info>✓</info> ' . $rel . (is_file($abs) && $force ? ' (force)' : ''));
        }

        $output->writeln('');
        $output->comment('路由/种子为【片段】，不会自动改既有 v1.php/seeder：');
        $output->writeln('  · 路由片段并入 app/admin/route/v1.php「系统管理 CRUD」分组（具体 action > /:id > 集合）');
        $output->writeln('  · 种子片段复制到 database/seeds/ 后 `php think seed:run -s ' . $meta->ModuleName . 'MenuSeeder`');
        $output->writeln('  · 前端产物（frontend/src/…）不自动写 web 仓：人工 copy api/<module>.ts 与 views 页面，');
        $output->writeln('    动态路由由 import.meta.glob 自动接管，新建 .vue 即生效');

        return 0;
    }

    /**
     * 规范化表名：补表前缀（不带前缀时）。
     */
    private function normalizeTable(string $table): string
    {
        $prefix = (string) (config('database.connections.' . config('database.default') . '.prefix') ?? '');
        if ($prefix !== '' && !str_starts_with($table, $prefix)) {
            return $prefix . $table;
        }

        return $table;
    }

    /**
     * 解析产物落地路径。
     * --output 给定：六件全部落到 output/<相对子路径>（验收 diff 用）。
     * 缺省：四件套落真实 app 路径；路由/种子片段落 extend/generator/output/（不自动并入）。
     *
     * @param array<string,string> $files
     * @return array<string,string>
     */
    private function resolvePaths(array $files, string $output): array
    {
        $resolved = [];
        foreach ($files as $rel => $content) {
            if ($output !== '') {
                $base = trim(str_replace('\\', '/', $output), '/');
                $resolved[$base . '/' . $rel] = $content;
                continue;
            }
            if (str_starts_with($rel, 'route/') || str_starts_with($rel, 'seeder/')) {
                $resolved['extend/generator/output/' . basename($rel)] = $content;
            } elseif (str_starts_with($rel, 'frontend/')) {
                // 前端产物不自动写 web 仓（跨仓，人工 copy），默认落生成器 output 目录
                $resolved['extend/generator/output/' . $rel] = $content;
            } else {
                $resolved[$rel] = $content;
            }
        }

        return $resolved;
    }

    private function absPath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->app->getRootPath() . ltrim($path, '/');
    }
}

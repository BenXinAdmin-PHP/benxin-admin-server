<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   测试 — M6-B 页面 schema 模型离线自测（校验/渲染/slug唯一，真实 DB）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-17 10:00:00
// +----------------------------------------------------------------------
//
// 运行：php tests/m6b_page_mock.php （依赖已 migrate + HomePageSeeder 灌入 slug=home）
// 覆盖：validateBlocks（合法/缺必填/非法 type/i18n 形状/数组空）+ renderBySlug（zh/en/归一/
//      字段白名单/404）+ slug 唯一含软删（create 撞 home → 422）。

declare(strict_types=1);

use app\common\exception\BusinessException;
use app\common\library\ErrorCode;
use app\common\service\PageService;

require __DIR__ . '/../vendor/autoload.php';

$app = new \think\App();
$app->initialize();

$svc  = new PageService($app);
$pass = 0;
$fail = 0;

function ok(bool $cond, string $name): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  ✓ {$name}\n";
    } else {
        $fail++;
        echo "  ✗ {$name}\n";
    }
}

/** 断言 validateBlocks 抛 422 且 msg 含期望片段 */
function expectInvalid(PageService $svc, mixed $blocks, string $name, string $msgContains = ''): void
{
    try {
        $svc->validateBlocks($blocks);
        ok(false, "{$name}（应拒未拒）");
    } catch (BusinessException $e) {
        $okCode = $e->bizCode === ErrorCode::VALIDATE_FAIL;
        $okMsg  = $msgContains === '' || str_contains($e->getMessage(), $msgContains);
        ok($okCode && $okMsg, "{$name} → 422「{$e->getMessage()}」");
    }
}

echo "== 1) validateBlocks 合法样例 ==\n";
$valid = [
    ['type' => 'prose', 'title' => ['zh' => '标题', 'en' => 'Title'], 'body' => ['zh' => '正文', 'en' => 'Body']],
    ['type' => 'hero',
        'eyebrow' => ['zh' => 'A'], 'title' => ['zh' => 'B'], 'subtitle' => ['zh' => 'C'],
        'ctaPrimary' => ['text' => ['zh' => '看'], 'href' => '#a'],
        'ctaSecondary' => ['text' => ['zh' => '始'], 'href' => '#b']],
    ['type' => 'security', 'title' => ['zh' => 'S'], 'body' => ['zh' => 'b'], 'chips' => [['zh' => 'x'], ['zh' => 'y']]],
    ['type' => 'feature-grid', 'title' => ['zh' => 'F'], 'items' => [['title' => ['zh' => 't'], 'desc' => ['zh' => 'd']]]],
    ['type' => 'badge-list', 'title' => ['zh' => 'B'], 'items' => [['label' => 'PHP']]],
];
try {
    $svc->validateBlocks($valid);
    ok(true, '合法 blocks 通过');
} catch (BusinessException $e) {
    ok(false, '合法 blocks 误拒：' . $e->getMessage());
}
// 未知字段宽容忽略
try {
    $svc->validateBlocks([['type' => 'prose', 'title' => ['zh' => 't'], 'body' => ['zh' => 'b'], 'extraFoo' => 'bar']]);
    ok(true, '未知字段宽容忽略');
} catch (BusinessException $e) {
    ok(false, '未知字段误拒：' . $e->getMessage());
}

echo "== 2) validateBlocks 拒脏数据 ==\n";
expectInvalid($svc, 'notarray', '非数组 blocks');
expectInvalid($svc, [['type' => 'nope', 'title' => ['zh' => 'x']]], '非法 type', 'type');
expectInvalid($svc, [['type' => 'prose', 'body' => ['zh' => 'b']]], 'prose 缺 title', 'blocks[0](prose).title');
expectInvalid($svc, [['type' => 'prose', 'title' => 'flat', 'body' => ['zh' => 'b']]], 'i18n 非对象', 'title');
expectInvalid($svc, [['type' => 'prose', 'title' => ['en' => 'only-en'], 'body' => ['zh' => 'b']]], 'i18n 缺 zh', '.zh');
expectInvalid($svc, [['type' => 'prose', 'title' => ['zh' => '  '], 'body' => ['zh' => 'b']]], 'zh 空白', '.zh');
expectInvalid($svc, [['type' => 'security', 'title' => ['zh' => 's'], 'body' => ['zh' => 'b'], 'chips' => []]], 'chips 空数组', 'chips');
expectInvalid($svc, [['type' => 'feature-grid', 'title' => ['zh' => 'f'], 'items' => [['title' => ['zh' => 't']]]]], 'item 缺 desc', 'items[0].desc');
expectInvalid($svc, [['type' => 'hero', 'eyebrow' => ['zh' => 'a'], 'title' => ['zh' => 'b'], 'subtitle' => ['zh' => 'c'], 'ctaSecondary' => ['text' => ['zh' => 's']]]], 'hero 缺 ctaPrimary', 'ctaPrimary');
expectInvalid($svc, [['type' => 'badge-list', 'title' => ['zh' => 'b'], 'items' => [['label' => '']]]], 'badge label 空', 'label');

echo "== 3) renderBySlug 渲染 + 字段白名单 ==\n";
$zh = $svc->renderBySlug('home', 'zh');
ok(($zh['slug'] ?? '') === 'home', 'home zh: slug=home');
ok(is_array($zh['blocks'] ?? null) && count($zh['blocks']) === 8, 'home zh: 8 区块');
$hero = $zh['blocks'][0];
ok(($hero['type'] ?? '') === 'hero', '首块 type=hero');
ok(is_string($hero['title'] ?? null) && str_contains($hero['title'], '开源底座'), 'hero.title 解析为中文字符串');
ok(is_string($hero['ctaPrimary']['text'] ?? null) && $hero['ctaPrimary']['text'] === '在 GitHub 上查看', '嵌套 object i18n 解析');
ok(($hero['ctaPrimary']['href'] ?? '') === 'https://github.com/BenXinAdmin-PHP/benxin-admin-server', '非 i18n href 透传');
$sec = null;
foreach ($zh['blocks'] as $b) {
    if ($b['type'] === 'security') {
        $sec = $b;
    }
}
ok(is_array($sec['chips'] ?? null) && is_string($sec['chips'][0]) && $sec['chips'][0] === '双 guard 认证隔离', 'arrayOfI18n chips 解析为字符串数组');
// 字段白名单：整页与块内不得含内部字段
$flat = json_encode($zh, JSON_UNESCAPED_UNICODE);
ok(!str_contains($flat, 'tenant_id') && !str_contains($flat, 'deleted_at')
    && !str_contains($flat, 'create_by') && !str_contains($flat, 'create_dept'),
    '字段白名单：无 tenant_id/deleted_at/create_by/create_dept');
ok(!array_key_exists('id', $zh) && !array_key_exists('status', $zh), '渲染不外露 id/status');

echo "== 4) lang 解析与归一 ==\n";
$en = $svc->renderBySlug('home', 'en');
ok(str_contains($en['blocks'][0]['title'], 'production-ready'), 'home en: hero.title 英文');
$xx = $svc->renderBySlug('home', 'fr-x');
ok(str_contains($xx['blocks'][0]['title'], '开源底座'), '非法 lang 归一回退 zh');

echo "== 5) renderBySlug 404 ==\n";
try {
    $svc->renderBySlug('no-such-page', 'zh');
    ok(false, '不存在 slug 应 404 未抛');
} catch (BusinessException $e) {
    ok($e->bizCode === ErrorCode::NOT_FOUND, '不存在 slug → 404000');
}

echo "== 6) slug 唯一含软删 ==\n";
try {
    $svc->create(['slug' => 'home', 'title' => '撞名', 'status' => 1, 'blocks' => [['type' => 'prose', 'title' => ['zh' => 't'], 'body' => ['zh' => 'b']]]]);
    ok(false, '撞 slug=home 应 422 未拒');
} catch (BusinessException $e) {
    ok(str_contains($e->getMessage(), '已存在'), 'create 撞 home → 422「' . $e->getMessage() . '」');
}

echo "\n结果：{$pass} 通过 / {$fail} 失败\n";
exit($fail === 0 ? 0 : 1);

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   离线 mock — M-素材-B 多路存储路由/签名URL/回退/COS（不连真实云）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 16:00:00
// +----------------------------------------------------------------------
//
// 用法：php tests/mb_storage_mock.php
// 验：forMediaType 真路由（image→qiniu/document→oss/video→local）、未开通回退 local、
//     签名 URL 实时签发（fake driver）、raw 云 302、COS 扩展位 throw、upload 云分支落库。
// fake driver 为进程内静态注入，故须 CLI 内验（运行的 8801 server 看不到 fake）。

require __DIR__ . '/../vendor/autoload.php';

use app\admin\service\ResourceService;
use app\common\library\BxCache;
use app\common\library\storage\LocalStorage;
use app\common\library\storage\StorageInterface;
use app\common\library\storage\StorageManager;
use app\common\model\Resource;
use think\facade\Db;

$app = new \think\App();
$app->initialize();

$pass = 0;
$fail = 0;
function check(string $label, bool $ok): void
{
    global $pass, $fail;
    echo ($ok ? '  ✅ ' : '  ❌ ') . $label . PHP_EOL;
    $ok ? $pass++ : $fail++;
}

/** 进程内伪驱动：记录调用、返回可识别的假签名 URL（不真连云）。 */
class FakeCloudStorage implements StorageInterface
{
    /** @var array<int,array{0:string,1:string}> */
    public array $calls = [];

    public function __construct(public string $tag)
    {
    }

    public function put(string $tmpPath, string $saveName): string
    {
        $key          = ltrim(str_replace('\\', '/', $saveName), '/');
        $this->calls[] = ['put', $key];

        return $key;
    }

    public function url(string $path): string
    {
        $this->calls[] = ['url', $path];

        return "https://fake-{$this->tag}.example/{$path}?sign=ABC&e=9999999999";
    }

    public function delete(string $path): bool
    {
        $this->calls[] = ['delete', $path];

        return true;
    }
}

// 配置改值小工具（直改 DB + 清聚合缓存，模拟后台开通/切换）
function setCfg(string $key, string $value): void
{
    Db::name('config')->where(['group' => 'storage', 'key' => $key])->update(['value' => $value]);
    BxCache::forget('config:all');
}

echo "=== T1 默认态：云未开通 → 全部 local（守 §1）===\n";
// 还原确保默认
setCfg('storage_driver_image', 'local');
setCfg('storage_driver_document', 'local');
setCfg('storage_driver_archive', 'local');
setCfg('qiniu_bucket', '');
setCfg('qiniu_domain', '');
setCfg('oss_endpoint', '');
setCfg('oss_bucket', '');
foreach (['image', 'document', 'archive', 'video', 'audio'] as $mt) {
    check("driverNameForMediaType($mt)=local", StorageManager::driverNameForMediaType($mt) === 'local');
}
check('forMediaType(image) instanceof LocalStorage', StorageManager::forMediaType('image') instanceof LocalStorage);

echo "=== T2 选了云但配置不全 → 回退 local + warning ===\n";
setCfg('storage_driver_image', 'qiniu'); // 选七牛，但 bucket/domain 仍空
check('driverNameForMediaType(image)=local（未开通回退）', StorageManager::driverNameForMediaType('image') === 'local');

echo "=== T3 云开通 + fake 注入 → 真路由 ===\n";
setCfg('qiniu_bucket', 'test-bucket');
setCfg('qiniu_domain', 'cdn.qiniu.test');
setCfg('storage_driver_document', 'oss');
setCfg('oss_endpoint', 'oss-cn-hangzhou.aliyuncs.com');
setCfg('oss_bucket', 'test-oss-bucket');
$fakeQ = new FakeCloudStorage('qiniu');
$fakeO = new FakeCloudStorage('oss');
StorageManager::fake('qiniu', $fakeQ);
StorageManager::fake('oss', $fakeO);
check('driverNameForMediaType(image)=qiniu', StorageManager::driverNameForMediaType('image') === 'qiniu');
check('driverNameForMediaType(document)=oss', StorageManager::driverNameForMediaType('document') === 'oss');
check('driverNameForMediaType(video)=local（B 步音视频不上云）', StorageManager::driverNameForMediaType('video') === 'local');
check('forMediaType(image) === fakeQiniu', StorageManager::forMediaType('image') === $fakeQ);
check('forMediaType(document) === fakeOss', StorageManager::forMediaType('document') === $fakeO);
// put 路由
$key = StorageManager::forMediaType('image')->put('/tmp/none', 'resources/image/2026/06/a.png');
check('qiniu put 被调用并返回 key', $key === 'resources/image/2026/06/a.png' && $fakeQ->calls[0] === ['put', 'resources/image/2026/06/a.png']);

echo "=== T4 取数：url 实时签名 + raw 云 302 ===\n";
$row = Resource::create([
    'tenant_id' => 0, 'category_id' => 0, 'name' => 'mock图', 'media_type' => 'image',
    'storage' => 'qiniu', 'path' => 'resources/image/2026/06/a.png', 'url' => 'resources/image/2026/06/a.png',
    'file_name' => 'a.png', 'original_name' => 'a.png', 'ext' => 'png', 'mime' => 'image/png',
    'size' => 10, 'hash' => 'x', 'transcode_status' => 0,
]);
$svc    = new ResourceService($app);
$detail = $svc->detail((int) $row->id)->toArray();
check('detail url=七牛签名URL', str_contains((string) $detail['url'], 'fake-qiniu.example'));
$list = $svc->list(['media_type' => 'image'], 1, 10);
$found = false;
foreach ($list['list'] as $r) {
    if ((int) $r['id'] === (int) $row->id && str_contains((string) $r['url'], 'fake-qiniu.example')) {
        $found = true;
    }
}
check('list url=七牛签名URL', $found);
$raw = $svc->rawTarget((int) $row->id);
check('rawTarget 云=redirect + 签名URL', ($raw['type'] ?? '') === 'redirect' && str_contains((string) ($raw['url'] ?? ''), 'fake-qiniu.example'));

// local 行
$rowLocal = Resource::create([
    'tenant_id' => 0, 'category_id' => 0, 'name' => 'mock本地', 'media_type' => 'document',
    'storage' => 'local', 'path' => 'resources/document/2026/06/x.pdf', 'url' => '/admin/v1/resources/0/raw',
    'file_name' => 'x.pdf', 'original_name' => 'x.pdf', 'ext' => 'pdf', 'mime' => 'application/pdf',
    'size' => 10, 'hash' => 'y', 'transcode_status' => 0,
]);
$detailLocal = $svc->detail((int) $rowLocal->id)->toArray();
check('local detail url=raw 代理路由', $detailLocal['url'] === '/admin/v1/resources/' . $rowLocal->id . '/raw');

echo "=== T5 腾讯 COS 扩展位 throw（不写实现）===\n";
$thrown = false;
try {
    StorageManager::makeByName('cos');
} catch (\Throwable $e) {
    $thrown = str_contains($e->getMessage(), 'COS');
}
check('makeByName(cos) throw 未实现', $thrown);

echo "=== T6 upload() 云分支落库（fake 七牛，不真连）===\n";
try {
    // 造一个真 png 临时文件
    $tmp = sys_get_temp_dir() . '/mb_mock_' . uniqid() . '.png';
    file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'));
    $uploaded = new \think\file\UploadedFile($tmp, 'shot.png', 'image/png');
    $res      = $svc->upload($uploaded, 0);
    check('upload media_type=image', ($res['media_type'] ?? '') === 'image');
    check('upload storage=qiniu（路由到七牛）', ($res['storage'] ?? '') === 'qiniu');
    check('upload 响应 url=七牛签名URL', str_contains((string) ($res['url'] ?? ''), 'fake-qiniu.example'));
    $dbRow = Resource::find((int) $res['id']);
    check('落库 storage=qiniu & url 列存 key（稳定标识非签名URL）',
        (string) $dbRow->storage === 'qiniu' && !str_contains((string) $dbRow->getData('url'), 'fake-qiniu'));
    @unlink($tmp);
} catch (\Throwable $e) {
    check('upload() 云分支（异常：' . $e->getMessage() . '）', false);
}

echo "=== 清理：flushFakes + 硬删 mock 行 + 还原配置 ===\n";
StorageManager::flushFakes();
Db::name('resource')->where('hash', 'in', ['x', 'y'])->delete(true);
Db::name('resource')->where('name', 'shot.png')->delete(true);
setCfg('storage_driver_image', 'local');
setCfg('storage_driver_document', 'local');
setCfg('storage_driver_archive', 'local');
setCfg('qiniu_bucket', '');
setCfg('qiniu_domain', '');
setCfg('oss_endpoint', '');
setCfg('oss_bucket', '');
$resid = (int) Db::name('resource')->whereIn('hash', ['x', 'y'])->count();
echo "残留 mock 行：{$resid}\n";

echo str_repeat('-', 50) . PHP_EOL;
echo "通过 {$pass} / 失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);

<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   离线 mock — M-素材-C VOD 路由/上传签名对拍/转码回调验签幂等/扩展位（不连真实云）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-15 18:30:00
// +----------------------------------------------------------------------
//
// 用法：php tests/mc_vod_mock.php
// 验：① video/audio 默认 local + 未开通回退 local（守 §1）；② 上传签名 HMAC-SHA1 自洽对拍
//     （复现腾讯服务端验签逻辑）；③ 转码回调验签(HMAC-SHA256)+解析+幂等+状态机迁移(1→2/3/4)
//     +验签失败拒绝；④ forMediaType(video)=VodTxStorage + put throw + delete→DeleteMedia；
//     ⑤ 阿里 VOD 扩展位 throw；⑥ upload() 对 VOD 媒体类型引导直传；⑦ vodUploadSign/vodConfirm。
// fake driver / 测试配置为进程内/库内临时态，CLI 内验，跑完还原。

require __DIR__ . '/../vendor/autoload.php';

use app\admin\service\ResourceService;
use app\common\library\BxCache;
use app\common\library\ConfigCrypt;
use app\common\library\storage\StorageManager;
use app\common\library\storage\VodTxStorage;
use app\common\library\vod\VodInterface;
use app\common\library\vod\VodManager;
use app\common\library\vod\VodNotifyResult;
use app\common\model\Resource;
use app\common\model\ResourceVodNotifyLog;
use app\common\service\BxVod;
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

// 配置改值小工具（非敏感直写；敏感 AES 加密写，模拟后台开通）
function setCfg(string $key, string $value, bool $sensitive = false): void
{
    Db::name('config')->where(['group' => 'storage', 'key' => $key])
        ->update(['value' => $sensitive && $value !== '' ? ConfigCrypt::encrypt($value) : $value]);
    BxCache::forget('config:all');
}

// 测试凭证（非真实）
const T_SECRET_ID  = 'AKIDTEST_SECRET_ID_0123456789';
const T_SECRET_KEY = 'TEST_SECRET_KEY_abcdefghijklmnop';
const T_SUBAPP     = '1500000123';
const T_CALLBACK   = 'TEST_VOD_CALLBACK_KEY_xyz';

/** 进程内伪 VOD 驱动：记录 deleteMedia 调用（测删媒资路由，不真连云）。 */
class FakeVod implements VodInterface
{
    /** @var array<int,string> */
    public array $deleted = [];

    public function ready(): bool
    {
        return true;
    }

    public function signUpload(array $opts = []): array
    {
        return ['signature' => 'FAKE_SIGN', 'sub_app_id' => 1, 'procedure' => '', 'expire' => 600, 'region' => ''];
    }

    public function verifyNotify(array $headers, string $body): VodNotifyResult
    {
        return new VodNotifyResult(true);
    }

    public function deleteMedia(string $fileId): bool
    {
        $this->deleted[] = $fileId;

        return true;
    }
}

echo "=== T1 默认态：video/audio 未开通 → local（守 §1）===\n";
setCfg('storage_driver_video', 'local');
setCfg('storage_driver_audio', 'local');
setCfg('vod_tx_sub_app_id', '');
check('driverNameForMediaType(video)=local', StorageManager::driverNameForMediaType('video') === 'local');
check('driverNameForMediaType(audio)=local', StorageManager::driverNameForMediaType('audio') === 'local');

echo "=== T2 选了 vod_tx 但配置不全 → 回退 local + warning ===\n";
setCfg('storage_driver_video', 'vod_tx'); // 选 VOD，但 sub_app_id/secret 仍空
check('driverNameForMediaType(video)=local（未开通回退）', StorageManager::driverNameForMediaType('video') === 'local');

echo "=== T3 上传签名 HMAC-SHA1 对拍（复现腾讯服务端验签逻辑）===\n";
// 开通 VOD（video + audio 均切 vod_tx）
setCfg('storage_driver_video', 'vod_tx');
setCfg('storage_driver_audio', 'vod_tx');
setCfg('vod_tx_secret_id', T_SECRET_ID, true);
setCfg('vod_tx_secret_key', T_SECRET_KEY, true);
setCfg('vod_tx_sub_app_id', T_SUBAPP);
setCfg('vod_tx_callback_key', T_CALLBACK, true);
setCfg('vod_tx_procedure', ''); // 先无转码任务流
$provider = VodManager::driver('vod_tx');
check('provider.ready()=true（配置齐全）', $provider->ready() === true);
$now = time();
$sig = $provider->buildUploadSignature($now, $now + 600, 1234567);
$decoded = base64_decode($sig, true);
check('签名 base64 可解 & 长度 > 20B', is_string($decoded) && strlen($decoded) > 20);
$hmacPart   = substr((string) $decoded, 0, 20);
$original   = substr((string) $decoded, 20);
$recomputed = hash_hmac('sha1', $original, T_SECRET_KEY, true); // 腾讯服务端用账户 SecretKey 重算
check('hmac 自洽（前20B == 重算 HMAC-SHA1(原文)）—— 服务端验签通过', hash_equals($hmacPart, $recomputed));
check('原文含 secretId/currentTimeStamp/expireTime/random',
    str_contains($original, 'secretId=') && str_contains($original, 'currentTimeStamp=')
    && str_contains($original, 'expireTime=') && str_contains($original, 'random='));
check('原文含 vodSubAppId=' . T_SUBAPP, str_contains($original, 'vodSubAppId=' . T_SUBAPP));
$signResult = $provider->signUpload(['media_type' => 'video']);
check('signUpload 返回 signature/sub_app_id 结构', ($signResult['signature'] ?? '') !== '' && (int) $signResult['sub_app_id'] === (int) T_SUBAPP);

echo "=== T4 转码回调：验签 + 解析 + 幂等 + 状态机迁移 ===\n";
$bxvod = new BxVod($app);
// 配 procedure → confirm 落 transcode_status=1（待转码）
setCfg('vod_tx_procedure', 'LongVideoPreset');
$fileId = 'vodfile-mock-' . substr(sha1((string) $now), 0, 10);
$res    = (new ResourceService($app))->vodConfirm([
    'file_id' => $fileId, 'media_type' => 'video', 'name' => 'mock视频', 'url' => 'https://play.vod.test/' . $fileId . '.mp4',
]);
check('vodConfirm 落库 storage=vod_tx', ($res['storage'] ?? '') === 'vod_tx');
check('vodConfirm transcode_status=1（配了 procedure，待转码）', (int) ($res['transcode_status'] ?? -1) === 1);
$resId = (int) $res['id'];

// 构造 FINISH 成功回调报文 + 正确签名（HMAC-SHA256 over body，置于 X-Vod-Signature 头）
function vodBody(string $fileId, string $status, int $errCode, string $taskId): string
{
    return json_encode([
        'EventType' => 'ProcedureStateChanged',
        'ProcedureStateChangeEvent' => [
            'TaskId' => $taskId,
            'Status' => $status,
            'FileId' => $fileId,
            'MediaProcessResultSet' => [
                ['Type' => 'Transcode', 'TranscodeTask' => ['ErrCode' => $errCode, 'Output' => ['Url' => 'https://play.vod.test/hd/' . $fileId . '.mp4']]],
            ],
        ],
        'EventHandle' => 'handle-' . $taskId,
    ], JSON_UNESCAPED_UNICODE) ?: '{}';
}
function signedHeaders(string $body): array
{
    return ['x-vod-signature' => hash_hmac('sha256', $body, T_CALLBACK)];
}

// 4a 验签通过 + 解析（直接用 provider）
$bodyFinish = vodBody($fileId, 'FINISH', 0, 'task-001');
$parsed     = VodManager::driver('vod_tx')->verifyNotify(signedHeaders($bodyFinish), $bodyFinish);
check('verifyNotify verified=true', $parsed->verified === true);
check('解析 fileId 正确', $parsed->fileId === $fileId);
check('解析 transcode_status=3（FINISH 成功）', $parsed->transcodeStatus === 3);
check('解析 playUrl 回填', str_contains($parsed->playUrl, '/hd/'));

// 4b handleNotify 全流程：1 → 3
$ack = $bxvod->handleNotify(signedHeaders($bodyFinish), $bodyFinish);
$resAfter = Resource::find($resId);
check('handleNotify 后 transcode_status=3', (int) $resAfter->transcode_status === 3);
check('handleNotify ACK code=0', ($ack->getData()['code'] ?? null) === 0);
$logCount1 = ResourceVodNotifyLog::where('vod_media_id', $fileId)->where('idem_no', 'task-001')->count();
check('notify_log 写入 1 条 processed=1', $logCount1 === 1
    && (int) ResourceVodNotifyLog::where('idem_no', 'task-001')->value('processed') === 1);

// 4c 幂等：重复同报文 → 不重复处理、不新增 log、状态不回退
$bxvod->handleNotify(signedHeaders($bodyFinish), $bodyFinish);
$logCount2 = ResourceVodNotifyLog::where('vod_media_id', $fileId)->where('idem_no', 'task-001')->count();
check('幂等：重复回调 notify_log 仍 1 条', $logCount2 === 1);
check('幂等：终态 3 不回退', (int) Resource::find($resId)->transcode_status === 3);

// 4d 验签失败 → 拒绝 + 审计 verified=0 + 不更新
$res2  = (new ResourceService($app))->vodConfirm(['file_id' => $fileId . '-2', 'media_type' => 'video', 'name' => 'mock2']);
$body2 = vodBody($fileId . '-2', 'FINISH', 0, 'task-002');
$ackBad = $bxvod->handleNotify(['x-vod-signature' => 'deadbeefbadsignature'], $body2);
check('验签失败 ACK code=1（拒绝）', ($ackBad->getData()['code'] ?? null) === 1);
check('验签失败 resource 未更新（仍待转码 1）', (int) Resource::find((int) $res2['id'])->transcode_status === 1);
check('验签失败留痕 verified=0', ResourceVodNotifyLog::where('event_type', 'invalid')->where('verified', 0)->count() >= 1);

// 4e PROCESSING → 2；FINISH 失败(ErrCode!=0) → 4
$res3 = (new ResourceService($app))->vodConfirm(['file_id' => $fileId . '-3', 'media_type' => 'audio', 'name' => 'mock3']);
$bodyProcessing = vodBody($fileId . '-3', 'PROCESSING', 0, 'task-003');
$bxvod->handleNotify(signedHeaders($bodyProcessing), $bodyProcessing);
check('PROCESSING → transcode_status=2（转码中）', (int) Resource::find((int) $res3['id'])->transcode_status === 2);
$bodyFail = vodBody($fileId . '-3', 'FINISH', 10001, 'task-004');
$bxvod->handleNotify(signedHeaders($bodyFail), $bodyFail);
check('FINISH 失败(ErrCode!=0) → transcode_status=4', (int) Resource::find((int) $res3['id'])->transcode_status === 4);

echo "=== T5 forMediaType(video)=VodTxStorage + put throw + delete→DeleteMedia ===\n";
$inst = StorageManager::forMediaType('video');
check('forMediaType(video) instanceof VodTxStorage（开通态）', $inst instanceof VodTxStorage);
$putThrown = false;
try {
    $inst->put('/tmp/x', 'k');
} catch (\Throwable $e) {
    $putThrown = str_contains($e->getMessage(), '直传');
}
check('VodTxStorage::put() throw（不走服务端中转）', $putThrown);
// 注入 fake 驱动验 delete 路由到 DeleteMedia
$fake = new FakeVod();
VodManager::fake('vod_tx', $fake);
StorageManager::makeByName('vod_tx')->delete('vodfile-del-001');
check('delete 路由到 deleteMedia(fileId)', $fake->deleted === ['vodfile-del-001']);
VodManager::flushFakes();

echo "=== T6 阿里 VOD 扩展位 throw（不写实现）===\n";
$aliThrown1 = false;
try {
    StorageManager::makeByName('vod_ali');
} catch (\Throwable $e) {
    $aliThrown1 = str_contains($e->getMessage(), '阿里云 VOD');
}
check('StorageManager::makeByName(vod_ali) throw 未实现', $aliThrown1);
$aliThrown2 = false;
try {
    VodManager::driver('vod_ali');
} catch (\Throwable $e) {
    $aliThrown2 = str_contains($e->getMessage(), '阿里云 VOD');
}
check('VodManager::driver(vod_ali) throw 未实现', $aliThrown2);

echo "=== T7 upload() 对 VOD 媒体类型引导直传（不走服务端 put）===\n";
$tmp = sys_get_temp_dir() . '/mc_vod_mock_' . uniqid() . '.mp4';
file_put_contents($tmp, "\x00\x00\x00\x18ftypmp42" . str_repeat("\x00", 64)); // 伪 mp4 头
$uploadGuided = false;
try {
    $uploaded = new \think\file\UploadedFile($tmp, 'clip.mp4', 'video/mp4');
    (new ResourceService($app))->upload($uploaded, 0);
} catch (\Throwable $e) {
    $uploadGuided = str_contains($e->getMessage(), '直传');
}
check('upload(video) 在 VOD 开通态引导走直传端点', $uploadGuided);
@unlink($tmp);

echo "=== T8 vodUploadSign 未开通拒签 422 ===\n";
setCfg('storage_driver_video', 'local'); // 关掉 VOD
$signRejected = false;
try {
    (new ResourceService($app))->vodUploadSign(['media_type' => 'video']);
} catch (\app\common\library\vod\VodException $e) {
    $signRejected = $e->bizCode === \app\common\library\ErrorCode::RESOURCE_VOD_NOT_READY;
}
check('vodUploadSign 未开通 → VodException 422101', $signRejected);

echo "=== 清理：flushFakes + 硬删 mock 行 + 还原配置 ===\n";
VodManager::flushFakes();
Db::name('resource')->where('vod_media_id', 'like', 'vodfile-mock-%')->delete(true);
Db::name('resource_vod_notify_log')->where('idem_no', 'in', ['task-001', 'task-003', 'task-004'])->delete(true);
Db::name('resource_vod_notify_log')->where('event_type', 'invalid')->where('result', '验签失败')->delete(true);
// 还原 storage 组配置为种子占位态
setCfg('storage_driver_video', 'local');
setCfg('storage_driver_audio', 'local');
setCfg('vod_tx_secret_id', 'PLACEHOLDER_FAKE_VOD_SECRET_ID', true);
setCfg('vod_tx_secret_key', 'PLACEHOLDER_FAKE_VOD_SECRET_KEY_DO_NOT_USE', true);
setCfg('vod_tx_sub_app_id', '');
setCfg('vod_tx_procedure', '');
setCfg('vod_tx_callback_key', 'PLACEHOLDER_FAKE_VOD_CALLBACK_KEY', true);
$leftRes = (int) Db::name('resource')->where('vod_media_id', 'like', 'vodfile-mock-%')->count();
$leftLog = (int) Db::name('resource_vod_notify_log')->whereIn('idem_no', ['task-001', 'task-003', 'task-004'])->count();
echo "残留 mock 素材行：{$leftRes}，残留 notify_log：{$leftLog}\n";

echo str_repeat('-', 50) . PHP_EOL;
echo "通过 {$pass} / 失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);

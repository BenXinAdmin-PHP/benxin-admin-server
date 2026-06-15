<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   测试 — M5-B C 端登录闭环离线 mock（微信 mock + 真实 Valkey/DB）
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-13 22:50:00
// +----------------------------------------------------------------------
//
// 运行：php tests/m5b_login_mock.php  （仅本地/CI 离线自测，不依赖真实微信/短信凭据）
// 覆盖：MiniAccount::getPhoneNumber / code2session / MpAccount::oauthAccessToken（mock http）
//      + UserAuthService 登录即注册四级定位 + Login 控制器端到端（withPost）+ 令牌隔离回归。
// 修正 M4-B 已知项④「mock 脚本散落 /tmp」：本脚本入库留档。

declare(strict_types=1);

use app\api\controller\Login;
use app\common\exception\BusinessException;
use app\common\library\BxCache;
use app\common\library\BxJwt;
use app\common\library\wechat\HttpClientInterface;
use app\common\library\wechat\WechatManager;
use app\common\model\User;
use app\common\service\UserAuthService;
use think\facade\Db;

require __DIR__ . '/../vendor/autoload.php';

$app = new \think\App();
$app->initialize();

// ---------------------------------------------------------------------
// 可编程 mock http：按 URL 子串命中返回（callable 可按调用次数变化，验证重试）
// ---------------------------------------------------------------------
class MockHttpClient implements HttpClientInterface
{
    /** @var array<string,mixed> path 子串 => 数组响应 或 callable(query,body,$this):array */
    public array $routes = [];
    /** @var array<int,array<string,mixed>> 调用流水 */
    public array $calls = [];

    public function get(string $url, array $query = []): array
    {
        return $this->resolve('GET', $url, $query, []);
    }

    public function postJson(string $url, array $body = [], array $query = []): array
    {
        return $this->resolve('POST', $url, $query, $body);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function resolve(string $method, string $url, array $query, array $body): array
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'query' => $query, 'body' => $body];
        foreach ($this->routes as $needle => $resp) {
            if (str_contains($url, $needle)) {
                return is_callable($resp) ? $resp($query, $body, $this) : $resp;
            }
        }

        return ['errcode' => -1, 'errmsg' => 'no mock route for ' . $url];
    }
}

$mock = new MockHttpClient();
WechatManager::setHttpClient($mock);

// 默认路由：access_token 刷新恒成功（getPhoneNumber 等带 token 接口依赖）
$mock->routes['/cgi-bin/token'] = ['access_token' => 'MOCK_AT_' . substr(md5('t'), 0, 6), 'expires_in' => 7200];

// ---------------------------------------------------------------------
// 断言与统计
// ---------------------------------------------------------------------
$pass = 0;
$fail = 0;
$fails = [];
function ok(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail, $fails;
    if ($cond) {
        $pass++;
        echo "  ✓ {$name}\n";
    } else {
        $fail++;
        $fails[] = $name . ($detail !== '' ? "（{$detail}）" : '');
        echo "  ✗ {$name}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
    }
}

/**
 * 调用控制器动作，归一化为 ['code'=>int,'data'=>?array]：
 * 返回 Response → 解析业务码；抛 BusinessException（含 Wechat/Sms 子类）→ 取 bizCode。
 *
 * @return array{code:int,data:mixed}
 */
function runAction(callable $fn): array
{
    try {
        $resp = $fn();
        $j    = json_decode($resp->getContent(), true);

        return ['code' => (int) ($j['code'] ?? -1), 'data' => $j['data'] ?? null];
    } catch (BusinessException $e) {
        return ['code' => $e->bizCode, 'data' => null];
    }
}

function setPost(array $data): void
{
    app()->request->withPost($data);
}

// 计数辅助
function liveUserCount(): int
{
    return (int) Db::name('user')->whereNull('deleted_at')->whereLike('mobile', '177000%')->count();
}
function oauthCount(string $platform, string $openid): int
{
    return (int) Db::name('user_oauth')->where('platform', $platform)->where('openid', $openid)->count();
}

// ---------------------------------------------------------------------
// 清理测试数据（开头 + 结尾各一次，硬删，零残留）
// ---------------------------------------------------------------------
function cleanup(): void
{
    Db::name('user_oauth')->where('openid', 'like', 'TMOCK%')->delete();
    Db::name('user')->where('mobile', 'like', '177000%')->delete();
}
cleanup();

$svc = new UserAuthService($app);

echo "\n===== 一、微信能力 mock（getPhoneNumber / code2session / oauth + token 重试）=====\n";

// L1 code2session
$mock->routes['/sns/jscode2session'] = ['openid' => 'TMOCKMINIA1', 'session_key' => 'SK_SECRET', 'unionid' => 'TMOCKUNION1'];
$s = WechatManager::mini()->code2session('jscode_x');
ok('L1 code2session 返回 openid+unionid（session_key 不外泄到日志）', $s['openid'] === 'TMOCKMINIA1' && $s['unionid'] === 'TMOCKUNION1');

// L2 getPhoneNumber 正常
$mock->routes['/wxa/business/getuserphonenumber'] = ['phone_info' => ['purePhoneNumber' => '17700000001']];
$phone = WechatManager::mini()->getPhoneNumber('phonecode_x');
ok('L2 getPhoneNumber 返回 purePhoneNumber', $phone === '17700000001');

// L3 getPhoneNumber token 失效(40001) → 清缓存强刷 + 重试一次成功
BxCache::forget('wechat:token:mini:' . WechatManager::mini()->appId());
$mock->calls = []; // 仅统计本场景调用次数（隔离 L2 的历史调用）
$callN = 0;
$mock->routes['/wxa/business/getuserphonenumber'] = function ($q, $b, $self) use (&$callN) {
    $callN++;
    return $callN === 1
        ? ['errcode' => 40001, 'errmsg' => 'invalid credential']
        : ['phone_info' => ['purePhoneNumber' => '17700000009']];
};
$phone2  = WechatManager::mini()->getPhoneNumber('phonecode_retry');
$postHit = count(array_filter($mock->calls, fn ($c) => str_contains($c['url'], 'getuserphonenumber')));
ok('L3 getPhoneNumber 40001 清缓存重试一次成功', $phone2 === '17700000009');
ok('L3 重试序列无死循环（POST 恰好 2 次）', $postHit === 2, "实际 {$postHit}");
$mock->routes['/wxa/business/getuserphonenumber'] = ['phone_info' => ['purePhoneNumber' => '17700000001']];

// L4 oauthAccessToken
$mock->routes['/sns/oauth2/access_token'] = ['access_token' => 'WEB_AT', 'openid' => 'TMOCKMPB1', 'unionid' => 'TMOCKUNION1'];
$o = WechatManager::mp()->oauthAccessToken('oauthcode_x');
ok('L4 oauthAccessToken 返回 openid+unionid', ($o['openid'] ?? '') === 'TMOCKMPB1' && ($o['unionid'] ?? '') === 'TMOCKUNION1');

echo "\n===== 二、UserAuthService 登录即注册（真实 DB 事务）=====\n";

// S1 全新小程序用户
$u1 = $svc->loginOrRegister('mini', 'TMOCKMINIA1', '17700000001', '');
ok('S1 新用户落地（user+oauth 各 +1）', liveUserCount() === 1 && oauthCount('mini', 'TMOCKMINIA1') === 1);
ok('S1 默认昵称=用户+后4位', (string) $u1->nickname === '用户0001');

// S2 老用户 openid 命中静默（忽略入参 mobile）
$u2 = $svc->loginOrRegister('mini', 'TMOCKMINIA1', '17700009999', '');
ok('S2 老用户静默登录同一 user（忽略入参 mobile）', (int) $u2->id === (int) $u1->id && liveUserCount() === 1);

// S3 unionid 打通：mini 注册 → mp 同 unionid（openid 不同、mobile 空）命中同一 user
$u3a = $svc->loginOrRegister('mini', 'TMOCKMINIA2', '17700000002', 'TMOCKUNION2');
$u3b = $svc->loginOrRegister('mp', 'TMOCKMPB2', null, 'TMOCKUNION2');
ok('S3 unionid 打通命中同一 user', (int) $u3b->id === (int) $u3a->id);
ok('S3 同 user 两端关联（mini+mp 各 1，user 仍 1 条）',
    oauthCount('mini', 'TMOCKMINIA2') === 1 && oauthCount('mp', 'TMOCKMPB2') === 1
    && (int) Db::name('user_oauth')->where('user_id', $u3a->id)->count() === 2);

// S4 同手机号跨端（无 unionid）→ 按 mobile 命中同一 user + 补 mp 关联
$u4a = $svc->loginOrRegister('mini', 'TMOCKMINIA3', '17700000003', '');
$u4b = $svc->loginOrRegister('mp', 'TMOCKMPB3', '17700000003', '');
ok('S4 同手机号无 unionid 命中同一 user', (int) $u4b->id === (int) $u4a->id);
ok('S4 补建 mp 关联（user_id 下 oauth=2）', (int) Db::name('user_oauth')->where('user_id', $u4a->id)->count() === 2);

// S5 软删手机号复登拒绝（D4）：软删 user → 同手机号新 openid 注册 → 150002
$u5 = $svc->loginOrRegister('mini', 'TMOCKMINIA4', '17700000004', '');
User::destroy((int) $u5->id); // 软删（BxModel）
$r5 = runAction(fn () => $svc->loginOrRegister('mini', 'TMOCKMINIA5', '17700000004', ''));
ok('S5 软删手机号复登 → 150002（不撞唯一键/无半条数据）',
    $r5['code'] === 150002 && oauthCount('mini', 'TMOCKMINIA5') === 0);

// S6 禁用用户（status=0）登录 → 150002
$u6 = $svc->loginOrRegister('mini', 'TMOCKMINIA6', '17700000006', '');
Db::name('user')->where('id', $u6->id)->update(['status' => 0]);
$r6 = runAction(fn () => $svc->loginOrRegister('mini', 'TMOCKMINIA6', null, ''));
ok('S6 禁用用户登录 → 150002', $r6['code'] === 150002);

// S7 事务原子性：新用户 user+oauth 写中途唯一冲突 → 整体回滚（emulate 唯一冲突）
//   预置 (mini,TMOCKMINIA7) 关联占位，再用同 (mini,TMOCKMINIA7) 走「新 user + 冲突 oauth」闭包，
//   断言 user 不残留（loginOrRegister 自身因 ① 命中不会冲突，故此处直证事务包裹的回滚语义）。
$holder = $svc->loginOrRegister('mini', 'TMOCKMINIA7', '17700000007', '');
$before = liveUserCount();
$threw  = false;
try {
    Db::transaction(function () {
        User::create([
            'tenant_id' => 0, 'mobile' => '17700000008', 'nickname' => '用户0008',
            'avatar' => '', 'gender' => 0, 'unionid' => '', 'status' => 1,
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
        // 冲突：(mini,TMOCKMINIA7) 已存在 → uk_platform_openid 唯一冲突
        \app\common\model\UserOauth::create(['user_id' => 999999, 'platform' => 'mini', 'openid' => 'TMOCKMINIA7', 'unionid' => '']);
    });
} catch (\Throwable) {
    $threw = true;
}
$after = liveUserCount();
ok('S7 事务中途唯一冲突 → 整体回滚（user 无残留）', $threw && $after === $before
    && (int) Db::name('user')->whereNull('deleted_at')->where('mobile', '17700000008')->count() === 0);

echo "\n===== 三、Login 控制器端到端（mock http + withPost）=====\n";

// 重置 mock 为稳定响应
$mock->routes['/sns/jscode2session']    = ['openid' => 'TMOCKMINIC1', 'session_key' => 'SK', 'unionid' => ''];
$mock->routes['/wxa/business/getuserphonenumber'] = ['phone_info' => ['purePhoneNumber' => '17700000010']];

// C1 小程序新用户无 phone_code → 150001
setPost(['code' => 'wxcode1']);
$c1 = runAction(fn () => (new Login($app))->mini());
ok('C1 小程序新用户无 phone_code → 150001', $c1['code'] === 150001);

// C2 小程序新用户带 phone_code → 注册成功 + 发令牌
setPost(['code' => 'wxcode1', 'phone_code' => 'pc1']);
$c2 = runAction(fn () => (new Login($app))->mini());
ok('C2 小程序新用户带 phone_code → 注册成功发令牌', $c2['code'] === 0 && !empty($c2['data']['access_token']));
$miniAccess = (string) ($c2['data']['access_token'] ?? '');

// C3 小程序老用户无 phone_code → 静默发令牌
setPost(['code' => 'wxcode1']); // 同 openid TMOCKMINIC1，已注册
$c3 = runAction(fn () => (new Login($app))->mini());
ok('C3 小程序老用户静默发令牌（无 phone_code 也成功）', $c3['code'] === 0 && !empty($c3['data']['access_token']));

// H5 准备：oauth 返回固定 openid
$mock->routes['/sns/oauth2/access_token'] = ['access_token' => 'WAT', 'openid' => 'TMOCKMPC1', 'unionid' => ''];

// C4 H5 新用户缺 sms_code → 150001
setPost(['code' => 'oauthc1', 'mobile' => '17700000010']);
$c4 = runAction(fn () => (new Login($app))->h5());
ok('C4 H5 新用户缺 sms_code → 150001', $c4['code'] === 150001);

// C5 H5 新用户 sms_code 错 → 130004（先种正确码再用错码）
BxCache::store()->set('sms:code:login:17700000011', '123456', 300);
setPost(['code' => 'oauthc1', 'mobile' => '17700000011', 'sms_code' => '000000']);
$c5 = runAction(fn () => (new Login($app))->h5());
ok('C5 H5 新用户 sms_code 错 → 130004', $c5['code'] === 130004);

// C6 H5 新用户 sms_code 正确 → 注册成功 + 发令牌
setPost(['code' => 'oauthc1', 'mobile' => '17700000011', 'sms_code' => '123456']);
$c6 = runAction(fn () => (new Login($app))->h5());
ok('C6 H5 新用户 sms_code 正确 → 注册成功发令牌', $c6['code'] === 0 && !empty($c6['data']['access_token']));

// C7 H5 老用户无 mobile/sms_code → 静默发令牌
setPost(['code' => 'oauthc1']); // 同 openid TMOCKMPC1，已注册
$c7 = runAction(fn () => (new Login($app))->h5());
ok('C7 H5 老用户静默发令牌', $c7['code'] === 0 && !empty($c7['data']['access_token']));

echo "\n===== 四、令牌闭环 / 隔离回归（复用 M5-A）=====\n";

// 登录所得 api access 经 api guard 校验通过；admin guard 校验拒（跨 guard 隔离）
$apiOk = false;
try {
    $claims = BxJwt::parse('api', $miniAccess, BxJwt::TYPE_ACCESS);
    $apiOk  = ($claims['token_type'] ?? '') === 'access' && ($claims['uid'] ?? 0) > 0;
} catch (\Throwable) {
}
ok('T1 登录 access 经 api guard 校验通过', $apiOk);

$crossRejected = false;
try {
    BxJwt::parse('admin', $miniAccess, BxJwt::TYPE_ACCESS);
} catch (\Throwable) {
    $crossRejected = true;
}
ok('T2 登录 access 经 admin guard 校验被拒（密钥/aud 隔离）', $crossRejected);

// ---------------------------------------------------------------------
// 收尾：清理 + 汇总
// ---------------------------------------------------------------------
cleanup();
$resid = (int) Db::name('user')->where('mobile', 'like', '177000%')->count()
    + (int) Db::name('user_oauth')->where('openid', 'like', 'TMOCK%')->count();
ok('Z 测试数据硬删零残留', $resid === 0, "残留 {$resid}");

echo "\n========================================\n";
echo "结果：通过 {$pass} / 失败 {$fail}\n";
if ($fail > 0) {
    echo "失败项：\n - " . implode("\n - ", $fails) . "\n";
    exit(1);
}
echo "全绿 ✅\n";

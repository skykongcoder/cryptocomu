<?php
/**
 * Detrade 게임 플러그인 — 관리자 설정
 */

$_dtFile = __DIR__ . '/config.json';
$_dtRaw  = file_exists($_dtFile) ? json_decode(file_get_contents($_dtFile), true) : [];
if (!is_array($_dtRaw)) $_dtRaw = [];

$_dtDefaults = file_exists(__DIR__ . '/config.example.json')
    ? json_decode(file_get_contents(__DIR__ . '/config.example.json'), true)
    : [];

$_dt = array_merge((array)$_dtDefaults, $_dtRaw);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dt_save'])) {
    $boolKeys = ['enabled', 'hero_enabled'];
    $strKeys = [
        'api_key', 'private_key_pem', 'detrade_public_key_pem',
        'baseurl', 'ip_whitelist', 'currency', 'exchange_rate',
        'min_amount', 'max_amount', 'daily_bonus', 'starting_balance',
        'label_text', 'label_subtext',
    ];
    foreach ($boolKeys as $k) {
        $_dt[$k] = isset($_POST[$k]) ? '1' : '0';
    }
    foreach ($strKeys as $k) {
        $_dt[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : ($_dt[$k] ?? '');
    }
    file_put_contents($_dtFile, json_encode($_dt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success" style="background:#dcfce7;color:#15803d;padding:10px 14px;border-radius:8px;margin-bottom:14px">✓ 설정이 저장되었습니다.</div>';
}

// 통계
$prefix = DB::getPrefix();
$stats = ['orders' => 0, 'users' => 0, 'total_bet' => 0, 'total_win' => 0];
try {
    $r = DB::fetch("SELECT COUNT(*) AS n, COUNT(DISTINCT user_id) AS u,
                    SUM(CASE WHEN op_type='deduction' THEN amount ELSE 0 END) AS bet,
                    SUM(CASE WHEN op_type='add'       THEN amount ELSE 0 END) AS win
                    FROM {$prefix}dt_orders");
    if ($r) {
        $stats['orders']    = (int)$r['n'];
        $stats['users']     = (int)$r['u'];
        $stats['total_bet'] = (float)$r['bet'];
        $stats['total_win'] = (float)$r['win'];
    }
} catch (Exception $e) {}

$siteIp = '(unknown)';
$tmp = @file_get_contents('https://ifconfig.me/ip', false, stream_context_create(['http'=>['timeout'=>2]]));
if ($tmp) $siteIp = trim($tmp);
?>

<style>
.dt-cfg-section { background:#fff; padding:20px; border-radius:10px; margin-bottom:14px; border:1px solid #e5e7eb; }
.dt-cfg-section h3 { margin:0 0 12px; font-size:15px; font-weight:700; color:#111827; }
.dt-cfg-section h3 .num { color:#f59e0b; margin-right:6px; }
.dt-cfg-row { display:flex; gap:12px; margin-bottom:10px; align-items:flex-start; flex-wrap:wrap; }
.dt-cfg-row label { flex:0 0 160px; padding-top:8px; font-size:13px; font-weight:600; color:#374151; }
.dt-cfg-row input[type=text], .dt-cfg-row input[type=number], .dt-cfg-row textarea {
    flex:1; min-width:240px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; font-family:inherit;
}
.dt-cfg-row textarea { font-family:'JetBrains Mono', monospace; font-size:11px; min-height:120px; }
.dt-cfg-row .help { flex-basis:100%; font-size:11px; color:#6b7280; margin-left:172px; margin-top:-4px; line-height:1.5; }
.dt-cfg-row .help b { color:#f59e0b; }
.dt-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:14px; }
.dt-stat { background:#fff; padding:14px; border-radius:8px; border:1px solid #e5e7eb; text-align:center; }
.dt-stat .n { font-size:22px; font-weight:700; color:#f59e0b; }
.dt-stat .l { font-size:11px; color:#6b7280; margin-top:4px; }
.dt-toggle-grid { display:flex; gap:24px; flex-wrap:wrap; }
.dt-toggle { display:flex; align-items:center; gap:8px; padding:8px 14px; background:#f9fafb; border-radius:8px; cursor:pointer; }
.dt-info-box { background:#fffbeb; border:1px solid #fbbf24; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:8px; font-size:12px; color:#78350f; line-height:1.7; margin-bottom:14px; }
.dt-info-box code { background:rgba(0,0,0,0.05); padding:1px 5px; border-radius:3px; font-size:11px; }
.dt-save-btn { background:#f59e0b; color:#fff; border:0; padding:11px 28px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px; }
.dt-save-btn:hover { background:#d97706; }
</style>

<?= $msg ?>

<div class="dt-info-box">
    <b>🎮 Detrade 게임 통합 설정</b><br>
    Detrade 측에 통합 신청 후 받은 <code>apiKey</code>, <code>privateKey</code>, 그쪽 <code>공개키</code>, <code>baseurl</code>, <code>IP 목록</code>을 입력하세요.<br>
    <b>이 서버 IP</b>: <code><?= htmlspecialchars($siteIp) ?></code> — Detrade 측에 알려서 그쪽 화이트리스트에 등록 요청.
</div>

<div class="dt-stats">
    <div class="dt-stat"><div class="n"><?= number_format($stats['orders']) ?></div><div class="l">총 거래</div></div>
    <div class="dt-stat"><div class="n"><?= number_format($stats['users']) ?></div><div class="l">참여 유저</div></div>
    <div class="dt-stat"><div class="n"><?= number_format($stats['total_bet']) ?></div><div class="l">총 베팅 PT</div></div>
    <div class="dt-stat"><div class="n"><?= number_format($stats['total_win']) ?></div><div class="l">총 정산 PT</div></div>
</div>

<form method="POST">
    <input type="hidden" name="dt_save" value="1">

    <div class="dt-cfg-section">
        <h3><span class="num">1️⃣</span> 활성화</h3>
        <div class="dt-toggle-grid">
            <label class="dt-toggle">
                <input type="checkbox" name="enabled" <?= $_dt['enabled'] === '1' ? 'checked' : '' ?>>
                플러그인 활성화 (체크 시 /game 라우트 작동)
            </label>
            <label class="dt-toggle">
                <input type="checkbox" name="hero_enabled" <?= $_dt['hero_enabled'] === '1' ? 'checked' : '' ?>>
                메인페이지 히어로 배너 표시
            </label>
        </div>
    </div>

    <div class="dt-cfg-section">
        <h3><span class="num">2️⃣</span> Detrade 인증 정보</h3>
        <div class="dt-cfg-row">
            <label>baseurl</label>
            <input type="text" name="baseurl" value="<?= htmlspecialchars($_dt['baseurl']) ?>" placeholder="https://test.detrade.com">
            <div class="help">Detrade 가 제공하는 베이스 URL (테스트/운영). 마지막 슬래시 X.</div>
        </div>
        <div class="dt-cfg-row">
            <label>apiKey</label>
            <input type="text" name="api_key" value="<?= htmlspecialchars($_dt['api_key']) ?>" placeholder="발급받은 apiKey 문자열" autocomplete="off">
            <div class="help">Detrade 가 제공하는 식별자. 모든 요청 헤더에 포함됨.</div>
        </div>
        <div class="dt-cfg-row">
            <label>privateKey (RSA)</label>
            <textarea name="private_key_pem" placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;... PEM 형식 ...&#10;-----END RSA PRIVATE KEY-----" autocomplete="off"><?= htmlspecialchars($_dt['private_key_pem']) ?></textarea>
            <div class="help">Detrade 가 우리에게 발급한 RSA 개인키. 우리 요청에 RS256 서명할 때 사용. <b>PEM 형식 그대로 붙여넣기</b>.</div>
        </div>
        <div class="dt-cfg-row">
            <label>Detrade 공개키 (RSA)</label>
            <textarea name="detrade_public_key_pem" placeholder="-----BEGIN PUBLIC KEY-----&#10;... PEM 형식 ...&#10;-----END PUBLIC KEY-----" autocomplete="off"><?= htmlspecialchars($_dt['detrade_public_key_pem']) ?></textarea>
            <div class="help">Detrade 의 공개키. <b>그쪽 webhook 의 서명을 검증</b>할 때 사용. 비워두면 서명 검증 스킵 (테스트용).</div>
        </div>
        <div class="dt-cfg-row">
            <label>IP 화이트리스트</label>
            <input type="text" name="ip_whitelist" value="<?= htmlspecialchars($_dt['ip_whitelist']) ?>" placeholder="1.2.3.4, 5.6.7.8">
            <div class="help">Detrade 측 서버 IP. 콤마로 구분. 비워두면 IP 검사 안 함 (보안 약함).</div>
        </div>
    </div>

    <div class="dt-cfg-section">
        <h3><span class="num">3️⃣</span> 게임 통화 / 베팅 한도</h3>
        <div class="dt-cfg-row">
            <label>currency 코드</label>
            <input type="text" name="currency" value="<?= htmlspecialchars($_dt['currency']) ?>" placeholder="PT" style="max-width:120px">
            <div class="help">Detrade 와 주고받을 통화 코드. 자체 포인트면 "PT" 사용.</div>
        </div>
        <div class="dt-cfg-row">
            <label>exchange_rate</label>
            <input type="text" name="exchange_rate" value="<?= htmlspecialchars($_dt['exchange_rate']) ?>" placeholder="1" style="max-width:120px">
            <div class="help">USD 대비 환율. 가상 포인트면 1 그대로.</div>
        </div>
        <div class="dt-cfg-row">
            <label>최소 베팅 (PT)</label>
            <input type="number" name="min_amount" value="<?= htmlspecialchars($_dt['min_amount']) ?>" style="max-width:160px">
        </div>
        <div class="dt-cfg-row">
            <label>최대 베팅 (PT)</label>
            <input type="number" name="max_amount" value="<?= htmlspecialchars($_dt['max_amount']) ?>" style="max-width:160px">
        </div>
    </div>

    <div class="dt-cfg-section">
        <h3><span class="num">4️⃣</span> 일일 보너스 / 시작 자금</h3>
        <div class="dt-cfg-row">
            <label>일일 보너스 (PT)</label>
            <input type="number" name="daily_bonus" value="<?= htmlspecialchars($_dt['daily_bonus']) ?>" style="max-width:160px">
            <div class="help">/wallet 페이지에서 매일 1회 청구 가능한 무료 포인트.</div>
        </div>
        <div class="dt-cfg-row">
            <label>신규가입 시작 (PT)</label>
            <input type="number" name="starting_balance" value="<?= htmlspecialchars($_dt['starting_balance']) ?>" style="max-width:160px">
            <div class="help">(아직 미구현 — 회원가입 훅에 연결 필요)</div>
        </div>
    </div>

    <div class="dt-cfg-section">
        <h3><span class="num">5️⃣</span> 라벨 / 안내 문구</h3>
        <div class="dt-cfg-row">
            <label>히어로 제목</label>
            <input type="text" name="label_text" value="<?= htmlspecialchars($_dt['label_text']) ?>">
        </div>
        <div class="dt-cfg-row">
            <label>히어로 부제</label>
            <input type="text" name="label_subtext" value="<?= htmlspecialchars($_dt['label_subtext']) ?>">
        </div>
    </div>

    <div style="text-align:center;margin-top:18px">
        <button type="submit" class="dt-save-btn">💾 설정 저장</button>
    </div>
</form>

<div class="dt-info-box" style="margin-top:18px">
    <b>📡 Webhook 엔드포인트 (Detrade 측에 알려줄 URL)</b><br>
    Detrade 측이 호출할 우리 webhook 들입니다. 통합 신청 시 다음 URL 들을 알려주세요:
    <ul style="margin:8px 0;padding-left:24px">
        <li><code>POST {site_url}/api/wallet/amount/deduction</code> (베팅 시 차감)</li>
        <li><code>POST {site_url}/api/wallet/amount/add</code> (정산 시 추가)</li>
        <li><code>POST {site_url}/api/wallet/balance/{currency}</code> (잔액 조회)</li>
        <li><code>POST {site_url}/api/order/push</code> (주문 데이터 푸시)</li>
    </ul>
</div>

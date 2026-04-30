<?php
/**
 * NuriBoard 관리자 - 플러그인 마켓 상세 페이지
 * URL: /admin/market-plugin.php?id={id}
 */
require __DIR__ . '/common.php';
Auth::requireAdmin();

$pluginId = (int)($_GET['id'] ?? 0);
if ($pluginId <= 0) {
    header('Location: ' . nb_url('admin/?page=plugins&tab=market'));
    exit;
}

// 사이트 토큰 (누리코리아 라이선스)
$nkConfig = @include NB_ROOT . '/config/nurikorea.php';
$siteToken = (is_array($nkConfig) && !empty($nkConfig['license_key'])) ? $nkConfig['license_key'] : '';

// nuribd.com API에서 플러그인 정보 가져오기
$apiUrl  = 'https://nuribd.com/api/v1/market/plugins?id=' . $pluginId . ($siteToken ? '&site_token=' . urlencode($siteToken) : '');
$plugin  = null;
$apiErr  = '';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'NuriBoard/' . NB_VERSION,
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body && $httpCode === 200) {
    $data = json_decode($body, true);
    if (!empty($data['success']) && !empty($data['plugin'])) {
        $plugin = $data['plugin'];
    } else {
        $apiErr = $data['message'] ?? '플러그인 정보를 불러올 수 없습니다.';
    }
} else {
    $apiErr = '마켓 서버에 연결할 수 없습니다. 잠시 후 다시 시도해주세요.';
}

// 설치 여부 확인 (plugins/ 폴더 스캔)
$isInstalled = false;
if ($plugin) {
    $pName   = mb_strtolower(trim($plugin['name'] ?? ''));
    $plugDir = NB_ROOT . '/plugins';
    if (is_dir($plugDir)) {
        foreach (scandir($plugDir) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $jsonFile = $plugDir . '/' . $dir . '/plugin.json';
            if (!file_exists($jsonFile)) continue;
            $meta = json_decode(file_get_contents($jsonFile), true);
            if (mb_strtolower(trim($meta['name'] ?? '')) === $pName) {
                $isInstalled = true;
                break;
            }
        }
    }
}

// 구매/설치 URL 빌드
$_isPurchased   = !empty($plugin['purchased']);
$_downloadUrl   = $plugin['download_url'] ?? '';
$_adminBase     = rtrim(nb_setting('site_url', ''), '/') . '/admin/';
$_returnUrl     = $_adminBase . '?page=plugins&tab=market';
$_buyUrl        = 'https://nuribd.com/market/buy/' . $pluginId;
if ($siteToken) {
    $_buyUrl .= '?site_token=' . urlencode($siteToken)
              . '&site_url='   . urlencode(rtrim(nb_setting('site_url', ''), '/'))
              . '&return_url=' . urlencode($_returnUrl);
}

// 누리코리아 플랜 확인 (tier 게이트용)
$nkPlan = 'free';
if ($siteToken) {
    $verUrl = 'https://nurikorea.com/api/verify-license.php?key=' . urlencode($siteToken)
        . '&domain=' . urlencode($nkConfig['domain'] ?? nb_setting('site_url', ''));
    $ch2 = curl_init($verUrl);
    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true]);
    $verBody = curl_exec($ch2);
    curl_close($ch2);
    if ($verBody) {
        $verData = json_decode($verBody, true);
        if (!empty($verData['ok'])) $nkPlan = $verData['plan'] ?? 'free';
    }
}

adminHeader('plugins');
?>

<!-- 뒤로가기 -->
<div class="mpd-back-wrap">
    <a href="?page=plugins&tab=market" class="mpd-back">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        마켓으로 돌아가기
    </a>
</div>

<?php if ($apiErr): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px;color:#94a3b8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <p style="font-weight:600;color:#dc2626"><?= htmlspecialchars($apiErr) ?></p>
</div></div>
<?php else: ?>

<?php
$_price = (int)($plugin['price'] ?? 0);
?>

<!-- 상단: 이미지(좌) + 카드(우) -->
<div class="mpd-top">

    <!-- 이미지 -->
    <div class="mpd-thumb">
        <?php if (!empty($plugin['thumbnail'])): ?>
        <img src="<?= htmlspecialchars($plugin['thumbnail']) ?>" alt="<?= htmlspecialchars($plugin['name']) ?>">
        <?php else: ?>
        <div class="mpd-thumb-ph">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        </div>
        <?php endif; ?>
    </div>

    <!-- 카드 -->
    <div class="mpd-card">
        <h1 class="mpd-title"><?= htmlspecialchars($plugin['name']) ?></h1>

        <!-- 가격 -->
        <div class="mpd-price-row">
            <span class="mpd-price-label">판매가</span>
            <?php if ($_price > 0): ?>
            <span class="mpd-price-val paid"><?= number_format($_price) ?>원</span>
            <?php else: ?>
            <span class="mpd-price-val free">무료</span>
            <?php endif; ?>
        </div>

        <!-- 메타 -->
        <div class="mpd-meta-list">
            <div class="mpd-meta-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                <span class="mpd-meta-k">버전</span>
                <span class="mpd-meta-v"><?= htmlspecialchars($plugin['version'] ?? '1.0') ?></span>
            </div>
            <div class="mpd-meta-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span class="mpd-meta-k">제작자</span>
                <span class="mpd-meta-v"><?= htmlspecialchars($plugin['author'] ?? '') ?></span>
            </div>
            <div class="mpd-meta-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span class="mpd-meta-k">다운로드</span>
                <span class="mpd-meta-v"><?= number_format((int)($plugin['downloads'] ?? 0)) ?>회</span>
            </div>
            <?php if (!empty($plugin['category'])): ?>
            <div class="mpd-meta-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <span class="mpd-meta-k">카테고리</span>
                <span class="mpd-meta-v"><?= htmlspecialchars($plugin['category']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- 구매/설치 버튼 -->
        <div class="mpd-action-row">
            <?php if ($isInstalled): ?>
                <div class="mpd-installed-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    설치됨
                </div>
            <?php elseif ($_price > 0 && $_isPurchased && $_downloadUrl): ?>
                <button class="mpd-btn-install" id="mpInstallBtn" onclick="mpInstall('<?= htmlspecialchars($_downloadUrl, ENT_QUOTES) ?>', '<?= htmlspecialchars($plugin['name'], ENT_QUOTES) ?>')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    설치하기
                </button>
            <?php elseif ($_price > 0): ?>
                <a href="<?= htmlspecialchars($_buyUrl) ?>" target="_blank" class="mpd-btn-buy">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <?= number_format($_price) ?>원 구매하기
                </a>
                <?php if (!$siteToken): ?>
                <p class="mpd-token-warn">사이트 토큰이 없습니다. 누리보드 계정으로 로그인 후 구매해 주세요.</p>
                <?php endif; ?>
            <?php elseif (!empty($plugin['download_url'])): ?>
                <button class="mpd-btn-install" id="mpInstallBtn" onclick="mpInstall('<?= htmlspecialchars($plugin['download_url'], ENT_QUOTES) ?>', '<?= htmlspecialchars($plugin['name'], ENT_QUOTES) ?>')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    무료 설치
                </button>
            <?php endif; ?>
        </div>

    </div><!-- /.mpd-card -->
</div><!-- /.mpd-top -->

<!-- 하단: 상품 설명 -->
<?php if (!empty($plugin['description'])): ?>
<div class="mpd-desc-section">
    <h2 class="mpd-desc-title">상품 소개</h2>
    <div class="mpd-desc"><?= nl2br(htmlspecialchars($plugin['description'])) ?></div>
</div>
<?php endif; ?>

<style>
/* 뒤로가기 */
.mpd-back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#64748b;text-decoration:none;margin-bottom:20px;padding:6px 10px;border-radius:8px;transition:background .15s;max-width:960px;width:100%}
.mpd-back:hover{background:#f1f5f9;color:#1e293b}
.mpd-back-wrap{max-width:960px;margin:0 auto 8px;}

/* 상단 2컬럼: 이미지 좌 / 카드 우 */
.mpd-top{display:grid;grid-template-columns:600px 1fr;gap:24px;align-items:flex-start;margin-bottom:28px;max-width:960px;margin-left:auto;margin-right:auto}

/* 이미지 */
.mpd-thumb{border-radius:12px;overflow:hidden;background:#fff}
.mpd-thumb img{width:100%;height:auto;display:block}
.mpd-thumb-ph{width:100%;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;color:#cbd5e1}

/* 카드 */
.mpd-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.05);position:sticky;top:20px}
.mpd-title{font-size:16px;font-weight:800;color:#1e293b;margin:0 0 14px 0;line-height:1.5}

/* 가격 */
.mpd-price-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;margin-bottom:16px}
.mpd-price-label{font-size:13px;color:#94a3b8}
.mpd-price-val{font-size:20px;font-weight:800}
.mpd-price-val.paid{color:#d97706}
.mpd-price-val.free{color:#16a34a}

/* 메타 */
.mpd-meta-list{display:flex;flex-direction:column;gap:10px;margin-bottom:20px}
.mpd-meta-item{display:flex;align-items:center;gap:8px;font-size:13px}
.mpd-meta-item svg{flex-shrink:0;color:#94a3b8}
.mpd-meta-k{color:#94a3b8;flex:1}
.mpd-meta-v{color:#1e293b;font-weight:600}

/* 구매/설치 버튼 */
.mpd-action-row{margin-top:4px;display:flex;flex-direction:column;gap:8px}
.mpd-btn-buy{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;background:#f59e0b;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;transition:background .15s}
.mpd-btn-buy:hover{background:#d97706;color:#fff;text-decoration:none}
.mpd-btn-install{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;background:#22c55e;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;transition:background .15s}
.mpd-btn-install:hover{background:#16a34a}
.mpd-btn-install:disabled{background:#cbd5e1;color:#94a3b8;cursor:not-allowed}
.mpd-installed-badge{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:14px;background:#f0fdf4;color:#16a34a;border:1px solid #86efac;border-radius:10px;font-size:15px;font-weight:700}
.mpd-token-warn{font-size:12px;color:#94a3b8;text-align:center;margin:4px 0 0}

/* 하단 설명 */
.mpd-desc-section{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px;max-width:960px;margin-left:auto;margin-right:auto}
.mpd-desc-title{font-size:15px;font-weight:700;color:#1e293b;margin:0 0 16px 0;padding-bottom:14px;border-bottom:1px solid #f1f5f9}
.mpd-desc{font-size:14px;color:#475569;line-height:1.9;white-space:pre-wrap}

@media(max-width:768px){
    .mpd-top{grid-template-columns:1fr;max-width:100%}
    .mpd-card{position:static}
}
</style>

<?php endif; ?>

<script>
function mpInstall(downloadUrl, name) {
    if (!confirm('"' + name + '" 플러그인을 설치할까요?')) return;
    var btn = document.getElementById('mpInstallBtn');
    if (btn) { btn.disabled = true; btn.textContent = '설치 중...'; }
    var fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'plugin_install');
    fd.append('url', downloadUrl);
    fd.append('market_name', name);
    fetch('index.php?page=plugins', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        alert(res.message || '설치 완료!');
        if (res.success) location.href = 'index.php?page=plugins&tab=installed';
        else if (btn) { btn.disabled = false; btn.textContent = '설치하기'; }
    })
    .catch(function(e) {
        alert('설치 실패: ' + (e.message || e));
        if (btn) { btn.disabled = false; btn.textContent = '설치하기'; }
    });
}
</script>

<?php adminFooter(); ?>

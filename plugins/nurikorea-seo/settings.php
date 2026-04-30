<?php
/**
 * 누리코리아 SEO 플러그인 - 관리자 설정 UI
 * 저장 방식: POST 직접 처리 (NuriBoard settings 테이블에 upsert)
 */

// POST 저장 처리
$nks_flash = '';
$prefix = DB::getPrefix(); // 설정 저장 + 현재값 로드에 모두 사용

// 검증 코드 추출 헬퍼 (스마트 따옴표까지 정규화)
$__nks_extract = function ($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    // 스마트 따옴표 (") → 일반 따옴표 (")
    $raw = str_replace(
        ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99"],
        '"',
        $raw
    );
    // 1차: content="..." 추출
    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $raw, $m)) {
        return trim($m[1]);
    }
    // 2차: 태그가 들어왔는데 추출 실패 → 태그 모두 제거
    if (strpos($raw, '<') !== false) {
        return trim(strip_tags($raw));
    }
    // 3차: 순수 코드 (영문/숫자/대시/언더스코어만 유효)
    return $raw;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['nks_act'] ?? '') === 'save') {
    $fields = [
        'google_verification' => $__nks_extract($_POST['google_verification'] ?? ''),
        'naver_verification'  => $__nks_extract($_POST['naver_verification'] ?? ''),
        'nks_ga4_id'          => trim((string) ($_POST['nks_ga4_id'] ?? '')),
        'nks_gtm_id'          => trim((string) ($_POST['nks_gtm_id'] ?? '')),
        'nks_fb_pixel'        => trim((string) ($_POST['nks_fb_pixel'] ?? '')),
        'nks_kakao_pixel'     => trim((string) ($_POST['nks_kakao_pixel'] ?? '')),
        'nks_custom_head'     => (string) ($_POST['nks_custom_head'] ?? ''),
    ];

    foreach ($fields as $key => $val) {
        $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            DB::update("{$prefix}settings", ['setting_value' => $val], 'setting_key = ?', [$key]);
        } else {
            DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $val]);
        }
    }

    // SEO 캐시 무효화 (있다면)
    if (class_exists('Cache') && method_exists('Cache', 'flush')) {
        try { Cache::flush(); } catch (Throwable $e) {}
    }

    AdminLog::write('plugin_settings', 'nurikorea-seo', 0, 'SEO 설정 저장');
    $nks_flash = '저장 완료. 웹사이트 어느 페이지든 새로고침하면 즉시 반영됩니다.';
}

// 현재 값 로드 — DB에서 직접 조회 (nb_setting 쓰면 NB_SETTINGS 상수가 캐시돼서 방금 저장한 값이 안 보임)
$__nks_dbload = function ($keys) use ($prefix) {
    $out = array_fill_keys($keys, '');
    try {
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = DB::fetchAll("SELECT setting_key, setting_value FROM {$prefix}settings WHERE setting_key IN ($placeholders)", $keys);
        foreach ($rows as $r) $out[$r['setting_key']] = (string)$r['setting_value'];
    } catch (Exception $e) {}
    return $out;
};
$v = $__nks_dbload([
    'google_verification','naver_verification','nks_ga4_id','nks_gtm_id','nks_fb_pixel','nks_kakao_pixel','nks_custom_head'
]);

// === 진단: 실제 사이트 홈페이지 <head>에 메타태그가 나오는지 확인 ===
$nks_diag = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['nks_act'] ?? '') === 'diagnose') {
    $siteUrl = rtrim(nb_setting('site_url', ''), '/');
    if (!$siteUrl) {
        // 폴백: 현재 호스트
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $targetUrl = $siteUrl . '/?_nks_diag=' . time();

    $html = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 NuriKoreaSEO-Diagnostic',
        ]);
        $html = (string)curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
    } else {
        $html = @file_get_contents($targetUrl);
        $httpCode = $html ? 200 : 0;
        $curlErr = $html ? '' : 'curl 또는 allow_url_fopen 미지원';
    }

    $headMatch = '';
    if ($html && preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $html, $m)) {
        $headMatch = $m[1];
    }

    $checks = [];
    // Google verification
    if (!empty($v['google_verification'])) {
        $needle = 'google-site-verification';
        $found = $headMatch && strpos($headMatch, $needle) !== false && strpos($headMatch, $v['google_verification']) !== false;
        $checks[] = [
            'label' => '구글 서치콘솔 메타태그',
            'expected' => $v['google_verification'],
            'ok' => $found,
        ];
    }
    // Naver verification
    if (!empty($v['naver_verification'])) {
        $found = $headMatch && strpos($headMatch, 'naver-site-verification') !== false && strpos($headMatch, $v['naver_verification']) !== false;
        $checks[] = [
            'label' => '네이버 서치어드바이저 메타태그',
            'expected' => $v['naver_verification'],
            'ok' => $found,
        ];
    }
    // GA4
    if (!empty($v['nks_ga4_id'])) {
        $found = $headMatch && strpos($headMatch, $v['nks_ga4_id']) !== false;
        $checks[] = ['label' => 'Google Analytics 4', 'expected' => $v['nks_ga4_id'], 'ok' => $found];
    }
    // GTM
    if (!empty($v['nks_gtm_id'])) {
        $found = $headMatch && strpos($headMatch, $v['nks_gtm_id']) !== false;
        $checks[] = ['label' => 'Google Tag Manager', 'expected' => $v['nks_gtm_id'], 'ok' => $found];
    }
    // FB Pixel
    if (!empty($v['nks_fb_pixel'])) {
        $found = $headMatch && strpos($headMatch, $v['nks_fb_pixel']) !== false;
        $checks[] = ['label' => '페이스북 픽셀', 'expected' => $v['nks_fb_pixel'], 'ok' => $found];
    }
    // Kakao Pixel
    if (!empty($v['nks_kakao_pixel'])) {
        $found = $headMatch && strpos($headMatch, $v['nks_kakao_pixel']) !== false;
        $checks[] = ['label' => '카카오 픽셀', 'expected' => $v['nks_kakao_pixel'], 'ok' => $found];
    }

    $nks_diag = [
        'url' => $targetUrl,
        'http' => $httpCode,
        'err' => $curlErr,
        'has_head' => $headMatch !== '',
        'head_len' => strlen($headMatch),
        'checks' => $checks,
    ];
}

// 상태 카운트 (몇 개 활성화돼 있는지)
$active_count = 0;
foreach ($v as $val) if (trim((string)$val) !== '') $active_count++;
?>

<style>
.nks-wrap { max-width: 820px; }
.nks-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; flex-wrap: wrap; gap: 10px;
}
.nks-head h2 { font-size: 22px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em; }
.nks-status {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; background: #f0fdf4; border: 1px solid #86efac;
    border-radius: 20px; font-size: 13px; color: #16a34a; font-weight: 600;
}
.nks-status.empty { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.nks-status .dot { width: 8px; height: 8px; border-radius: 50%; background: #16a34a; }
.nks-status.empty .dot { background: #f59e0b; }

.nks-flash {
    padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0;
    color: #15803d; border-radius: 8px; margin-bottom: 20px; font-size: 14px;
    display: flex; align-items: center; gap: 10px;
}
.nks-flash svg { flex-shrink: 0; }

.nks-section {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    margin-bottom: 16px; overflow: hidden;
}
.nks-section-head {
    padding: 14px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb;
    display: flex; align-items: center; gap: 10px;
}
.nks-section-head svg { flex-shrink: 0; color: #16a34a; }
.nks-section-head h3 { font-size: 15px; font-weight: 700; color: #111827; margin: 0; }
.nks-section-body { padding: 20px; }

.nks-field { margin-bottom: 18px; }
.nks-field:last-child { margin-bottom: 0; }
.nks-field label {
    display: block; font-size: 13px; font-weight: 600; color: #374151;
    margin-bottom: 6px;
}
.nks-field input, .nks-field textarea {
    width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px;
    font-size: 14px; color: #111827; background: #fff; font-family: inherit;
    transition: border-color .15s, box-shadow .15s;
}
.nks-field input:focus, .nks-field textarea:focus {
    outline: none; border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.1);
}
.nks-field textarea { min-height: 120px; resize: vertical; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; }
.nks-hint { margin-top: 6px; font-size: 12px; color: #6b7280; line-height: 1.5; }
.nks-hint a { color: #16a34a; text-decoration: underline; }
.nks-hint code {
    background: #f3f4f6; padding: 2px 6px; border-radius: 4px;
    font-family: 'Consolas', monospace; font-size: 11px; color: #374151;
}

.nks-state {
    display: inline-flex; align-items: center; gap: 5px;
    margin-left: 8px; padding: 2px 8px; border-radius: 10px;
    font-size: 11px; font-weight: 700;
}
.nks-state.on  { background: #dcfce7; color: #16a34a; }
.nks-state.off { background: #f3f4f6; color: #9ca3af; }

.nks-warn {
    padding: 10px 14px; background: #fff7ed; border: 1px solid #fdba74;
    border-radius: 8px; font-size: 12px; color: #9a3412; margin-top: 10px;
    display: flex; align-items: flex-start; gap: 8px;
}

.nks-submit {
    position: sticky; bottom: 0; background: #fff;
    padding: 16px 0; margin-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex; justify-content: space-between; align-items: center; gap: 10px;
}
.nks-btn {
    padding: 10px 20px; background: #16a34a; color: #fff;
    border: 1px solid #16a34a; border-radius: 8px;
    font-size: 14px; font-weight: 700; cursor: pointer;
    transition: background .15s;
}
.nks-btn:hover { background: #15803d; }
</style>

<div class="nks-wrap">
    <div class="nks-head">
        <h2>누리코리아 SEO · 통합 설정</h2>
        <?php if ($active_count > 0): ?>
        <span class="nks-status"><span class="dot"></span> <?= $active_count ?>개 항목 활성화</span>
        <?php else: ?>
        <span class="nks-status empty"><span class="dot"></span> 아직 아무것도 설정 안 됨</span>
        <?php endif; ?>
    </div>

    <?php if ($nks_flash): ?>
    <div class="nks-flash">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($nks_flash) ?>
    </div>
    <?php endif; ?>

    <!-- === 진단 도구 === -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
                <strong style="font-size:14px;color:#111827">사이트 출력 진단</strong>
                <div style="font-size:12px;color:#6b7280;margin-top:3px">
                    실제 사이트 홈페이지의 <code>&lt;head&gt;</code>에 설정값이 정말 나오는지 확인해요. 구글이 "인증 실패"라고 하면 여기서 먼저 체크.
                </div>
            </div>
            <form method="post" style="margin:0">
                <input type="hidden" name="nks_act" value="diagnose">
                <button type="submit" class="nks-btn" style="background:#0ea5e9;border-color:#0ea5e9">지금 진단 실행</button>
            </form>
        </div>

        <?php if ($nks_diag): ?>
            <div style="margin-top:14px;padding:14px;background:#f9fafb;border-radius:8px;font-size:13px">
                <div style="margin-bottom:8px">
                    <strong>요청 URL:</strong> <code style="font-size:11px"><?= htmlspecialchars($nks_diag['url']) ?></code>
                </div>
                <div style="margin-bottom:8px">
                    <strong>응답:</strong>
                    <?php if ($nks_diag['http'] === 200): ?>
                        <span style="color:#16a34a">HTTP 200 (정상)</span>
                    <?php else: ?>
                        <span style="color:#dc2626">HTTP <?= (int)$nks_diag['http'] ?><?= $nks_diag['err'] ? ' — ' . htmlspecialchars($nks_diag['err']) : '' ?></span>
                    <?php endif; ?>
                </div>
                <div style="margin-bottom:12px">
                    <strong>&lt;head&gt; 태그:</strong>
                    <?= $nks_diag['has_head'] ? '<span style="color:#16a34a">발견됨 (' . number_format($nks_diag['head_len']) . '자)</span>' : '<span style="color:#dc2626">없음!</span>' ?>
                </div>

                <?php if (empty($nks_diag['checks'])): ?>
                    <div style="color:#9ca3af">설정된 항목이 없어서 검사할 내용 없음. 먼저 저장해주세요.</div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <?php foreach ($nks_diag['checks'] as $c): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:<?= $c['ok'] ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $c['ok'] ? '#bbf7d0' : '#fecaca' ?>;border-radius:6px">
                                <div>
                                    <strong style="color:<?= $c['ok'] ? '#15803d' : '#991b1b' ?>;font-size:13px"><?= $c['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($c['label']) ?></strong>
                                    <span style="color:#6b7280;font-size:11px;margin-left:6px">값: <?= htmlspecialchars($c['expected']) ?></span>
                                </div>
                                <span style="font-size:11px;font-weight:600;color:<?= $c['ok'] ? '#15803d' : '#991b1b' ?>">
                                    <?= $c['ok'] ? '출력됨' : '출력 안 됨' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    $allOk = true;
                    foreach ($nks_diag['checks'] as $c) { if (!$c['ok']) { $allOk = false; break; } }
                    ?>
                    <?php if ($allOk): ?>
                        <div style="margin-top:12px;padding:10px 14px;background:#ecfdf5;border:1px solid #86efac;border-radius:6px;font-size:12px;color:#15803d">
                            ✓ 모든 항목이 정상 출력 중입니다. 구글에서 "인증 실패"가 계속 뜨면 <strong>10~30분 대기 후 다시 시도</strong>하거나, Search Console에서 <strong>"HTML 태그" 방식이 선택됐는지</strong> 확인해주세요.
                        </div>
                    <?php else: ?>
                        <div style="margin-top:12px;padding:10px 14px;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;font-size:12px;color:#991b1b">
                            ✗ 일부 항목이 &lt;head&gt;에 출력 안 되고 있어요. 원인 확인:<br>
                            1. 플러그인이 활성화돼 있나요?<br>
                            2. 사이트에 캐시 플러그인이 있으면 캐시 비워주세요<br>
                            3. 누리보드 코어가 최신 버전인가요?
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post">
        <input type="hidden" name="nks_act" value="save">

        <!-- === 섹션 1: 검색엔진 사이트 인증 === -->
        <div class="nks-section">
            <div class="nks-section-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <h3>검색엔진 사이트 인증</h3>
            </div>
            <div class="nks-section-body">

                <div class="nks-field">
                    <label>
                        구글 서치콘솔 확인 코드
                        <span class="nks-state <?= $v['google_verification']?'on':'off' ?>"><?= $v['google_verification']?'활성':'미설정' ?></span>
                    </label>
                    <input type="text" name="google_verification" value="<?= htmlspecialchars((string)$v['google_verification']) ?>" placeholder="예: abc123def456...">
                    <p class="nks-hint">
                        <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>
                        → 속성 추가 → <b>"HTML 태그"</b> 방식 선택 → 나온 태그에서 <code>content="..."</code> 값만 복사해서 붙여넣으세요.
                        (태그 통째로 붙여도 자동 추출됩니다)
                    </p>
                </div>

                <div class="nks-field">
                    <label>
                        네이버 서치어드바이저 확인 코드
                        <span class="nks-state <?= $v['naver_verification']?'on':'off' ?>"><?= $v['naver_verification']?'활성':'미설정' ?></span>
                    </label>
                    <input type="text" name="naver_verification" value="<?= htmlspecialchars((string)$v['naver_verification']) ?>" placeholder="예: xyz789abc123...">
                    <p class="nks-hint">
                        <a href="https://searchadvisor.naver.com/" target="_blank">네이버 서치어드바이저</a>
                        → 사이트 등록 → <b>"HTML 태그"</b> 방식 → content 값만 붙여넣으세요.
                    </p>
                </div>

            </div>
        </div>

        <!-- === 섹션 2: 방문자 분석 / 광고 추적 === -->
        <div class="nks-section">
            <div class="nks-section-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <h3>방문자 분석 & 광고 추적</h3>
            </div>
            <div class="nks-section-body">

                <div class="nks-field">
                    <label>
                        Google Analytics 4 (GA4)
                        <span class="nks-state <?= $v['nks_ga4_id']?'on':'off' ?>"><?= $v['nks_ga4_id']?'활성':'미설정' ?></span>
                    </label>
                    <input type="text" name="nks_ga4_id" value="<?= htmlspecialchars((string)$v['nks_ga4_id']) ?>" placeholder="G-XXXXXXXXXX">
                    <p class="nks-hint">
                        <a href="https://analytics.google.com/" target="_blank">Google Analytics</a> → 관리 → 데이터 스트림 → <b>측정 ID</b> (<code>G-</code>로 시작)
                    </p>
                </div>

                <div class="nks-field">
                    <label>
                        Google Tag Manager (GTM)
                        <span class="nks-state <?= $v['nks_gtm_id']?'on':'off' ?>"><?= $v['nks_gtm_id']?'활성':'미설정' ?></span>
                    </label>
                    <input type="text" name="nks_gtm_id" value="<?= htmlspecialchars((string)$v['nks_gtm_id']) ?>" placeholder="GTM-XXXXXX">
                    <p class="nks-hint">
                        <a href="https://tagmanager.google.com/" target="_blank">Tag Manager</a> 컨테이너 ID (<code>GTM-</code>로 시작)
                    </p>
                </div>

                <div class="nks-field">
                    <label>
                        페이스북(Meta) 픽셀
                        <span class="nks-state <?= $v['nks_fb_pixel']?'on':'off' ?>"><?= $v['nks_fb_pixel']?'활성':'미설정' ?></span>
                    </label>
                    <input type="text" name="nks_fb_pixel" value="<?= htmlspecialchars((string)$v['nks_fb_pixel']) ?>" placeholder="예: 123456789012345">
                    <p class="nks-hint">
                        <a href="https://business.facebook.com/events_manager" target="_blank">Meta Events Manager</a> → 픽셀 ID (15자리 숫자)
                    </p>
                </div>

                <div class="nks-field">
                    <label>
                        카카오 픽셀
                        <span class="nks-state <?= $v['nks_kakao_pixel']?'on':'off' ?>"><?= $v['nks_kakao_pixel']?'활성':'미설정' ?></span>
                    </label>
                    <input type="text" name="nks_kakao_pixel" value="<?= htmlspecialchars((string)$v['nks_kakao_pixel']) ?>" placeholder="예: 1234567890">
                    <p class="nks-hint">
                        <a href="https://moment.kakao.com/" target="_blank">카카오모먼트</a> → 내 픽셀 → 픽셀 ID
                    </p>
                </div>

            </div>
        </div>

        <!-- === 섹션 3: 고급 === -->
        <div class="nks-section">
            <div class="nks-section-head">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <h3>고급 · 사용자 정의 HTML</h3>
            </div>
            <div class="nks-section-body">

                <div class="nks-field">
                    <label>
                        사용자 정의 head HTML
                        <span class="nks-state <?= $v['nks_custom_head']?'on':'off' ?>"><?= $v['nks_custom_head']?'활성':'미설정' ?></span>
                    </label>
                    <textarea name="nks_custom_head" placeholder="&lt;!-- 예: 광고주 태그, 커스텀 메타태그 등 --&gt;
&lt;meta name=&quot;example&quot; content=&quot;value&quot;&gt;"><?= htmlspecialchars((string)$v['nks_custom_head']) ?></textarea>
                    <p class="nks-hint">
                        위 입력란으로 해결 안 되는 **추가 태그**만 넣으세요 (예: 광고 리타겟팅 코드, 베타 서비스 추적).
                        사이트 모든 페이지 <code>&lt;head&gt;</code> 내부에 자동 삽입됩니다.
                    </p>
                    <div class="nks-warn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <div>
                            <b>주의</b>: 잘못된 HTML을 넣으면 전체 사이트 레이아웃이 깨질 수 있습니다.
                            보안을 위해 <code>onload</code>, <code>onclick</code> 등 이벤트 핸들러와 <code>javascript:</code> 프로토콜은 자동 제거됩니다.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="nks-submit">
            <p class="nks-hint" style="margin:0">변경한 설정은 저장 후 모든 페이지 새로고침 시 즉시 반영됩니다.</p>
            <button type="submit" class="nks-btn">설정 저장</button>
        </div>
    </form>
</div>

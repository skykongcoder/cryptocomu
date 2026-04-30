<?php
/**
 * 이미지 최적화 + Core Web Vitals 부스터 — 설정
 */

$cfg = _imgopt_load_cfg();
$msg = '';
$msg_type = 'ok';
$webp_supported = function_exists('imagewebp');

// ── 일괄 리사이즈 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_resize'])) {
    $uploads_dir = defined('NB_ROOT') ? NB_ROOT . '/uploads' : '';
    $resized = $skipped = $failed = 0;
    $total_saved = 0;
    $max_size = (int)($cfg['max_width'] ?? 1200);
    $quality  = (int)($cfg['quality'] ?? 82);
    $backup   = !empty($cfg['backup_orig']);

    if ($uploads_dir && is_dir($uploads_dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads_dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) continue;

            $r = _imgopt_resize_image($file->getPathname(), $max_size, $quality, $backup);
            if (!$r['ok']) { $failed++; continue; }
            if (($r['reason'] ?? '') === 'already_small') { $skipped++; continue; }

            $resized++;
            $total_saved += max(0, ($r['orig_size'] ?? 0) - ($r['new_size'] ?? 0));
        }
    }
    $saved_kb = round($total_saved / 1024);
    $msg = "리사이즈 완료: {$resized}개 / 작아서 스킵: {$skipped}개" . ($failed ? " / 실패: {$failed}개" : '') . " / 절약: {$saved_kb}KB";
}

// ── 백업에서 복원 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $backup_dir = _imgopt_data_dir() . '/backup';
    $restored = 0;
    if (is_dir($backup_dir)) {
        foreach (glob($backup_dir . '/*') as $bk) {
            $base = basename($bk);
            // 형식: md5_원본파일명
            if (!preg_match('/^[a-f0-9]{32}_(.+)$/', $base, $m)) continue;
            $hash = substr($base, 0, 32);
            // 모든 업로드 위치를 다 알기 어려우니, 파일 자체 삭제만 하지 않고 보관만 함
            $restored++;
        }
    }
    $msg = "백업 폴더에 {$restored}개 파일이 보관되어 있습니다. 수동 복원이 필요한 경우 /data/image-optimizer/backup/ 에서 가져가세요.";
}

// ── 백업 삭제 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_backup'])) {
    $backup_dir = _imgopt_data_dir() . '/backup';
    $deleted = 0;
    if (is_dir($backup_dir)) {
        foreach (glob($backup_dir . '/*') as $bk) {
            if (@unlink($bk)) $deleted++;
        }
    }
    $msg = "백업 파일 {$deleted}개가 삭제되었습니다.";
}

// ── 일괄 변환 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_convert'])) {
    if (!$webp_supported) {
        $msg = 'GD 라이브러리가 WebP를 지원하지 않습니다.';
        $msg_type = 'err';
    } else {
        $uploads_dir = defined('NB_ROOT') ? NB_ROOT . '/uploads' : '';
        $converted = $skipped = $failed = 0;
        $quality = (int)($cfg['quality'] ?? 82);

        if ($uploads_dir && is_dir($uploads_dir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads_dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) continue;

                $dst = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file->getPathname());
                if (file_exists($dst)) { $skipped++; continue; }

                _imgopt_to_webp($file->getPathname(), $quality) ? $converted++ : $failed++;
            }
        }
        $msg = "변환 완료: {$converted}개 / 이미 존재: {$skipped}개" . ($failed ? " / 실패: {$failed}개" : '');
    }
}

// ── 캐시 삭제 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    $cache_dir = _imgopt_data_dir() . '/dims';
    if (is_dir($cache_dir)) array_map('unlink', glob($cache_dir . '/*.json') ?: []);
    $msg = '캐시가 삭제되었습니다.';
}

// ── 설정 저장 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_save'])) {
    $cfg['enabled']        = isset($_POST['enabled']);
    $cfg['webp_convert']   = isset($_POST['webp_convert']);
    $cfg['auto_resize']    = isset($_POST['auto_resize']);
    $cfg['backup_orig']    = isset($_POST['backup_orig']);
    $cfg['max_width']      = max(400, min(3000, (int)($_POST['max_width'] ?? 1200)));
    $cfg['lazy_load']      = isset($_POST['lazy_load']);
    $cfg['lcp_preload']    = isset($_POST['lcp_preload']);
    $cfg['fix_dimensions'] = isset($_POST['fix_dimensions']);
    $cfg['css_preload']    = isset($_POST['css_preload']);
    $cfg['font_swap']      = isset($_POST['font_swap']);
    $cfg['async_css']      = isset($_POST['async_css']);
    $cfg['quality']        = max(50, min(100, (int)($_POST['quality'] ?? 82)));
    _imgopt_save_cfg($cfg);
    $cfg = _imgopt_load_cfg();
    if (!$msg) $msg = '설정이 저장되었습니다.';
}

// ── 통계 ──
$uploads_dir = defined('NB_ROOT') ? NB_ROOT . '/uploads' : '';
$total_orig = $total_webp = 0;
$saved_bytes = 0;
$oversize_count = 0;   // max_width 초과 파일 개수
$max_size = (int)($cfg['max_width'] ?? 1200);

if ($uploads_dir && is_dir($uploads_dir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads_dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());

        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $total_orig++;
            $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file->getPathname());
            if (file_exists($webp)) {
                $total_webp++;
                $saved_bytes += max(0, $file->getSize() - filesize($webp));
            }
        }

        // 해상도 체크 (모든 이미지 대상)
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $sz = @getimagesize($file->getPathname());
            if ($sz && (max($sz[0], $sz[1]) > $max_size)) {
                $oversize_count++;
            }
        }
    }
}
$saved_kb = round($saved_bytes / 1024);
$convert_pct = $total_orig > 0 ? round($total_webp / $total_orig * 100) : 0;

// 백업 파일 개수
$backup_count = 0;
$backup_size  = 0;
$backup_dir   = _imgopt_data_dir() . '/backup';
if (is_dir($backup_dir)) {
    foreach (glob($backup_dir . '/*') as $bk) {
        if (is_file($bk)) {
            $backup_count++;
            $backup_size += filesize($bk);
        }
    }
}
$backup_mb = round($backup_size / 1024 / 1024, 1);
?>

<style>
.io-wrap { max-width: 720px; font-family: -apple-system, sans-serif; }
.io-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.io-card h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; letter-spacing: .5px; }
.io-row { margin-bottom: 14px; }
.io-row:last-child { margin-bottom: 0; }
.io-check { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; transition: background .15s; }
.io-check:hover { background: #f8fafc; }
.io-check input { margin-top: 2px; accent-color: #22c55e; width: 15px; height: 15px; flex-shrink: 0; }
.io-check-inner { flex: 1; }
.io-check-title { font-size: 13px; font-weight: 600; color: #1e293b; }
.io-check-sub { font-size: 12px; color: #64748b; margin-top: 3px; line-height: 1.5; }
.io-check.danger { border-color: #fecaca; background: #fff8f8; }
.io-check.danger:hover { background: #fff0f0; }
.badge { display:inline-block; padding: 1px 7px; border-radius: 4px; font-size: 11px; font-weight: 700; margin-left: 6px; vertical-align: middle; }
.badge-red   { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.badge-green { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
.badge-blue  { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.badge-warn  { background:#fefce8; color:#92400e; border:1px solid #fde68a; }
.io-btn { padding: 10px 28px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.io-btn:hover { background: #15803d; }
.io-btn:disabled { opacity: .4; cursor: not-allowed; }
.io-btn-sub { padding: 9px 20px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.io-btn-sub:hover { background: #e2e8f0; }
.io-msg-ok  { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.io-msg-err { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center; }
.stat-num { font-size: 22px; font-weight: 800; }
.stat-label { font-size: 11px; color: #64748b; margin-top: 3px; }
.stat-num.red { color: #ef4444; }
.stat-num.yellow { color: #f59e0b; }
.stat-num.green { color: #16a34a; }
.impact-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.impact-table th { background: #f8fafc; padding: 8px 12px; text-align: left; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
.impact-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #374151; vertical-align: top; }
.impact-table tr:last-child td { border-bottom: none; }
.progress-bar { background: #e2e8f0; border-radius: 4px; height: 6px; margin-top: 6px; }
.progress-fill { background: #22c55e; border-radius: 4px; height: 6px; }
.io-quality-wrap { display: flex; align-items: center; gap: 12px; margin-top: 6px; }
.io-quality-wrap input[type=range] { flex: 1; accent-color: #22c55e; }
.io-quality-val { font-size: 16px; font-weight: 700; color: #16a34a; min-width: 40px; }
</style>

<div class="io-wrap">

<?php if ($msg): ?>
<div class="io-msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- 기능 안내 -->
<div class="io-card" style="background:#f0fdf4;border-color:#bbf7d0">
    <h2 style="border-color:#bbf7d0;color:#15803d">최적화 기능 효과</h2>

    <p style="font-size:13px;color:#374151;margin:0 0 14px;line-height:1.7">
        구글 모바일 검색 순위에 직접 영향을 주는 <strong>Core Web Vitals</strong> 지표를 자동으로 최적화합니다.<br>
        아래 기능들이 활성화되면 LCP, FCP, CLS 등 핵심 지표가 모두 개선됩니다.
    </p>

    <table class="impact-table">
        <tr>
            <th>기능</th>
            <th>해결 문제</th>
            <th>개선 지표</th>
        </tr>
        <tr>
            <td><strong>이미지 WebP 변환</strong></td>
            <td>이미지 용량 70% 절감</td>
            <td><span class="badge badge-red">LCP</span></td>
        </tr>
        <tr>
            <td><strong>이미지 자동 리사이즈</strong></td>
            <td>큰 이미지 → 적정 크기로 축소</td>
            <td><span class="badge badge-red">LCP</span></td>
        </tr>
        <tr>
            <td><strong>LCP preload + fetchpriority</strong></td>
            <td>히어로 이미지 발견 지연 제거</td>
            <td><span class="badge badge-red">LCP</span></td>
        </tr>
        <tr>
            <td><strong>CSS preload 힌트</strong></td>
            <td>스타일시트 다운로드 가속</td>
            <td><span class="badge badge-warn">FCP</span></td>
        </tr>
        <tr>
            <td><strong>이미지 width/height</strong></td>
            <td>CLS 0.101 → 0.05</td>
            <td><span class="badge badge-warn">+3~4점</span></td>
        </tr>
        <tr>
            <td><strong>Lazy Loading</strong></td>
            <td>초기 이미지 로딩 감소</td>
            <td><span class="badge badge-blue">전반</span></td>
        </tr>
        <tr>
            <td><strong>이미지 width/height</strong></td>
            <td>레이아웃 밀림 방지</td>
            <td><span class="badge badge-warn">CLS</span></td>
        </tr>
        <tr>
            <td><strong>Lazy Loading</strong></td>
            <td>화면 밖 이미지 지연 로딩</td>
            <td><span class="badge badge-blue">전반</span></td>
        </tr>
        <tr>
            <td><strong>font-display: swap</strong></td>
            <td>폰트 로딩 중 텍스트 표시</td>
            <td><span class="badge badge-blue">FCP</span></td>
        </tr>
    </table>

    <p style="font-size:12px;color:#64748b;margin:14px 0 0;line-height:1.6">
        실제 점수는 사이트마다 다릅니다. 적용 후 아래 PageSpeed Insights에서 측정해보세요.
    </p>
</div>

<?php if (!$webp_supported): ?>
<div class="io-card" style="background:#fef2f2;border-color:#fecaca">
    <h2 style="border-color:#fecaca;color:#dc2626">서버 환경 경고</h2>
    <p style="font-size:13px;color:#dc2626;margin:0">GD 라이브러리가 WebP를 지원하지 않습니다. WebP 변환 기능을 제외한 나머지 최적화는 정상 동작합니다.</p>
</div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="plugin_save" value="1">

<!-- 기능 ON/OFF -->
<div class="io-card">
    <h2>Core Web Vitals 최적화 설정</h2>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">플러그인 활성화</div>
                <div class="io-check-sub">비활성화 시 모든 최적화가 중단됩니다.</div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="lcp_preload" value="1" <?= !empty($cfg['lcp_preload']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">LCP 이미지 최우선 로딩 <span class="badge badge-red">LCP 직결 — 필수</span></div>
                <div class="io-check-sub">
                    페이지 첫 번째 이미지(히어로)에 fetchpriority="high"와 preload 태그를 자동 삽입합니다.<br>
                    히어로 이미지가 늦게 발견되면 LCP가 크게 지연됩니다. 이 옵션으로 해결됩니다.
                </div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="webp_convert" value="1" <?= !empty($cfg['webp_convert']) ? 'checked' : '' ?> <?= !$webp_supported ? 'disabled' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">
                    WebP 자동 교체
                    <?= !$webp_supported ? '<span class="badge badge-red">서버 미지원</span>' : '<span class="badge badge-green">사용 가능</span>' ?>
                </div>
                <div class="io-check-sub">변환된 WebP 파일이 있으면 HTML의 이미지 src를 자동으로 .webp 경로로 교체합니다. 아래 일괄 변환을 먼저 실행하세요.</div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="auto_resize" value="1" <?= !empty($cfg['auto_resize']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">큰 이미지 자동 축소 <span class="badge badge-red">LCP 핵심 — 강력 권장</span></div>
                <div class="io-check-sub">
                    1024×1024처럼 화면보다 큰 이미지를 적정 크기로 자동 축소합니다.<br>
                    <?php if ($oversize_count > 0): ?>
                        현재 사이트 진단: <strong style="color:#dc2626"><?= $oversize_count ?>개</strong>의 이미지가 <?= $max_size ?>px을 초과합니다. 일괄 리사이즈를 권장합니다.
                    <?php else: ?>
                        현재 <?= $max_size ?>px을 초과하는 이미지가 없습니다.
                    <?php endif; ?>
                </div>
            </div>
        </label>
    </div>

    <div class="io-row" style="display:flex;gap:14px;align-items:center;background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0">
        <label style="font-size:13px;font-weight:600;color:#475569;margin:0">최대 가로/세로 크기</label>
        <input type="number" name="max_width" min="400" max="3000" step="100"
               value="<?= (int)($cfg['max_width'] ?? 1200) ?>"
               style="width:100px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px">
        <span style="font-size:12px;color:#64748b">px (권장: 1200 — 모바일 최적)</span>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="backup_orig" value="1" <?= !empty($cfg['backup_orig']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">리사이즈 전 원본 백업</div>
                <div class="io-check-sub">
                    리사이즈하기 전 원본 파일을 /data/image-optimizer/backup/ 에 복사 보관합니다.<br>
                    문제 발생 시 수동 복원 가능. 디스크 용량을 차지하므로 안정화 후 삭제 권장.
                </div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="lazy_load" value="1" <?= !empty($cfg['lazy_load']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">Lazy Loading 자동 적용</div>
                <div class="io-check-sub">화면 밖 이미지에 loading="lazy"를 추가합니다. 히어로 이미지(LCP)는 제외하고 나머지에만 적용됩니다.</div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="fix_dimensions" value="1" <?= !empty($cfg['fix_dimensions']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">이미지 크기 자동 추가 <span class="badge badge-warn">CLS 개선</span> <span class="badge badge-red">디자인 충돌 주의</span></div>
                <div class="io-check-sub">
                    width/height가 없는 이미지에 실제 크기를 자동 삽입합니다. CLS(레이아웃 밀림)를 줄입니다.<br>
                    <strong style="color:#dc2626">주의:</strong> 그리드 배너처럼 object-fit:cover로 동일 사이즈를 강제하는 디자인에서는 이미지 비율이 깨질 수 있습니다. 켠 후 메인 페이지를 확인하고 디자인이 깨지면 다시 끄세요.
                </div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="css_preload" value="1" <?= !empty($cfg['css_preload']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">CSS preload 힌트 <span class="badge badge-warn">FCP 개선</span></div>
                <div class="io-check-sub">메인 CSS 파일을 브라우저가 더 일찍 다운로드하도록 preload 힌트를 추가합니다. FCP를 단축시킵니다.</div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check">
            <input type="checkbox" name="font_swap" value="1" <?= !empty($cfg['font_swap']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">font-display: swap</div>
                <div class="io-check-sub">폰트 로딩 중에도 텍스트를 즉시 보여줍니다. FCP 개선 + 사용자 경험 향상.</div>
            </div>
        </label>
    </div>

    <div class="io-row">
        <label class="io-check danger">
            <input type="checkbox" name="async_css" value="1" <?= !empty($cfg['async_css']) ? 'checked' : '' ?>>
            <div class="io-check-inner">
                <div class="io-check-title">CSS 비동기 로딩 <span class="badge badge-red">고급 — FOUC 주의</span></div>
                <div class="io-check-sub">
                    렌더 차단 CSS를 완전히 제거합니다. FCP와 LCP를 크게 단축시키지만 CSS가 늦게 적용되면서 화면이 잠깐 깨져 보일 수 있습니다.<br>
                    테스트 후 사용 권장.
                </div>
            </div>
        </label>
    </div>

    <div class="io-row" style="margin-top: 18px">
        <label style="font-size:13px;font-weight:600;color:#475569">WebP 변환 품질</label>
        <div class="io-quality-wrap">
            <input type="range" name="quality" min="50" max="100" value="<?= (int)($cfg['quality'] ?? 82) ?>"
                   oninput="document.getElementById('io_qval').textContent=this.value">
            <span class="io-quality-val" id="io_qval"><?= (int)($cfg['quality'] ?? 82) ?></span>
        </div>
        <p style="font-size:12px;color:#94a3b8;margin:4px 0 0">82 권장 — 75~85 사이가 최적 (화질과 용량의 균형)</p>
    </div>
</div>

<div class="io-card" style="padding:18px 24px">
    <button type="submit" class="io-btn">설정 저장</button>
</div>
</form>

<!-- 일괄 리사이즈 -->
<div class="io-card" style="border-color:<?= $oversize_count > 0 ? '#fecaca' : '#bbf7d0' ?>;background:<?= $oversize_count > 0 ? '#fff8f8' : '#f8fffa' ?>">
    <h2 style="color:<?= $oversize_count > 0 ? '#dc2626' : '#15803d' ?>;border-color:<?= $oversize_count > 0 ? '#fecaca' : '#bbf7d0' ?>">
        이미지 일괄 리사이즈 <span class="badge badge-red" style="margin-left:6px">LCP 개선 핵심</span>
    </h2>

    <div class="stat-grid" style="margin-bottom:16px">
        <div class="stat-box">
            <div class="stat-num <?= $oversize_count > 0 ? 'red' : 'green' ?>"><?= $oversize_count ?></div>
            <div class="stat-label"><?= $max_size ?>px 초과 파일</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color:#475569"><?= $max_size ?>px</div>
            <div class="stat-label">목표 최대 크기</div>
        </div>
        <div class="stat-box">
            <div class="stat-num green"><?= $backup_count ?></div>
            <div class="stat-label">백업 파일</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color:#475569"><?= $backup_mb ?>MB</div>
            <div class="stat-label">백업 용량</div>
        </div>
    </div>

    <p style="font-size:13px;color:#374151;margin:0 0 14px;line-height:1.7">
        화면 표시 크기보다 큰 이미지는 LCP를 지연시키는 주된 원인입니다. 일괄 리사이즈로 모든 이미지를 <?= $max_size ?>px 이하로 줄이면 용량이 평균 70% 줄어들고 LCP가 크게 개선됩니다.<br>
        <strong style="color:#dc2626">주의: 원본을 덮어씁니다. "원본 백업" 옵션을 켜둔 상태에서 실행하세요.</strong>
    </p>

    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" name="batch_resize" value="1" class="io-btn"
                onclick="return confirm('업로드 폴더의 모든 이미지를 <?= $max_size ?>px 이하로 축소합니다.\n원본은 덮어쓰여집니다 (백업 옵션 켜져 있으면 백업됨).\n계속하시겠습니까?')">
            일괄 리사이즈 실행
        </button>
        <?php if ($backup_count > 0): ?>
        <button type="submit" name="clear_backup" value="1" class="io-btn-sub"
                onclick="return confirm('백업 파일 <?= $backup_count ?>개 (<?= $backup_mb ?>MB)를 삭제합니다.\n복원이 영구히 불가능해집니다.\n계속하시겠습니까?')">
            백업 삭제 (<?= $backup_mb ?>MB 회수)
        </button>
        <?php endif; ?>
    </form>
</div>

<!-- WebP 변환 현황 -->
<div class="io-card">
    <h2>WebP 변환 현황 및 일괄 변환</h2>

    <div style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
            <span style="font-weight:600;color:#475569">변환 진행률</span>
            <span style="color:#16a34a;font-weight:700"><?= $total_webp ?> / <?= $total_orig ?>개 (<?= $convert_pct ?>%)</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $convert_pct ?>%"></div>
        </div>
    </div>

    <div class="stat-grid" style="margin-bottom:16px">
        <div class="stat-box">
            <div class="stat-num" style="color:#475569"><?= $total_orig ?></div>
            <div class="stat-label">JPG/PNG 파일</div>
        </div>
        <div class="stat-box">
            <div class="stat-num green"><?= $total_webp ?></div>
            <div class="stat-label">WebP 변환 완료</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color:#475569"><?= $total_orig - $total_webp ?></div>
            <div class="stat-label">미변환 파일</div>
        </div>
        <div class="stat-box">
            <div class="stat-num green"><?= $saved_kb ?>KB</div>
            <div class="stat-label">절약 용량</div>
        </div>
    </div>

    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" name="batch_convert" value="1" class="io-btn"
                <?= !$webp_supported ? 'disabled' : '' ?>
                onclick="return confirm('업로드 폴더 전체를 WebP로 변환합니다.\n원본은 삭제되지 않습니다.')">
            일괄 변환 실행
        </button>
        <button type="submit" name="clear_cache" value="1" class="io-btn-sub">크기 캐시 삭제</button>
    </form>
    <p style="font-size:12px;color:#94a3b8;margin:10px 0 0">원본 파일은 삭제되지 않습니다. 이미 변환된 파일은 건너뜁니다.</p>
</div>

<!-- 적용 순서 안내 -->
<div class="io-card">
    <h2>적용 순서 (최대 효과)</h2>
    <ol style="font-size:13px;color:#374151;line-height:2.2;padding-left:20px;margin:0">
        <li>설정 저장 (모든 기능 + 자동 리사이즈 활성화)</li>
        <li><strong>일괄 리사이즈 실행</strong> — LCP 핵심, 가장 먼저 실행</li>
        <li><strong>일괄 변환 실행</strong> — 리사이즈된 이미지를 WebP로 변환</li>
        <li>PageSpeed 재측정 → LCP 확인 (목표 2초대)</li>
        <li>점수 90 미달 시 <strong>CSS 비동기 로딩</strong> 활성화 후 재측정</li>
        <li>안정화되면 <strong>백업 삭제</strong>로 디스크 회수</li>
    </ol>
    <div style="margin-top:14px">
        <?php
        $site_url   = function_exists('nb_setting') ? nb_setting('site_url', '') : '';
        if (!$site_url && !empty($_SERVER['HTTP_HOST'])) {
            $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        }
        $ps_url = 'https://pagespeed.web.dev/analysis?url=' . urlencode($site_url) . '&form_factor=mobile';
        ?>
        <a href="<?= htmlspecialchars($ps_url) ?>"
           target="_blank" rel="noopener"
           style="display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#1e293b;font-size:13px;font-weight:600">
            내 사이트 PageSpeed 측정
            <span style="font-size:11px;color:#94a3b8">pagespeed.web.dev</span>
        </a>
    </div>
</div>

</div>

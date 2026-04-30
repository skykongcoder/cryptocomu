<?php
require_once __DIR__ . '/common.php';
adminRequireAuth();

require_once NB_ROOT . '/core/Updater.php';

// ──────────────────────────────────────────
// AJAX 핸들러
// ──────────────────────────────────────────
if (isset($_POST['upd_action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['upd_action'];

    try {
        // 0) 권한 사전 체크
        if ($action === 'preflight') {
            $notWritable = [];
            $check = ['admin', 'core', 'routes', 'theme'];
            foreach ($check as $dir) {
                $path = NB_ROOT . '/' . $dir;
                if (is_dir($path) && !is_writable($path)) $notWritable[] = $dir . '/';
            }
            if (!is_writable(NB_ROOT)) $notWritable[] = '(루트)';
            echo json_encode(['ok' => empty($notWritable), 'not_writable' => $notWritable]);
            exit;
        }

        // 1) 버전 체크 (강제 갱신)
        if ($action === 'check') {
            $info = Updater::fetchLatest(true);
            echo json_encode(['ok' => true, 'info' => $info, 'current' => NB_VERSION]);
            exit;
        }

        // 2) 다운로드
        if ($action === 'download') {
            $info = Updater::fetchLatest(false);
            $url  = (string)($info['zip_url'] ?? '');
            $zip  = Updater::download($url);
            $_SESSION['upd_zip'] = $zip;
            $_SESSION['upd_ver'] = (string)($info['version'] ?? '');
            echo json_encode(['ok' => true, 'size' => filesize($zip)]);
            exit;
        }

        // 3) 압축 해제
        if ($action === 'extract') {
            $zip = $_SESSION['upd_zip'] ?? '';
            if (!$zip || !file_exists($zip)) throw new RuntimeException('다운로드된 파일이 없습니다. 다시 시도해주세요.');
            $dir = Updater::extract($zip);
            $_SESSION['upd_dir'] = $dir;
            echo json_encode(['ok' => true]);
            exit;
        }

        // 4) 파일 교체
        if ($action === 'apply') {
            $dir = $_SESSION['upd_dir'] ?? '';
            $zip = $_SESSION['upd_zip'] ?? '';
            $ver = $_SESSION['upd_ver'] ?? '';
            if (!$dir || !is_dir($dir)) throw new RuntimeException('압축 해제 데이터가 없습니다. 다시 시도해주세요.');

            $applied = Updater::apply($dir);
            Updater::cleanup($zip, $dir);
            unset($_SESSION['upd_zip'], $_SESSION['upd_dir'], $_SESSION['upd_ver']);

            // config/version.php 버전 번호 업데이트
            if ($ver) {
                file_put_contents(
                    NB_ROOT . '/config/version.php',
                    "<?php\ndefine('NB_VERSION', " . var_export($ver, true) . ");\n"
                );
            }

            if (class_exists('AdminLog')) AdminLog::write('nuriboard_update', 'system', 0, "v{$ver}");

            echo json_encode(['ok' => true, 'version' => $ver, 'count' => count($applied)]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => '알 수 없는 요청입니다.']);
        exit;

    } catch (Throwable $e) {
        // 로그 기록
        $logDir = NB_ROOT . '/data/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        @file_put_contents(
            $logDir . '/update-error.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n",
            FILE_APPEND
        );
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ──────────────────────────────────────────
// 페이지 렌더링
// ──────────────────────────────────────────
$info      = Updater::fetchLatest(false);
$latest    = (string)($info['version'] ?? '');
$hasUpdate = $latest !== '' && version_compare($latest, NB_VERSION, '>');
$checkedAt = (int)($info['_checked_at'] ?? 0);

adminHeader('update');
?>

<style>
.upd-wrap{max-width:700px}
.upd-head{margin-bottom:20px}
.upd-head h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.upd-head p{margin:0;font-size:13px;color:#6b7280}

.upd-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;overflow:hidden}
.upd-card-head{padding:14px 20px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:700;color:#111827;display:flex;align-items:center;gap:8px}
.upd-card-body{padding:20px}

.upd-ver-row{display:flex;align-items:center;gap:16px;padding:16px;border-radius:10px;background:#f9fafb;margin-bottom:16px}
.upd-ver-row.has-update{background:#f0fdf4;border:1px solid #86efac}
.upd-ver-row.up-to-date{background:#f0fdf4;border:1px solid #86efac}
.upd-ver-block .label{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.upd-ver-block .num{font-size:26px;font-weight:800;color:#111827;font-family:monospace}
.upd-ver-arrow{font-size:22px;color:#22c55e;font-weight:700}
.upd-ver-status{font-size:14px;font-weight:700;color:#16a34a}

.upd-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:#22c55e;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:background .15s}
.upd-btn:hover:not(:disabled){background:#16a34a}
.upd-btn:disabled{opacity:.5;cursor:not-allowed}
.upd-btn-ghost{background:#fff;color:#374151;border:1px solid #d1d5db}
.upd-btn-ghost:hover:not(:disabled){background:#f3f4f6}

.upd-progress{display:none;margin-top:4px}
.upd-step{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:8px;margin-bottom:6px;background:#f9fafb;border:1px solid #e5e7eb;font-size:14px;color:#6b7280;transition:all .3s}
.upd-step.active{background:#f0fdf4;border-color:#86efac;color:#15803d;font-weight:600}
.upd-step.done{background:#f0fdf4;border-color:#86efac;color:#15803d}
.upd-step.fail{background:#fef2f2;border-color:#fca5a5;color:#dc2626;font-weight:600}
.upd-step-icon{width:24px;height:24px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#6b7280;flex-shrink:0;transition:all .3s}
.upd-step.active .upd-step-icon{background:#22c55e;color:#fff}
.upd-step.done .upd-step-icon{background:#22c55e;color:#fff}
.upd-step.fail .upd-step-icon{background:#dc2626;color:#fff}

/* 진행률 바 */
.upd-pbar-wrap{margin:16px 0 4px}
.upd-pbar-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.upd-pbar-label{font-size:13px;font-weight:600;color:#15803d}
.upd-pbar-pct{font-size:13px;font-weight:700;color:#15803d}
.upd-pbar-track{height:10px;background:#e5e7eb;border-radius:10px;overflow:hidden}
.upd-pbar-fill{height:100%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:10px;width:0%;transition:width .5s ease}

/* 상태 텍스트 */
.upd-status-text{margin-top:10px;padding:10px 14px;background:#f0fdf4;border-radius:8px;font-size:13px;color:#15803d;min-height:40px;display:flex;align-items:center;gap:8px}
.upd-status-text svg{flex-shrink:0}

/* 스피너 */
@keyframes upd-spin{to{transform:rotate(360deg)}}
.upd-spinner{animation:upd-spin .7s linear infinite;display:inline-block}

.upd-warn{display:flex;align-items:flex-start;gap:10px;padding:14px 16px;margin-bottom:16px;background:#fef3c7;border:1px solid #fcd34d;border-left:4px solid #f59e0b;border-radius:8px}
.upd-warn svg{flex-shrink:0;color:#d97706;margin-top:2px}
.upd-warn b{display:block;font-size:13px;color:#78350f;margin-bottom:3px}
.upd-warn span{font-size:12px;color:#92400e;line-height:1.6}

.upd-success{display:none;flex-direction:column;align-items:center;padding:30px;text-align:center}
.upd-success svg{color:#22c55e;margin-bottom:12px}
.upd-success h3{font-size:20px;font-weight:700;color:#15803d;margin:0 0 6px}
.upd-success p{font-size:13px;color:#6b7280;margin:0}

.upd-fail-box{display:none;padding:16px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;margin-top:12px}
.upd-fail-box b{display:block;font-size:13px;color:#991b1b;margin-bottom:8px}
.upd-fail-log{font-size:12px;color:#7f1d1d;font-family:monospace;white-space:pre-wrap;word-break:break-all;max-height:120px;overflow-y:auto;margin-bottom:10px}
.upd-fail-copy{padding:5px 12px;font-size:12px;background:#fff;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;cursor:pointer}
.upd-fail-copy:hover{background:#fef2f2}
.upd-fail-contact{font-size:12px;color:#6b7280;margin-top:8px}

.upd-changelog{padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-top:14px;font-size:13px;color:#166534}
.upd-changelog b{display:block;margin-bottom:6px;color:#14532d}
.upd-changelog ul{margin:0;padding-left:18px;line-height:1.8}
</style>

<div class="upd-wrap">
    <div class="upd-head">
        <h1>누리보드 업데이트</h1>
        <p>버튼 한 번으로 최신 버전으로 업데이트합니다. 설정과 데이터는 그대로 유지됩니다.</p>
    </div>

    <!-- 버전 현황 -->
    <div class="upd-card">
        <div class="upd-card-head">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            버전 정보
        </div>
        <div class="upd-card-body">
            <div class="upd-ver-row <?= $hasUpdate ? 'has-update' : ($latest ? 'up-to-date' : '') ?>">
                <div class="upd-ver-block">
                    <div class="label">현재 버전</div>
                    <div class="num"><?= htmlspecialchars(NB_VERSION) ?></div>
                </div>
                <?php if ($hasUpdate): ?>
                <div class="upd-ver-arrow">→</div>
                <div class="upd-ver-block">
                    <div class="label">새 버전</div>
                    <div class="num" style="color:#16a34a"><?= htmlspecialchars($latest) ?></div>
                </div>
                <?php elseif ($latest): ?>
                <div class="upd-ver-block" style="margin-left:auto;text-align:right">
                    <div class="label">상태</div>
                    <div class="upd-ver-status">✓ 최신 버전입니다</div>
                </div>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button type="button" class="upd-btn upd-btn-ghost" onclick="updCheck(this)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    버전 확인
                </button>
                <?php if ($hasUpdate): ?>
                <button type="button" class="upd-btn" id="updStartBtn" onclick="updStart()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?= htmlspecialchars($latest) ?>로 업데이트
                </button>
                <?php endif; ?>
                <span style="font-size:11px;color:#9ca3af" id="updCheckedAt">
                    <?php if ($checkedAt): ?>마지막 확인: <?= date('Y-m-d H:i', $checkedAt) ?><?php endif; ?>
                </span>
            </div>

            <?php if (!empty($info['changelog'])): ?>
            <div class="upd-changelog">
                <b>업데이트 내용</b>
                <ul>
                    <?php foreach ((array)$info['changelog'] as $line): ?>
                    <li><?= htmlspecialchars($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 진행 상황 카드 -->
    <div class="upd-card" id="updProgressCard" style="display:none">
        <div class="upd-card-head">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            업데이트 진행 중
        </div>
        <div class="upd-card-body">
            <div class="upd-warn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <b>업데이트 중에는 이 창을 닫지 마세요</b>
                    <span>완료 메시지가 뜰 때까지 페이지를 이동하거나 새로고침하지 마세요.</span>
                </div>
            </div>

            <!-- 진행률 바 -->
            <div class="upd-pbar-wrap" id="updPbarWrap" style="display:none">
                <div class="upd-pbar-row">
                    <span class="upd-pbar-label" id="updPbarLabel">준비 중...</span>
                    <span class="upd-pbar-pct" id="updPbarPct">0%</span>
                </div>
                <div class="upd-pbar-track"><div class="upd-pbar-fill" id="updPbarFill"></div></div>
                <div class="upd-status-text" id="updStatusText">
                    <svg class="upd-spinner" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    <span id="updStatusMsg">업데이트를 시작합니다...</span>
                </div>
            </div>

            <div class="upd-progress" id="updProgress">
                <div class="upd-step" data-step="1">
                    <div class="upd-step-icon" id="updIcon1">1</div>
                    최신 파일 다운로드
                </div>
                <div class="upd-step" data-step="2">
                    <div class="upd-step-icon" id="updIcon2">2</div>
                    압축 해제
                </div>
                <div class="upd-step" data-step="3">
                    <div class="upd-step-icon" id="updIcon3">3</div>
                    파일 교체 적용
                </div>
                <div class="upd-step" data-step="4">
                    <div class="upd-step-icon" id="updIcon4">4</div>
                    완료
                </div>
            </div>

            <!-- 완료 메시지 -->
            <div class="upd-success" id="updSuccess">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h3 id="updSuccessTitle">업데이트 완료!</h3>
                <p id="updSuccessDesc">잠시 후 자동으로 새로고침됩니다.</p>
            </div>

            <!-- 실패 메시지 -->
            <div class="upd-fail-box" id="updFailBox">
                <b>업데이트 중 문제가 발생했습니다</b>
                <div class="upd-fail-log" id="updFailLog"></div>
                <button type="button" class="upd-fail-copy" onclick="updCopyLog()">로그 복사</button>
                <div class="upd-fail-contact">로그를 복사한 후 <strong>누리코리아에 문의</strong>해 주세요.</div>
            </div>
        </div>
    </div>

    <!-- 안내 -->
    <div class="upd-card">
        <div class="upd-card-head">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            업데이트 안내
        </div>
        <div class="upd-card-body" style="font-size:13px;color:#374151;line-height:1.8">
            <ul style="padding-left:18px;margin:0">
                <li><b>설정 · 회원 · 게시글 데이터는 변경되지 않습니다.</b></li>
                <li>업데이트 후 브라우저 캐시를 지우면 (Ctrl+Shift+R) 변경사항이 바로 반영됩니다.</li>
                <li>누리코리아 호스팅 사용자는 서버에서 직접 관리하므로 이 기능이 필요하지 않습니다.</li>
            </ul>
        </div>
    </div>
</div>

<script>
window.addEventListener('beforeunload', function(e) {
    if (window._updRunning) {
        e.preventDefault();
        e.returnValue = '업데이트 진행 중입니다. 정말 나가시겠습니까?';
        return e.returnValue;
    }
});

var _updLog = [];
var SPINNER = '<svg class="upd-spinner" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';
var CHECK   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
var CROSS   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

function updSetStep(n, state) {
    document.querySelectorAll('#updProgress .upd-step').forEach(function(el) {
        var s = parseInt(el.dataset.step);
        var icon = document.getElementById('updIcon' + s);
        el.classList.remove('active', 'done', 'fail');
        if (s < n) {
            el.classList.add('done');
            if (icon) icon.innerHTML = CHECK;
        } else if (s === n) {
            el.classList.add(state);
            if (icon) icon.innerHTML = (state === 'active') ? SPINNER : (state === 'done' ? CHECK : CROSS);
        }
    });
}

function updSetProgress(pct, label, msg) {
    document.getElementById('updPbarWrap').style.display = '';
    document.getElementById('updPbarFill').style.width = pct + '%';
    document.getElementById('updPbarPct').textContent = pct + '%';
    document.getElementById('updPbarLabel').textContent = label;
    if (msg) document.getElementById('updStatusMsg').textContent = msg;
}

async function updAjax(action) {
    var fd = new FormData();
    fd.append('upd_action', action);
    var r = await fetch(location.href, { method: 'POST', body: fd });
    return await r.json();
}

async function updCheck(btn) {
    btn.disabled = true;
    btn.textContent = '확인 중...';
    try {
        var r = await updAjax('check');
        if (r.ok) {
            location.reload();
        } else {
            alert('버전 확인 실패: ' + (r.error || '알 수 없는 오류'));
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> 버전 확인';
        }
    } catch(e) {
        alert('네트워크 오류가 발생했습니다.');
        btn.disabled = false;
    }
}

async function updStart() {
    if (!confirm('누리보드를 최신 버전으로 업데이트합니다.\n설정과 데이터는 그대로 유지됩니다.\n\n진행하시겠습니까?')) return;

    var startBtn = document.getElementById('updStartBtn');
    if (startBtn) startBtn.disabled = true;

    document.getElementById('updProgressCard').style.display = '';
    document.getElementById('updProgress').style.display = '';
    document.getElementById('updSuccess').style.display = 'none';
    document.getElementById('updFailBox').style.display = 'none';
    _updLog = [];
    window._updRunning = true;

    function log(msg) { _updLog.push(msg); }

    try {
        updSetProgress(2, '권한 확인 중...', '서버에 파일을 쓸 수 있는지 확인하고 있습니다...');
        log('[0/4] 서버 권한 확인 중...');
        var pre = await updAjax('preflight');
        if (!pre.ok) {
            throw new Error('파일 쓰기 권한이 없습니다: ' + (pre.not_writable || []).join(', ') + '\n\nFTP/SSH로 해당 폴더의 소유자를 웹서버 사용자(www-data 등)로 변경하거나, 누리코리아에 문의해주세요.');
        }
        log('[0/4] 권한 확인 완료');

        updSetStep(1, 'active');
        updSetProgress(10, '파일 다운로드 중...', '누리코리아 서버에서 최신 파일을 받고 있습니다...');
        log('[1/4] 최신 파일 다운로드 중...');
        var r = await updAjax('download');
        if (!r.ok) throw new Error('다운로드 실패: ' + r.error);
        log('[1/4] 완료 (' + Math.round(r.size / 1024) + ' KB)');
        updSetStep(1, 'done');

        updSetStep(2, 'active');
        updSetProgress(40, '압축 해제 중...', '다운로드한 파일의 압축을 풀고 있습니다...');
        log('[2/4] 압축 해제 중...');
        r = await updAjax('extract');
        if (!r.ok) throw new Error('압축 해제 실패: ' + r.error);
        log('[2/4] 완료');
        updSetStep(2, 'done');

        updSetStep(3, 'active');
        updSetProgress(70, '파일 교체 중...', '서버에 새 파일을 적용하고 있습니다. 잠시만 기다려주세요...');
        log('[3/4] 파일 교체 중...');
        r = await updAjax('apply');
        if (!r.ok) throw new Error('파일 교체 실패: ' + r.error);
        log('[3/4] ' + r.count + '개 파일 교체 완료');
        updSetStep(3, 'done');

        updSetStep(4, 'done');
        updSetProgress(100, '업데이트 완료!', '모든 파일이 성공적으로 교체되었습니다.');
        log('[완료] v' + r.version + ' 업데이트 성공!');
        window._updRunning = false;

        document.getElementById('updProgress').style.display = 'none';
        document.getElementById('updPbarWrap').style.display = 'none';
        var suc = document.getElementById('updSuccess');
        suc.style.display = 'flex';
        document.getElementById('updSuccessTitle').textContent = 'v' + r.version + ' 업데이트 완료!';
        document.getElementById('updSuccessDesc').textContent = '3초 후 자동으로 새로고침됩니다.';
        setTimeout(function() { location.reload(); }, 3000);

    } catch(e) {
        log('[실패] ' + e.message);
        window._updRunning = false;

        var activeStep = document.querySelector('#updProgress .upd-step.active');
        if (activeStep) {
            var failN = parseInt(activeStep.dataset.step);
            updSetStep(failN, 'fail');
        }
        updSetProgress(0, '업데이트 실패', e.message);
        document.getElementById('updPbarFill').style.background = '#ef4444';
        document.getElementById('updPbarPct').style.color = '#dc2626';
        document.getElementById('updPbarLabel').style.color = '#dc2626';
        document.getElementById('updStatusText').style.background = '#fef2f2';
        document.getElementById('updStatusText').querySelector('svg').style.stroke = '#dc2626';
        document.getElementById('updStatusText').querySelector('svg').classList.remove('upd-spinner');
        document.getElementById('updStatusMsg').style.color = '#dc2626';

        document.getElementById('updFailLog').textContent = _updLog.join('\n');
        document.getElementById('updFailBox').style.display = '';

        if (startBtn) startBtn.disabled = false;
    }
}

function updCopyLog() {
    var text = _updLog.join('\n');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert('로그가 클립보드에 복사되었습니다.\n누리코리아에 문의할 때 붙여넣기 해주세요.');
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('로그가 복사되었습니다.');
    }
}
</script>

<?php adminFooter(); ?>

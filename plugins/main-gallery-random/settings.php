<?php
/**
 * 메인 그리드 랜덤화 — 설정 페이지
 */

$cfg = _mgr_load_cfg();
$msg = '';
$msg_type = 'ok';

// ── 강제 갱신 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_refresh'])) {
    _mgr_clear_cache();
    $msg = '캐시가 삭제되었습니다. 다음 메인 페이지 방문 시 새 이미지로 교체됩니다.';
}

// ── 설정 저장 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_save'])) {
    $cfg['enabled']   = isset($_POST['enabled']);
    $cfg['count']     = max(3, min(24, (int)($_POST['count'] ?? 6)));
    $cfg['cache_ttl'] = max(60, (int)($_POST['cache_ttl'] ?? 3600));
    $cfg['pool_size'] = max(10, min(500, (int)($_POST['pool_size'] ?? 100)));
    $cfg['board_id']  = trim($_POST['board_id'] ?? '');

    _mgr_save_cfg($cfg);
    _mgr_clear_cache();
    $cfg = _mgr_load_cfg();
    if (!$msg) $msg = '설정이 저장되었습니다. 캐시도 함께 초기화되어 다음 방문 시 새 이미지로 교체됩니다.';
}

// ── 캐시 상태 ──
$cache_file   = _mgr_cache_file();
$cache_exists = file_exists($cache_file);
$cache_age    = $cache_exists ? (time() - filemtime($cache_file)) : 0;
$cache_left   = $cache_exists ? max(0, (int)$cfg['cache_ttl'] - $cache_age) : 0;
$cache_count  = 0;
if ($cache_exists) {
    $cdata = json_decode(file_get_contents($cache_file), true);
    if (is_array($cdata)) $cache_count = count($cdata);
}

// 갱신 주기 옵션
$ttl_options = [
    60     => '1분 (테스트용)',
    600    => '10분',
    1800   => '30분',
    3600   => '1시간',
    7200   => '2시간',
    10800  => '3시간',
    21600  => '6시간',
    43200  => '12시간',
    86400  => '24시간 (하루)',
    172800 => '48시간 (이틀)',
];

// 갤러리 게시판 목록
$gallery_boards = [];
if (class_exists('DB')) {
    try {
        $prefix = DB::getPrefix();
        $rows = DB::fetchAll("SELECT board_id, title FROM {$prefix}boards WHERE board_type = 'gallery' AND is_active = 1 ORDER BY title");
        if (is_array($rows)) $gallery_boards = $rows;
    } catch (Exception $e) {}
}

function _mgr_human_time(int $sec): string {
    if ($sec < 60)    return $sec . '초';
    if ($sec < 3600)  return floor($sec / 60) . '분 ' . ($sec % 60) . '초';
    if ($sec < 86400) return floor($sec / 3600) . '시간 ' . floor(($sec % 3600) / 60) . '분';
    return floor($sec / 86400) . '일 ' . floor(($sec % 86400) / 3600) . '시간';
}
?>

<style>
.mr-wrap { max-width: 720px; font-family: -apple-system, sans-serif; }
.mr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.mr-card h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; letter-spacing: .5px; }
.mr-row { margin-bottom: 18px; }
.mr-row > label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; }
.mr-input, .mr-select {
    width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px;
    font-size: 14px; box-sizing: border-box;
}
.mr-input:focus, .mr-select:focus {
    outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.12);
}
.mr-help {
    font-size: 12px; color: #475569; margin: 6px 0 0; line-height: 1.7;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 10px 12px;
}
.mr-help strong { color: #16a34a; }
.mr-btn { padding: 10px 28px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.mr-btn:hover { background: #15803d; }
.mr-btn-sub { padding: 9px 20px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.mr-btn-sub:hover { background: #e2e8f0; }
.mr-msg-ok  { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.mr-msg-err { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.mr-check { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px; }
.mr-check:hover { background: #f8fafc; }
.mr-check input { margin-top: 2px; accent-color: #22c55e; width: 15px; height: 15px; flex-shrink: 0; }
.mr-check-title { font-size: 13px; font-weight: 600; color: #1e293b; }
.mr-check-sub { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.6; }
.mr-stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
.mr-stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center; }
.mr-stat-num { font-size: 22px; font-weight: 800; color: #16a34a; }
.mr-stat-label { font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.4; }
.mr-section-intro {
    font-size: 13px; color: #475569; line-height: 1.7;
    background: #f8fafc; border-left: 3px solid #16a34a;
    padding: 10px 14px; margin-bottom: 18px; border-radius: 0 6px 6px 0;
}
</style>

<div class="mr-wrap">

<?php if ($msg): ?>
<div class="mr-msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- 동작 방식 -->
<div class="mr-card" style="background:#f0fdf4;border-color:#bbf7d0">
    <h2 style="border-color:#bbf7d0;color:#15803d">이 플러그인은 무엇을 하나요?</h2>
    <div style="font-size:13px;color:#374151;line-height:1.9">
        메인 페이지 그리드 배너에 표시되는 이미지 게시판 글을 <strong>일정 시간마다 랜덤으로 교체</strong>합니다.<br>
        <br>
        <strong style="color:#15803d">기대 효과</strong>
        <ul style="margin:6px 0 0;padding-left:20px;line-height:1.8">
            <li>방문자가 사이트에 올 때마다 다른 이미지를 봐서 신선함 유지</li>
            <li>오래된 글도 메인에 노출되어 클릭률 상승</li>
            <li>구글이 "콘텐츠가 자주 갱신되는 사이트"로 인식 → SEO 가산점</li>
            <li>이미지 게시판이 풍부한 사이트의 활용도 극대화</li>
        </ul>
    </div>
</div>

<!-- 캐시 상태 -->
<div class="mr-card">
    <h2>현재 상태</h2>

    <div class="mr-stat-grid">
        <div class="mr-stat-box">
            <div class="mr-stat-num"><?= $cache_count ?></div>
            <div class="mr-stat-label">현재 캐시된<br>이미지 개수</div>
        </div>
        <div class="mr-stat-box">
            <div class="mr-stat-num" style="color:#475569">
                <?= $cache_exists ? _mgr_human_time($cache_age) . ' 전' : '-' ?>
            </div>
            <div class="mr-stat-label">마지막 갱신</div>
        </div>
        <div class="mr-stat-box">
            <div class="mr-stat-num" style="color:#16a34a">
                <?= $cache_exists ? (_mgr_human_time($cache_left) . ' 후') : '곧' ?>
            </div>
            <div class="mr-stat-label">다음 자동 갱신</div>
        </div>
    </div>

    <form method="POST" style="display:inline">
        <button type="submit" name="force_refresh" value="1" class="mr-btn-sub"
                onclick="return confirm('캐시를 초기화하고 다음 방문 시 새 이미지로 교체합니다.\n계속하시겠습니까?')">
            지금 즉시 새로고침
        </button>
    </form>
    <span style="font-size:12px;color:#94a3b8;margin-left:8px">
        설정한 갱신 주기를 기다리지 않고 바로 새 이미지로 바꿉니다.
    </span>
</div>

<form method="POST">
<input type="hidden" name="plugin_save" value="1">

<!-- 활성화 -->
<div class="mr-card">
    <h2>1단계 — 플러그인 켜기</h2>
    <label class="mr-check">
        <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
        <div>
            <div class="mr-check-title">플러그인 활성화</div>
            <div class="mr-check-sub">
                체크하면 메인 그리드가 랜덤으로 교체됩니다. 체크 해제하면 기본 누리보드 동작(최신순 고정)으로 돌아갑니다.
            </div>
        </div>
    </label>
</div>

<!-- 갱신 주기 -->
<div class="mr-card">
    <h2>2단계 — 갱신 주기 설정</h2>
    <div class="mr-section-intro">
        몇 시간마다 새 이미지로 교체할지 선택합니다.<br>
        짧을수록 방문자에게 신선해 보이고, 길수록 서버 부담이 적습니다.
    </div>

    <div class="mr-row">
        <label>갱신 주기</label>
        <select name="cache_ttl" class="mr-select">
            <?php foreach ($ttl_options as $sec => $label): ?>
                <option value="<?= $sec ?>" <?= (int)$cfg['cache_ttl'] === $sec ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="mr-help">
            <strong>추천 설정</strong><br>
            방문자가 많은 사이트: <strong>1~3시간</strong> (자주 새로워 보이지만 부담 적음)<br>
            방문자가 적은 사이트: <strong>6~12시간</strong> (방문자가 다시 와도 새로 보임)<br>
            테스트용: <strong>1분</strong> (설정 후 새로고침으로 즉시 확인 가능)<br>
            <br>
            <strong>참고</strong> 캐시는 메인 페이지 첫 방문자가 들어왔을 때 갱신됩니다. 트래픽이 없으면 갱신도 없으니 서버에 무리 없습니다.
        </div>
    </div>
</div>

<!-- 표시 설정 -->
<div class="mr-card">
    <h2>3단계 — 표시 설정</h2>

    <div class="mr-row">
        <label>그리드에 표시할 이미지 개수</label>
        <select name="count" class="mr-select" style="max-width:240px">
            <?php foreach ([3, 6, 9, 12, 15, 18, 24] as $n): ?>
                <option value="<?= $n ?>" <?= (int)$cfg['count'] === $n ? 'selected' : '' ?>>
                    <?= $n ?>개 (<?= $n / 3 ?>줄)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="mr-help">
            가로 3개씩 표시되므로 3의 배수를 권장합니다.<br>
            <strong>일반</strong> 6개 (2줄) — 깔끔함<br>
            <strong>풍부함</strong> 9~12개 (3~4줄) — 콘텐츠 풍성<br>
            <strong>대용량</strong> 15개 이상 — 이미지 게시판이 많은 사이트
        </div>
    </div>

    <div class="mr-row">
        <label>랜덤 풀 크기 (이 글들 중에서 무작위 선택)</label>
        <select name="pool_size" class="mr-select" style="max-width:240px">
            <option value="20"  <?= (int)$cfg['pool_size'] === 20  ? 'selected' : '' ?>>최근 20개 글에서</option>
            <option value="50"  <?= (int)$cfg['pool_size'] === 50  ? 'selected' : '' ?>>최근 50개 글에서</option>
            <option value="100" <?= (int)$cfg['pool_size'] === 100 ? 'selected' : '' ?>>최근 100개 글에서</option>
            <option value="200" <?= (int)$cfg['pool_size'] === 200 ? 'selected' : '' ?>>최근 200개 글에서</option>
            <option value="500" <?= (int)$cfg['pool_size'] === 500 ? 'selected' : '' ?>>최근 500개 글 전체에서</option>
        </select>
        <div class="mr-help">
            <strong>예시</strong> "최근 100개 글에서" + "표시 개수 6개" 설정 시,<br>
            최근 100개 글 중 무작위 6개를 골라 표시합니다.<br><br>
            <strong>풀이 클수록</strong> 다양한 글이 노출되지만, 너무 오래된 글이 나올 수 있습니다.<br>
            <strong>풀이 작을수록</strong> 최신 글 위주로 노출됩니다.
        </div>
    </div>

    <?php if (!empty($gallery_boards)): ?>
    <div class="mr-row">
        <label>특정 게시판만 사용 (선택)</label>
        <select name="board_id" class="mr-select" style="max-width:300px">
            <option value="">모든 갤러리 게시판</option>
            <?php foreach ($gallery_boards as $b): ?>
                <option value="<?= htmlspecialchars($b['board_id']) ?>" <?= $cfg['board_id'] === $b['board_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="mr-help">
            여러 갤러리 게시판이 있을 때 한 곳만 사용하고 싶으면 선택하세요.<br>
            기본값은 "모든 갤러리 게시판"으로, 모든 이미지 게시판의 글이 함께 사용됩니다.
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 저장 버튼 -->
<div class="mr-card" style="padding:18px 24px">
    <button type="submit" class="mr-btn">설정 저장</button>
    <span style="font-size:12px;color:#94a3b8;margin-left:12px">
        설정을 저장하면 캐시가 초기화되고 다음 메인 페이지 방문 시 새 이미지로 교체됩니다.
    </span>
</div>

</form>

<!-- 작동 확인 안내 -->
<div class="mr-card">
    <h2>제대로 작동하는지 확인하는 방법</h2>
    <div style="font-size:13px;color:#475569;line-height:1.8">
        <ol style="padding-left:20px;margin:0">
            <li>이미지 게시판에 최소 6개 이상의 이미지 글이 있어야 합니다 (글이 적으면 같은 이미지가 반복됨)</li>
            <li>설정 저장 후 메인 페이지 방문 (강력 새로고침: Ctrl+F5)</li>
            <li>위에서 설정한 갱신 주기만큼 기다린 후 다시 메인 페이지 방문</li>
            <li>이미지 순서가 바뀌어 있으면 정상 작동</li>
            <li>만약 즉시 확인하고 싶으면 위 "지금 즉시 새로고침" 버튼 클릭</li>
        </ol>
    </div>
</div>

</div>

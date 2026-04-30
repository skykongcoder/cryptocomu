<?php
/**
 * Authority Connector — 설정 페이지
 */
require_once __DIR__ . '/../_openrouter_models.php';

$cfg = _ac_load_config();
$msg = '';
$batch_result = '';

// ===== 캐시 삭제 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    _ac_clear_all_cache();
    $msg = '캐시가 삭제되었습니다.';
}

// ===== 로그 삭제 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    @file_put_contents(_ac_data_dir() . '/debug.log', '');
    $msg = '로그가 삭제되었습니다.';
}

// ===== 설정 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_save'])) {
    $cfg['enabled']        = isset($_POST['enabled']);
    $cfg['openai_api_key'] = trim($_POST['openai_api_key'] ?? '');
    $cfg['openai_model']   = nb_openrouter_is_valid($_POST['openai_model'] ?? '')
                             ? $_POST['openai_model'] : 'meta-llama/llama-3.3-70b-instruct:free';
    $boards_checked        = $_POST['allowed_boards'] ?? [];
    $cfg['allowed_boards'] = implode(',', array_map('trim', (array)$boards_checked));
    $cfg['max_links']      = in_array((int)($_POST['max_links'] ?? 2), [1, 2], true)
                             ? (int)$_POST['max_links'] : 2;
    _ac_save_config($cfg);
    $cfg = _ac_load_config();
    $msg = '설정이 저장되었습니다.';
}

// ===== 수동 일괄 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_batch'])) {
    @set_time_limit(300);
    $batch_boards = $_POST['batch_boards'] ?? [];
    $batch_boards = array_values(array_filter(array_map('trim', (array)$batch_boards)));

    if (empty($batch_boards)) {
        $batch_result = '<span style="color:#ef4444">게시판을 선택하세요.</span>';
    } else {
        $prefix     = DB::getPrefix();
        $cfg_now    = _ac_load_config();
        $processed  = 0;
        $linked     = 0;
        $errors     = 0;

        foreach ($batch_boards as $bid) {
            try {
                $posts = DB::fetchAll(
                    "SELECT id, title, content FROM {$prefix}posts WHERE board_id = ? ORDER BY id DESC LIMIT 200",
                    [$bid]
                );
                foreach ((array)$posts as $post) {
                    $processed++;
                    $new_content = _ac_process_content(
                        $post['content'] ?? '',
                        $post['title']   ?? '',
                        $cfg_now
                    );
                    if ($new_content !== ($post['content'] ?? '')) {
                        DB::update($prefix . 'posts', ['content' => $new_content], 'id = ?', [(int)$post['id']]);
                        $linked++;
                    }
                }
            } catch (Throwable $e) {
                $errors++;
                _ac_log("일괄처리 오류 board={$bid}: " . $e->getMessage());
            }
        }
        $batch_result = "처리 완료 — 총 <strong>{$processed}</strong>개 게시글 중 "
                      . "<strong style='color:#15803d'>{$linked}</strong>개에 링크 삽입"
                      . ($errors > 0 ? " (<span style='color:#ef4444'>오류 {$errors}건</span>)" : "");
    }
}

// ===== 게시판 목록 =====
$boardList = [];
try {
    $boardList = DB::fetchAll(
        "SELECT board_id, title FROM " . DB::getPrefix() . "boards WHERE is_active = 1 ORDER BY sort_order ASC"
    );
} catch (Throwable $e) {
    _ac_log("게시판 목록 로드 실패: " . $e->getMessage());
}

$allowedArr = array_filter(array_map('trim', explode(',', $cfg['allowed_boards'] ?? '')));
$cacheCount = _ac_cache_count();
$logFile    = _ac_data_dir() . '/debug.log';
$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
?>

<style>
.ac-wrap { max-width: 760px; font-family: -apple-system, sans-serif; }
.ac-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.ac-card h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; letter-spacing: .5px; }
.ac-row { margin-bottom: 18px; }
.ac-row:last-child { margin-bottom: 0; }
.ac-row > label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.ac-desc { font-size: 12px; color: #94a3b8; margin: 4px 0 0; }
.ac-input { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.ac-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.12); }
.ac-btn  { padding: 10px 28px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.ac-btn:hover { background: #15803d; }
.ac-btn-sm { padding: 6px 14px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; cursor: pointer; }
.ac-btn-sm:hover { background: #e2e8f0; }
.ac-btn-green { padding: 9px 22px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.ac-btn-green:hover { background: #15803d; }
.ac-msg  { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.ac-badge { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; margin-left: 8px; vertical-align: middle; }
.ac-badge-ai       { background: #dcfce7; color: #15803d; }
.ac-badge-fallback { background: #f3f4f6; color: #6b7280; }
.ac-board-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; margin-top: 6px; }
.ac-board-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: border-color .15s, background .15s; }
.ac-board-item:hover { border-color: #22c55e; background: #f0fdf4; }
.ac-board-item input[type=checkbox] { width: 15px; height: 15px; accent-color: #22c55e; cursor: pointer; flex-shrink: 0; }
.ac-board-item span { font-size: 13px; color: #374151; }
.ac-board-item small { font-size: 11px; color: #94a3b8; }
.ac-batch-result { padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 13px; margin-top: 14px; }
.ac-cache-bar { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
</style>

<div class="ac-wrap">

<?php if ($msg): ?>
<div class="ac-msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- 동작 안내 -->
<div class="ac-card" style="background:#f0fdf4;border-color:#bbf7d0">
    <h2 style="border-color:#bbf7d0;color:#15803d">이 플러그인은 이렇게 동작합니다</h2>
    <div style="font-size:13px;color:#374151;line-height:2">
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">언제 작동하나요?</strong><br>
            게시글을 저장(등록·수정)할 때 1회만 동작합니다. 본문에 위키백과 링크를 삽입한 뒤 DB에 저장하므로 방문자 열람 시 추가 처리가 없습니다.
        </div>
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">어떻게 키워드를 찾나요?</strong><br>
            API 키가 있으면 AI가 위키백과 문서가 존재할 전문 용어를 추출합니다. 없으면 제목의 단어로 위키백과를 직접 조회합니다.
        </div>
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">링크는 몇 개 삽입하나요?</strong><br>
            게시글당 최대 1~2개입니다. 위키백과에 문서가 없는 키워드는 건너뜁니다. 이미 링크된 단어는 재삽입하지 않습니다.
        </div>
        <div>
            <strong style="color:#15803d">기존 게시글에 적용하려면?</strong><br>
            아래 "일괄 처리" 기능으로 선택한 게시판의 기존 글에도 소급 적용할 수 있습니다.
        </div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="plugin_save" value="1">

<!-- 기본 설정 -->
<div class="ac-card">
    <h2>기본 설정</h2>

    <div class="ac-row">
        <label>
            <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
            &nbsp;플러그인 활성화
        </label>
        <p class="ac-desc">체크 해제 시 게시글 저장 시 링크 삽입이 중단됩니다. 이미 삽입된 링크는 유지됩니다.</p>
    </div>

    <div class="ac-row">
        <label>게시글당 최대 링크 수</label>
        <div style="display:flex;gap:10px;margin-top:2px">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:normal;cursor:pointer">
                <input type="radio" name="max_links" value="1" <?= ($cfg['max_links'] ?? 2) == 1 ? 'checked' : '' ?> accent-color="#22c55e">
                1개 (보수적)
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:normal;cursor:pointer">
                <input type="radio" name="max_links" value="2" <?= ($cfg['max_links'] ?? 2) == 2 ? 'checked' : '' ?> accent-color="#22c55e">
                2개 (최대)
            </label>
        </div>
        <p class="ac-desc">핵심 개념 1~2개에만 달아야 공신력 효과가 납니다. 너무 많으면 스팸처럼 보입니다.</p>
    </div>

    <div class="ac-row">
        <label>허용 게시판</label>
        <?php if (!empty($boardList)): ?>
            <div class="ac-board-grid">
                <?php foreach ($boardList as $board):
                    $bid    = (string)($board['board_id'] ?? '');
                    $btitle = $board['title'] ?? $bid;
                    $chk    = in_array($bid, $allowedArr, true) ? 'checked' : '';
                ?>
                <label class="ac-board-item">
                    <input type="checkbox" name="allowed_boards[]"
                           value="<?= htmlspecialchars($bid) ?>" <?= $chk ?>>
                    <span><?= htmlspecialchars($btitle) ?></span>
                    <small>(<?= htmlspecialchars($bid) ?>)</small>
                </label>
                <?php endforeach; ?>
            </div>
            <p class="ac-desc">아무것도 선택 안 하면 전체 게시판에 적용됩니다.</p>
        <?php else: ?>
            <div style="padding:12px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;font-size:13px;color:#92400e;">
                게시판 목록을 불러오지 못했습니다. 디버그 로그를 확인하세요.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- OpenAI 설정 -->
<div class="ac-card">
    <h2>OpenAI 설정
        <?php if (!empty($cfg['openai_api_key'])): ?>
            <span class="ac-badge ac-badge-ai">AI 키워드 추출</span>
        <?php else: ?>
            <span class="ac-badge ac-badge-fallback">제목 단어 매칭</span>
        <?php endif; ?>
    </h2>

    <div class="ac-row">
        <label for="openai_api_key">OpenRouter API 키 (선택)</label>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="password" id="openai_api_key" name="openai_api_key" class="ac-input"
                   placeholder="sk-or-v1-..." autocomplete="off"
                   value="<?= htmlspecialchars($cfg['openai_api_key'] ?? '') ?>">
            <button type="button" onclick="acTestKey()"
                    style="white-space:nowrap;padding:9px 14px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">
                테스트
            </button>
        </div>
        <div id="ac_test_result" style="font-size:13px;margin-top:6px"></div>
        <p class="ac-desc">입력 시 AI가 위키백과 문서가 있을 법한 전문 용어를 정확하게 추출합니다. 없으면 제목 단어로 위키백과를 직접 조회합니다.</p>
    </div>

    <div class="ac-row">
        <label for="openai_model">모델</label>
        <select id="openai_model" name="openai_model" class="ac-input" style="width:280px" autocomplete="off">
            <?= nb_openrouter_options($cfg['openai_model'] ?? '') ?>
        </select>
    </div>
</div>

<!-- 저장 -->
<div class="ac-card" style="padding:18px 24px">
    <button type="submit" class="ac-btn">설정 저장</button>
</div>

</form>

<!-- 일괄 처리 -->
<div class="ac-card">
    <h2>기존 게시글 일괄 처리</h2>
    <p style="font-size:13px;color:#475569;margin:0 0 14px">플러그인 설치 전에 작성된 기존 게시글에 소급 적용합니다. 게시판당 최근 200개까지 처리됩니다.</p>

    <?php if (!empty($batch_result)): ?>
    <div class="ac-batch-result"><?= $batch_result ?></div>
    <?php endif; ?>

    <form method="POST" style="margin-top:14px">
        <input type="hidden" name="run_batch" value="1">
        <?php if (!empty($boardList)): ?>
            <div class="ac-board-grid" style="margin-bottom:14px">
                <?php foreach ($boardList as $board):
                    $bid    = (string)($board['board_id'] ?? '');
                    $btitle = $board['title'] ?? $bid;
                ?>
                <label class="ac-board-item">
                    <input type="checkbox" name="batch_boards[]" value="<?= htmlspecialchars($bid) ?>">
                    <span><?= htmlspecialchars($btitle) ?></span>
                    <small>(<?= htmlspecialchars($bid) ?>)</small>
                </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button type="submit" class="ac-btn-green"
                onclick="return confirm('선택한 게시판의 기존 게시글에 위키백과 링크를 삽입합니다.\n기존 위키 링크는 갱신됩니다. 진행할까요?')">
            선택 게시판 일괄 처리
        </button>
        <p class="ac-desc" style="margin-top:8px">처리량이 많으면 시간이 걸릴 수 있습니다. 완료될 때까지 창을 닫지 마세요.</p>
    </form>
</div>

<!-- 캐시 관리 -->
<div class="ac-card">
    <h2>캐시 관리</h2>
    <div class="ac-cache-bar" style="margin-bottom:14px">
        <div style="font-size:22px;font-weight:700;color:#15803d"><?= number_format($cacheCount) ?></div>
        <div>
            <div style="font-size:13px;font-weight:600;color:#475569">캐시된 키워드</div>
            <div style="font-size:12px;color:#94a3b8">위키백과 조회 결과가 저장된 키워드 수 (7일 유효)</div>
        </div>
    </div>
    <form method="POST" style="display:inline">
        <input type="hidden" name="clear_cache" value="1">
        <button type="submit" class="ac-btn-sm"
                onclick="return confirm('캐시를 삭제할까요? 다음 저장 시 위키백과를 다시 조회합니다.')">
            전체 캐시 삭제
        </button>
    </form>
</div>

<!-- 디버그 로그 -->
<div class="ac-card">
    <h2>디버그 로그</h2>
    <?php if ($logContent): ?>
        <pre style="background:#111;color:#0f0;padding:16px;border-radius:8px;font-size:12px;max-height:300px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;margin:0 0 12px;font-family:monospace"><?= htmlspecialchars($logContent) ?></pre>
        <form method="POST" style="display:inline">
            <input type="hidden" name="clear_log" value="1">
            <button type="submit" class="ac-btn-sm" onclick="return confirm('로그를 삭제하시겠습니까?')">로그 삭제</button>
        </form>
    <?php else: ?>
        <p style="color:#94a3b8;font-size:13px;margin:0">기록된 로그가 없습니다.</p>
    <?php endif; ?>
</div>

</div>

<script>
function acTestKey() {
    var key = document.getElementById('openai_api_key').value.trim();
    var el  = document.getElementById('ac_test_result');
    if (!key) { el.innerHTML = '<span style="color:#ef4444">API 키를 입력하세요.</span>'; return; }
    el.innerHTML = '<span style="color:#94a3b8">테스트 중...</span>';
    fetch('https://openrouter.ai/api/v1/models', {
        headers: { 'Authorization': 'Bearer ' + key }
    }).then(function(r) {
        el.innerHTML = r.ok
            ? '<span style="color:#22c55e">연결 성공!</span>'
            : '<span style="color:#ef4444">API 키 오류 (HTTP ' + r.status + ')</span>';
    }).catch(function() {
        el.innerHTML = '<span style="color:#ef4444">네트워크 오류</span>';
    });
}
</script>

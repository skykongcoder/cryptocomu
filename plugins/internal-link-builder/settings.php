<?php
/**
 * AI 스마트 내부 링크 빌더 - 설정 페이지
 */
require_once __DIR__ . '/../_openrouter_models.php';

$cfg = _ilb_load_config_fresh();
$msg = '';

// ===== 로그 삭제 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    @file_put_contents(_ilb_data_dir() . '/debug.log', '');
    $msg = '로그가 삭제되었습니다.';
}

// ===== 설정 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_save'])) {
    $cfg['enabled']        = isset($_POST['enabled']);
    $cfg['link_count']     = max(1, min(5, (int)($_POST['link_count'] ?? 3)));
    $boards_checked        = $_POST['allowed_boards'] ?? [];
    $cfg['allowed_boards'] = implode(',', array_map('trim', (array)$boards_checked));
    $cfg['openai_api_key'] = trim($_POST['openai_api_key'] ?? '');
    $cfg['openai_model']   = nb_openrouter_is_valid($_POST['openai_model'] ?? '')
                             ? $_POST['openai_model'] : 'meta-llama/llama-3.3-70b-instruct:free';
    _ilb_save_config($cfg);
    $cfg = _ilb_load_config_fresh();
    $msg = '설정이 저장되었습니다.';
}

// ===== DB에서 게시판 목록 =====
$boardList = [];
try {
    $boardList = DB::fetchAll(
        "SELECT board_id, title FROM " . DB::getPrefix() . "boards WHERE is_active = 1 ORDER BY sort_order ASC"
    );
} catch (Throwable $e) {
    _ilb_log("게시판 목록 로드 실패: " . $e->getMessage());
}

$allowedArr = array_filter(array_map('trim', explode(',', $cfg['allowed_boards'] ?? '')));
$logFile    = _ilb_data_dir() . '/debug.log';
$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
?>

<style>
.ilb-wrap { max-width: 760px; font-family: -apple-system, sans-serif; }
.ilb-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.ilb-card h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; letter-spacing: .5px; }
.ilb-row { margin-bottom: 18px; }
.ilb-row:last-child { margin-bottom: 0; }
.ilb-row > label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.ilb-desc { font-size: 12px; color: #94a3b8; margin: 4px 0 0; }
.ilb-input { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.ilb-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.12); }
.ilb-btn { padding: 10px 28px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.ilb-btn:hover { background: #15803d; }
.ilb-btn-sm { padding: 6px 14px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; cursor: pointer; }
.ilb-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.ilb-badge { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; margin-left: 8px; vertical-align: middle; }
.ilb-badge-ai       { background: #dcfce7; color: #15803d; }
.ilb-badge-fallback { background: #f3f4f6; color: #6b7280; }
.ilb-board-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; margin-top: 6px; }
.ilb-board-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: border-color .15s, background .15s; }
.ilb-board-item:hover { border-color: #22c55e; background: #f0fdf4; }
.ilb-board-item input[type=checkbox] { width: 15px; height: 15px; accent-color: #22c55e; cursor: pointer; flex-shrink: 0; }
.ilb-board-item span { font-size: 13px; color: #374151; }
.ilb-board-item small { font-size: 11px; color: #94a3b8; }
</style>

<div class="ilb-wrap">

<?php if ($msg): ?>
<div class="ilb-msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- 동작 안내 -->
<div class="ilb-card" style="background:#f0fdf4;border-color:#bbf7d0">
    <h2 style="border-color:#bbf7d0;color:#15803d">이 플러그인은 이렇게 동작합니다</h2>
    <div style="font-size:13px;color:#374151;line-height:2">
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">언제 작동하나요?</strong><br>
            방문자가 게시글을 열어볼 때 자동으로 작동합니다. 별도 작업 없이 활성화 후 저장하면 끝입니다.
        </div>
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">어떻게 관련글을 찾나요?</strong><br>
            OpenRouter API 키가 있으면 → 본문에서 핵심 키워드 5개를 AI가 추출해 관련글을 검색합니다.<br>
            API 키가 없으면 → 제목의 단어를 기준으로 단순 LIKE 검색을 합니다.
        </div>
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">박스는 어디에 삽입되나요?</strong><br>
            본문 40% 지점 문단 사이에 자동 삽입됩니다. 관련글이 없으면 박스 자체가 표시되지 않습니다.
        </div>
        <div>
            <strong style="color:#15803d">허용 게시판을 선택 안 하면?</strong><br>
            전체 게시판에 적용됩니다. 특정 게시판에만 표시하고 싶으면 아래에서 체크하세요.
        </div>
    </div>
</div>

<form method="POST">
<input type="hidden" name="plugin_save" value="1">

<!-- 기본 설정 -->
<div class="ilb-card">
    <h2>기본 설정</h2>

    <div class="ilb-row">
        <label>
            <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
            &nbsp;플러그인 활성화
        </label>
        <p class="ilb-desc">체크 해제 시 모든 게시판에서 관련글 박스가 표시되지 않습니다.</p>
    </div>

    <div class="ilb-row">
        <label for="link_count">관련글 표시 개수</label>
        <input type="number" id="link_count" name="link_count" class="ilb-input"
               style="width:100px" min="1" max="5"
               value="<?= (int)($cfg['link_count'] ?? 3) ?>">
        <p class="ilb-desc">1~5개 (기본값: 3)</p>
    </div>

    <div class="ilb-row">
        <label>허용 게시판</label>
        <?php if (!empty($boardList)): ?>
            <div class="ilb-board-grid">
                <?php foreach ($boardList as $board):
                    $bid     = (string)($board['board_id'] ?? '');
                    $btitle  = $board['title'] ?? $bid;
                    $checked = in_array($bid, $allowedArr, true) ? 'checked' : '';
                ?>
                <label class="ilb-board-item">
                    <input type="checkbox" name="allowed_boards[]"
                           value="<?= htmlspecialchars($bid) ?>" <?= $checked ?>>
                    <span><?= htmlspecialchars($btitle) ?></span>
                    <small>(<?= htmlspecialchars($bid) ?>)</small>
                </label>
                <?php endforeach; ?>
            </div>
            <p class="ilb-desc">아무것도 선택 안 하면 전체 게시판에 적용됩니다.</p>
        <?php else: ?>
            <div style="padding:12px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;font-size:13px;color:#92400e;">
                게시판 목록을 불러오지 못했습니다. 디버그 로그를 확인하세요.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 저장 -->
<div class="ilb-card" style="padding:18px 24px">
    <button type="submit" class="ilb-btn">설정 저장</button>
</div>

</form>

<!-- 디버그 로그 -->
<div class="ilb-card">
    <h2>디버그 로그</h2>
    <?php if ($logContent): ?>
        <pre style="background:#111;color:#0f0;padding:16px;border-radius:8px;font-size:12px;max-height:300px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;margin:0 0 12px;font-family:monospace"><?= htmlspecialchars($logContent) ?></pre>
        <form method="POST" style="display:inline">
            <input type="hidden" name="clear_log" value="1">
            <button type="submit" class="ilb-btn-sm" onclick="return confirm('로그를 삭제하시겠습니까?')">로그 삭제</button>
        </form>
    <?php else: ?>
        <p style="color:#94a3b8;font-size:13px;margin:0">기록된 로그가 없습니다.</p>
    <?php endif; ?>
</div>

</div>

<script></script>

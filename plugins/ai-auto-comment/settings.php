<?php
/**
 * AI 자동 댓글 관리자 설정 페이지
 */
require_once __DIR__ . '/../_openrouter_models.php';

$_aicFile  = __DIR__ . '/config.json';
$_aicState = __DIR__ . '/state.json';

$_aicRaw = file_exists($_aicFile) ? json_decode(file_get_contents($_aicFile), true) : [];
if (!is_array($_aicRaw)) $_aicRaw = [];
$_aic = array_merge([
    'enabled'             => '0',
    'openai_api_key'      => '',
    'openai_model'        => 'openai/gpt-4o-mini',
    'system_prompt'       => '당신은 한국어 커뮤니티 사이트의 평범한 방문자로서 게시글에 달리는 자연스러운 댓글을 작성하는 작가입니다. 실제 사람이 쓴 것처럼 다양한 어투/말투/반응을 섞어서 작성하세요. 광고나 홍보성 멘트는 절대 넣지 말고, 과한 이모지 남발도 피하세요.',
    'target_all_boards'   => '1',
    'target_board_ids'    => '',
    'comment_min'         => '2',
    'comment_max'         => '5',
    'length_mode'         => 'random',
    'target_days'         => '7',
    'target_max_comments' => '5',
    'auto_interval_minutes' => '30',
    'batch_size'          => '3',
    'skip_own_comments'   => '1',
    'reply_enabled'       => '1',
    'reply_ratio'         => '30',
], $_aicRaw);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aic_save'])) {
    foreach (['enabled','openai_api_key','openai_model','system_prompt',
              'target_all_boards','comment_min','comment_max','length_mode',
              'target_days','target_max_comments','auto_interval_minutes','batch_size',
              'skip_own_comments','reply_enabled','reply_ratio'] as $k) {
        if (in_array($k, ['enabled','target_all_boards','skip_own_comments','reply_enabled'])) {
            $_aic[$k] = isset($_POST[$k]) ? '1' : '0';
        } else {
            $_aic[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : $_aic[$k];
        }
    }
    // 체크된 게시판 목록
    if (isset($_POST['board_ids']) && is_array($_POST['board_ids'])) {
        $_aic['target_board_ids'] = implode(',', array_map('trim', $_POST['board_ids']));
    } else {
        $_aic['target_board_ids'] = '';
    }

    file_put_contents($_aicFile, json_encode($_aic, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">설정이 저장되었습니다.</div>';
}

// 게시판 목록 로드
$allBoards = [];
if (class_exists('DB')) {
    try {
        $prefix = DB::getPrefix();
        $allBoards = DB::fetchAll("SELECT board_id, title, board_type FROM {$prefix}boards WHERE is_active = 1 ORDER BY board_id") ?: [];
    } catch (Exception $e) {}
}
$selectedBoards = array_filter(array_map('trim', explode(',', $_aic['target_board_ids'])));

// 상태 파일 읽기
$state = file_exists($_aicState) ? json_decode(file_get_contents($_aicState), true) : [];
if (!is_array($state)) $state = [];

$tab = $_GET['tab'] ?? 'ai';
?>
<style>
.aic-nav{display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:20px;padding-bottom:0}
.aic-nav a{padding:10px 16px;text-decoration:none;color:#6b7280;font-weight:500;border-bottom:2px solid transparent;margin-bottom:-2px}
.aic-nav a.active{color:#f59e0b;border-color:#f59e0b}
.aic-section{background:#fff;padding:20px;border-radius:8px;margin-bottom:16px;border:1px solid #e5e7eb}
.aic-section h3{margin:0 0 12px;font-size:15px;font-weight:600;color:#111827}
.aic-boards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;margin-top:8px}
.aic-board-item{display:flex;align-items:center;gap:8px;padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;cursor:pointer;font-size:13px}
.aic-board-item:hover{background:#fef3c7;border-color:#f59e0b}
.aic-board-item input{margin:0}
.aic-board-id{color:#9ca3af;font-size:11px;margin-left:6px}
.aic-runs{max-height:400px;overflow-y:auto}
.aic-run{padding:10px 12px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;font-size:13px}
.aic-run .t{color:#6b7280;font-size:11px}
.aic-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.aic-stat{background:#fff;padding:16px;border-radius:10px;border:1px solid #e5e7eb;text-align:center}
.aic-stat .n{font-size:24px;font-weight:700;color:#f59e0b}
.aic-stat .l{font-size:12px;color:#6b7280;margin-top:4px}
</style>

<?= $msg ?>

<div class="aic-nav">
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=ai" class="<?= $tab==='ai'?'active':'' ?>">AI 설정</a>
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=boards" class="<?= $tab==='boards'?'active':'' ?>">대상 게시판</a>
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=rules" class="<?= $tab==='rules'?'active':'' ?>">댓글 규칙</a>
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=run" class="<?= $tab==='run'?'active':'' ?>">실행 & 이력</a>
</div>

<?php if ($tab === 'ai'): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;margin-bottom:16px">
    <div style="font-size:13px;color:#92400e;line-height:1.6">
        <strong>AI 설정 순서</strong><br>
        1. <strong>OpenRouter API 키</strong> 입력 → 저장<br>
        2. "대상 게시판" 탭에서 댓글 달 게시판 선택<br>
        3. "댓글 규칙" 탭에서 댓글 수/길이/대댓글 등 설정<br>
        4. "실행 & 이력" 탭에서 "지금 1회 실행" 또는 <strong>챗봇 활성화</strong> 체크 (자동 실행)
    </div>
</div>

<form method="post">
    <input type="hidden" name="aic_save" value="1">

    <div class="aic-section">
        <h3>활성화</h3>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="enabled" <?= $_aic['enabled']==='1'?'checked':'' ?>>
            자동 실행 활성화 (관리자 페이지 방문 시 설정된 간격마다 자동으로 배치 실행)
        </label>
        <small>체크 안 해도 "실행 & 이력" 탭에서 수동으로 돌릴 수 있어요.</small>
    </div>

    <div class="aic-section">
        <h3>OpenAI 연결</h3>
        <div class="form-group">
            <label>OpenRouter API 키</label>
            <input type="password" name="openai_api_key" value="<?= htmlspecialchars($_aic['openai_api_key']) ?>" placeholder="sk-or-v1-..." autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>모델</label>
            <select name="openai_model">
                <?= nb_openrouter_options($_aic['openai_model'] ?? '') ?>
            </select>
        </div>
    </div>

    <div class="aic-section">
        <h3>시스템 프롬프트 (AI 성격)</h3>
        <textarea name="system_prompt" rows="5"><?= htmlspecialchars($_aic['system_prompt']) ?></textarea>
        <small>AI가 어떤 톤으로 댓글 쓸지 지시합니다. 사이트 성격에 맞게 수정하세요.</small>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<?php elseif ($tab === 'boards'): ?>
<form method="post">
    <input type="hidden" name="aic_save" value="1">
    <?php foreach (['enabled','openai_api_key','openai_model','system_prompt','comment_min','comment_max','length_mode','target_days','target_max_comments','auto_interval_minutes','batch_size','skip_own_comments','reply_enabled','reply_ratio'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_aic[$k]) ?>">
    <?php endforeach; ?>

    <div class="aic-section">
        <h3>대상 게시판</h3>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600">
                <input type="checkbox" name="target_all_boards" id="aic_all" <?= $_aic['target_all_boards']==='1'?'checked':'' ?>>
                전체 게시판 순환하며 댓글 달기 (아래 개별 선택 무시)
            </label>
        </div>

        <div style="margin-top:20px">
            <div style="font-size:13px;color:#6b7280;margin-bottom:8px">또는 체크한 게시판만 대상:</div>

            <?php if (empty($allBoards)): ?>
                <div style="padding:16px;background:#f9fafb;border-radius:8px;color:#9ca3af;font-size:13px">
                    활성화된 게시판이 없습니다.
                </div>
            <?php else: ?>
                <div style="margin-bottom:8px">
                    <button type="button" class="btn" id="aic_check_all" style="font-size:12px;padding:4px 10px">모두 선택</button>
                    <button type="button" class="btn" id="aic_uncheck_all" style="font-size:12px;padding:4px 10px">모두 해제</button>
                </div>
                <div class="aic-boards-grid">
                    <?php foreach ($allBoards as $b): ?>
                    <label class="aic-board-item">
                        <input type="checkbox" name="board_ids[]" value="<?= htmlspecialchars($b['board_id']) ?>" <?= in_array($b['board_id'], $selectedBoards, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($b['title']) ?></span>
                        <span class="aic-board-id"><?= htmlspecialchars($b['board_id']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<script>
document.getElementById('aic_check_all')?.addEventListener('click', function(){
    document.querySelectorAll('input[name="board_ids[]"]').forEach(function(el){ el.checked = true; });
});
document.getElementById('aic_uncheck_all')?.addEventListener('click', function(){
    document.querySelectorAll('input[name="board_ids[]"]').forEach(function(el){ el.checked = false; });
});
</script>

<?php elseif ($tab === 'rules'): ?>
<form method="post">
    <input type="hidden" name="aic_save" value="1">
    <?php foreach (['enabled','openai_api_key','openai_model','system_prompt','target_all_boards','auto_interval_minutes','batch_size','skip_own_comments'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_aic[$k]) ?>">
    <?php endforeach; ?>
    <?php foreach ($selectedBoards as $bid): ?>
        <input type="hidden" name="board_ids[]" value="<?= htmlspecialchars($bid) ?>">
    <?php endforeach; ?>

    <div class="aic-section">
        <h3>댓글 개수 (게시글 1개당)</h3>
        <div class="form-row">
            <div class="form-group">
                <label>최소</label>
                <input type="number" name="comment_min" value="<?= (int)$_aic['comment_min'] ?>" min="1" max="20" style="width:100px">
            </div>
            <div class="form-group">
                <label>최대</label>
                <input type="number" name="comment_max" value="<?= (int)$_aic['comment_max'] ?>" min="1" max="20" style="width:100px">
            </div>
        </div>
        <small>각 게시글마다 이 범위 안에서 랜덤한 개수의 댓글이 생성돼요.</small>
    </div>

    <div class="aic-section">
        <h3>댓글 길이</h3>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px">
            <input type="radio" name="length_mode" value="short" <?= $_aic['length_mode']==='short'?'checked':'' ?>>
            <strong>짧게</strong> — 5~15자 (예: "오 좋네요", "ㅋㅋㅋ 공감")
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px">
            <input type="radio" name="length_mode" value="medium" <?= $_aic['length_mode']==='medium'?'checked':'' ?>>
            <strong>중간</strong> — 15~40자 (예: "저도 이거 궁금했는데 좋은 정보네요!")
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px">
            <input type="radio" name="length_mode" value="long" <?= $_aic['length_mode']==='long'?'checked':'' ?>>
            <strong>길게</strong> — 40~100자 (경험/질문 섞어서)
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="radio" name="length_mode" value="random" <?= $_aic['length_mode']==='random'?'checked':'' ?>>
            <strong>전체 랜덤</strong> — 짧게/중간/길게 자유 조합 (가장 자연스러움, 추천)
        </label>
    </div>

    <div class="aic-section">
        <h3>이모지 사용</h3>
        <div style="padding:10px 14px;background:#f3f4f6;border-radius:8px;color:#4b5563;font-size:13px">
            <strong>전체 랜덤 (고정)</strong> — AI가 댓글마다 0~3개를 자연스럽게 섞어 넣습니다.
        </div>
    </div>

    <div class="aic-section">
        <h3>대댓글 (답글)</h3>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="reply_enabled" <?= $_aic['reply_enabled']==='1'?'checked':'' ?>>
                대댓글 섞기 (훨씬 자연스러워요)
            </label>
        </div>
        <div class="form-group">
            <label>대댓글 비율 (%)</label>
            <input type="number" name="reply_ratio" value="<?= (int)$_aic['reply_ratio'] ?>" min="0" max="80" style="width:100px"> %
            <small>전체 댓글 중 몇 %를 앞선 댓글에 대한 답글로 만들지 (권장 20~40%)</small>
        </div>
    </div>

    <div class="aic-section">
        <h3>대상 게시글 조건</h3>
        <div class="form-row">
            <div class="form-group">
                <label>최근 N일 이내 글만</label>
                <input type="number" name="target_days" value="<?= (int)$_aic['target_days'] ?>" min="1" max="365" style="width:100px">
                <small>오래된 글에 갑자기 댓글 달리면 부자연스러우니 기본 7일 권장.</small>
            </div>
            <div class="form-group">
                <label>이미 댓글 N개 이하인 글만 대상</label>
                <input type="number" name="target_max_comments" value="<?= (int)$_aic['target_max_comments'] ?>" min="0" max="100" style="width:100px">
                <small>이미 댓글이 충분히 달린 글은 건드리지 않아요.</small>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<?php elseif ($tab === 'run'): ?>
<?php
$totalGenerated = (int)($state['total_comments_generated'] ?? 0);
$lastRun = $state['last_run'] ?? '';
$recentRuns = $state['recent_runs'] ?? [];
?>

<div class="aic-stat-grid">
    <div class="aic-stat">
        <div class="n"><?= number_format($totalGenerated) ?></div>
        <div class="l">지금까지 생성된 댓글</div>
    </div>
    <div class="aic-stat">
        <div class="n"><?= $lastRun ? date('m/d H:i', strtotime($lastRun)) : '-' ?></div>
        <div class="l">마지막 실행</div>
    </div>
    <div class="aic-stat">
        <div class="n"><?= count($recentRuns) ?></div>
        <div class="l">최근 실행 횟수</div>
    </div>
</div>

<div class="aic-section">
    <h3>수동 실행</h3>
    <p style="color:#6b7280;font-size:13px;margin-bottom:12px">
        지금 바로 1배치(기본 3개 게시글) 처리하고 결과를 바로 확인할 수 있어요.<br>
        OpenAI 비용: 글 1개당 약 $0.001 ~ $0.003 (gpt-4o-mini 기준).
    </p>
    <button type="button" class="btn btn-primary" id="aic_run_now" style="background:#f59e0b;border-color:#f59e0b">
        지금 1회 실행 (배치 처리)
    </button>
    <button type="button" class="btn" id="aic_refresh_state" style="margin-left:8px">이력 새로고침</button>
    <div id="aic_run_result" style="margin-top:16px"></div>
</div>

<div class="aic-section">
    <h3>자동 실행 설정</h3>
    <form method="post" style="display:flex;gap:12px;align-items:end">
        <input type="hidden" name="aic_save" value="1">
        <?php foreach (['enabled','openai_api_key','openai_model','system_prompt','target_all_boards','comment_min','comment_max','length_mode','target_days','target_max_comments','skip_own_comments','reply_enabled','reply_ratio'] as $k): ?>
            <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_aic[$k]) ?>">
        <?php endforeach; ?>
        <?php foreach ($selectedBoards as $bid): ?>
            <input type="hidden" name="board_ids[]" value="<?= htmlspecialchars($bid) ?>">
        <?php endforeach; ?>
        <div class="form-group">
            <label>자동 실행 간격 (분)</label>
            <input type="number" name="auto_interval_minutes" value="<?= (int)$_aic['auto_interval_minutes'] ?>" min="1" max="1440" style="width:120px">
            <small>관리자 페이지 방문 시 체크. 최소 1분, 권장 30분.</small>
        </div>
        <div class="form-group">
            <label>1회 배치 크기</label>
            <input type="number" name="batch_size" value="<?= (int)$_aic['batch_size'] ?>" min="1" max="20" style="width:100px">
            <small>한번 실행에 처리할 글 수.</small>
        </div>
        <button type="submit" class="btn btn-primary">저장</button>
    </form>
</div>

<div class="aic-section">
    <h3>최근 실행 이력</h3>
    <div class="aic-runs" id="aic_runs">
        <?php if (empty($recentRuns)): ?>
            <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px">아직 실행 이력이 없습니다.</div>
        <?php else: ?>
            <?php foreach ($recentRuns as $r): ?>
                <div class="aic-run">
                    <div>
                        <strong>처리 <?= (int)$r['processed'] ?>개 글</strong>
                        → 댓글 <strong><?= (int)$r['comments'] ?>개</strong> 생성
                        <?php if (!empty($r['errors'])): ?>
                            <span style="color:#ef4444">(오류 <?= (int)$r['errors'] ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="t"><?= htmlspecialchars($r['at'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('aic_run_now');
    var refreshBtn = document.getElementById('aic_refresh_state');
    var resultEl = document.getElementById('aic_run_result');

    btn.addEventListener('click', function(){
        if (!confirm('OpenAI 호출로 실제 비용이 발생합니다. 1회 실행할까요?')) return;
        btn.disabled = true;
        btn.textContent = '실행 중... (최대 1분 소요)';
        resultEl.innerHTML = '<div style="padding:12px;background:#fffbeb;border-radius:8px;color:#92400e">AI가 댓글을 생성하고 있어요...</div>';

        fetch('/?aic_run_now=1', { method:'POST', credentials:'same-origin' })
            .then(function(r){ return r.text(); })
            .then(function(t){
                btn.disabled = false;
                btn.textContent = '지금 1회 실행 (배치 처리)';
                try {
                    var res = JSON.parse(t);
                    if (!res.ok) throw new Error(res.error || '실패');
                    var html = '<div style="padding:14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;color:#065f46">' +
                        '<strong>완료!</strong> 게시글 <strong>' + res.processed + '개</strong>에 댓글 <strong>' + res.comments_added + '개</strong>를 달았어요.';
                    if (res.errors && res.errors.length > 0) {
                        html += '<div style="margin-top:8px;color:#b45309"><strong>오류:</strong><br>' + res.errors.map(function(e){return '- ' + e;}).join('<br>') + '</div>';
                    }
                    html += '</div>';
                    resultEl.innerHTML = html;
                    // 이력 새로고침
                    setTimeout(function(){ location.reload(); }, 1500);
                } catch (e) {
                    resultEl.innerHTML = '<div style="padding:14px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#991b1b"><strong>실패:</strong> ' + (e.message || e) + '<br><small>' + t.slice(0, 300) + '</small></div>';
                }
            })
            .catch(function(e){
                btn.disabled = false;
                btn.textContent = '지금 1회 실행 (배치 처리)';
                resultEl.innerHTML = '<div style="padding:14px;background:#fef2f2;color:#991b1b">네트워크 오류: ' + e.message + '</div>';
            });
    });

    refreshBtn.addEventListener('click', function(){ location.reload(); });
})();
</script>
<?php endif; ?>

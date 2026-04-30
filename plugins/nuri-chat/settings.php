<?php
/**
 * 누리챗 관리자 설정 페이지
 */
require_once __DIR__ . '/../_openrouter_models.php';

$_ncFile = __DIR__ . '/config.json';
$_ncFaqsFile = __DIR__ . '/faqs.json';
$_ncRaw = file_exists($_ncFile) ? json_decode(file_get_contents($_ncFile), true) : [];
if (!is_array($_ncRaw)) $_ncRaw = [];
$_nc = array_merge([
    'enabled'         => '1',
    'bot_name'        => '누리챗',
    'bot_subtitle'    => '24시간 운영해요',
    'greeting'        => '안녕하세요, 무엇을 도와드릴까요?',
    'offline_text'    => '',
    'offline_hours'   => '',
    'openai_api_key'  => '',
    'openai_model'    => 'openai/gpt-4o-mini',
    'system_prompt'   => '당신은 이 사이트의 친절한 상담 도우미입니다. 사용자가 편하게 느끼도록 존댓말로 짧고 명확하게 답하세요. 모르는 내용은 솔직히 모른다고 답하고, 관리자에게 문의를 남길 수 있다고 안내하세요.',
    'site_knowledge'  => '',
    'use_board_rag'   => '1',
    'rag_board_ids'   => '',
    'accent_color'    => '#22c55e',
    'position'        => 'right',
    'bottom'          => '24',
    'offset'          => '24',
    'hide_admin'      => '1',
    'ban_words'       => '',
], $_ncRaw);

$_ncFaqs = file_exists($_ncFaqsFile) ? json_decode(file_get_contents($_ncFaqsFile), true) : [];
if (!is_array($_ncFaqs)) $_ncFaqs = [];

$msg = '';

// === 저장: 기본/AI 설정 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nc_save'])) {
    foreach (['enabled','bot_name','bot_subtitle','greeting','offline_text','offline_hours',
              'openai_api_key','openai_model','system_prompt','site_knowledge',
              'use_board_rag','rag_board_ids','accent_color','position','bottom','offset',
              'hide_admin','ban_words'] as $k) {
        if ($k === 'enabled' || $k === 'use_board_rag' || $k === 'hide_admin') {
            $_nc[$k] = isset($_POST[$k]) ? '1' : '0';
        } else {
            $_nc[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : $_nc[$k];
        }
    }
    file_put_contents($_ncFile, json_encode($_nc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">설정이 저장되었습니다.</div>';
}

// === FAQ 칩 저장 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nc_faqs_save'])) {
    $ids    = $_POST['faq_id']    ?? [];
    $labels = $_POST['faq_label'] ?? [];
    $icons  = $_POST['faq_icon']  ?? [];
    $answers = $_POST['faq_answer'] ?? [];
    $out = [];
    for ($i = 0; $i < count($labels); $i++) {
        $lb = trim($labels[$i] ?? '');
        if ($lb === '') continue;
        $id = trim($ids[$i] ?? '') ?: ('faq_' . substr(md5($lb . microtime(true) . $i), 0, 10));
        $out[] = [
            'id' => $id,
            'label' => $lb,
            'icon' => trim($icons[$i] ?? ''),
            'answer' => trim($answers[$i] ?? ''),
        ];
    }
    file_put_contents($_ncFaqsFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $_ncFaqs = $out;
    $msg = '<div class="alert success">FAQ 칩이 저장되었습니다.</div>';
}

// === AI 자동 분석 실행 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nc_analyze'])) {
    if (!function_exists('_nc_auto_analyze_site')) {
        require_once __DIR__ . '/plugin.php';
    }
    $result = _nc_auto_analyze_site($_nc);
    if (!empty($result['success'])) {
        $_nc['site_knowledge'] = $result['content'];
        file_put_contents($_ncFile, json_encode($_nc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $msg = '<div class="alert success">✓ AI 분석 완료! 아래 "사이트 지식" 박스에 결과가 채워졌어요. 확인하고 필요 시 수정 후 <strong>저장</strong> 버튼 눌러주세요.</div>';
    } else {
        $msg = '<div class="alert error">분석 실패: ' . htmlspecialchars($result['error'] ?? '알 수 없는 오류') . '</div>';
    }
}

// === 캐시 새로고침 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nc_refresh_cache'])) {
    $cacheFile = __DIR__ . '/site_context.cache';
    if (file_exists($cacheFile)) @unlink($cacheFile);
    if (function_exists('_nc_get_site_context')) {
        _nc_get_site_context(true);
    }
    $msg = '<div class="alert success">✓ 사이트 컨텍스트 캐시를 새로 생성했습니다.</div>';
}

// === 관리자 답변 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nc_admin_reply']) && class_exists('DB')) {
    $sid = (int)$_POST['session_id'];
    $reply = trim($_POST['reply'] ?? '');
    if ($sid && $reply !== '') {
        $prefix = DB::getPrefix();
        DB::insert("{$prefix}nc_messages", [
            'session_id' => $sid,
            'sender' => 'admin',
            'content' => $reply,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        DB::query("UPDATE {$prefix}nc_sessions SET unread_for_visitor = unread_for_visitor + 1, unread_for_admin = 0, last_active_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $sid]);
        $msg = '<div class="alert success">답변이 전송되었습니다.</div>';
    }
}

// === 대화 이력 로드 ===
$sessions = [];
$selectedSid = (int)($_GET['sid'] ?? 0);
$selectedMsgs = [];
if (class_exists('DB')) {
    try {
        $prefix = DB::getPrefix();
        $sessions = DB::fetchAll("SELECT s.*, (SELECT content FROM {$prefix}nc_messages WHERE session_id = s.id AND sender='user' ORDER BY id DESC LIMIT 1) AS last_user_msg FROM {$prefix}nc_sessions s ORDER BY s.last_active_at DESC LIMIT 100");
        if ($selectedSid) {
            $selectedMsgs = DB::fetchAll("SELECT * FROM {$prefix}nc_messages WHERE session_id = ? ORDER BY id ASC", [$selectedSid]) ?: [];
            DB::query("UPDATE {$prefix}nc_sessions SET unread_for_admin = 0 WHERE id = ?", [$selectedSid]);
        }
    } catch (Exception $e) {}
}

$tab = $_GET['tab'] ?? 'basic';
?>
<style>
.nc-nav{display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:20px;padding-bottom:0}
.nc-nav a{padding:10px 16px;text-decoration:none;color:#6b7280;font-weight:500;border-bottom:2px solid transparent;margin-bottom:-2px}
.nc-nav a.active{color:#22c55e;border-color:#22c55e}
.nc-section{background:#fff;padding:20px;border-radius:8px;margin-bottom:16px;border:1px solid #e5e7eb}
.nc-section h3{margin:0 0 12px;font-size:15px;font-weight:600;color:#111827}
.nc-faq-row{display:grid;grid-template-columns:80px 160px 1fr 40px;gap:8px;margin-bottom:8px;align-items:start}
.nc-faq-row input,.nc-faq-row textarea{font-size:13px}
.nc-faq-row textarea{min-height:60px}
.nc-sess-list{max-height:500px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px}
.nc-sess-item{padding:12px;border-bottom:1px solid #f3f4f6;cursor:pointer;text-decoration:none;color:#111827;display:block}
.nc-sess-item:hover{background:#f9fafb}
.nc-sess-item.active{background:#ecfdf5;border-left:3px solid #22c55e}
.nc-sess-item .nc-sess-time{font-size:11px;color:#9ca3af}
.nc-sess-item .nc-sess-preview{font-size:13px;color:#374151;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nc-unread-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:4px}
.nc-msg-log{max-height:480px;overflow-y:auto;padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb}
.nc-msg-log .row{margin-bottom:10px;padding:8px 12px;border-radius:8px;font-size:13px;line-height:1.5}
.nc-msg-log .row.user{background:#dcfce7;margin-left:40px}
.nc-msg-log .row.bot{background:#fff;border:1px solid #e5e7eb;margin-right:40px}
.nc-msg-log .row.admin{background:#dbeafe;margin-right:40px;border:1px solid #bfdbfe}
.nc-msg-log .row small{display:block;font-size:10px;color:#9ca3af;margin-bottom:2px}
.nc-icon-preset{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}
.nc-icon-preset button{background:#f3f4f6;border:1px solid #e5e7eb;padding:4px 6px;border-radius:4px;cursor:pointer;font-size:11px}
</style>

<?= $msg ?>

<div class="nc-nav">
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=basic" class="<?= $tab==='basic'?'active':'' ?>">기본 설정</a>
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=faqs" class="<?= $tab==='faqs'?'active':'' ?>">FAQ 칩 관리</a>
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=ai" class="<?= $tab==='ai'?'active':'' ?>">AI 설정</a>
    <a href="?page=plugins&settings=<?= (int)($_GET['settings'] ?? 0) ?>&tab=chats" class="<?= $tab==='chats'?'active':'' ?>">대화 이력</a>
</div>

<?php if ($tab === 'basic'): ?>

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div style="font-size:13px;color:#1e40af">
        <strong>처음 설정하시나요?</strong> "권장 기본값 불러오기"를 누르시면 모든 필드가 자동으로 채워져요. 그 다음 본인 사이트에 맞게 조금씩 수정하시면 됩니다.
    </div>
    <button type="button" class="btn btn-primary" id="ncLoadBasicDefaults" style="font-size:13px;padding:8px 14px">권장 기본값 불러오기</button>
</div>

<form method="post">
    <input type="hidden" name="nc_save" value="1">

    <div class="nc-section">
        <h3>기본 작동</h3>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="enabled" id="nc_enabled" <?= $_nc['enabled']==='1'?'checked':'' ?>>
                챗봇 활성화 (체크 해제 시 사이트에 표시되지 않음)
            </label>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="hide_admin" id="nc_hide_admin" <?= $_nc['hide_admin']==='1'?'checked':'' ?>>
                관리자 페이지에서는 숨기기
            </label>
        </div>
    </div>

    <div class="nc-section">
        <h3>봇 프로필</h3>
        <div class="form-group">
            <label>봇 이름</label>
            <input type="text" name="bot_name" id="nc_bot_name" value="<?= htmlspecialchars($_nc['bot_name']) ?>" placeholder="예: 누리챗, 그로블, 우리봇">
            <small>방문자에게 보여질 챗봇 이름이에요. 짧을수록 좋아요 (2~5글자 추천).</small>
        </div>
        <div class="form-group">
            <label>부제목 (상태)</label>
            <input type="text" name="bot_subtitle" id="nc_bot_subtitle" value="<?= htmlspecialchars($_nc['bot_subtitle']) ?>" placeholder="예: 24시간 운영해요 / 평일 9시~6시 상담">
            <small>봇 이름 아래에 작게 표시되는 한 줄 문구예요.</small>
        </div>
        <div class="form-group">
            <label>인사말 (방문자가 처음 열면 나오는 메시지)</label>
            <textarea name="greeting" id="nc_greeting" rows="2" placeholder="예: 안녕하세요! 궁금하신 점 있으시면 편하게 물어봐 주세요."><?= htmlspecialchars($_nc['greeting']) ?></textarea>
            <small>방문자가 챗봇을 처음 열었을 때 자동으로 뜨는 첫 메시지입니다.</small>
        </div>
    </div>

    <div class="nc-section">
        <h3>운영 시간 & 오프라인 안내</h3>
        <div class="form-row">
            <div class="form-group">
                <label>운영 외 시간대</label>
                <input type="text" name="offline_hours" id="nc_offline_hours" value="<?= htmlspecialchars($_nc['offline_hours']) ?>" placeholder="예: 22:00-09:00 (비워두면 항상 운영)">
                <small>예: <code>22:00-09:00</code> → 밤 10시~아침 9시는 오프라인. <code>18-09</code> 같이 짧게도 가능. 비워두면 24시간 운영.</small>
            </div>
        </div>
        <div class="form-group">
            <label>오프라인 안내문</label>
            <textarea name="offline_text" id="nc_offline_text" rows="2" placeholder="예: 지금은 상담 시간이 아니에요. 메시지를 남겨주시면 확인 후 빠르게 답변드릴게요!"><?= htmlspecialchars($_nc['offline_text']) ?></textarea>
            <small>운영 외 시간대에 AI 호출 없이 이 문구만 자동 반환돼요.</small>
        </div>
    </div>

    <div class="nc-section">
        <h3>디자인 & 위치</h3>
        <div class="form-row">
            <div class="form-group">
                <label>포인트 색상</label>
                <input type="color" name="accent_color" value="<?= htmlspecialchars($_nc['accent_color']) ?>" style="width:80px;height:36px">
            </div>
            <div class="form-group">
                <label>위치</label>
                <select name="position">
                    <option value="right" <?= $_nc['position']==='right'?'selected':'' ?>>우측 하단</option>
                    <option value="left" <?= $_nc['position']==='left'?'selected':'' ?>>좌측 하단</option>
                </select>
            </div>
            <div class="form-group">
                <label>하단 여백(px)</label>
                <input type="number" name="bottom" value="<?= (int)$_nc['bottom'] ?>" style="width:80px">
            </div>
            <div class="form-group">
                <label>좌/우 여백(px)</label>
                <input type="number" name="offset" value="<?= (int)$_nc['offset'] ?>" style="width:80px">
            </div>
        </div>
    </div>

    <div class="nc-section">
        <h3>금칙어 (쉼표 또는 줄바꿈으로 구분)</h3>
        <textarea name="ban_words" id="nc_ban_words" rows="2" placeholder="예: 도박, 성인용품, 비아그라, 대출, 보이스피싱"><?= htmlspecialchars($_nc['ban_words']) ?></textarea>
        <small>방문자 메시지에 이 단어가 들어있으면 AI를 호출하지 않고 즉시 거부 메시지를 반환해요. 스팸/어뷰징 방어용.</small>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<script>
(function(){
    var defaults = {
        enabled: true,
        hide_admin: true,
        bot_name: '누리챗',
        bot_subtitle: '24시간 운영해요',
        greeting: '안녕하세요, 무엇을 도와드릴까요?',
        offline_hours: '',
        offline_text: '지금은 상담 가능 시간이 아니에요. 메시지를 남겨주시면 확인 후 빠르게 답변드릴게요!',
        ban_words: '도박, 성인용품, 비아그라, 대출, 보이스피싱'
    };
    var btn = document.getElementById('ncLoadBasicDefaults');
    if (btn) btn.addEventListener('click', function(){
        if (!confirm('이 탭의 모든 필드를 권장 기본값으로 채웁니다. (체크박스 상태는 유지) 진행할까요?')) return;
        var setVal = function(id, val){ var el = document.getElementById(id); if (el) el.value = val; };
        setVal('nc_bot_name', defaults.bot_name);
        setVal('nc_bot_subtitle', defaults.bot_subtitle);
        setVal('nc_greeting', defaults.greeting);
        setVal('nc_offline_hours', defaults.offline_hours);
        setVal('nc_offline_text', defaults.offline_text);
        setVal('nc_ban_words', defaults.ban_words);
        // 첫 번째 채워진 필드로 스크롤
        var f = document.getElementById('nc_bot_name'); if (f) { f.focus(); window.scrollTo({top: f.offsetTop - 100, behavior:'smooth'}); }
    });
})();
</script>

<?php elseif ($tab === 'faqs'): ?>

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div style="font-size:13px;color:#1e40af">
        <strong>FAQ 칩이 처음이시면?</strong> "예시 FAQ 5개 불러오기"를 누르면 자주 쓰는 템플릿이 자동 생성돼요. 본인 사이트에 맞게 라벨/답변만 살짝 고쳐서 저장하세요.
    </div>
    <button type="button" class="btn btn-primary" id="ncLoadFaqExamples" style="font-size:13px;padding:8px 14px">예시 FAQ 5개 불러오기</button>
</div>

<form method="post" id="ncFaqForm">
    <input type="hidden" name="nc_faqs_save" value="1">
    <div class="nc-section">
        <h3>FAQ 칩 관리</h3>
        <p style="color:#6b7280;font-size:13px;margin-bottom:12px">
            방문자가 대화창을 열면 표시되는 빠른 답변 버튼이에요. 칩을 누르면 AI를 호출하지 않고 저장된 답변을 즉시 보여주니, <strong>자주 묻는 질문은 꼭 여기에 등록</strong>하세요 (응답 빠르고 비용 절약).
        </p>

        <div id="ncFaqList">
            <div class="nc-faq-row" style="font-weight:600;font-size:12px;color:#6b7280">
                <div>아이콘 SVG</div><div>라벨</div><div>답변</div><div></div>
            </div>
            <?php if (empty($_ncFaqs)): $_ncFaqs = [
                ['id'=>'','label'=>'이용 방법','icon'=>'','answer'=>'사이트 이용 방법은 공지사항을 확인해주세요.'],
            ]; endif; ?>
            <?php foreach ($_ncFaqs as $f): ?>
            <div class="nc-faq-row">
                <input type="hidden" name="faq_id[]" value="<?= htmlspecialchars($f['id'] ?? '') ?>">
                <textarea name="faq_icon[]" placeholder="<svg ...>" rows="2"><?= htmlspecialchars($f['icon'] ?? '') ?></textarea>
                <input type="text" name="faq_label[]" value="<?= htmlspecialchars($f['label'] ?? '') ?>" placeholder="칩 라벨">
                <textarea name="faq_answer[]" placeholder="답변 본문"><?= htmlspecialchars($f['answer'] ?? '') ?></textarea>
                <button type="button" class="btn" onclick="this.parentElement.remove()" style="color:#ef4444">×</button>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn" id="ncAddFaq">+ 칩 추가</button>

        <div class="nc-icon-preset">
            <span style="font-size:12px;color:#6b7280;align-self:center;margin-right:8px">자주 쓰는 SVG:</span>
            <button type="button" data-svg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>'>카드</button>
            <button type="button" data-svg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>'>환불</button>
            <button type="button" data-svg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'>글쓰기</button>
            <button type="button" data-svg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'>문의</button>
            <button type="button" data-svg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'>회원</button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<script>
document.getElementById('ncAddFaq').addEventListener('click', function(){
    var html = '<div class="nc-faq-row">' +
        '<input type="hidden" name="faq_id[]" value="">' +
        '<textarea name="faq_icon[]" placeholder="<svg ...>" rows="2"></textarea>' +
        '<input type="text" name="faq_label[]" placeholder="칩 라벨">' +
        '<textarea name="faq_answer[]" placeholder="답변 본문"></textarea>' +
        '<button type="button" class="btn" onclick="this.parentElement.remove()" style="color:#ef4444">×</button>' +
    '</div>';
    var list = document.getElementById('ncFaqList');
    list.insertAdjacentHTML('beforeend', html);
});
document.querySelectorAll('.nc-icon-preset button').forEach(function(b){
    b.addEventListener('click', function(){
        var svg = b.getAttribute('data-svg');
        navigator.clipboard.writeText(svg).then(function(){ b.textContent = '복사됨!'; setTimeout(function(){ b.textContent = b.getAttribute('data-label') || b.textContent; }, 1000); });
    });
    b.setAttribute('data-label', b.textContent);
});

// === 예시 FAQ 5개 불러오기 ===
(function(){
    var examples = [
        {
            label: '이용 방법',
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            answer: '환영합니다!\n\n1. 회원가입 (무료, 이메일만 있으면 OK)\n2. 원하는 게시판 선택\n3. 글쓰기 버튼으로 소통 시작\n\n더 궁금한 게 있으면 편하게 물어봐 주세요.'
        },
        {
            label: '회원가입 문의',
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
            answer: '회원가입은 완전 무료예요!\n\n• 우측 상단 [회원가입] 버튼 클릭\n• 이메일 인증만 하면 바로 완료\n• 카카오/네이버 간편가입도 지원\n\n문제가 있으시면 아래 채팅으로 알려주세요.'
        },
        {
            label: '글쓰기 방법',
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            answer: '로그인 후 원하는 게시판에 들어가 [글쓰기] 버튼을 눌러주세요.\n\n• 이미지/동영상/링크 삽입 가능\n• 임시저장 기능 지원\n• 하루 최대 10개까지 작성 가능'
        },
        {
            label: '광고/제휴 문의',
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
            answer: '광고/제휴 문의는 아래 채팅창에 회사명, 연락처, 제안 내용을 남겨주세요.\n\n담당자 확인 후 평일 기준 1~2일 내에 답변드리겠습니다.'
        },
        {
            label: '기타 문의',
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            answer: '아래 채팅창에 궁금하신 내용을 자유롭게 남겨주세요. 확인 후 순차적으로 안내드리겠습니다.'
        }
    ];
    var btn = document.getElementById('ncLoadFaqExamples');
    if (!btn) return;
    btn.addEventListener('click', function(){
        var list = document.getElementById('ncFaqList');
        var existing = list.querySelectorAll('.nc-faq-row:not(:first-child)');
        if (existing.length > 0 && !confirm('현재 등록된 ' + existing.length + '개 FAQ를 모두 삭제하고 예시 5개로 교체할까요?')) return;
        existing.forEach(function(el){ el.remove(); });
        var esc = function(s){ return (s||'').replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); };
        examples.forEach(function(e){
            var html = '<div class="nc-faq-row">' +
                '<input type="hidden" name="faq_id[]" value="">' +
                '<textarea name="faq_icon[]" rows="2">' + esc(e.icon) + '</textarea>' +
                '<input type="text" name="faq_label[]" value="' + esc(e.label) + '">' +
                '<textarea name="faq_answer[]">' + esc(e.answer) + '</textarea>' +
                '<button type="button" class="btn" onclick="this.parentElement.remove()" style="color:#ef4444">×</button>' +
            '</div>';
            list.insertAdjacentHTML('beforeend', html);
        });
        alert('예시 FAQ 5개가 추가됐어요. 본인 사이트에 맞게 수정 후 "저장" 버튼을 눌러주세요.');
    });
})();
</script>

<?php elseif ($tab === 'ai'): ?>

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin-bottom:16px">
    <div style="font-size:13px;color:#1e40af;line-height:1.6">
        <strong>AI 설정 3단계</strong><br>
        1️⃣ 아래 <strong>OpenRouter API 키</strong>만 입력하고 저장<br>
        2️⃣ 초록색 박스의 <strong>"AI로 사이트 자동 분석하기"</strong> 한 번 클릭 (사이트 지식 자동 생성)<br>
        3️⃣ 필요 시 <strong>시스템 프롬프트</strong>/<strong>사이트 지식</strong> 옆의 <strong>"권장 기본값/예시 템플릿 불러오기"</strong> 버튼으로 템플릿 불러와서 수정
    </div>
</div>

<!-- AI 자동 분석 섹션 (저장 form 바깥에 독립된 form으로 배치 - 중첩 금지) -->
<div class="nc-section" style="border:2px dashed #22c55e;background:#f0fdf4">
    <h3 style="color:#166534;display:flex;align-items:center;gap:8px">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="stroke:#22c55e"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        AI 자동 분석 (원클릭 학습)
    </h3>
    <p style="color:#166534;font-size:13px;margin-bottom:12px;line-height:1.6">
        <strong>질문답변 하나하나 입력하기 귀찮을 땐 이걸 눌러보세요.</strong><br>
        AI가 사이트의 게시판 목록 + 최근 글 60개를 스캔해서 <strong>사이트 성격·주제·톤</strong>을 자동 파악하고 아래 "사이트 지식"을 알아서 채워줍니다.
    </p>
    <form method="post" style="display:inline" onsubmit="return confirm('AI가 사이트를 분석합니다. 약 10~20초 걸리고 OpenAI 비용이 약 $0.01 정도 발생해요. 진행할까요?')">
        <input type="hidden" name="nc_analyze" value="1">
        <button type="submit" class="btn btn-primary" style="background:#22c55e;border-color:#22c55e">
            ✨ AI로 사이트 자동 분석하기
        </button>
    </form>
    <form method="post" style="display:inline;margin-left:8px">
        <input type="hidden" name="nc_refresh_cache" value="1">
        <button type="submit" class="btn" style="color:#16a34a">컨텍스트 캐시 새로고침</button>
    </form>
    <p style="color:#16a34a;font-size:12px;margin-top:10px">
        💡 참고로 <strong>매 답변마다</strong> 게시판 목록 + 최근 공지 + 인기글은 <strong>자동으로</strong> AI에 전달됩니다 (1시간 캐시). 아래 "사이트 지식"은 추가적인 심화 컨텍스트예요.
    </p>
</div>

<form method="post">
    <input type="hidden" name="nc_save" value="1">
    <?php foreach (['enabled','bot_name','bot_subtitle','greeting','offline_text','offline_hours','accent_color','position','bottom','offset','hide_admin','ban_words'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_nc[$k]) ?>">
    <?php endforeach; ?>

    <div class="nc-section">
        <h3>OpenAI 연결</h3>
        <div class="form-group">
            <label>OpenRouter API 키</label>
            <input type="password" name="openai_api_key" value="<?= htmlspecialchars($_nc['openai_api_key']) ?>" placeholder="sk-or-v1-..." autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>모델</label>
            <select name="openai_model">
                <?= nb_openrouter_options($_nc['openai_model'] ?? '') ?>
            </select>
        </div>
    </div>

    <div class="nc-section">
        <h3 style="display:flex;justify-content:space-between;align-items:center">
            <span>시스템 프롬프트 (봇 성격)</span>
            <button type="button" class="btn" id="ncLoadDefaultPrompt" style="font-size:12px;padding:4px 10px">권장 기본값 불러오기</button>
        </h3>
        <textarea name="system_prompt" id="ncSystemPrompt" rows="8"><?= htmlspecialchars($_nc['system_prompt']) ?></textarea>
        <small>봇이 어떤 톤으로 어떻게 답할지 지시하는 문구예요. 위 "권장 기본값 불러오기" 누르면 추천 템플릿이 자동으로 들어갑니다. 본인 사이트에 맞게 수정 후 저장하세요.</small>
    </div>

    <script>
    (function(){
        var recommendedPrompt = "당신은 이 사이트의 친절하고 유능한 상담 도우미입니다.\n\n[말투]\n- 항상 존댓말, 1~4문장으로 명확하게 답변\n- 따뜻하고 사람같은 톤 (과도한 격식 X)\n\n[답변 원칙 - 중요]\n- 컨텍스트로 제공되는 [사이트 정보], [게시판 구조], [관련 글]을 적극 활용해서 구체적으로 답하세요.\n- 관련 글이 있으면 반드시 그 내용을 근거로 답변하고 링크를 제시하세요. 예: \"네, [글 제목]에 따르면 ...입니다.\"\n- '확인이 필요해요', '관리자에게 문의하세요' 같은 회피성 답변은 컨텍스트에 진짜로 관련 정보가 전혀 없을 때만 최후의 수단으로 쓰세요.\n- 조금이라도 관련 키워드가 있으면 그 글을 인용해서 답변하세요.\n- 가격·개인정보 같은 수치 데이터는 절대 추측하지 말고 컨텍스트 그대로만 전달.\n\n[금지]\n- 경쟁 사이트 언급, 욕설, 법률/의료 전문 자문";
        var btn = document.getElementById('ncLoadDefaultPrompt');
        var ta = document.getElementById('ncSystemPrompt');
        if (btn && ta) {
            btn.addEventListener('click', function(){
                if (ta.value.trim() && !confirm('현재 입력된 내용을 권장 기본값으로 교체할까요?')) return;
                ta.value = recommendedPrompt;
                ta.focus();
                ta.scrollTop = 0;
            });
        }
    })();
    </script>

    <div class="nc-section">
        <h3 style="display:flex;justify-content:space-between;align-items:center">
            <span>사이트 지식 (AI 분석 결과 또는 직접 입력)</span>
            <button type="button" class="btn" id="ncLoadDefaultKnowledge" style="font-size:12px;padding:4px 10px">예시 템플릿 불러오기</button>
        </h3>
        <p style="color:#6b7280;font-size:13px">위 "AI 자동 분석"을 실행하면 여기가 자동으로 채워집니다. 직접 수정하셔도 되고, 500~2500자 권장입니다.<br>
        처음이시면 <strong>"예시 템플릿 불러오기"</strong> 누르시고 OOO 부분만 본인 사이트에 맞게 수정하세요.</p>
        <textarea name="site_knowledge" id="ncSiteKnowledge" rows="14" placeholder="AI 자동 분석 버튼을 누르거나, 예시 템플릿 불러오기로 시작하세요..."><?= htmlspecialchars($_nc['site_knowledge']) ?></textarea>
    </div>

    <script>
    (function(){
        var tmpl = "[사이트 소개]\n저희는 OOO 커뮤니티입니다. OOO에 관심 있는 분들이 자유롭게 정보를 나누고 소통하는 공간이에요.\n\n[주요 서비스]\n- 자유게시판: 일상 이야기, 정보 공유\n- 질문/답변: 궁금한 점 물어보기\n- 정보/팁 게시판: 노하우 모음\n- 공지사항: 운영자 공지 확인\n\n[회원가입]\n- 완전 무료\n- 이메일 인증만 하면 바로 가입 완료\n- SNS 간편가입도 지원 (카카오/네이버)\n\n[글쓰기 안내]\n- 로그인 후 게시판 상단의 '글쓰기' 버튼을 눌러주세요\n- 이미지, 동영상, 링크 모두 삽입 가능\n- 하루 최대 10개까지 작성 가능\n\n[운영 정책]\n- 광고성 글, 도배글, 욕설은 통보 없이 삭제됩니다\n- 동일 내용 반복 게시 시 계정이 제한될 수 있어요\n- 초상권/저작권 침해 콘텐츠 금지\n\n[자주 묻는 질문]\nQ. 비밀번호를 잊어버렸어요\nA. 로그인 화면 아래 \"비밀번호 찾기\"를 눌러 가입한 이메일로 재설정 링크를 받으실 수 있어요.\n\nQ. 닉네임은 변경할 수 있나요?\nA. 마이페이지 > 회원정보 수정에서 30일에 1번 변경 가능합니다.\n\nQ. 제 글이 갑자기 사라졌어요\nA. 운영 정책 위반으로 삭제되었을 수 있어요. 문의 남겨주시면 사유 확인 후 안내드릴게요.\n\nQ. 광고 문의는 어디로 하나요?\nA. 관리자에게 메시지를 남겨주시거나, 이 채팅창에서 '광고 문의' 라고 입력해주세요.\n\n[연락처]\n- 이메일: admin@example.com\n- 카카오톡 채널: @example\n- 평균 응답 시간: 평일 기준 2~4시간 이내";
        var btn = document.getElementById('ncLoadDefaultKnowledge');
        var ta = document.getElementById('ncSiteKnowledge');
        if (btn && ta) {
            btn.addEventListener('click', function(){
                if (ta.value.trim() && !confirm('현재 입력된 내용을 예시 템플릿으로 교체할까요?')) return;
                ta.value = tmpl;
                ta.focus();
                ta.scrollTop = 0;
            });
        }
    })();
    </script>

    <div class="nc-section">
        <h3>게시판 자동 참고 (RAG)</h3>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="use_board_rag" <?= $_nc['use_board_rag']==='1'?'checked':'' ?>>
                방문자 질문과 관련 있는 게시판 글을 자동으로 찾아 AI에게 전달 + 답변에 링크 첨부
            </label>
        </div>
        <div class="form-group">
            <label>검색 대상 게시판 ID (쉼표 구분, 비우면 전체)</label>
            <input type="text" name="rag_board_ids" value="<?= htmlspecialchars($_nc['rag_board_ids']) ?>" placeholder="예: notice,faq,guide">
            <small>예: "notice,faq" 로 지정하면 공지와 FAQ 게시판만 검색합니다.</small>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<?php elseif ($tab === 'chats'): ?>
<style>
.nca-wrap{display:grid;grid-template-columns:340px 1fr;gap:14px;height:calc(100vh - 280px);min-height:520px}
.nca-left{background:#fff;border:1px solid #e5e7eb;border-radius:10px;display:flex;flex-direction:column;overflow:hidden}
.nca-left-head{padding:12px 14px;border-bottom:1px solid #f1f3f5;display:flex;justify-content:space-between;align-items:center}
.nca-left-head h3{margin:0;font-size:14px;font-weight:700}
.nca-left-head .badge{background:#ef4444;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;font-weight:700}
.nca-ctrl{padding:10px 14px;border-bottom:1px solid #f1f3f5;display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:12px;color:#6b7280}
.nca-ctrl label{display:flex;align-items:center;gap:4px;cursor:pointer}
.nca-ctrl button{background:#fef2f2;color:#ef4444;border:1px solid #fecaca;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;margin-left:auto}
.nca-ctrl button:hover{background:#fee2e2}
.nca-list{flex:1;overflow-y:auto}
.nca-sess{padding:12px 14px;border-bottom:1px solid #f3f4f6;cursor:pointer;display:block;position:relative}
.nca-sess:hover{background:#f9fafb}
.nca-sess.active{background:#ecfdf5;border-left:3px solid #22c55e;padding-left:11px}
.nca-sess.unread{background:#fef9c3}
.nca-sess.unread.active{background:#ecfdf5}
.nca-sess-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.nca-sess-title{font-size:13px;font-weight:600;color:#111827;display:flex;align-items:center;gap:6px}
.nca-sess-title .dot{width:8px;height:8px;background:#ef4444;border-radius:50%}
.nca-sess-time{font-size:11px;color:#9ca3af}
.nca-sess-prev{font-size:12px;color:#4b5563;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nca-sess-del{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#fff;border:1px solid #e5e7eb;width:22px;height:22px;border-radius:50%;cursor:pointer;display:none;align-items:center;justify-content:center;color:#ef4444;font-size:14px;line-height:1;padding:0}
.nca-sess:hover .nca-sess-del{display:flex}
.nca-sess-del:hover{background:#fef2f2}

.nca-right{background:#fff;border:1px solid #e5e7eb;border-radius:10px;display:flex;flex-direction:column;overflow:hidden}
.nca-right-head{padding:12px 16px;border-bottom:1px solid #f1f3f5;display:flex;justify-content:space-between;align-items:center;background:#f9fafb}
.nca-right-head h3{margin:0;font-size:14px;font-weight:700}
.nca-msgs{flex:1;overflow-y:auto;padding:14px;background:#f7f8fa}
.nca-row{margin-bottom:10px;display:flex;gap:6px}
.nca-row.user{justify-content:flex-start}
.nca-row.admin,.nca-row.bot{justify-content:flex-end}
.nca-bubble{max-width:70%;padding:8px 12px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word;white-space:pre-wrap}
.nca-row.user .nca-bubble{background:#fff;border:1px solid #e5e7eb;border-bottom-left-radius:4px;color:#111827}
.nca-row.bot .nca-bubble{background:#f3f4f6;border:1px solid #e5e7eb;border-bottom-right-radius:4px;color:#374151}
.nca-row.admin .nca-bubble{background:#22c55e;color:#fff;border-bottom-right-radius:4px}
.nca-row .nca-meta{font-size:10px;color:#9ca3af;margin-top:2px;padding:0 4px;align-self:flex-end}
.nca-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;gap:10px}
.nca-empty svg{width:50px;height:50px;stroke:currentColor;fill:none;stroke-width:1.5}
.nca-reply{padding:12px;border-top:1px solid #f1f3f5;background:#fff;display:flex;gap:8px}
.nca-reply textarea{flex:1;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;font-size:13px;resize:none;font-family:inherit;min-height:44px;max-height:100px}
.nca-reply button{background:#22c55e;color:#fff;border:none;padding:0 18px;border-radius:8px;font-weight:600;cursor:pointer;white-space:nowrap}
.nca-reply button:disabled{background:#d1d5db;cursor:not-allowed}
.nca-live{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:#22c55e;font-weight:500}
.nca-live::before{content:"";width:7px;height:7px;background:#22c55e;border-radius:50%;animation:ncaPulse 1.5s infinite}
@keyframes ncaPulse{0%,100%{opacity:1}50%{opacity:.4}}
</style>

<div class="nca-wrap">
    <div class="nca-left">
        <div class="nca-left-head">
            <h3>대화 세션</h3>
            <span class="nca-live">실시간</span>
        </div>
        <div class="nca-ctrl">
            <label><input type="checkbox" id="ncaSoundOn"> 알림음</label>
            <label><input type="checkbox" id="ncaDesktopOn"> 데스크톱 알림</label>
            <button id="ncaDeleteAll" title="모든 대화 영구삭제">전체 삭제</button>
        </div>
        <div class="nca-list" id="ncaList">
            <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px">로딩중...</div>
        </div>
    </div>
    <div class="nca-right">
        <div class="nca-right-head">
            <h3 id="ncaSessTitle">세션을 선택하세요</h3>
            <span id="ncaSessMeta" style="font-size:11px;color:#9ca3af"></span>
        </div>
        <div class="nca-msgs" id="ncaMsgs">
            <div class="nca-empty">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <div>좌측에서 대화 세션을 선택하세요</div>
            </div>
        </div>
        <div class="nca-reply" id="ncaReplyWrap" style="display:none">
            <textarea id="ncaReply" placeholder="방문자에게 보낼 답변을 입력하세요 (Enter로 전송, Shift+Enter 줄바꿈)"></textarea>
            <button id="ncaSend">전송</button>
        </div>
    </div>
</div>

<script>
(function(){
    var API = '/?nc_api=';
    var listEl = document.getElementById('ncaList');
    var msgsEl = document.getElementById('ncaMsgs');
    var titleEl = document.getElementById('ncaSessTitle');
    var metaEl = document.getElementById('ncaSessMeta');
    var replyWrap = document.getElementById('ncaReplyWrap');
    var replyInput = document.getElementById('ncaReply');
    var sendBtn = document.getElementById('ncaSend');
    var soundOn = document.getElementById('ncaSoundOn');
    var desktopOn = document.getElementById('ncaDesktopOn');

    var state = { currentSid: null, sessions: [], messages: [], prevTotalUnread: 0, ticking: false };

    // 체크박스 상태 localStorage 연동
    soundOn.checked = localStorage.getItem('nca_sound') !== '0';
    desktopOn.checked = localStorage.getItem('nca_desktop') === '1';
    soundOn.addEventListener('change', function(){ localStorage.setItem('nca_sound', soundOn.checked ? '1' : '0'); });

    // 알림음 (beep)
    var beep = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU').catch ? null : new Audio();
    function playBeep(){
        if (!soundOn.checked) return;
        try {
            var a = new Audio('data:audio/wav;base64,UklGRiQFAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAFAAB/f39/f39/f3+AgICAgICAgICAgICAgIB/f39/f39/f38AAA==');
            a.volume = 0.5; a.play();
        } catch(e) {}
    }

    // 데스크톱 알림
    desktopOn.addEventListener('change', function(){
        if (desktopOn.checked && 'Notification' in window) {
            if (Notification.permission === 'default') {
                Notification.requestPermission().then(function(p){
                    if (p !== 'granted') desktopOn.checked = false;
                    localStorage.setItem('nca_desktop', desktopOn.checked ? '1' : '0');
                });
                return;
            } else if (Notification.permission === 'denied') {
                alert('브라우저에서 알림 권한이 차단되어 있어요. 주소창 좌측 자물쇠 아이콘에서 허용해주세요.');
                desktopOn.checked = false;
            }
        }
        localStorage.setItem('nca_desktop', desktopOn.checked ? '1' : '0');
    });
    function notifyDesktop(title, body){
        if (!desktopOn.checked) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        try { new Notification(title, { body: body, icon: '/favicon.ico', tag: 'nc-admin' }); } catch(e){}
    }

    function api(action, data, method){
        method = method || 'POST';
        var url = API + encodeURIComponent(action);
        var opts = { method: method, credentials:'same-origin' };
        if (method === 'POST'){
            var fd = new FormData();
            if (data) Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
            opts.body = fd;
        } else if (data){
            Object.keys(data).forEach(function(k){ url += '&'+k+'='+encodeURIComponent(data[k]); });
        }
        return fetch(url, opts).then(function(r){ return r.text(); }).then(function(t){
            try{ return JSON.parse(t); }catch(e){ console.error('응답 파싱 실패', t.slice(0,500)); return {ok:false,error:'응답 오류'}; }
        });
    }

    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
    function fmtTime(iso){
        if(!iso) return '';
        var d = new Date(iso.replace(' ','T'));
        if (isNaN(d.getTime())) return '';
        var now = new Date();
        var sameDay = d.toDateString() === now.toDateString();
        if (sameDay) {
            var h = d.getHours(), m = d.getMinutes();
            return (h<10?'0':'')+h + ':' + (m<10?'0':'')+m;
        }
        return (d.getMonth()+1)+'/'+d.getDate()+' '+d.getHours()+':'+(d.getMinutes()<10?'0':'')+d.getMinutes();
    }

    function loadSessions(){
        api('admin_sessions', { since_unread_total: state.prevTotalUnread }, 'GET').then(function(res){
            if (!res.ok) { listEl.innerHTML = '<div style="padding:20px;color:#ef4444;font-size:12px">'+escapeHtml(res.error||'로딩 실패')+'</div>'; return; }

            var newUnread = res.total_unread || 0;
            if (newUnread > state.prevTotalUnread && state.prevTotalUnread >= 0) {
                // 새 메시지 알림
                playBeep();
                notifyDesktop('누리챗 새 메시지', '방문자 ' + (newUnread - state.prevTotalUnread) + '건의 답변 대기중');
            }
            state.prevTotalUnread = newUnread;
            state.sessions = res.sessions || [];

            renderList();
            updatePageTitle(newUnread);
        });
    }

    function updatePageTitle(n){
        var base = document.title.replace(/^\(\d+\)\s*/, '');
        document.title = n > 0 ? ('(' + n + ') ' + base) : base;
    }

    function renderList(){
        if (state.sessions.length === 0) {
            listEl.innerHTML = '<div style="padding:30px;text-align:center;color:#9ca3af;font-size:13px">아직 대화가 없습니다.</div>';
            return;
        }
        var totalUnread = state.prevTotalUnread;
        document.querySelector('.nca-left-head').innerHTML = '<h3>대화 세션 (' + state.sessions.length + ')</h3>' +
            (totalUnread > 0 ? '<span class="badge">답변대기 ' + totalUnread + '</span>' : '<span class="nca-live">실시간</span>');

        var html = '';
        state.sessions.forEach(function(s){
            var unread = (s.unread_for_admin || 0) > 0;
            var who = s.member_id ? ('회원 #' + s.member_id) : '비회원';
            html += '<div class="nca-sess ' + (state.currentSid === s.id ? 'active' : '') + (unread ? ' unread' : '') + '" data-sid="' + s.id + '">' +
                    '<div class="nca-sess-top">' +
                      '<div class="nca-sess-title">' +
                        (unread ? '<span class="dot"></span>' : '') +
                        '세션 #' + s.id + ' <span style="color:#9ca3af;font-weight:400;font-size:11px">· ' + escapeHtml(who) + '</span>' +
                      '</div>' +
                      '<div class="nca-sess-time">' + fmtTime(s.last_msg_at || s.last_active_at) + '</div>' +
                    '</div>' +
                    '<div class="nca-sess-prev">' + escapeHtml(s.last_user_msg || '(질문 없음)') + '</div>' +
                    '<button class="nca-sess-del" data-del-sid="' + s.id + '" title="이 대화 삭제">×</button>' +
                   '</div>';
        });
        listEl.innerHTML = html;

        listEl.querySelectorAll('.nca-sess').forEach(function(el){
            el.addEventListener('click', function(e){
                if (e.target.dataset.delSid) return;
                selectSession(parseInt(el.dataset.sid, 10));
            });
        });
        listEl.querySelectorAll('[data-del-sid]').forEach(function(el){
            el.addEventListener('click', function(e){
                e.stopPropagation();
                var sid = parseInt(el.dataset.delSid, 10);
                if (!confirm('세션 #' + sid + ' 대화를 영구 삭제할까요?')) return;
                api('admin_delete', { session_id: sid }, 'POST').then(function(res){
                    if (res.ok) {
                        if (state.currentSid === sid) clearRight();
                        loadSessions();
                    } else {
                        alert('삭제 실패: ' + (res.error || ''));
                    }
                });
            });
        });
    }

    function selectSession(sid){
        state.currentSid = sid;
        renderList();
        replyWrap.style.display = 'flex';
        replyInput.focus();
        api('admin_messages', { session_id: sid }, 'GET').then(function(res){
            if (!res.ok) { msgsEl.innerHTML = '<div class="nca-empty">'+escapeHtml(res.error||'')+'</div>'; return; }
            state.messages = res.messages || [];
            var s = res.session || {};
            titleEl.textContent = '세션 #' + sid + (s.member_id ? ' · 회원 #' + s.member_id : ' · 비회원');
            metaEl.textContent = '시작: ' + fmtTime(s.started_at) + ' · 최근: ' + fmtTime(s.last_active_at);
            renderMessages();
            // 읽음 처리되었으니 목록 갱신
            setTimeout(loadSessions, 200);
        });
    }

    function clearRight(){
        state.currentSid = null;
        state.messages = [];
        titleEl.textContent = '세션을 선택하세요';
        metaEl.textContent = '';
        replyWrap.style.display = 'none';
        msgsEl.innerHTML = '<div class="nca-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><div>좌측에서 대화 세션을 선택하세요</div></div>';
    }

    function renderMessages(){
        if (state.messages.length === 0) {
            msgsEl.innerHTML = '<div class="nca-empty" style="height:100%">아직 메시지가 없습니다.</div>';
            return;
        }
        var html = '';
        state.messages.forEach(function(m){
            var who = m.sender === 'user' ? '방문자' : (m.sender === 'admin' ? '관리자' : '봇');
            html += '<div class="nca-row ' + m.sender + '">' +
                    '<div style="display:flex;flex-direction:column;' + (m.sender==='user'?'align-items:flex-start':'align-items:flex-end') + '">' +
                      '<div class="nca-bubble">' + escapeHtml(m.content) + '</div>' +
                      '<div class="nca-meta">' + who + ' · ' + fmtTime(m.created_at) + '</div>' +
                    '</div></div>';
        });
        msgsEl.innerHTML = html;
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function sendReply(){
        var text = replyInput.value.trim();
        if (!text || !state.currentSid || state.ticking) return;
        state.ticking = true;
        sendBtn.disabled = true;
        api('admin_reply', { session_id: state.currentSid, reply: text }, 'POST').then(function(res){
            state.ticking = false;
            sendBtn.disabled = false;
            if (!res.ok) { alert('전송 실패: ' + (res.error || '')); return; }
            replyInput.value = '';
            state.messages.push(res.message);
            renderMessages();
            loadSessions();
        });
    }

    sendBtn.addEventListener('click', sendReply);
    replyInput.addEventListener('keydown', function(e){
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
    });

    document.getElementById('ncaDeleteAll').addEventListener('click', function(){
        if (!confirm('모든 대화 세션과 메시지를 영구 삭제합니다.\n정말 진행할까요?')) return;
        if (!confirm('⚠ 복구 불가능합니다. 한 번 더 확인합니다.\n진짜 전체 삭제할까요?')) return;
        api('admin_delete_all', {}, 'POST').then(function(res){
            if (res.ok) { clearRight(); loadSessions(); }
            else alert('삭제 실패: ' + (res.error || ''));
        });
    });

    // 선택된 세션 있으면 그것도 10초마다 메시지 재로드 (관리자 답변 외 방문자 새 질문 반영)
    setInterval(function(){
        if (state.currentSid) {
            api('admin_messages', { session_id: state.currentSid }, 'GET').then(function(res){
                if (res.ok && res.messages) {
                    var prevLen = state.messages.length;
                    state.messages = res.messages;
                    if (state.messages.length > prevLen) renderMessages();
                }
            });
        }
    }, 10000);

    // 세션 목록 10초마다
    loadSessions();
    setInterval(loadSessions, 10000);
})();
</script>
<?php endif; ?>

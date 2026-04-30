<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * 워드프레스 동시 포스팅 v2.0 - 설정 페이지
 */

$_wps_base        = defined('NB_ROOT') ? NB_ROOT : dirname(dirname(__DIR__));
$_wps_config_file = $_wps_base . '/data/wp-sync-post/config.json';
$_wps_log_file    = $_wps_base . '/data/wp-sync-post/log.json';
if (!is_dir(dirname($_wps_config_file))) @mkdir(dirname($_wps_config_file), 0755, true);

$_wps_raw = file_exists($_wps_config_file)
    ? json_decode(file_get_contents($_wps_config_file), true)
    : [];
if (!is_array($_wps_raw)) $_wps_raw = [];

$_wps = array_merge([
    'enabled'          => '0',
    'post_type'        => 'posts',
    'wp_url'           => '',
    'wp_username'      => '',
    'wp_password'      => '',
    'openai_api_key'   => '',
    'unsplash_api_key' => '',
    'prompts'          => [],
    'anchor_links'     => [],
], $_wps_raw);

// ===== 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wps_save'])) {
    $_wps['enabled']          = isset($_POST['enabled']) ? '1' : '0';
    $_wps['post_type']        = $_POST['post_type'] === 'pages' ? 'pages' : 'posts';
    $_wps['wp_url']           = trim($_POST['wp_url'] ?? '');
    $_wps['wp_username']      = trim($_POST['wp_username'] ?? '');
    $_wps['wp_password']      = trim($_POST['wp_password'] ?? '');
    $_wps['openai_api_key']   = trim($_POST['openai_api_key'] ?? '');
    $_wps['unsplash_api_key'] = trim($_POST['unsplash_api_key'] ?? '');
    $_wps['prompts']          = array_values(array_filter(array_map('trim', $_POST['prompts'] ?? [])));
    $anchorRaw                = trim($_POST['anchor_links'] ?? '');
    $_wps['anchor_links']     = $anchorRaw ? array_values(array_filter(array_map('trim', explode("\n", $anchorRaw)))) : [];

    file_put_contents($_wps_config_file, json_encode($_wps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="wps-alert wps-alert-ok">저장되었습니다.</div>';
}

// ===== 로그 데이터 =====
$_wps_logs = [];
if (file_exists($_wps_log_file)) {
    $raw = json_decode(file_get_contents($_wps_log_file), true);
    if (is_array($raw)) $_wps_logs = $raw;
}
?>

<style>
.wps-section { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px; margin-bottom:16px; }
.wps-section h3 { margin:0 0 16px; font-size:15px; font-weight:700; color:#111827; display:flex; align-items:center; gap:8px; }
.wps-row { margin-bottom:14px; }
.wps-row label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
.wps-row input[type=text],.wps-row input[type=password],.wps-row input[type=url] {
    width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; box-sizing:border-box;
}
.wps-row input:focus,.wps-row textarea:focus { outline:none; border-color:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.15); }
.wps-flex { display:flex; gap:8px; align-items:center; }
.wps-flex input { flex:1; }
.wps-btn { padding:7px 14px; border:1px solid #d1d5db; border-radius:6px; background:#fff; font-size:13px; cursor:pointer; white-space:nowrap; }
.wps-btn:hover { background:#f9fafb; }
.wps-btn-green { background:#22c55e; border-color:#22c55e; color:#fff; font-weight:600; }
.wps-btn-green:hover { background:#16a34a; border-color:#16a34a; }
.wps-btn-del { padding:3px 10px; font-size:11px; background:#fff; border:1px solid #fca5a5; color:#dc2626; border-radius:5px; cursor:pointer; }
.wps-btn-del:hover { background:#fee2e2; }
.wps-result { font-size:13px; font-weight:700; min-width:50px; }
.wps-small { font-size:11px; color:#9ca3af; margin-top:4px; }
.wps-badge { display:inline-block; padding:2px 8px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; font-size:11px; color:#166534; font-weight:600; }
.wps-prompt-item { border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:10px; }
.wps-prompt-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.wps-prompt-header span { font-size:13px; font-weight:600; color:#6b7280; }
.wps-prompt-item textarea { width:100%; min-height:90px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:12px; font-family:monospace; resize:vertical; box-sizing:border-box; }
.wps-toggle-wrap { display:flex; align-items:center; gap:10px; }
.wps-toggle { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
.wps-toggle input { opacity:0; width:0; height:0; }
.wps-toggle-slider { position:absolute; inset:0; background:#d1d5db; border-radius:24px; cursor:pointer; transition:.2s; }
.wps-toggle-slider:before { content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.wps-toggle input:checked + .wps-toggle-slider { background:#22c55e; }
.wps-toggle input:checked + .wps-toggle-slider:before { transform:translateX(20px); }
.wps-radio-group { display:flex; gap:10px; }
.wps-radio-group label { display:flex; align-items:center; gap:6px; padding:8px 16px; border:1px solid #d1d5db; border-radius:7px; cursor:pointer; font-size:13px; font-weight:600; }
.wps-radio-group input[type=radio]:checked + span { color:#16a34a; }
.wps-radio-group label:has(input:checked) { border-color:#22c55e; background:#f0fdf4; }
.wps-alert { padding:12px 16px; border-radius:8px; font-weight:600; margin-bottom:16px; font-size:13px; }
.wps-alert-ok { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.wps-log-table { width:100%; border-collapse:collapse; font-size:12px; }
.wps-log-table th { text-align:left; padding:7px 10px; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-weight:600; color:#6b7280; }
.wps-log-table td { padding:7px 10px; border-bottom:1px solid #f3f4f6; vertical-align:top; }
.wps-log-table tr:last-child td { border-bottom:none; }
.wps-dot-ok { display:inline-block; width:8px; height:8px; border-radius:50%; background:#22c55e; margin-right:5px; }
.wps-dot-err { display:inline-block; width:8px; height:8px; border-radius:50%; background:#ef4444; margin-right:5px; }
.wps-log-msg { color:#6b7280; max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>

<form method="post">
<input type="hidden" name="wps_save" value="1">

<!-- 활성화 -->
<div class="wps-section">
    <div class="wps-toggle-wrap">
        <label class="wps-toggle">
            <input type="checkbox" name="enabled" <?= $_wps['enabled'] === '1' ? 'checked' : '' ?>>
            <span class="wps-toggle-slider"></span>
        </label>
        <div>
            <strong style="font-size:14px">동시 포스팅 활성화</strong>
            <div class="wps-small">켜면 누리보드에서 글 작성 시 워드프레스에 자동으로 포스팅됩니다.</div>
        </div>
    </div>
</div>

<!-- 워드프레스 연결 -->
<div class="wps-section">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        워드프레스 연결
    </h3>

    <div class="wps-row">
        <label>포스팅 유형</label>
        <div class="wps-radio-group">
            <label>
                <input type="radio" name="post_type" value="posts" <?= $_wps['post_type'] !== 'pages' ? 'checked' : '' ?>>
                <span>글 (Posts)</span>
            </label>
            <label>
                <input type="radio" name="post_type" value="pages" <?= $_wps['post_type'] === 'pages' ? 'checked' : '' ?>>
                <span>페이지 (Pages)</span>
            </label>
        </div>
        <div class="wps-small">워드프레스 관리자에서 글 목록에 보이려면 "글" 선택</div>
    </div>

    <div class="wps-row">
        <label>사이트 주소</label>
        <input type="url" name="wp_url" id="wps_wp_url" value="<?= htmlspecialchars($_wps['wp_url']) ?>" placeholder="https://example.com">
        <div class="wps-small">워드프레스가 설치된 주소 (끝에 / 없이)</div>
    </div>

    <div class="wps-row">
        <label>사용자 아이디</label>
        <input type="text" name="wp_username" id="wps_wp_username" value="<?= htmlspecialchars($_wps['wp_username']) ?>" placeholder="admin">
    </div>

    <div class="wps-row">
        <label>애플리케이션 비밀번호</label>
        <input type="password" name="wp_password" id="wps_wp_password" value="<?= htmlspecialchars($_wps['wp_password']) ?>" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx">
        <div class="wps-small">워드프레스 관리자 &gt; 사용자 &gt; 프로필 &gt; 애플리케이션 비밀번호에서 발급</div>
    </div>

    <div style="display:flex;align-items:center;gap:10px">
        <button type="button" class="wps-btn" onclick="wpsTestWp()">연결 테스트</button>
        <span id="wps_result_wp" class="wps-result"></span>
    </div>
</div>

<!-- OpenAI -->
<div class="wps-section">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        OpenAI 설정
        <span class="wps-badge">gpt-4o-mini</span>
    </h3>

    <div class="wps-row">
        <label>API 키</label>
        <div class="wps-flex">
            <input type="password" name="openai_api_key" id="wps_openai_key" value="<?= htmlspecialchars($_wps['openai_api_key']) ?>" placeholder="sk-or-v1-...">
            <button type="button" class="wps-btn" onclick="wpsTestOpenai()">테스트</button>
            <span id="wps_result_openai" class="wps-result"></span>
        </div>
        <div class="wps-small">원문을 워드프레스용으로 재가공. 비워두면 원본 그대로 포스팅.</div>
    </div>
</div>

<!-- Unsplash -->
<div class="wps-section">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        Unsplash 이미지
    </h3>

    <div class="wps-row">
        <label>API 키 (Access Key)</label>
        <div class="wps-flex">
            <input type="text" name="unsplash_api_key" id="wps_unsplash_key" value="<?= htmlspecialchars($_wps['unsplash_api_key']) ?>" placeholder="Access Key 입력">
            <button type="button" class="wps-btn" onclick="wpsTestUnsplash()">테스트</button>
            <span id="wps_result_unsplash" class="wps-result"></span>
        </div>
        <div class="wps-small">대표 이미지 1장 + 본문 사이사이 3장 자동 삽입. 무료 · 시간당 50건. <a href="https://unsplash.com/developers" target="_blank" style="color:#22c55e">발급 바로가기</a></div>
    </div>
</div>

<!-- 앵커텍스트 -->
<div class="wps-section">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        앵커텍스트 (홍보 링크)
    </h3>
    <div class="wps-row">
        <label>홍보 링크 목록 (한 줄에 하나씩, URL | 표시텍스트)</label>
        <textarea name="anchor_links" rows="5" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-family:monospace;resize:vertical;box-sizing:border-box"
            placeholder="https://example.com | 예시 사이트&#10;https://shop.example.com | 쇼핑몰 바로가기"><?= htmlspecialchars(implode("\n", $_wps['anchor_links'])) ?></textarea>
        <div class="wps-small">AI가 본문에 자연스럽게 1~2개 삽입합니다. 홍보, 백링크, 내부링크 등에 활용하세요.</div>
    </div>
</div>

<!-- AI 프롬프트 -->
<div class="wps-section">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        AI 프롬프트 (원문 변형 지시)
    </h3>
    <p style="font-size:13px;color:#6b7280;margin:0 0 6px">원문 내용 + 아래 프롬프트를 합쳐서 AI에게 전달합니다. 여러 개 등록 시 랜덤 선택.</p>
    <p style="font-size:12px;color:#9ca3af;margin:0 0 12px">변수: <code style="background:#f3f4f6;padding:1px 5px;border-radius:3px">{title}</code> <code style="background:#f3f4f6;padding:1px 5px;border-radius:3px">{content}</code> — 비워두면 기본 프롬프트 사용</p>

    <div id="wps_prompt_list">
        <?php
        $prompts = $_wps['prompts'];
        if (empty($prompts)) $prompts = [''];
        foreach ($prompts as $pi => $pv):
        ?>
        <div class="wps-prompt-item">
            <div class="wps-prompt-header">
                <span>프롬프트 #<span class="wps-pnum"><?= $pi + 1 ?></span></span>
                <button type="button" class="wps-btn-del" onclick="wpsRemovePrompt(this)">삭제</button>
            </div>
            <textarea name="prompts[]" placeholder="예: 전문가 분위기로 작성해주세요. SEO 키워드를 자연스럽게 포함하고, 독자가 행동을 취하도록 유도하는 마무리 문장을 넣어주세요."><?= htmlspecialchars($pv) ?></textarea>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="wps-btn wps-btn-green" onclick="wpsAddPrompt()" style="margin-top:4px">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        프롬프트 추가
    </button>
</div>

<!-- 저장 -->
<button type="submit" class="wps-btn wps-btn-green" style="padding:10px 28px;font-size:14px">저장</button>
</form>

<!-- 포스팅 로그 -->
<div class="wps-section" style="margin-top:24px">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        포스팅 로그
        <?php if (!empty($_wps_logs)): ?>
        <span style="font-size:11px;font-weight:400;color:#9ca3af">최근 <?= count($_wps_logs) ?>건</span>
        <?php endif; ?>
    </h3>

    <?php if (empty($_wps_logs)): ?>
    <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px">
        아직 포스팅 기록이 없습니다. 글을 작성하면 여기에 결과가 표시됩니다.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="wps-log-table">
        <thead>
            <tr>
                <th>시간</th>
                <th>결과</th>
                <th>제목</th>
                <th>메시지</th>
                <th>WP 링크</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($_wps_logs as $log): ?>
        <tr>
            <td style="white-space:nowrap;color:#6b7280"><?= htmlspecialchars($log['time'] ?? '') ?></td>
            <td style="white-space:nowrap">
                <?php if (($log['status'] ?? '') === 'success'): ?>
                    <span class="wps-dot-ok"></span><span style="color:#16a34a;font-weight:600">성공</span>
                <?php else: ?>
                    <span class="wps-dot-err"></span><span style="color:#dc2626;font-weight:600">실패</span>
                <?php endif; ?>
            </td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($log['title'] ?? '') ?></td>
            <td class="wps-log-msg" title="<?= htmlspecialchars($log['message'] ?? '') ?>"><?= htmlspecialchars($log['message'] ?? '') ?></td>
            <td>
                <?php if (!empty($log['wp_post_url'])): ?>
                <a href="<?= htmlspecialchars($log['wp_post_url']) ?>" target="_blank" style="color:#22c55e;font-size:11px">보기</a>
                <?php else: ?>
                <span style="color:#d1d5db">-</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<script>
function wpsAddPrompt(){
    var list=document.getElementById('wps_prompt_list');
    var count=list.querySelectorAll('.wps-prompt-item').length+1;
    var div=document.createElement('div');
    div.className='wps-prompt-item';
    div.innerHTML='<div class="wps-prompt-header"><span>프롬프트 #<span class="wps-pnum">'+count+'</span></span><button type="button" class="wps-btn-del" onclick="wpsRemovePrompt(this)">삭제</button></div><textarea name="prompts[]" placeholder="예: 전문가 분위기로 작성해주세요..."></textarea>';
    list.appendChild(div);
    div.querySelector('textarea').focus();
}
function wpsRemovePrompt(btn){
    var list=document.getElementById('wps_prompt_list');
    if(list.querySelectorAll('.wps-prompt-item').length<=1){alert('최소 1개가 필요합니다.');return;}
    btn.closest('.wps-prompt-item').remove();
    list.querySelectorAll('.wps-pnum').forEach(function(s,i){s.textContent=i+1;});
}
function wpsTestWp(){
    var url=document.getElementById('wps_wp_url').value.trim().replace(/\/$/,'');
    var user=document.getElementById('wps_wp_username').value.trim();
    var pass=document.getElementById('wps_wp_password').value.trim();
    var res=document.getElementById('wps_result_wp');
    if(!url||!user||!pass){res.textContent='정보를 입력하세요';res.style.color='#dc2626';return;}
    res.textContent='확인 중...';res.style.color='#9ca3af';
    fetch(url+'/wp-json/wp/v2/users/me',{headers:{'Authorization':'Basic '+btoa(unescape(encodeURIComponent(user+':'+pass)))}})
    .then(function(r){res.textContent=r.ok?'연결 성공':'연결 실패 ('+r.status+')';res.style.color=r.ok?'#22c55e':'#dc2626';})
    .catch(function(){res.textContent='연결 실패 (CORS 또는 URL 오류)';res.style.color='#dc2626';});
}
function wpsTestOpenai(){
    var key=document.getElementById('wps_openai_key').value.trim();
    var res=document.getElementById('wps_result_openai');
    if(!key){res.textContent='키를 입력하세요';res.style.color='#dc2626';return;}
    res.textContent='확인 중...';res.style.color='#9ca3af';
    fetch('https://openrouter.ai/api/v1/models',{headers:{'Authorization':'Bearer '+key}})
    .then(function(r){res.textContent=r.ok?'성공':'실패 ('+r.status+')';res.style.color=r.ok?'#22c55e':'#dc2626';})
    .catch(function(){res.textContent='실패';res.style.color='#dc2626';});
}
function wpsTestUnsplash(){
    var key=document.getElementById('wps_unsplash_key').value.trim();
    var res=document.getElementById('wps_result_unsplash');
    if(!key){res.textContent='키를 입력하세요';res.style.color='#dc2626';return;}
    res.textContent='확인 중...';res.style.color='#9ca3af';
    fetch('https://api.unsplash.com/search/photos?query=test&per_page=1&client_id='+encodeURIComponent(key),{headers:{'Accept-Version':'v1'}})
    .then(function(r){res.textContent=r.ok?'성공':'실패 ('+r.status+')';res.style.color=r.ok?'#22c55e':'#dc2626';})
    .catch(function(){res.textContent='실패';res.style.color='#dc2626';});
}
</script>

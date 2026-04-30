<?php
/**
 * 텔레그램 알림 - 설정 페이지
 */

$_configFile = _tgn_data_dir() . '/config.json';
$_raw        = file_exists($_configFile) ? json_decode(file_get_contents($_configFile), true) : [];
if (!is_array($_raw)) $_raw = [];

$cfg = array_merge([
    'bot_token'      => '',
    'chat_id'        => '',
    'notify_post'    => '1',
    'notify_comment' => '1',
    'notify_memo'    => '1',
], $_raw);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tgn_save'])) {
    $cfg['bot_token']      = trim($_POST['bot_token']      ?? '');
    $cfg['chat_id']        = trim($_POST['chat_id']        ?? '');
    $cfg['notify_post']    = isset($_POST['notify_post'])    ? '1' : '0';
    $cfg['notify_comment'] = isset($_POST['notify_comment']) ? '1' : '0';
    $cfg['notify_memo']    = isset($_POST['notify_memo'])    ? '1' : '0';
    file_put_contents($_configFile, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '✅ 저장되었습니다.';
}
?>

<style>
.tgn-wrap { max-width: 620px; font-family: -apple-system, sans-serif; }
.tgn-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
.tgn-card h2 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
.tgn-row { margin-bottom: 16px; }
.tgn-row label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.tgn-input { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
.tgn-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.tgn-btn { padding: 10px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; background: #3b82f6; color: #fff; }
.tgn-btn:hover { background: #2563eb; }
.tgn-btn-test { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; margin-left: 8px; padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px; }
.tgn-btn-test:hover { background: #e2e8f0; }
.tgn-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.tgn-check { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 14px; color: #334155; cursor: pointer; }
.tgn-check input { width: 16px; height: 16px; cursor: pointer; }
.tgn-info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #1e40af; line-height: 1.7; margin-bottom: 16px; }
</style>

<div class="tgn-wrap">

<?php if ($msg): ?>
<div class="tgn-msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="tgn-card">
    <h2>📲 텔레그램 봇 설정</h2>

    <div class="tgn-info">
        <strong>설정 방법</strong><br>
        1. 텔레그램에서 <b>@BotFather</b> 에게 /newbot 명령으로 봇 생성<br>
        2. 받은 <b>Bot Token</b> 입력 (예: 123456:ABC-DEF...)<br>
        3. 봇과 대화 후 <b>@userinfobot</b> 에서 본인 <b>Chat ID</b> 확인<br>
        4. 저장 후 테스트 버튼으로 확인
    </div>

    <form method="post">
        <input type="hidden" name="tgn_save" value="1">

        <div class="tgn-row">
            <label>Bot Token</label>
            <div style="display:flex;align-items:center">
                <input type="password" name="bot_token" id="tgn_token" class="tgn-input"
                       value="<?= htmlspecialchars($cfg['bot_token']) ?>" placeholder="123456789:AABBcc...">
                <button type="button" class="tgn-btn-test" onclick="tgnTest()">테스트</button>
            </div>
            <div id="tgn_test_result" style="font-size:13px;margin-top:6px"></div>
        </div>

        <div class="tgn-row">
            <label>Chat ID <span style="font-weight:400;color:#94a3b8">(본인 또는 그룹 채팅 ID)</span></label>
            <input type="text" name="chat_id" class="tgn-input"
                   value="<?= htmlspecialchars($cfg['chat_id']) ?>" placeholder="123456789">
        </div>

        <div class="tgn-row">
            <label>알림 항목</label>
            <label class="tgn-check">
                <input type="checkbox" name="notify_post" <?= $cfg['notify_post']    === '1' ? 'checked' : '' ?>>
                📝 새 게시글
            </label>
            <label class="tgn-check">
                <input type="checkbox" name="notify_comment" <?= $cfg['notify_comment'] === '1' ? 'checked' : '' ?>>
                💬 새 댓글
            </label>
            <label class="tgn-check">
                <input type="checkbox" name="notify_memo" <?= $cfg['notify_memo']    === '1' ? 'checked' : '' ?>>
                ✉️ 새 쪽지
            </label>
        </div>

        <button type="submit" class="tgn-btn">💾 저장</button>
    </form>
</div>

<div class="tgn-card" style="font-size:13px;color:#64748b">
    <h2>ℹ️ 작동 방식</h2>
    <ul style="margin:0;padding-left:18px;line-height:2">
        <li><b>게시글</b>: 글이 작성되는 즉시 알림 전송</li>
        <li><b>댓글/쪽지</b>: 사이트 방문자 접속 시마다 새 항목 확인 (최대 60초 지연)</li>
        <li>설정은 <code>/data/telegram-notify/config.json</code> 에 저장 → 플러그인 삭제 후 재설치해도 유지</li>
    </ul>
</div>

</div>

<script>
function tgnTest() {
    var token  = document.getElementById('tgn_token').value.trim();
    var result = document.getElementById('tgn_test_result');
    if (!token) { result.innerHTML = '<span style="color:#ef4444">Bot Token을 입력하세요.</span>'; return; }
    result.innerHTML = '<span style="color:#94a3b8">테스트 중...</span>';
    fetch('https://api.telegram.org/bot' + encodeURIComponent(token) + '/getMe')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            result.innerHTML = '<span style="color:#22c55e">✅ 연결 성공! 봇 이름: @' + data.result.username + '</span>';
        } else {
            result.innerHTML = '<span style="color:#ef4444">❌ 실패: ' + (data.description || '알 수 없는 오류') + '</span>';
        }
    }).catch(function() {
        result.innerHTML = '<span style="color:#ef4444">❌ 네트워크 오류</span>';
    });
}
</script>

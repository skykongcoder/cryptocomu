<?php
/**
 * AI 운영 모니터 — 챗봇 대화 / 자동 글 / 자동 댓글을 한 화면에서 검토
 *
 * 매일 admin 이 들어와서:
 *  - 이상한 응답 ⭐ 별점 + 메모
 *  - "Claude 리포트 복사" 버튼으로 마크다운 추출
 *  - 채팅에 붙여넣어 프롬프트 개선 요청
 */

$prefix = DB::getPrefix();

// === 리뷰 테이블 자동 생성 ===
try {
    DB::query("CREATE TABLE IF NOT EXISTS {$prefix}ai_review (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        item_type VARCHAR(20) NOT NULL,
        item_id INT UNSIGNED NOT NULL,
        rating TINYINT NULL,
        note TEXT NULL,
        flag VARCHAR(30) NULL,
        reviewed_at DATETIME NOT NULL,
        UNIQUE KEY uk_item (item_type, item_id),
        INDEX idx_reviewed (reviewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// === POST: 평가 저장 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_review_save'])) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $type   = $_POST['item_type'] ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
    $note   = trim($_POST['note'] ?? '');
    $flag   = trim($_POST['flag'] ?? '');
    if (!in_array($type, ['chat', 'post', 'comment'], true) || !$id) {
        echo json_encode(['ok' => false, 'error' => 'invalid params']);
        exit;
    }
    DB::query(
        "INSERT INTO {$prefix}ai_review (item_type, item_id, rating, note, flag, reviewed_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE rating=VALUES(rating), note=VALUES(note), flag=VALUES(flag), reviewed_at=NOW()",
        [$type, $id, $rating, $note, $flag]
    );
    echo json_encode(['ok' => true]);
    exit;
}

// === 데이터 로드 ===
$hours = max(1, (int)($_GET['hours'] ?? 48));
$tab   = $_GET['tab'] ?? 'chat';

// 챗봇 대화 (user → bot 페어)
$chatPairs = DB::fetchAll("
    SELECT
        u.id AS user_msg_id, u.session_id, u.content AS user_content, u.created_at AS user_at,
        (SELECT id FROM {$prefix}nc_messages WHERE session_id = u.session_id AND sender = 'bot' AND id > u.id ORDER BY id ASC LIMIT 1) AS bot_msg_id,
        (SELECT content FROM {$prefix}nc_messages WHERE session_id = u.session_id AND sender = 'bot' AND id > u.id ORDER BY id ASC LIMIT 1) AS bot_content,
        (SELECT created_at FROM {$prefix}nc_messages WHERE session_id = u.session_id AND sender = 'bot' AND id > u.id ORDER BY id ASC LIMIT 1) AS bot_at
    FROM {$prefix}nc_messages u
    WHERE u.sender = 'user'
      AND u.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY u.id DESC
    LIMIT 100
", [$hours]);

// AI 자동 글 (가상 회원이 쓴 글 = ai_seed_*, ai_user_*, asb_bot_*)
$autoPosts = DB::fetchAll("
    SELECT p.id, p.board_id, p.title, p.content, p.created_at, m.nickname, m.user_id
    FROM {$prefix}posts p
    LEFT JOIN {$prefix}members m ON p.member_id = m.id
    WHERE m.user_id REGEXP '^(ai_seed_|ai_user_|asb_bot_)'
      AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY p.id DESC
    LIMIT 50
", [$hours]);

// AI 자동 댓글 (aic_* 또는 ai_seed_* 작성)
$autoComments = DB::fetchAll("
    SELECT c.id, c.post_id, c.content, c.created_at, m.nickname, m.user_id, p.title AS post_title, p.board_id
    FROM {$prefix}comments c
    LEFT JOIN {$prefix}members m ON c.member_id = m.id
    LEFT JOIN {$prefix}posts p ON c.post_id = p.id
    WHERE m.user_id REGEXP '^(aic_|ai_seed_)'
      AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY c.id DESC
    LIMIT 100
", [$hours]);

// 기존 리뷰 정보 로드
$reviewMap = [];
foreach (DB::fetchAll("SELECT * FROM {$prefix}ai_review") as $r) {
    $reviewMap[$r['item_type'] . '_' . $r['item_id']] = $r;
}
function rev($map, $type, $id) {
    return $map[$type . '_' . $id] ?? null;
}

// 통계
$stats = [
    'chat_count'    => count($chatPairs),
    'post_count'    => count($autoPosts),
    'comment_count' => count($autoComments),
    'reviewed'      => count($reviewMap),
];

adminHeader('ai-monitor');
?>

<style>
.aim-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.aim-head h1 { margin:0; font-size:22px; font-weight:700; }
.aim-stats { display:flex; gap:16px; font-size:13px; color:#64748b; }
.aim-stats b { color:#2563eb; font-size:16px; }

.aim-controls { display:flex; gap:8px; margin-bottom:18px; align-items:center; flex-wrap:wrap; }
.aim-tab { padding:8px 18px; background:#fff; border:1px solid #d1d5db; border-radius:8px; cursor:pointer; text-decoration:none; color:#475569; font-weight:500; font-size:13px; }
.aim-tab.active { background:#2563eb; color:#fff; border-color:#2563eb; }
.aim-tab small { background:rgba(255,255,255,0.25); padding:1px 6px; border-radius:8px; margin-left:6px; }
.aim-period { margin-left:auto; }
.aim-period select { padding:6px 12px; border:1px solid #d1d5db; border-radius:6px; }

.aim-toolbar { background:linear-gradient(90deg,#dbeafe,#fef3c7); border:1px solid #fbbf24; border-radius:10px; padding:12px 16px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
.aim-toolbar small { color:#475569; }
.aim-btn-export { background:#0f172a; color:#fff; border:0; padding:10px 20px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px; }
.aim-btn-export:hover { background:#1e293b; }

.aim-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:10px; transition:all .15s; }
.aim-card.flagged { border-color:#fb7185; box-shadow:0 0 0 3px rgba(251,113,133,0.1); }
.aim-card.good { border-color:#10b981; }
.aim-card-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; gap:10px; }
.aim-card-meta { font-size:11px; color:#64748b; font-family:'JetBrains Mono', monospace; }
.aim-card-meta b { color:#1e293b; }
.aim-card-tag { display:inline-block; padding:1px 8px; background:#f1f5f9; border-radius:10px; font-size:10px; font-weight:600; color:#475569; }
.aim-msg-user { background:#eff6ff; padding:10px 12px; border-radius:8px; margin-bottom:6px; font-size:13px; line-height:1.5; }
.aim-msg-user::before { content:"👤 "; }
.aim-msg-bot { background:#f0fdf4; padding:10px 12px; border-radius:8px; font-size:13px; line-height:1.5; white-space:pre-wrap; }
.aim-msg-bot::before { content:"🤖 "; }
.aim-msg-bot.empty { background:#fef2f2; color:#991b1b; }

.aim-rating { display:flex; gap:4px; margin-top:8px; }
.aim-star { cursor:pointer; font-size:18px; opacity:0.3; transition:all .1s; }
.aim-star.on, .aim-star:hover { opacity:1; }
.aim-star:hover ~ .aim-star { opacity:0.3; }
.aim-card-foot { display:flex; gap:10px; margin-top:8px; align-items:center; flex-wrap:wrap; }
.aim-note { flex:1; padding:5px 10px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; min-width:200px; }
.aim-incl { display:flex; align-items:center; gap:4px; font-size:12px; color:#475569; cursor:pointer; }
.aim-flag-select { padding:4px 8px; border:1px solid #e2e8f0; border-radius:6px; font-size:11px; }
.aim-save-btn { padding:5px 12px; background:#10b981; color:#fff; border:0; border-radius:6px; cursor:pointer; font-size:11px; }

.aim-content-preview { font-size:13px; line-height:1.5; max-height:120px; overflow:hidden; }
.aim-link { color:#2563eb; text-decoration:none; font-size:11px; }
.aim-link:hover { text-decoration:underline; }
</style>

<div class="aim-head">
    <h1>🤖 AI 운영 모니터</h1>
    <div class="aim-stats">
        <span>최근 <b><?= $hours ?></b>시간</span>
        <span>챗봇 대화 <b><?= $stats['chat_count'] ?></b></span>
        <span>자동 글 <b><?= $stats['post_count'] ?></b></span>
        <span>자동 댓글 <b><?= $stats['comment_count'] ?></b></span>
        <span>리뷰 <b><?= $stats['reviewed'] ?></b></span>
    </div>
</div>

<div class="aim-controls">
    <a class="aim-tab <?= $tab==='chat'?'active':'' ?>" href="?page=ai-monitor&tab=chat&hours=<?= $hours ?>">💬 챗봇 <small><?= $stats['chat_count'] ?></small></a>
    <a class="aim-tab <?= $tab==='post'?'active':'' ?>" href="?page=ai-monitor&tab=post&hours=<?= $hours ?>">📝 자동 글 <small><?= $stats['post_count'] ?></small></a>
    <a class="aim-tab <?= $tab==='comment'?'active':'' ?>" href="?page=ai-monitor&tab=comment&hours=<?= $hours ?>">💭 자동 댓글 <small><?= $stats['comment_count'] ?></small></a>
    <div class="aim-period">
        기간:
        <select onchange="location.href='?page=ai-monitor&tab=<?= $tab ?>&hours='+this.value">
            <option value="6"   <?= $hours==6?'selected':'' ?>>6시간</option>
            <option value="24"  <?= $hours==24?'selected':'' ?>>24시간</option>
            <option value="48"  <?= $hours==48?'selected':'' ?>>48시간</option>
            <option value="168" <?= $hours==168?'selected':'' ?>>7일</option>
        </select>
    </div>
</div>

<div class="aim-toolbar">
    <small>⭐ 별점 1~2점 또는 "이슈 있음" 체크된 항목들을 <b>마크다운 리포트로 복사</b>해 Claude 채팅에 붙여넣으면 즉시 프롬프트 개선 요청 가능합니다.</small>
    <button class="aim-btn-export" onclick="aimExport()">📋 Claude 리포트 복사</button>
</div>

<div id="aim-list">
<?php if ($tab === 'chat'): ?>
    <?php foreach ($chatPairs as $p):
        $r = rev($reviewMap, 'chat', $p['user_msg_id']);
        $rating = $r['rating'] ?? 0;
        $note = htmlspecialchars($r['note'] ?? '');
        $flag = $r['flag'] ?? '';
        $cls = $rating && $rating <= 2 ? 'flagged' : ($rating >= 4 ? 'good' : '');
    ?>
    <div class="aim-card <?= $cls ?>" data-type="chat" data-id="<?= $p['user_msg_id'] ?>">
        <div class="aim-card-head">
            <div class="aim-card-meta">
                <span class="aim-card-tag">💬 chat</span>
                · #<?= $p['user_msg_id'] ?> · <b>session <?= $p['session_id'] ?></b> · <?= htmlspecialchars($p['user_at']) ?>
            </div>
        </div>
        <div class="aim-msg-user"><?= htmlspecialchars($p['user_content']) ?></div>
        <div class="aim-msg-bot <?= $p['bot_content'] ? '' : 'empty' ?>">
            <?= $p['bot_content'] ? htmlspecialchars($p['bot_content']) : '(봇 응답 없음 — 에러 가능성)' ?>
        </div>
        <div class="aim-card-foot">
            <div class="aim-rating" data-current="<?= $rating ?>">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <span class="aim-star <?= $rating >= $i ? 'on' : '' ?>" data-r="<?= $i ?>">★</span>
                <?php endfor; ?>
            </div>
            <select class="aim-flag-select">
                <option value="">플래그 없음</option>
                <option value="ai_cliche" <?= $flag==='ai_cliche'?'selected':'' ?>>AI 티 표현</option>
                <option value="wrong_info" <?= $flag==='wrong_info'?'selected':'' ?>>잘못된 정보</option>
                <option value="too_long" <?= $flag==='too_long'?'selected':'' ?>>너무 김</option>
                <option value="too_formal" <?= $flag==='too_formal'?'selected':'' ?>>너무 격식</option>
                <option value="off_topic" <?= $flag==='off_topic'?'selected':'' ?>>주제 빗나감</option>
                <option value="excellent" <?= $flag==='excellent'?'selected':'' ?>>👍 모범 답안</option>
            </select>
            <input class="aim-note" type="text" placeholder="메모 (선택)" value="<?= $note ?>">
            <label class="aim-incl"><input type="checkbox" class="aim-incl-cb" <?= ($rating && $rating<=2) || in_array($flag, ['ai_cliche','wrong_info','too_long','too_formal','off_topic']) ? 'checked' : '' ?>> 리포트 포함</label>
            <button class="aim-save-btn" onclick="aimSave(this)">저장</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($chatPairs)): ?>
    <div class="aim-card" style="text-align:center;color:#94a3b8;padding:40px">
        💤 최근 <?= $hours ?>시간 안에 챗봇 대화가 없습니다
    </div>
    <?php endif; ?>

<?php elseif ($tab === 'post'): ?>
    <?php foreach ($autoPosts as $p):
        $r = rev($reviewMap, 'post', $p['id']);
        $rating = $r['rating'] ?? 0;
        $note = htmlspecialchars($r['note'] ?? '');
        $flag = $r['flag'] ?? '';
        $cls = $rating && $rating <= 2 ? 'flagged' : ($rating >= 4 ? 'good' : '');
        $body = strip_tags($p['content']);
        $body = mb_substr($body, 0, 250);
    ?>
    <div class="aim-card <?= $cls ?>" data-type="post" data-id="<?= $p['id'] ?>">
        <div class="aim-card-head">
            <div class="aim-card-meta">
                <span class="aim-card-tag">📝 [<?= htmlspecialchars($p['board_id']) ?>]</span>
                · #<?= $p['id'] ?> · <b><?= htmlspecialchars($p['nickname']) ?></b> · <?= htmlspecialchars($p['created_at']) ?>
                <a class="aim-link" href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>" target="_blank">보기</a>
            </div>
        </div>
        <div style="font-weight:700;margin-bottom:6px"><?= htmlspecialchars($p['title']) ?></div>
        <div class="aim-content-preview"><?= htmlspecialchars($body) ?></div>
        <div class="aim-card-foot">
            <div class="aim-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <span class="aim-star <?= $rating >= $i ? 'on' : '' ?>" data-r="<?= $i ?>">★</span>
                <?php endfor; ?>
            </div>
            <select class="aim-flag-select">
                <option value="">플래그 없음</option>
                <option value="off_topic" <?= $flag==='off_topic'?'selected':'' ?>>게시판 톤 안 맞음</option>
                <option value="ai_cliche" <?= $flag==='ai_cliche'?'selected':'' ?>>AI 티 표현</option>
                <option value="too_short" <?= $flag==='too_short'?'selected':'' ?>>너무 짧음</option>
                <option value="too_long" <?= $flag==='too_long'?'selected':'' ?>>너무 김</option>
                <option value="repetitive" <?= $flag==='repetitive'?'selected':'' ?>>반복적</option>
                <option value="excellent" <?= $flag==='excellent'?'selected':'' ?>>👍 모범</option>
            </select>
            <input class="aim-note" type="text" placeholder="메모" value="<?= $note ?>">
            <label class="aim-incl"><input type="checkbox" class="aim-incl-cb" <?= ($rating && $rating<=2) ? 'checked' : '' ?>> 리포트 포함</label>
            <button class="aim-save-btn" onclick="aimSave(this)">저장</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($autoPosts)): ?>
    <div class="aim-card" style="text-align:center;color:#94a3b8;padding:40px">
        💤 최근 <?= $hours ?>시간 안에 자동 생성된 글이 없습니다
    </div>
    <?php endif; ?>

<?php elseif ($tab === 'comment'): ?>
    <?php foreach ($autoComments as $c):
        $r = rev($reviewMap, 'comment', $c['id']);
        $rating = $r['rating'] ?? 0;
        $note = htmlspecialchars($r['note'] ?? '');
        $flag = $r['flag'] ?? '';
        $cls = $rating && $rating <= 2 ? 'flagged' : ($rating >= 4 ? 'good' : '');
    ?>
    <div class="aim-card <?= $cls ?>" data-type="comment" data-id="<?= $c['id'] ?>">
        <div class="aim-card-head">
            <div class="aim-card-meta">
                <span class="aim-card-tag">💭 [<?= htmlspecialchars($c['board_id']) ?>]</span>
                · #<?= $c['id'] ?> · <b><?= htmlspecialchars($c['nickname']) ?></b> · <?= htmlspecialchars($c['created_at']) ?>
                <a class="aim-link" href="<?= nb_url("board/{$c['board_id']}/{$c['post_id']}") ?>" target="_blank">댓글이 달린 글 →</a>
            </div>
        </div>
        <div style="font-size:11px;color:#94a3b8;margin-bottom:4px">→ "<?= htmlspecialchars(mb_substr($c['post_title'] ?? '', 0, 50)) ?>"</div>
        <div class="aim-msg-bot" style="background:#f8fafc"><?= htmlspecialchars($c['content']) ?></div>
        <div class="aim-card-foot">
            <div class="aim-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <span class="aim-star <?= $rating >= $i ? 'on' : '' ?>" data-r="<?= $i ?>">★</span>
                <?php endfor; ?>
            </div>
            <select class="aim-flag-select">
                <option value="">플래그 없음</option>
                <option value="ai_cliche" <?= $flag==='ai_cliche'?'selected':'' ?>>AI 티</option>
                <option value="too_formal" <?= $flag==='too_formal'?'selected':'' ?>>너무 격식</option>
                <option value="too_long" <?= $flag==='too_long'?'selected':'' ?>>너무 김</option>
                <option value="off_topic" <?= $flag==='off_topic'?'selected':'' ?>>주제 빗나감</option>
                <option value="excellent" <?= $flag==='excellent'?'selected':'' ?>>👍 자연스러움</option>
            </select>
            <input class="aim-note" type="text" placeholder="메모" value="<?= $note ?>">
            <label class="aim-incl"><input type="checkbox" class="aim-incl-cb" <?= ($rating && $rating<=2) ? 'checked' : '' ?>> 리포트 포함</label>
            <button class="aim-save-btn" onclick="aimSave(this)">저장</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($autoComments)): ?>
    <div class="aim-card" style="text-align:center;color:#94a3b8;padding:40px">
        💤 최근 <?= $hours ?>시간 안에 자동 댓글이 없습니다
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
// 별점 클릭
document.querySelectorAll('.aim-rating').forEach(function(rs) {
    rs.querySelectorAll('.aim-star').forEach(function(s) {
        s.addEventListener('click', function() {
            var r = parseInt(s.dataset.r);
            rs.dataset.current = r;
            rs.querySelectorAll('.aim-star').forEach(function(st) {
                var sr = parseInt(st.dataset.r);
                st.classList.toggle('on', sr <= r);
            });
            // 1~2점이면 자동으로 리포트 포함 체크
            if (r <= 2) {
                var card = rs.closest('.aim-card');
                card.querySelector('.aim-incl-cb').checked = true;
            }
        });
    });
});

// 저장
function aimSave(btn) {
    var card = btn.closest('.aim-card');
    var type = card.dataset.type;
    var id = card.dataset.id;
    var rating = parseInt(card.querySelector('.aim-rating').dataset.current || '0');
    var note = card.querySelector('.aim-note').value;
    var flag = card.querySelector('.aim-flag-select').value;
    var fd = new FormData();
    fd.append('ai_review_save', '1');
    fd.append('item_type', type);
    fd.append('item_id', id);
    fd.append('rating', rating);
    fd.append('note', note);
    fd.append('flag', flag);
    btn.disabled = true; btn.textContent = '...';
    fetch(location.pathname + location.search, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            btn.disabled = false; btn.textContent = j.ok ? '✓ 저장' : '실패';
            setTimeout(function(){ btn.textContent = '저장'; }, 1500);
        });
}

// Claude 리포트 마크다운 생성 + 클립보드 복사
function aimExport() {
    var checked = document.querySelectorAll('.aim-incl-cb:checked');
    if (!checked.length) {
        alert('"리포트 포함" 체크된 항목이 없습니다. ⭐ 별점 1~2점이나 플래그를 선택하면 자동 체크됩니다.');
        return;
    }
    var md = '# AI 운영 리포트 — ' + new Date().toLocaleString('ko-KR') + '\n\n';
    md += '아래 항목들이 어색하거나 문제가 있습니다. 프롬프트를 다듬어주세요.\n\n';

    var byType = { chat:[], post:[], comment:[] };
    checked.forEach(function(cb) {
        var card = cb.closest('.aim-card');
        var type = card.dataset.type;
        var id = card.dataset.id;
        var rating = parseInt(card.querySelector('.aim-rating').dataset.current || '0');
        var note = card.querySelector('.aim-note').value;
        var flag = card.querySelector('.aim-flag-select').value;
        var meta = card.querySelector('.aim-card-meta').innerText.replace(/\s+/g, ' ').trim();
        var item = { id:id, rating:rating, note:note, flag:flag, meta:meta };

        if (type === 'chat') {
            item.user = card.querySelector('.aim-msg-user').innerText.trim();
            item.bot = card.querySelector('.aim-msg-bot').innerText.trim();
        } else {
            var content = card.querySelector('.aim-content-preview, .aim-msg-bot');
            item.text = content ? content.innerText.trim() : '';
            var title = card.querySelector('[style*="font-weight:700"]');
            item.title = title ? title.innerText.trim() : '';
        }
        byType[type].push(item);
    });

    if (byType.chat.length) {
        md += '## 💬 챗봇 대화 — ' + byType.chat.length + '건\n\n';
        byType.chat.forEach(function(it) {
            md += '### #' + it.id + (it.rating ? ' (★' + it.rating + ')' : '') + (it.flag ? ' [' + it.flag + ']' : '') + '\n';
            md += '> ' + it.meta + '\n\n';
            md += '**유저**: ' + it.user.replace(/^👤\s*/, '') + '\n\n';
            md += '**봇**: ' + it.bot.replace(/^🤖\s*/, '') + '\n\n';
            if (it.note) md += '**메모**: ' + it.note + '\n\n';
            md += '---\n\n';
        });
    }
    if (byType.post.length) {
        md += '## 📝 자동 글 — ' + byType.post.length + '건\n\n';
        byType.post.forEach(function(it) {
            md += '### #' + it.id + (it.rating ? ' (★' + it.rating + ')' : '') + (it.flag ? ' [' + it.flag + ']' : '') + '\n';
            md += '> ' + it.meta + '\n\n';
            md += '**제목**: ' + it.title + '\n\n';
            md += '**본문**: ' + it.text + '\n\n';
            if (it.note) md += '**메모**: ' + it.note + '\n\n';
            md += '---\n\n';
        });
    }
    if (byType.comment.length) {
        md += '## 💭 자동 댓글 — ' + byType.comment.length + '건\n\n';
        byType.comment.forEach(function(it) {
            md += '### #' + it.id + (it.rating ? ' (★' + it.rating + ')' : '') + (it.flag ? ' [' + it.flag + ']' : '') + '\n';
            md += '> ' + it.meta + '\n\n';
            md += '**댓글**: ' + it.text + '\n\n';
            if (it.note) md += '**메모**: ' + it.note + '\n\n';
            md += '---\n\n';
        });
    }
    md += '## 요청\n\n위 케이스들을 보고 다음을 다듬어주세요:\n';
    md += '- 챗봇이 어색하면 `plugins/nuri-chat/config.json` 의 system_prompt\n';
    md += '- 자동 글이 어색하면 `plugins/ai-auto-post-generator/config.json` 의 boards_config\n';
    md += '- 자동 댓글이 어색하면 `plugins/ai-auto-comment/config.json` 의 system_prompt\n';

    navigator.clipboard.writeText(md).then(function() {
        alert('✓ 클립보드에 복사됨 (' + checked.length + '건). Claude 채팅에 붙여넣어 주세요.');
    }).catch(function() {
        // fallback: textarea로 보여주기
        var ta = document.createElement('textarea');
        ta.value = md;
        ta.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:80vw;height:60vh;z-index:9999;padding:20px';
        document.body.appendChild(ta);
        ta.select();
        alert('수동으로 Ctrl+C 복사하세요');
    });
}
</script>

<?php adminFooter(); ?>

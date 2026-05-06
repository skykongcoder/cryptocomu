<?php
/**
 * AI 운영 모니터 — 챗봇 대화 / 자동 글 / 자동 댓글을 한 화면에서 검토
 *
 * 기능:
 *  - 별점 + 메모 + 플래그 평가
 *  - "Claude 리포트 복사" 마크다운 export
 *  - 🚨 자동 cliche 감지 (AI 티 표현 정규식 매칭)
 *  - 📈 14일 평점 추이 차트 (Chart.js)
 *  - 🔬 A/B 프롬프트 버전별 평균 평점 (config.json 자동 스냅샷)
 *  - 🔔 1점 평가 시 Discord/Slack 웹훅 알림
 */

$prefix = DB::getPrefix();

// === 테이블 자동 생성 ===
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

    DB::query("CREATE TABLE IF NOT EXISTS {$prefix}ai_prompt_version (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        plugin VARCHAR(50) NOT NULL,
        content_hash CHAR(64) NOT NULL,
        excerpt TEXT NULL,
        snapshot_at DATETIME NOT NULL,
        UNIQUE KEY uk_plugin_hash (plugin, content_hash),
        INDEX idx_plugin_at (plugin, snapshot_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// === 프롬프트 자동 스냅샷 (config.json 변경 감지) ===
$promptFiles = [
    'nuri-chat'       => __DIR__ . '/../plugins/nuri-chat/config.json',
    'ai-auto-comment' => __DIR__ . '/../plugins/ai-auto-comment/config.json',
    'ai-auto-post'    => __DIR__ . '/../plugins/ai-auto-post-generator/config.json',
];
foreach ($promptFiles as $pluginKey => $path) {
    if (!file_exists($path)) continue;
    $content = @file_get_contents($path);
    if (!$content) continue;
    $hash = hash('sha256', $content);
    $exists = DB::fetch(
        "SELECT id FROM {$prefix}ai_prompt_version WHERE plugin=? AND content_hash=?",
        [$pluginKey, $hash]
    );
    if (!$exists) {
        $j = @json_decode($content, true);
        $excerpt = '';
        if (is_array($j)) {
            $excerpt = mb_substr($j['system_prompt'] ?? json_encode($j), 0, 400);
        }
        DB::query(
            "INSERT INTO {$prefix}ai_prompt_version (plugin, content_hash, excerpt, snapshot_at) VALUES (?, ?, ?, NOW())",
            [$pluginKey, $hash, $excerpt]
        );
    }
}

// === 웹훅 URL 로드 (settings 테이블 활용) ===
$webhookUrl = '';
try {
    $row = DB::fetch(
        "SELECT setting_value FROM {$prefix}settings WHERE setting_key=?",
        ['ai_monitor_webhook_url']
    );
    $webhookUrl = $row['setting_value'] ?? '';
} catch (Exception $e) {}

// === 웹훅 발사 함수 (Discord/Slack 호환) ===
function aim_fire_webhook($url, $message) {
    if (!$url) return;
    // Discord 와 Slack 둘 다 'content' 또는 'text' 사용 — 둘 다 보내면 어느 쪽이든 작동
    $data = json_encode(['content' => $message, 'text' => $message], JSON_UNESCAPED_UNICODE);
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $data,
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    @file_get_contents($url, false, $ctx);
}

// === POST: 웹훅 URL 저장 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_settings_save'])) {
    $newUrl = trim($_POST['webhook_url'] ?? '');
    DB::query(
        "INSERT INTO {$prefix}settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
        ['ai_monitor_webhook_url', $newUrl]
    );
    header('Location: ?page=ai-monitor&saved=1');
    exit;
}

// === POST: 평가 저장 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_review_save'])) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $type   = $_POST['item_type'] ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);
    $rating = isset($_POST['rating']) && $_POST['rating'] !== '' ? (int)$_POST['rating'] : null;
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

    // 🔔 1점 평가 시 웹훅 알림
    $webhookFired = false;
    if ($rating === 1 && $webhookUrl) {
        $typeName = ['chat'=>'💬 챗봇', 'post'=>'📝 자동 글', 'comment'=>'💭 자동 댓글'][$type] ?? $type;
        $msg  = "🚨 **AI 운영 알림** — 1점 평가\n";
        $msg .= "**유형**: {$typeName}\n";
        $msg .= "**ID**: #{$id}\n";
        if ($flag) $msg .= "**플래그**: `{$flag}`\n";
        if ($note) $msg .= "**메모**: {$note}\n";
        $siteUrlRow = DB::fetch("SELECT setting_value FROM {$prefix}settings WHERE setting_key=?", ['site_url']);
        $siteUrl = rtrim($siteUrlRow['setting_value'] ?? '', '/');
        $msg .= "**관리자**: {$siteUrl}/admin/?page=ai-monitor&tab={$type}\n";
        aim_fire_webhook($webhookUrl, $msg);
        $webhookFired = true;
    }

    echo json_encode(['ok' => true, 'webhook_fired' => $webhookFired]);
    exit;
}

// === Cliche 감지 패턴 ===
$AI_CLICHE_PATTERNS = [
    '/도움이\s*(됐|되|될|돼)/u'         => '도움이 됐/되/될',
    '/유익한/u'                          => '유익한',
    '/흥미롭/u'                          => '흥미롭',
    '/기쁩니다|기쁘게/u'                 => '기쁩니다',
    '/감사합니다\s*[😊🙏✨]/u'           => '감사합니다 + 이모지',
    '/좋은\s*(정보|글|글입니다|하루)/u' => '좋은 정보/글/하루',
    '/많은\s*도움/u'                     => '많은 도움',
    '/자세한\s*(정보|설명)/u'           => '자세한 정보/설명',
    '/통찰력/u'                          => '통찰력',
    '/소중한\s*(의견|시간)/u'           => '소중한 의견/시간',
    '/응원합니다|응원하고\s*있/u'       => '응원합니다',
    '/공감합니다/u'                      => '공감합니다',
    '/대단하시네요|훌륭하시네요/u'      => '대단/훌륭하시네요',
    '/도와드릴\s*수\s*있어\s*기쁩/u'    => '도와드릴 수 있어 기쁩',
    '/좋은\s*질문(이네요|입니다)/u'     => '좋은 질문이네요',
    '/말씀하신\s*대로/u'                => '말씀하신 대로',
    '/궁금하신\s*점이?\s*있으시면/u'   => '궁금하신 점 있으시면',
];
function aim_detect_cliches($text, $patterns) {
    if (!$text) return [];
    $found = [];
    foreach ($patterns as $pat => $name) {
        if (preg_match($pat, $text)) $found[] = $name;
    }
    return $found;
}

// === 데이터 로드 ===
$hours = max(1, (int)($_GET['hours'] ?? 48));
$tab   = $_GET['tab'] ?? 'chat';

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

$autoPosts = DB::fetchAll("
    SELECT p.id, p.board_id, p.title, p.content, p.created_at, m.nickname, m.user_id
    FROM {$prefix}posts p
    LEFT JOIN {$prefix}members m ON p.member_id = m.id
    WHERE m.user_id REGEXP '^(ai_seed_|ai_user_|asb_bot_)'
      AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY p.id DESC
    LIMIT 50
", [$hours]);

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

// 기존 리뷰 정보
$reviewMap = [];
foreach (DB::fetchAll("SELECT * FROM {$prefix}ai_review") as $r) {
    $reviewMap[$r['item_type'] . '_' . $r['item_id']] = $r;
}
function rev($map, $type, $id) {
    return $map[$type . '_' . $id] ?? null;
}

// === 14일 평점 추이 ===
$trendRows = DB::fetchAll("
    SELECT DATE(reviewed_at) AS day, item_type, AVG(rating) AS avg_rating, COUNT(*) AS cnt
    FROM {$prefix}ai_review
    WHERE reviewed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
      AND rating IS NOT NULL
    GROUP BY DATE(reviewed_at), item_type
    ORDER BY day ASC
");
$trendData = ['labels'=>[], 'chat'=>[], 'post'=>[], 'comment'=>[]];
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $days[] = $d;
    $trendData['labels'][] = date('m/d', strtotime($d));
    $trendData['chat'][$d]    = null;
    $trendData['post'][$d]    = null;
    $trendData['comment'][$d] = null;
}
foreach ($trendRows as $row) {
    if (isset($trendData[$row['item_type']][$row['day']])) {
        $trendData[$row['item_type']][$row['day']] = round($row['avg_rating'], 2);
    }
}
$trendData['chat']    = array_values($trendData['chat']);
$trendData['post']    = array_values($trendData['post']);
$trendData['comment'] = array_values($trendData['comment']);

// === A/B 프롬프트 버전별 평균 평점 ===
$versions = DB::fetchAll("SELECT * FROM {$prefix}ai_prompt_version ORDER BY snapshot_at ASC");
$versionsByPlugin = [];
foreach ($versions as $v) {
    $versionsByPlugin[$v['plugin']][] = $v;
}

$ratedItems = DB::fetchAll("
    SELECT r.item_type, r.item_id, r.rating, r.flag,
        CASE r.item_type
            WHEN 'chat'    THEN (SELECT created_at FROM {$prefix}nc_messages WHERE id = r.item_id LIMIT 1)
            WHEN 'post'    THEN (SELECT created_at FROM {$prefix}posts       WHERE id = r.item_id LIMIT 1)
            WHEN 'comment' THEN (SELECT created_at FROM {$prefix}comments    WHERE id = r.item_id LIMIT 1)
        END AS item_at
    FROM {$prefix}ai_review r
    WHERE r.rating IS NOT NULL
");
$typeToPlugin = ['chat'=>'nuri-chat', 'post'=>'ai-auto-post', 'comment'=>'ai-auto-comment'];
$abAgg = [];
foreach ($ratedItems as $r) {
    $plugin = $typeToPlugin[$r['item_type']] ?? null;
    if (!$plugin || !$r['item_at']) continue;
    $vlist = $versionsByPlugin[$plugin] ?? [];
    $activeV = null;
    foreach ($vlist as $v) {
        if ($v['snapshot_at'] <= $r['item_at']) $activeV = $v;
        else break;
    }
    if (!$activeV) continue;
    $key = $plugin . '_' . $activeV['id'];
    if (!isset($abAgg[$key])) {
        $abAgg[$key] = [
            'plugin'      => $plugin,
            'version_id'  => $activeV['id'],
            'snapshot_at' => $activeV['snapshot_at'],
            'sum'         => 0,
            'count'       => 0,
            'low_count'   => 0, // 1~2점
            'high_count'  => 0, // 4~5점
        ];
    }
    $abAgg[$key]['sum']   += $r['rating'];
    $abAgg[$key]['count'] += 1;
    if ($r['rating'] <= 2) $abAgg[$key]['low_count']++;
    if ($r['rating'] >= 4) $abAgg[$key]['high_count']++;
}
foreach ($abAgg as &$a) {
    $a['avg'] = round($a['sum'] / max(1, $a['count']), 2);
}
unset($a);
// 플러그인별로 그룹화 + 시간순 정렬
$abByPlugin = [];
foreach ($abAgg as $a) $abByPlugin[$a['plugin']][] = $a;
foreach ($abByPlugin as &$arr) {
    usort($arr, fn($x, $y) => strcmp($x['snapshot_at'], $y['snapshot_at']));
}
unset($arr);

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

.aim-panel { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 18px; margin-bottom:14px; }
.aim-panel summary { cursor:pointer; font-weight:700; color:#1e293b; font-size:14px; padding:4px 0; user-select:none; }
.aim-panel summary:hover { color:#2563eb; }
.aim-panel[open] summary { margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:10px; }

.aim-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:10px; transition:all .15s; position:relative; }
.aim-card.flagged { border-color:#fb7185; box-shadow:0 0 0 3px rgba(251,113,133,0.1); }
.aim-card.good { border-color:#10b981; }
.aim-card.cliche-detected { border-left:4px solid #f59e0b; }
.aim-card-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; gap:10px; }
.aim-card-meta { font-size:11px; color:#64748b; font-family:'JetBrains Mono', monospace; }
.aim-card-meta b { color:#1e293b; }
.aim-card-tag { display:inline-block; padding:1px 8px; background:#f1f5f9; border-radius:10px; font-size:10px; font-weight:600; color:#475569; }
.aim-cliche-badge { display:inline-block; background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; margin-left:6px; }
.aim-cliche-detail { background:#fffbeb; border:1px dashed #f59e0b; padding:6px 10px; border-radius:6px; font-size:11px; color:#92400e; margin-top:6px; }
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

.aim-ab-table { width:100%; border-collapse:collapse; font-size:12px; }
.aim-ab-table th, .aim-ab-table td { padding:8px 10px; border-bottom:1px solid #f1f5f9; text-align:left; }
.aim-ab-table th { background:#f8fafc; font-weight:700; color:#475569; }
.aim-ab-table tr.current { background:#dcfce7; }
.aim-ab-bar { display:inline-block; height:8px; background:linear-gradient(90deg,#fb7185,#fbbf24,#10b981); border-radius:4px; vertical-align:middle; }
.aim-settings-form { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.aim-settings-form input[type=text] { flex:1; min-width:300px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; }
.aim-settings-form button { padding:8px 16px; background:#2563eb; color:#fff; border:0; border-radius:6px; cursor:pointer; font-weight:600; }
.aim-saved-toast { background:#dcfce7; color:#15803d; padding:8px 14px; border-radius:8px; margin-bottom:14px; font-size:13px; }

#aimChart { max-height:240px !important; }
</style>

<div class="aim-head">
    <h1>🤖 AI 운영 모니터</h1>
    <div class="aim-stats">
        <span>최근 <b><?= $hours ?></b>시간</span>
        <span>챗봇 <b><?= $stats['chat_count'] ?></b></span>
        <span>자동 글 <b><?= $stats['post_count'] ?></b></span>
        <span>자동 댓글 <b><?= $stats['comment_count'] ?></b></span>
        <span>리뷰 <b><?= $stats['reviewed'] ?></b></span>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="aim-saved-toast">✓ 설정이 저장되었습니다</div>
<?php endif; ?>

<!-- 📈 14일 평점 추이 -->
<details class="aim-panel" open>
    <summary>📈 14일 평점 추이 (별점 평균)</summary>
    <canvas id="aimChart"></canvas>
    <div style="font-size:11px;color:#94a3b8;margin-top:8px">
        매일 평가한 항목의 별점 평균. 위로 올라갈수록 프롬프트가 좋아지고 있다는 뜻이에요.
    </div>
</details>

<!-- 🔬 A/B 프롬프트 버전 비교 -->
<details class="aim-panel">
    <summary>🔬 프롬프트 버전 비교 (A/B) — <?= count($versions) ?>개 스냅샷</summary>
    <?php foreach (['nuri-chat'=>'💬 nuri-chat (챗봇)', 'ai-auto-post'=>'📝 ai-auto-post (자동 글)', 'ai-auto-comment'=>'💭 ai-auto-comment (자동 댓글)'] as $pkey => $plabel): ?>
        <h4 style="margin:14px 0 6px;font-size:13px;color:#1e293b"><?= $plabel ?></h4>
        <?php $versionsList = $abByPlugin[$pkey] ?? []; ?>
        <?php if (empty($versionsList)): ?>
            <div style="color:#94a3b8;font-size:12px">아직 평가 데이터가 없어 비교할 수 없습니다.</div>
        <?php else: ?>
        <table class="aim-ab-table">
            <tr><th>버전</th><th>적용 시각</th><th>평가 수</th><th>평균</th><th>1~2점</th><th>4~5점</th><th>막대</th></tr>
            <?php
            $latestId = $versionsList[count($versionsList) - 1]['version_id'];
            foreach ($versionsList as $i => $v):
                $isCurrent = ($v['version_id'] === $latestId);
                $barWidth = (int)($v['avg'] / 5 * 200);
            ?>
            <tr class="<?= $isCurrent ? 'current' : '' ?>">
                <td><b>v<?= $i + 1 ?></b><?= $isCurrent ? ' 🟢 현재' : '' ?></td>
                <td><?= htmlspecialchars($v['snapshot_at']) ?></td>
                <td><?= $v['count'] ?></td>
                <td><b><?= number_format($v['avg'], 2) ?></b> / 5</td>
                <td style="color:#dc2626"><?= $v['low_count'] ?></td>
                <td style="color:#15803d"><?= $v['high_count'] ?></td>
                <td><span class="aim-ab-bar" style="width:<?= $barWidth ?>px"></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    <?php endforeach; ?>
    <div style="font-size:11px;color:#94a3b8;margin-top:10px">
        config.json 이 변경되면 자동으로 새 버전이 스냅샷됩니다. 같은 시기에 받은 평가의 평균을 비교해 새 프롬프트가 더 나은지 판단하세요.
    </div>
</details>

<!-- 🔔 웹훅 설정 -->
<details class="aim-panel">
    <summary>🔔 1점 평가 알림 (Discord / Slack 웹훅)</summary>
    <form method="POST" class="aim-settings-form">
        <input type="hidden" name="ai_settings_save" value="1">
        <div style="flex:1;min-width:300px">
            <label style="font-size:11px;color:#475569;display:block;margin-bottom:4px">웹훅 URL</label>
            <input type="text" name="webhook_url" value="<?= htmlspecialchars($webhookUrl) ?>" placeholder="https://discord.com/api/webhooks/... 또는 https://hooks.slack.com/services/...">
        </div>
        <button type="submit">저장</button>
    </form>
    <div style="font-size:11px;color:#94a3b8;margin-top:10px">
        ⭐ <b>1점</b>으로 평가하면 자동으로 채널에 알림이 전송됩니다. Discord 와 Slack 웹훅 모두 지원해요.<br>
        <b>Discord</b>: 채널 설정 → 통합 → 웹훅 → 새 웹훅 → URL 복사<br>
        <b>Slack</b>: api.slack.com/messaging/webhooks → Incoming Webhook 생성
    </div>
</details>

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
    <small>⭐ 별점 1~2점, 🚩 자동 cliche 감지, 또는 "이슈 있음" 체크된 항목들이 <b>마크다운 리포트</b>로 복사돼요. Claude 채팅에 붙여넣어 프롬프트 즉시 개선!</small>
    <button class="aim-btn-export" onclick="aimExport()">📋 Claude 리포트 복사</button>
</div>

<div id="aim-list">
<?php if ($tab === 'chat'): ?>
    <?php foreach ($chatPairs as $p):
        $r = rev($reviewMap, 'chat', $p['user_msg_id']);
        $rating = $r['rating'] ?? 0;
        $note = htmlspecialchars($r['note'] ?? '');
        $flag = $r['flag'] ?? '';
        $cliches = aim_detect_cliches($p['bot_content'] ?? '', $AI_CLICHE_PATTERNS);
        $cls = $rating && $rating <= 2 ? 'flagged' : ($rating >= 4 ? 'good' : '');
        if ($cliches && !$rating) $cls .= ' cliche-detected';
    ?>
    <div class="aim-card <?= $cls ?>" data-type="chat" data-id="<?= $p['user_msg_id'] ?>" data-cliches="<?= htmlspecialchars(implode(', ', $cliches)) ?>">
        <div class="aim-card-head">
            <div class="aim-card-meta">
                <span class="aim-card-tag">💬 chat</span>
                · #<?= $p['user_msg_id'] ?> · <b>session <?= htmlspecialchars($p['session_id']) ?></b> · <?= htmlspecialchars($p['user_at']) ?>
                <?php if ($cliches): ?><span class="aim-cliche-badge">🚩 AI cliche <?= count($cliches) ?>개</span><?php endif; ?>
            </div>
        </div>
        <div class="aim-msg-user"><?= htmlspecialchars($p['user_content']) ?></div>
        <div class="aim-msg-bot <?= $p['bot_content'] ? '' : 'empty' ?>">
            <?= $p['bot_content'] ? htmlspecialchars($p['bot_content']) : '(봇 응답 없음 — 에러 가능성)' ?>
        </div>
        <?php if ($cliches): ?>
        <div class="aim-cliche-detail">🚩 자동 감지된 AI 티 표현: <b><?= htmlspecialchars(implode(' / ', $cliches)) ?></b></div>
        <?php endif; ?>
        <div class="aim-card-foot">
            <div class="aim-rating" data-current="<?= $rating ?>">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <span class="aim-star <?= $rating >= $i ? 'on' : '' ?>" data-r="<?= $i ?>">★</span>
                <?php endfor; ?>
            </div>
            <select class="aim-flag-select">
                <option value="">플래그 없음</option>
                <option value="ai_cliche" <?= ($flag==='ai_cliche' || (!$flag && $cliches))?'selected':'' ?>>AI 티 표현</option>
                <option value="wrong_info" <?= $flag==='wrong_info'?'selected':'' ?>>잘못된 정보</option>
                <option value="too_long" <?= $flag==='too_long'?'selected':'' ?>>너무 김</option>
                <option value="too_formal" <?= $flag==='too_formal'?'selected':'' ?>>너무 격식</option>
                <option value="off_topic" <?= $flag==='off_topic'?'selected':'' ?>>주제 빗나감</option>
                <option value="excellent" <?= $flag==='excellent'?'selected':'' ?>>👍 모범 답안</option>
            </select>
            <input class="aim-note" type="text" placeholder="메모 (선택)" value="<?= $note ?>">
            <label class="aim-incl"><input type="checkbox" class="aim-incl-cb" <?= ($rating && $rating<=2) || $cliches || in_array($flag, ['ai_cliche','wrong_info','too_long','too_formal','off_topic']) ? 'checked' : '' ?>> 리포트 포함</label>
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
        $body = strip_tags($p['content']);
        $body = mb_substr($body, 0, 250);
        $cliches = aim_detect_cliches($p['title'] . ' ' . $body, $AI_CLICHE_PATTERNS);
        $cls = $rating && $rating <= 2 ? 'flagged' : ($rating >= 4 ? 'good' : '');
        if ($cliches && !$rating) $cls .= ' cliche-detected';
    ?>
    <div class="aim-card <?= $cls ?>" data-type="post" data-id="<?= $p['id'] ?>" data-cliches="<?= htmlspecialchars(implode(', ', $cliches)) ?>">
        <div class="aim-card-head">
            <div class="aim-card-meta">
                <span class="aim-card-tag">📝 [<?= htmlspecialchars($p['board_id']) ?>]</span>
                · #<?= $p['id'] ?> · <b><?= htmlspecialchars($p['nickname']) ?></b> · <?= htmlspecialchars($p['created_at']) ?>
                <a class="aim-link" href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>" target="_blank">보기</a>
                <?php if ($cliches): ?><span class="aim-cliche-badge">🚩 AI cliche <?= count($cliches) ?>개</span><?php endif; ?>
            </div>
        </div>
        <div style="font-weight:700;margin-bottom:6px"><?= htmlspecialchars($p['title']) ?></div>
        <div class="aim-content-preview"><?= htmlspecialchars($body) ?></div>
        <?php if ($cliches): ?>
        <div class="aim-cliche-detail">🚩 자동 감지: <b><?= htmlspecialchars(implode(' / ', $cliches)) ?></b></div>
        <?php endif; ?>
        <div class="aim-card-foot">
            <div class="aim-rating" data-current="<?= $rating ?>">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <span class="aim-star <?= $rating >= $i ? 'on' : '' ?>" data-r="<?= $i ?>">★</span>
                <?php endfor; ?>
            </div>
            <select class="aim-flag-select">
                <option value="">플래그 없음</option>
                <option value="off_topic" <?= $flag==='off_topic'?'selected':'' ?>>게시판 톤 안 맞음</option>
                <option value="ai_cliche" <?= ($flag==='ai_cliche' || (!$flag && $cliches))?'selected':'' ?>>AI 티 표현</option>
                <option value="too_short" <?= $flag==='too_short'?'selected':'' ?>>너무 짧음</option>
                <option value="too_long" <?= $flag==='too_long'?'selected':'' ?>>너무 김</option>
                <option value="repetitive" <?= $flag==='repetitive'?'selected':'' ?>>반복적</option>
                <option value="excellent" <?= $flag==='excellent'?'selected':'' ?>>👍 모범</option>
            </select>
            <input class="aim-note" type="text" placeholder="메모" value="<?= $note ?>">
            <label class="aim-incl"><input type="checkbox" class="aim-incl-cb" <?= ($rating && $rating<=2) || $cliches ? 'checked' : '' ?>> 리포트 포함</label>
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
        $cliches = aim_detect_cliches($c['content'] ?? '', $AI_CLICHE_PATTERNS);
        $cls = $rating && $rating <= 2 ? 'flagged' : ($rating >= 4 ? 'good' : '');
        if ($cliches && !$rating) $cls .= ' cliche-detected';
    ?>
    <div class="aim-card <?= $cls ?>" data-type="comment" data-id="<?= $c['id'] ?>" data-cliches="<?= htmlspecialchars(implode(', ', $cliches)) ?>">
        <div class="aim-card-head">
            <div class="aim-card-meta">
                <span class="aim-card-tag">💭 [<?= htmlspecialchars($c['board_id']) ?>]</span>
                · #<?= $c['id'] ?> · <b><?= htmlspecialchars($c['nickname']) ?></b> · <?= htmlspecialchars($c['created_at']) ?>
                <a class="aim-link" href="<?= nb_url("board/{$c['board_id']}/{$c['post_id']}") ?>" target="_blank">댓글이 달린 글 →</a>
                <?php if ($cliches): ?><span class="aim-cliche-badge">🚩 AI cliche <?= count($cliches) ?>개</span><?php endif; ?>
            </div>
        </div>
        <div style="font-size:11px;color:#94a3b8;margin-bottom:4px">→ "<?= htmlspecialchars(mb_substr($c['post_title'] ?? '', 0, 50)) ?>"</div>
        <div class="aim-msg-bot" style="background:#f8fafc"><?= htmlspecialchars($c['content']) ?></div>
        <?php if ($cliches): ?>
        <div class="aim-cliche-detail">🚩 자동 감지: <b><?= htmlspecialchars(implode(' / ', $cliches)) ?></b></div>
        <?php endif; ?>
        <div class="aim-card-foot">
            <div class="aim-rating" data-current="<?= $rating ?>">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <span class="aim-star <?= $rating >= $i ? 'on' : '' ?>" data-r="<?= $i ?>">★</span>
                <?php endfor; ?>
            </div>
            <select class="aim-flag-select">
                <option value="">플래그 없음</option>
                <option value="ai_cliche" <?= ($flag==='ai_cliche' || (!$flag && $cliches))?'selected':'' ?>>AI 티</option>
                <option value="too_formal" <?= $flag==='too_formal'?'selected':'' ?>>너무 격식</option>
                <option value="too_long" <?= $flag==='too_long'?'selected':'' ?>>너무 김</option>
                <option value="off_topic" <?= $flag==='off_topic'?'selected':'' ?>>주제 빗나감</option>
                <option value="excellent" <?= $flag==='excellent'?'selected':'' ?>>👍 자연스러움</option>
            </select>
            <input class="aim-note" type="text" placeholder="메모" value="<?= $note ?>">
            <label class="aim-incl"><input type="checkbox" class="aim-incl-cb" <?= ($rating && $rating<=2) || $cliches ? 'checked' : '' ?>> 리포트 포함</label>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// === 14일 추이 차트 ===
(function() {
    var canvas = document.getElementById('aimChart');
    if (!canvas || typeof Chart === 'undefined') return;
    var trend = <?= json_encode($trendData, JSON_UNESCAPED_UNICODE) ?>;
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: trend.labels,
            datasets: [
                { label: '💬 챗봇',     data: trend.chat,    borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.1)', tension:0.3, spanGaps:true },
                { label: '📝 자동 글',  data: trend.post,    borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.1)', tension:0.3, spanGaps:true },
                { label: '💭 자동 댓글', data: trend.comment, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.1)', tension:0.3, spanGaps:true }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { min: 1, max: 5, ticks: { stepSize: 1 } } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();

// === 별점 클릭 ===
document.querySelectorAll('.aim-rating').forEach(function(rs) {
    rs.querySelectorAll('.aim-star').forEach(function(s) {
        s.addEventListener('click', function() {
            var r = parseInt(s.dataset.r);
            rs.dataset.current = r;
            rs.querySelectorAll('.aim-star').forEach(function(st) {
                var sr = parseInt(st.dataset.r);
                st.classList.toggle('on', sr <= r);
            });
            if (r <= 2) {
                var card = rs.closest('.aim-card');
                card.querySelector('.aim-incl-cb').checked = true;
            }
        });
    });
});

// === 저장 ===
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
            btn.disabled = false;
            if (j.ok) {
                btn.textContent = j.webhook_fired ? '✓ 저장 + 🔔 알림' : '✓ 저장';
            } else {
                btn.textContent = '실패';
            }
            setTimeout(function(){ btn.textContent = '저장'; }, 2000);
        });
}

// === Claude 리포트 마크다운 생성 + 클립보드 복사 ===
function aimExport() {
    var checked = document.querySelectorAll('.aim-incl-cb:checked');
    if (!checked.length) {
        alert('"리포트 포함" 체크된 항목이 없습니다. ⭐ 별점 1~2점, 🚩 자동 cliche 감지가 있으면 자동 체크됩니다.');
        return;
    }
    var md = '# AI 운영 리포트 — ' + new Date().toLocaleString('ko-KR') + '\n\n';
    md += '아래 항목들이 어색하거나 문제가 있습니다. 프롬프트를 다듬어주세요.\n\n';

    var byType = { chat:[], post:[], comment:[] };
    checked.forEach(function(cb) {
        var card = cb.closest('.aim-card');
        var type = card.dataset.type;
        var id = card.dataset.id;
        var cliches = card.dataset.cliches || '';
        var rating = parseInt(card.querySelector('.aim-rating').dataset.current || '0');
        var note = card.querySelector('.aim-note').value;
        var flag = card.querySelector('.aim-flag-select').value;
        var meta = card.querySelector('.aim-card-meta').innerText.replace(/\s+/g, ' ').trim();
        var item = { id:id, rating:rating, note:note, flag:flag, meta:meta, cliches:cliches };

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

    function lineHeader(it) {
        var h = '### #' + it.id;
        if (it.rating) h += ' (★' + it.rating + ')';
        if (it.flag) h += ' [' + it.flag + ']';
        if (it.cliches) h += ' 🚩{' + it.cliches + '}';
        return h + '\n';
    }

    if (byType.chat.length) {
        md += '## 💬 챗봇 대화 — ' + byType.chat.length + '건\n\n';
        byType.chat.forEach(function(it) {
            md += lineHeader(it);
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
            md += lineHeader(it);
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
            md += lineHeader(it);
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
    md += '\n🚩{...} 표시는 자동 감지된 AI 티 표현이에요. 우선 그것들을 system_prompt 의 "절대 금지" 섹션에 추가해주세요.\n';

    navigator.clipboard.writeText(md).then(function() {
        alert('✓ 클립보드에 복사됨 (' + checked.length + '건). Claude 채팅에 붙여넣어 주세요.');
    }).catch(function() {
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

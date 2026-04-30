<?php
/**
 * 이벤트 캘린더 — 큐레이션된 코인 이벤트 (data/events.json)
 */
require __DIR__ . '/../../../theme/default/header.php';

$eventsFile = __DIR__ . '/../data/events.json';
$rawData = file_exists($eventsFile) ? json_decode(file_get_contents($eventsFile), true) : ['events' => []];
$events = is_array($rawData['events'] ?? null) ? $rawData['events'] : [];

// 미래 이벤트만 + 날짜 오름차순 + 30개 제한
$today = date('Y-m-d');
$events = array_filter($events, fn($e) => ($e['date'] ?? '') >= $today);
usort($events, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
$events = array_slice($events, 0, 30);

// 한글 월명
$mTxt = ['1'=>'1월','2'=>'2월','3'=>'3월','4'=>'4월','5'=>'5월','6'=>'6월','7'=>'7월','8'=>'8월','9'=>'9월','10'=>'10월','11'=>'11월','12'=>'12월'];
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1>📅 이벤트 캘린더</h1>
            <div class="cx-sub">다가오는 ETF · 하드포크 · 컨퍼런스 · 규제 일정</div>
        </div>
        <div style="font-size:11px;color:var(--text-light)">총 <strong style="color:var(--primary)"><?= count($events) ?></strong>개 예정</div>
    </div>

    <?php if (empty($events)): ?>
        <div class="cx-card" style="text-align:center;padding:50px"><div style="font-size:48px;margin-bottom:14px">🌙</div><div>예정된 이벤트가 없습니다.</div></div>
    <?php else: ?>
        <div class="cx-event-list">
            <?php foreach ($events as $e):
                $date = $e['date'] ?? '';
                $ts = strtotime($date);
                $title = $e['title'] ?? '';
                $desc = $e['description'] ?? '';
                $tag = $e['tag'] ?? '';
                $type = $e['type'] ?? '';
                $day = $ts ? date('j', $ts) : '?';
                $monthNum = $ts ? (int)date('n', $ts) : 0;
                $month = $mTxt[$monthNum] ?? '?';
                $weekday = $ts ? ['일','월','화','수','목','금','토'][date('w', $ts)] : '';
                $dDays = $ts ? max(0, ceil(($ts - time()) / 86400)) : 0;
            ?>
            <div class="cx-event-row">
                <div class="cx-event-date">
                    <small><?= $month ?></small>
                    <b><?= $day ?></b>
                    <small><?= $weekday ?>요일</small>
                </div>
                <div>
                    <div class="cx-event-title"><?= htmlspecialchars($title) ?></div>
                    <?php if ($desc): ?><div class="cx-event-desc"><?= htmlspecialchars($desc) ?></div><?php endif; ?>
                    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
                        <?php if ($tag): ?><span style="font-size:11px;color:var(--primary);font-weight:600;font-family:var(--font-mono)">#<?= htmlspecialchars($tag) ?></span><?php endif; ?>
                        <?php if ($type): ?><span style="font-size:11px;color:var(--secondary);font-weight:500">· <?= htmlspecialchars($type) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="cx-event-tag"><?= $dDays === 0 ? '오늘' : 'D-' . $dDays ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top:30px;padding:14px 18px;background:rgba(0,0,0,0.3);border-radius:10px;font-size:12px;color:var(--text-light);text-align:center">
        ℹ️ 이벤트 정보는 큐레이션된 데이터입니다. 변경될 수 있으니 공식 채널에서 한 번 더 확인해주세요.
    </div>
</div>
<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

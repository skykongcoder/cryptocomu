<?php
/**
 * 크립토 인플루언서 X 피드 (전체)
 */

// 상대 시간 헬퍼
if (!function_exists('cxi_relative_time')) {
    function cxi_relative_time(int $ts): string {
        if ($ts <= 0) return '';
        $diff = time() - $ts;
        if ($diff < 60) return '방금';
        if ($diff < 3600) return floor($diff / 60) . '분 전';
        if ($diff < 86400) return floor($diff / 3600) . '시간 전';
        if ($diff < 604800) return floor($diff / 86400) . '일 전';
        return date('m/d', $ts);
    }
}

require __DIR__ . '/../../../theme/default/header.php';

$influencers = cxi_load_influencers();
$isAdmin = class_exists('Auth') && Auth::check() && Auth::isAdmin();

// 사용자 페이지: 캐시만 사용 (즉시 응답). 신규 페치는 백그라운드 tick에서 처리.
usort($influencers, fn($a, $b) => ($a['priority'] ?? 9) <=> ($b['priority'] ?? 9));
$tweetsByUser = cxi_fetch_with_budget($influencers, 0);

$feed = [];
foreach ($influencers as $inf) {
    $tweets = $tweetsByUser[$inf['handle']] ?? [];
    foreach (array_slice($tweets, 0, 3) as $t) {
        $feed[] = ['inf' => $inf, 'tweet' => $t];
    }
}
$feed = array_slice($feed, 0, 30);

// === 자동 번역 — 모든 트윗을 한국어로 (배치, 캐시 활용) ===
$apiKey = cxi_get_api_key();
$texts  = array_column(array_column($feed, 'tweet'), 'text');
$translations = $apiKey ? cxi_translate_batch($texts, $apiKey) : [];
foreach ($feed as $i => &$row) {
    $row['ko'] = $translations[$i] ?? '';
}
unset($row);
// 최신순 정렬 (publish 없으면 0 → 뒤로)
usort($feed, fn($a, $b) => ($b['tweet']['published'] ?? 0) <=> ($a['tweet']['published'] ?? 0));
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1>🐦 크립토 인플루언서 X</h1>
            <div class="cx-sub">Vitalik · CZ · Saylor · 주기영 · 머스크 등 <?= count($influencers) ?>명의 X 활동을 자동 수집 + AI 한국어 번역</div>
        </div>
        <?php if ($isAdmin): ?>
        <button id="cxiAutoPost" style="background:linear-gradient(135deg, var(--accent), var(--magenta));color:#000;border:0;padding:10px 18px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px">
            🤖 새 트윗 → 게시판 자동 등록
        </button>
        <?php endif; ?>
    </div>

    <!-- 인플루언서 목록 -->
    <div class="cxi-grid">
        <?php foreach ($influencers as $inf):
            $priorityIcon = ['1' => '⭐', '2' => '✓', '3' => '·'][$inf['priority']] ?? '';
            $tweetCount = count($tweetsByUser[$inf['handle']] ?? []);
        ?>
        <a href="<?= nb_url('influencers/' . $inf['handle']) ?>" class="cxi-card" data-handle="<?= htmlspecialchars($inf['handle']) ?>">
            <div class="cxi-avatar"><?= htmlspecialchars(mb_substr($inf['name'], 0, 1)) ?></div>
            <div class="cxi-info">
                <div class="cxi-name"><?= htmlspecialchars($inf['name']) ?> <span class="cxi-priority"><?= $priorityIcon ?></span></div>
                <div class="cxi-handle">@<?= htmlspecialchars($inf['handle']) ?></div>
                <div class="cxi-role"><?= htmlspecialchars($inf['role']) ?></div>
            </div>
            <div class="cxi-meta">
                <?php foreach ($inf['tags'] as $tag): ?>
                <span class="cxi-tag">#<?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
                <div class="cxi-tweet-count"><?= $tweetCount ?>개</div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 통합 피드 -->
    <h2 style="font-size:18px;margin:30px 0 14px;font-family:var(--font-display)">
        💬 최근 트윗 통합 피드
        <small style="font-weight:400;color:var(--text-light);font-size:12px;margin-left:8px"><?= count($feed) ?>개</small>
    </h2>

    <?php if (empty($feed)): ?>
        <div class="cx-card" style="text-align:center;padding:50px 20px">
            <div style="font-size:48px;margin-bottom:12px">🌐</div>
            <div style="font-size:15px;font-weight:600;margin-bottom:6px">트윗을 불러올 수 없습니다</div>
            <div style="color:var(--text-light);font-size:13px;line-height:1.6">
                X(트위터)는 무료 API를 제공하지 않아 RSSHub·신디케이션 등 비공식 소스를 사용합니다.<br>
                해당 서비스가 일시적으로 다운됐거나 IP 차단됐을 수 있습니다. 잠시 후 다시 시도해주세요.
            </div>
        </div>
    <?php else: ?>
    <div class="cxi-feed">
        <?php foreach ($feed as $row):
            $inf = $row['inf']; $t = $row['tweet']; $ko = $row['ko'] ?? '';
            $time = $t['published'] ? cxi_relative_time($t['published']) : '';
        ?>
        <article class="cxi-tweet" data-text="<?= htmlspecialchars($t['text'], ENT_QUOTES) ?>">
            <div class="cxi-tweet-head">
                <a href="<?= nb_url('influencers/' . $inf['handle']) ?>" class="cxi-tweet-author">
                    <div class="cxi-tweet-avatar"><?= htmlspecialchars(mb_substr($inf['name'], 0, 1)) ?></div>
                    <div>
                        <div class="cxi-tweet-name"><?= htmlspecialchars($inf['name']) ?></div>
                        <div class="cxi-tweet-handle">@<?= htmlspecialchars($inf['handle']) ?> · <?= htmlspecialchars($inf['role']) ?></div>
                    </div>
                </a>
                <?php if ($time): ?><div class="cxi-tweet-time"><?= htmlspecialchars($time) ?></div><?php endif; ?>
            </div>
            <div class="cxi-tweet-body">
                <?php if ($ko): ?>
                <!-- 한국어 번역 (기본 표시) -->
                <div class="cxi-tweet-ko"><?= nl2br(htmlspecialchars($ko)) ?></div>
                <!-- 원문 (토글로 표시) -->
                <div class="cxi-tweet-en" style="display:none"><?= nl2br(htmlspecialchars($t['text'])) ?></div>
                <?php else: ?>
                <!-- 번역 없음 — 원문만 -->
                <div class="cxi-tweet-original"><?= nl2br(htmlspecialchars($t['text'])) ?></div>
                <?php endif; ?>
            </div>
            <div class="cxi-tweet-foot">
                <?php if ($ko): ?>
                <button type="button" class="cxi-toggle-en">📝 원문 보기</button>
                <span class="cxi-tr-badge">🇰🇷 자동 번역</span>
                <?php endif; ?>
                <?php if ($t['url']): ?>
                <a href="<?= htmlspecialchars($t['url']) ?>" target="_blank" rel="noopener" class="cxi-tweet-link">트위터 원본 →</a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:30px;padding:14px 18px;background:rgba(0,0,0,0.3);border-radius:10px;font-size:12px;color:var(--text-light);line-height:1.7">
        ℹ️ <strong>데이터 출처</strong>: X는 무료 API가 없어서 <code>RSSHub 공개 인스턴스</code> + <code>Twitter 신디케이션</code> 폴백으로 페치합니다.
        해당 서비스 상태에 따라 일부 인플루언서의 최신 트윗이 누락될 수 있습니다. <strong>15분 캐시</strong> 적용.
        <strong>번역</strong>은 OpenRouter 무료 모델(gpt-oss-120b)이 처리하며 <strong>24시간 캐시</strong>됩니다.
    </div>
</div>

<script>
(function () {
    'use strict';
    var AUTOPOST_URL = '<?= nb_url("api/influencers-auto-post") ?>';

    // 원문/번역 토글
    document.querySelectorAll('.cxi-toggle-en').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var article = btn.closest('.cxi-tweet');
            var ko = article.querySelector('.cxi-tweet-ko');
            var en = article.querySelector('.cxi-tweet-en');
            if (!ko || !en) return;
            var showingEn = en.style.display !== 'none';
            if (showingEn) {
                en.style.display = 'none'; ko.style.display = '';
                btn.textContent = '📝 원문 보기';
            } else {
                en.style.display = ''; ko.style.display = 'none';
                btn.textContent = '🇰🇷 번역 보기';
            }
        });
    });

    // 자동 등록 버튼 (관리자만)
    var autoBtn = document.getElementById('cxiAutoPost');
    if (autoBtn) {
        autoBtn.addEventListener('click', async function () {
            if (!confirm('우선순위 1 인플루언서의 새 트윗을 코인뉴스 게시판에 자동 등록합니다. 진행할까요?')) return;
            autoBtn.disabled = true; autoBtn.textContent = '⏳ 처리 중... (1~2분)';
            try {
                var r = await fetch(AUTOPOST_URL, { credentials: 'same-origin' });
                var j = await r.json();
                if (j.ok) {
                    var msg = '✅ ' + j.posted + '개 글 등록됨';
                    if (j.errors && j.errors.length) msg += ' (오류 ' + j.errors.length + '개)';
                    alert(msg);
                    autoBtn.textContent = '🤖 새 트윗 → 게시판 자동 등록';
                    autoBtn.disabled = false;
                } else {
                    alert('❌ ' + (j.error || '실패'));
                    autoBtn.disabled = false; autoBtn.textContent = '🤖 새 트윗 → 게시판 자동 등록';
                }
            } catch (e) {
                alert('❌ 네트워크 오류');
                autoBtn.disabled = false; autoBtn.textContent = '🤖 새 트윗 → 게시판 자동 등록';
            }
        });
    }
})();
</script>

<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

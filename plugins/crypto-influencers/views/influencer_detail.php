<?php
/**
 * 개별 인플루언서 트윗 페이지
 */
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

// 자동 번역 (배치)
$apiKey = cxi_get_api_key();
$texts  = array_map(fn($t) => $t['text'], $tweets);
$translations = $apiKey ? cxi_translate_batch($texts, $apiKey) : [];

require __DIR__ . '/../../../theme/default/header.php';
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1><?= htmlspecialchars($influencer['name']) ?> <small style="font-size:14px;color:var(--text-light);font-weight:500">@<?= htmlspecialchars($influencer['handle']) ?></small></h1>
            <div class="cx-sub"><?= htmlspecialchars($influencer['role']) ?> ·
                <?php foreach ($influencer['tags'] as $tag): ?>
                <span style="color:var(--primary)">#<?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="<?= nb_url('influencers') ?>" style="font-size:12px;color:var(--text-light);text-decoration:none">← 전체 인플루언서로</a>
    </div>

    <?php if (empty($tweets)): ?>
        <div class="cx-card" style="text-align:center;padding:50px 20px">
            <div style="font-size:48px;margin-bottom:12px">🌐</div>
            <div>이 인플루언서의 트윗을 불러오지 못했습니다.</div>
        </div>
    <?php else: ?>
        <div class="cxi-feed">
            <?php foreach ($tweets as $i => $t):
                $time = $t['published'] ? cxi_relative_time($t['published']) : '';
                $ko = $translations[$i] ?? '';
            ?>
            <article class="cxi-tweet" data-text="<?= htmlspecialchars($t['text'], ENT_QUOTES) ?>">
                <div class="cxi-tweet-head">
                    <div class="cxi-tweet-author">
                        <div class="cxi-tweet-avatar"><?= htmlspecialchars(mb_substr($influencer['name'], 0, 1)) ?></div>
                        <div>
                            <div class="cxi-tweet-name"><?= htmlspecialchars($influencer['name']) ?></div>
                            <div class="cxi-tweet-handle">@<?= htmlspecialchars($influencer['handle']) ?></div>
                        </div>
                    </div>
                    <?php if ($time): ?><div class="cxi-tweet-time"><?= htmlspecialchars($time) ?></div><?php endif; ?>
                </div>
                <div class="cxi-tweet-body">
                    <?php if ($ko): ?>
                    <div class="cxi-tweet-ko"><?= nl2br(htmlspecialchars($ko)) ?></div>
                    <div class="cxi-tweet-en" style="display:none"><?= nl2br(htmlspecialchars($t['text'])) ?></div>
                    <?php else: ?>
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
</div>
<script>
(function () {
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
})();
</script>
<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

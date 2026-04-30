<?php
/**
 * 코인 뉴스 — CryptoCompare 무료 News API + 30분 캐시
 */
require __DIR__ . '/../../../theme/default/header.php';
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1>⚡ 코인 속보</h1>
            <div class="cx-sub">한국어 코인 속보 — 토큰포스트 + 코인리더스 RSS · 30분마다 자동 갱신</div>
        </div>
        <div style="font-size:11px;color:var(--text-light)">
            출처:
            <a href="https://www.tokenpost.kr/" target="_blank" rel="noopener" style="color:var(--primary)">토큰포스트</a> ·
            <a href="https://www.coinreaders.com/" target="_blank" rel="noopener" style="color:var(--primary)">코인리더스</a>
        </div>
    </div>

    <?php if (empty($news)): ?>
        <div class="cx-card" style="text-align:center;padding:40px">
            <div style="font-size:48px;margin-bottom:14px">📰</div>
            <div style="color:var(--text-light)">뉴스를 불러올 수 없습니다. 잠시 후 다시 시도해주세요.</div>
        </div>
    <?php else: ?>
    <div class="cx-news-grid">
        <?php foreach ($news as $n):
            $title = $n['title'] ?: '(제목 없음)';
            $url = $n['url'] ?: '#';
            $img = $n['image'] ?: '';
            $source = $n['source'] ?: '';
            $body = $n['body'] ?: '';
            $time = $n['published'] ? date('m/d H:i', $n['published']) : '';
            $cats = explode('|', $n['categories'] ?? '');
            $catText = implode(' · ', array_slice(array_filter($cats), 0, 3));
        ?>
        <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer" class="cx-news-card">
            <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img) ?>" class="cx-news-img" loading="lazy" alt="" onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="cx-news-body">
                <h3 class="cx-news-title"><?= htmlspecialchars($title) ?></h3>
                <?php if ($body): ?>
                    <div style="font-size:12px;color:var(--text-light);line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">
                        <?= htmlspecialchars($body) ?>
                    </div>
                <?php endif; ?>
                <?php if ($catText): ?>
                    <div class="cx-news-cats"><?= htmlspecialchars($catText) ?></div>
                <?php endif; ?>
                <div class="cx-news-meta">
                    <span class="cx-news-source"><?= htmlspecialchars($source) ?></span>
                    <span><?= $time ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:30px;padding:14px 18px;background:rgba(0,0,0,0.3);border-radius:10px;font-size:12px;color:var(--text-light);text-align:center">
        ℹ️ 뉴스는 RSS 피드 기반으로 자동 수집됩니다. 기사 클릭 시 원문 사이트로 이동합니다.
    </div>
</div>
<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

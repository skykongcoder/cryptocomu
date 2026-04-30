<?php
/**
 * Teletify — 설정 페이지 (광고 표시 전용)
 */

$promo = _promo_fetch();
?>

<style>
.pt-wrap { max-width: 700px; font-family: -apple-system, sans-serif; }

/* 메인 카드 */
.pt-hero {
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
}
.pt-hero-top {
    padding: 32px 32px 28px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #fff;
    position: relative;
}
.pt-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    background: #22c55e;
    color: #fff;
    letter-spacing: .4px;
    margin-bottom: 14px;
    text-transform: uppercase;
}
.pt-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 10px;
    line-height: 1.3;
    letter-spacing: -.3px;
}
.pt-hero-subtitle {
    font-size: 15px;
    color: #94a3b8;
    margin: 0;
    line-height: 1.6;
}
.pt-hero-body {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 14px 14px;
    padding: 28px 32px;
}
.pt-desc {
    font-size: 14px;
    color: #475569;
    line-height: 1.8;
    margin: 0 0 22px;
}

/* 특징 리스트 */
.pt-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 24px;
}
.pt-feature-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}
.pt-feature-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 1px;
    color: #22c55e;
}
.pt-feature-text {
    font-size: 13px;
    color: #374151;
    font-weight: 500;
    line-height: 1.4;
}

/* CTA 버튼 */
.pt-cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 28px;
    background: #16a34a;
    color: #fff;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    transition: background .15s, transform .15s;
    box-shadow: 0 2px 8px rgba(22,163,74,.3);
}
.pt-cta:hover {
    background: #15803d;
    text-decoration: none;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(22,163,74,.35);
}

/* 이미지 */
.pt-image {
    width: 100%;
    border-radius: 8px;
    margin-bottom: 22px;
    border: 1px solid #e2e8f0;
    display: block;
}

/* 오류/로딩 */
.pt-error {
    padding: 24px;
    background: #fef9c3;
    border: 1px solid #fde68a;
    border-radius: 10px;
    font-size: 13px;
    color: #92400e;
    text-align: center;
}

/* 하단 안내 */
.pt-footer {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 16px;
    text-align: center;
}
</style>

<div class="pt-wrap">

<?php if (empty($promo)): ?>

    <div class="pt-error">
        콘텐츠를 불러오지 못했습니다. 잠시 후 페이지를 새로고침해 주세요.
    </div>

<?php else:
    $title    = htmlspecialchars($promo['title']    ?? '', ENT_QUOTES, 'UTF-8');
    $subtitle = htmlspecialchars($promo['subtitle'] ?? '', ENT_QUOTES, 'UTF-8');
    $desc     = htmlspecialchars($promo['description'] ?? '', ENT_QUOTES, 'UTF-8');
    $cta_text = htmlspecialchars($promo['cta_text'] ?? '자세히 보기', ENT_QUOTES, 'UTF-8');
    $cta_url  = htmlspecialchars($promo['cta_url']  ?? '#', ENT_QUOTES, 'UTF-8');
    $badge    = htmlspecialchars($promo['badge']    ?? 'NuriBoard 추천', ENT_QUOTES, 'UTF-8');
    $image    = htmlspecialchars($promo['image_url'] ?? '', ENT_QUOTES, 'UTF-8');
    $features = $promo['features'] ?? [];
?>

<div class="pt-hero">
    <!-- 상단 헤더 -->
    <div class="pt-hero-top">
        <div class="pt-badge"><?= $badge ?></div>
        <h1 class="pt-hero-title"><?= $title ?></h1>
        <?php if ($subtitle): ?>
            <p class="pt-hero-subtitle"><?= $subtitle ?></p>
        <?php endif; ?>
    </div>

    <!-- 본문 -->
    <div class="pt-hero-body">

        <?php if ($image): ?>
            <img src="<?= $image ?>" alt="<?= $title ?>" class="pt-image">
        <?php endif; ?>

        <?php if ($desc): ?>
            <p class="pt-desc"><?= nl2br($desc) ?></p>
        <?php endif; ?>

        <?php if (!empty($features)): ?>
        <div class="pt-features">
            <?php foreach ((array)$features as $feat): ?>
            <div class="pt-feature-item">
                <svg class="pt-feature-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="pt-feature-text"><?= htmlspecialchars($feat, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <a href="<?= $cta_url ?>" target="_blank" rel="noopener noreferrer" class="pt-cta">
            <?= $cta_text ?>
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </a>
    </div>
</div>

<p class="pt-footer">이 플러그인은 NuriBoard에서 제공하는 서비스 안내입니다.</p>

<?php endif; ?>

</div>

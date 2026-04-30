<?php
/**
 * NuriBoard - 공개 플러그인 마켓 페이지
 * 로그인 없이 누구나 볼 수 있는 플러그인 소개 페이지 (SEO 노출용)
 */
require __DIR__ . '/header.php';

$type = $_GET['type'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['category'] ?? '');
$catLabels = [
    'seo' => 'SEO', 'security' => '보안', 'community' => '커뮤니티', 'content' => '콘텐츠',
    'design' => '디자인', 'advertising' => '광고/수익', 'notification' => '알림/연동',
    'management' => '관리', 'media' => '미디어', 'form' => '폼/설문', 'shopping' => '쇼핑몰', 'utility' => '유틸리티',
];
?>

<div class="container" style="padding:24px 16px;max-width:1200px;margin:0 auto">

    <div style="text-align:center;margin-bottom:32px">
        <h1 style="font-size:28px;font-weight:800;margin-bottom:8px;color:var(--text)">NuriBoard 플러그인 마켓</h1>
        <p style="font-size:15px;color:var(--text-light)">다양한 플러그인으로 누리보드를 더 강력하게 만들어보세요.</p>
    </div>

    <!-- 필터 + 검색 -->
    <form method="get" action="<?= nb_url('market') ?>" style="margin-bottom:20px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <a href="<?= nb_url('market') ?>" class="nb-mk-tab <?= $type === 'all' ? 'active' : '' ?>">전체</a>
        <a href="<?= nb_url('market?type=free') ?>" class="nb-mk-tab <?= $type === 'free' ? 'active' : '' ?>">무료</a>
        <a href="<?= nb_url('market?type=paid') ?>" class="nb-mk-tab <?= $type === 'paid' ? 'active' : '' ?>">유료</a>
        <input type="hidden" name="type" value="<?= nb_e($type) ?>">
        <input type="text" name="q" value="<?= nb_e($search) ?>" placeholder="플러그인 검색..."
               style="flex:1;min-width:200px;padding:8px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;color:var(--text);background:var(--white)">
        <button type="submit" style="padding:8px 16px;border:1px solid var(--primary);background:var(--primary);color:#fff;border-radius:8px;font-size:14px;cursor:pointer">검색</button>
    </form>

    <!-- 카테고리 필터 -->
    <div style="margin-bottom:20px;display:flex;gap:6px;flex-wrap:wrap">
        <a href="<?= nb_url('market') ?>?type=<?= nb_e($type) ?>" class="nb-mk-cat <?= !$catFilter ? 'active' : '' ?>">전체</a>
        <?php foreach ($catLabels as $key => $label): ?>
        <a href="<?= nb_url('market') ?>?type=<?= nb_e($type) ?>&category=<?= $key ?>" class="nb-mk-cat <?= $catFilter === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php
    // 필터 적용
    $filtered = $plugins;
    if ($type === 'free') {
        $filtered = array_filter($filtered, fn($p) => (int)$p['price'] === 0);
    } elseif ($type === 'paid') {
        $filtered = array_filter($filtered, fn($p) => (int)$p['price'] > 0);
    }
    if ($catFilter) {
        $filtered = array_filter($filtered, fn($p) => ($p['category'] ?? '') === $catFilter);
    }
    if ($search) {
        $filtered = array_filter($filtered, fn($p) => mb_stripos($p['name'], $search) !== false || mb_stripos($p['description'] ?? '', $search) !== false);
    }
    $filtered = array_values($filtered);
    ?>

    <?php if (empty($filtered)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-light)">
        <p style="font-size:48px;margin-bottom:12px">&#128268;</p>
        <p style="font-size:16px;font-weight:600;margin-bottom:4px">등록된 플러그인이 없습니다.</p>
        <p style="font-size:13px">곧 새로운 플러그인이 추가될 예정입니다.</p>
    </div>
    <?php else: ?>
    <div class="nb-mk-grid">
    <?php foreach ($filtered as $p): ?>
        <a class="nb-mk-card" href="https://nuribd.com/market/plugin/<?= (int)$p['id'] ?>" target="_blank">
            <div class="nb-mk-thumb">
                <?php if ($p['thumbnail']): ?>
                <img src="<?= nb_url($p['thumbnail']) ?>" alt="<?= nb_e($p['name']) ?>" loading="lazy">
                <?php else: ?>
                <span style="font-size:32px;color:#cbd5e1">&#128268;</span>
                <?php endif; ?>
            </div>
            <div class="nb-mk-body">
                <h3 class="nb-mk-name"><?= nb_e($p['name']) ?></h3>
                <p class="nb-mk-desc"><?= nb_e(mb_strimwidth($p['description'] ?? '', 0, 80, '...')) ?></p>
                <div class="nb-mk-meta">
                    <span>v<?= nb_e($p['version']) ?> · <?= number_format($p['downloads']) ?> 다운로드</span>
                    <?php $_t = $p['access_tier'] ?? 'all'; ?>
                    <?php if ((int)$p['price'] > 0): ?>
                    <span class="nb-mk-price paid"><?= number_format($p['price']) ?>원</span>
                    <?php elseif ($_t === 'pro'): ?>
                    <span class="nb-mk-price tier-pro">프로 포함</span>
                    <?php elseif ($_t === 'basic'): ?>
                    <span class="nb-mk-price tier-basic">베이직 포함</span>
                    <?php else: ?>
                    <span class="nb-mk-price free">무료</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:32px;padding:20px;font-size:13px;color:var(--text-light)">
        총 <strong><?= count($filtered) ?></strong>개의 플러그인
    </div>
    <?php endif; ?>

</div>

<style>
.nb-mk-tab{display:inline-block;padding:8px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;color:var(--text);text-decoration:none;transition:all .15s;cursor:pointer;background:var(--white)}
.nb-mk-tab:hover{border-color:var(--primary);text-decoration:none}
.nb-mk-tab.active{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:600}
.nb-mk-cat{display:inline-block;padding:5px 12px;border:1px solid var(--border);border-radius:20px;font-size:13px;color:var(--text-light);text-decoration:none;transition:all .15s;background:var(--white)}
.nb-mk-cat:hover{border-color:var(--primary);color:var(--primary);text-decoration:none}
.nb-mk-cat.active{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:600}
.nb-mk-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.nb-mk-card{display:block;background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:box-shadow .15s,transform .15s;text-decoration:none;color:inherit}
.nb-mk-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.08);transform:translateY(-2px);text-decoration:none}
.nb-mk-thumb{width:100%;aspect-ratio:16/9;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.nb-mk-thumb img{width:100%;height:100%;object-fit:fill}
.nb-mk-body{padding:14px 16px 16px}
.nb-mk-name{font-size:15px;font-weight:700;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)}
.nb-mk-desc{font-size:13px;color:var(--text-light);line-height:1.5;height:40px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;margin-bottom:10px}
.nb-mk-meta{display:flex;align-items:center;justify-content:space-between;font-size:12px;color:#94a3b8}
.nb-mk-price.free{font-weight:700;color:#059669}
.nb-mk-price.paid{font-weight:700;color:#f59e0b}
@media(max-width:900px){.nb-mk-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){
.nb-mk-grid{grid-template-columns:1fr}
}
</style>

<?php require __DIR__ . '/footer.php'; ?>

<?php
/**
 * 코인 용어 사전 — JSON 데이터 + 클라이언트 측 검색/필터
 */
require __DIR__ . '/../../../theme/default/header.php';

$glossaryFile = __DIR__ . '/../data/glossary.json';
$rawData = file_exists($glossaryFile) ? json_decode(file_get_contents($glossaryFile), true) : ['terms' => []];
$terms = is_array($rawData['terms'] ?? null) ? $rawData['terms'] : [];

// 카테고리 한글명
$catLabels = [
    'basics'  => '기본',
    'trading' => '거래',
    'defi'    => 'DeFi',
    'nft'     => 'NFT',
    'tech'    => '기술',
    'korean'  => '국내·규제',
];
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1>📖 코인 용어 사전</h1>
            <div class="cx-sub">기본부터 DeFi·NFT·기술까지 — 한국어로 정리한 <?= count($terms) ?>개 용어</div>
        </div>
    </div>

    <input type="text" id="cxGSearch" class="cx-glossary-search" placeholder="🔍 용어 검색 — 한글/영문 모두 가능 (예: '디파이', 'Stablecoin')">

    <div class="cx-glossary-cats">
        <button class="cx-glossary-cat active" data-cat="all">전체</button>
        <?php foreach ($catLabels as $key => $label): ?>
            <button class="cx-glossary-cat" data-cat="<?= $key ?>"><?= htmlspecialchars($label) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="cx-glossary-grid" id="cxGGrid">
        <?php foreach ($terms as $t):
            $name = $t['name'] ?? '';
            $cat  = $t['cat'] ?? 'basics';
            $def  = $t['def'] ?? '';
            $catLabel = $catLabels[$cat] ?? $cat;
        ?>
        <div class="cx-term" data-cat="<?= htmlspecialchars($cat) ?>" data-search="<?= htmlspecialchars(mb_strtolower($name . ' ' . $def)) ?>">
            <div class="cx-term-name">
                <?= htmlspecialchars($name) ?>
                <span class="cx-term-cat"><?= htmlspecialchars($catLabel) ?></span>
            </div>
            <p class="cx-term-def"><?= htmlspecialchars($def) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="cxGEmpty" style="display:none;text-align:center;padding:40px;color:var(--text-light)">
        🔍 검색 결과가 없습니다. 다른 키워드로 시도해보세요.
    </div>
</div>

<script>
(function () {
    var search = document.getElementById('cxGSearch');
    var grid = document.getElementById('cxGGrid');
    var empty = document.getElementById('cxGEmpty');
    var cats = document.querySelectorAll('.cx-glossary-cat');
    var currentCat = 'all';

    function applyFilter() {
        var q = search.value.trim().toLowerCase();
        var visibleCount = 0;
        grid.querySelectorAll('.cx-term').forEach(function (el) {
            var matchCat = (currentCat === 'all') || (el.dataset.cat === currentCat);
            var matchQuery = !q || el.dataset.search.indexOf(q) !== -1;
            var visible = matchCat && matchQuery;
            el.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });
        empty.style.display = visibleCount === 0 ? '' : 'none';
        grid.style.display = visibleCount === 0 ? 'none' : '';
    }

    search.addEventListener('input', applyFilter);
    cats.forEach(function (btn) {
        btn.addEventListener('click', function () {
            cats.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentCat = btn.dataset.cat;
            applyFilter();
        });
    });
})();
</script>

<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

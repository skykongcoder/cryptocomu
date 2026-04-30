<?php
/**
 * NuriBoard 메인 페이지
 */
SEO::setDescription(nb_setting('site_description', ''));
$boards = Board::listAll(true);
$menuTree = Menu::getTree();
$useCustomMenu = !empty($menuTree);

$prefix = DB::getPrefix();
$boardNames = [];
foreach ($boards as $_b) $boardNames[$_b['board_id']] = $_b['title'];

// 캐싱 적용 (60초)
$latestPosts   = Cache::remember('main_latest', 60, function() { return Post::recentPosts(20); });
$popularPosts  = Cache::remember('main_popular', 60, function() use ($prefix) {
    return DB::fetchAll("SELECT p.*, m.nickname as writer_name, m.level as writer_level FROM {$prefix}posts p LEFT JOIN {$prefix}members m ON p.member_id = m.id ORDER BY p.vote_up DESC, p.hit DESC LIMIT 10");
});
$latestComments= Cache::remember('main_comments', 60, function() use ($prefix) {
    return DB::fetchAll("SELECT c.*, m.nickname as writer_name, p.title as post_title, p.board_id, p.id as post_id FROM {$prefix}comments c LEFT JOIN {$prefix}members m ON c.member_id = m.id LEFT JOIN {$prefix}posts p ON c.post_id = p.id ORDER BY c.id DESC LIMIT 10");
});
$todayPosts    = Cache::remember('main_today_posts', 120, function() { return Post::todayCount(); });
$todayComments = Cache::remember('main_today_comments', 120, function() { return Comment::todayCount(); });
$todayMembers  = Cache::remember('main_today_members', 120, function() use ($prefix) { return DB::count("{$prefix}members", "DATE(created_at) = CURDATE()"); });

// 접속자 수 (캐싱 안 함 - 실시간)
$visitorDir  = NB_ROOT . '/data';
if (!is_dir($visitorDir)) mkdir($visitorDir, 0755, true);
$visitorFile = $visitorDir . '/visitors.json';
$now         = time();
$visitors    = file_exists($visitorFile) ? (json_decode(file_get_contents($visitorFile), true) ?: []) : [];
$visitors    = array_filter($visitors, function($t) use ($now) { return ($now - $t) < 300; });
$visitorKey  = session_id() ?: md5($_SERVER['REMOTE_ADDR']);
$visitors[$visitorKey] = $now;
file_put_contents($visitorFile, json_encode($visitors));
$onlineCount = count($visitors);

// 확장 통계 (캐싱 5분)
$totalPosts    = Cache::remember('main_total_posts', 300, function() use ($prefix) { return DB::count("{$prefix}posts"); });
$totalComments = Cache::remember('main_total_comments', 300, function() use ($prefix) { return DB::count("{$prefix}comments"); });
$totalMembers  = Cache::remember('main_total_members', 300, function() use ($prefix) { return DB::count("{$prefix}members"); });

require __DIR__ . '/header.php';
?>
<!-- 히어로 섹션 -->
<?php if (nb_setting('main_hero_enabled') === '1'): ?>
    <?php if (nb_setting('main_hero_type') === 'single' && nb_setting('main_hero_image')): ?>
    <!-- 단일 이미지 히어로 -->
    <div class="hero-section">
        <div class="hero-section-inner">
            <?php $heroLink = nb_setting('main_hero_link'); ?>
            <?php if ($heroLink): ?><a href="<?= nb_e($heroLink) ?>"><?php endif; ?>
                <img src="<?= nb_url(nb_setting('main_hero_image')) ?>" alt="히어로" class="hero-img">
                <?php if (nb_setting('main_hero_title') || nb_setting('main_hero_desc')): ?>
                <div class="hero-overlay">
                    <?php if (nb_setting('main_hero_title')): ?><h2><?= nb_e(nb_setting('main_hero_title')) ?></h2><?php endif; ?>
                    <?php if (nb_setting('main_hero_desc')): ?><p><?= nb_e(nb_setting('main_hero_desc')) ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
            <?php if ($heroLink): ?></a><?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- 배너 슬라이더 히어로 -->
    <?php $mainBanners = Banner::listByPosition('main'); ?>
    <?php if (!empty($mainBanners)): ?>
    <div class="banner-wrap">
        <div class="container">
            <div class="main-banner-slider" id="mainSlider">
                <?php foreach ($mainBanners as $i => $bn): ?>
                    <a href="<?= nb_e($bn['link']) ?>" target="<?= nb_e($bn['target']) ?>" class="mbs-slide <?= $i === 0 ? 'active' : '' ?>">
                        <img src="<?= nb_url($bn['image']) ?>" alt="<?= nb_e($bn['title']) ?>">
                    </a>
                <?php endforeach; ?>
                <?php if (count($mainBanners) > 1): ?>
                <div class="mbs-dots">
                    <?php foreach ($mainBanners as $i => $bn): ?>
                        <span class="mbs-dot <?= $i === 0 ? 'active' : '' ?>" onclick="goSlide(<?= $i ?>)"></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<!-- ===== BTC 히어로 섹션: TradingView 히트맵 + 5개 코인 카드 ===== -->
<?php
if (function_exists('cm_tickers') && function_exists('cm_candles_days')):
    $btc = cm_tickers(['KRW-BTC'])[0] ?? null;
    if ($btc):
        $btcRate = $btc['signed_change_rate'] ?? 0;
        $btcCls  = $btcRate > 0 ? 'up' : ($btcRate < 0 ? 'down' : 'flat');
        // 사이드 코인 5개 (BTC 풀폭 + ETH/XRP/SOL/DOGE 2x2) + 14일 스파크라인
        $sideMarkets = ['KRW-BTC','KRW-ETH','KRW-XRP','KRW-SOL','KRW-DOGE'];
        $sideTickers = cm_tickers($sideMarkets);
        $sideMap = [];
        foreach ($sideTickers as $st) $sideMap[$st['market']] = $st;
        $sideSparks = [];
        foreach ($sideMarkets as $sm) {
            $candles = cm_candles_days($sm, 14);
            $sideSparks[$sm] = array_reverse(array_map(fn($c) => $c['trade_price'], $candles));
        }
?>
<link rel="stylesheet" href="<?= nb_url('plugins/crypto-market/assets/market.css') ?>">
<style>
/* 구조만 정의 - 색상/배경/타이포는 crypto-theme 플러그인이 담당 */
.btc-hero { padding:24px 0; margin-bottom:18px; isolation:isolate; position:relative; z-index:0; }
/* 좌측 = 큰 히트맵, 우측 = 5개 코인 카드 */
.btc-hero-grid { display:grid; grid-template-columns:2fr 1fr; gap:16px; align-items:stretch; min-height:520px; }
.btc-hero-left { padding:0; display:flex; flex-direction:column; overflow:hidden; isolation:isolate; }
.btc-hero-right{ display:grid; grid-template-columns:1fr 1fr; grid-auto-rows:1fr; gap:10px; }
/* 5개 카드 — 첫 번째(BTC) 풀폭 + 나머지 4개 2x2 */
.btc-hero-side:first-child { grid-column: 1 / -1; }
/* 히트맵 wrap 이 카드 전체를 가득 채움 */
.btc-hero-chart-wrap { flex:1; height:100%; position:relative; overflow:hidden; }
.btc-hero-chart-wrap iframe { width:100% !important; height:100% !important; display:block; border:0; }
.btc-hero-chart-wrap .tradingview-widget-container { width:100%; height:100%; }
.btc-hero-chart-wrap .tradingview-widget-container__widget { width:100%; height:100%; }
/* 우측 작은 카드들 */
.btc-hero-side { padding:14px; text-decoration:none; transition:all .2s; display:grid; grid-template-rows:auto 1fr auto; gap:8px; align-content:space-between; min-height:0; }
.btc-hero-side-name { font-size:13px; font-weight:700; line-height:1.1; display:flex; align-items:baseline; flex-wrap:wrap; gap:4px 6px; }
.btc-hero-side-name small { font-size:10px; font-weight:500; }
.btc-hero-side-spark { width:100%; height:36px; opacity:0.7; }
.btc-hero-side-foot { display:flex; justify-content:space-between; align-items:flex-end; gap:8px; }
.btc-hero-side-price { font-size:15px; font-weight:700; line-height:1.1; }
.btc-hero-side-rate { font-size:12px; font-weight:700; }
@media (max-width:900px) {
    .btc-hero-grid { grid-template-columns:1fr; gap:12px; min-height:auto; }
    .btc-hero-left { min-height:380px; }
    .btc-hero-side-spark { height:28px; }
}
</style>
<section class="btc-hero">
    <div class="container">
        <div class="btc-hero-grid">
            <div class="btc-hero-left">
                <!-- TradingView Crypto Coins Heatmap -->
                <div class="btc-hero-chart-wrap">
                    <div class="tradingview-widget-container">
                        <div class="tradingview-widget-container__widget"></div>
                        <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-crypto-coins-heatmap.js" async>
                        {
                          "dataSource": "Crypto",
                          "blockSize": "market_cap_calc",
                          "blockColor": "24h_close_change|5",
                          "locale": "ko",
                          "symbolUrl": "",
                          "colorTheme": "dark",
                          "hasTopBar": false,
                          "isDataSetEnabled": false,
                          "isZoomEnabled": true,
                          "hasSymbolTooltip": true,
                          "isMonoSize": false,
                          "width": "100%",
                          "height": "100%"
                        }
                        </script>
                    </div>
                </div>
            </div>
            <div class="btc-hero-right">
                <?php foreach ($sideMarkets as $sm):
                    $st = $sideMap[$sm] ?? null;
                    if (!$st) continue;
                    $sr = $st['signed_change_rate'] ?? 0;
                    $sc = $sr > 0 ? 'up' : ($sr < 0 ? 'down' : 'flat');
                    $sym = explode('-', $sm)[1];
                    $names = ['BTC'=>'비트코인','ETH'=>'이더리움','XRP'=>'리플','SOL'=>'솔라나','DOGE'=>'도지코인'];
                ?>
                <a href="<?= nb_url('coin/' . $sm) ?>" class="btc-hero-side" data-spark='<?= json_encode($sideSparks[$sm] ?? []) ?>' data-cls="<?= $sc ?>">
                    <div class="btc-hero-side-name"><?= $names[$sym] ?? $sym ?><small><?= $sym ?></small></div>
                    <canvas class="btc-hero-side-spark"></canvas>
                    <div class="btc-hero-side-foot">
                        <div class="btc-hero-side-price"><?= cm_fmt_price($st['trade_price']) ?></div>
                        <div class="btc-hero-side-rate <?= $sc ?>"><?= cm_fmt_pct($sr) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- 상승/하락 TOP 5 위젯 -->
<section class="container" style="margin-bottom:18px">
    <div class="cx-movers">
        <div class="cx-movers-col">
            <h3 style="color:#fb7185">🚀 상승 TOP 5 <small style="font-size:11px;color:var(--text-light);font-weight:500;margin-left:6px">24h</small></h3>
            <div id="cxMoversUp"><div style="padding:14px;color:var(--text-light);font-size:13px;text-align:center">불러오는 중...</div></div>
        </div>
        <div class="cx-movers-col">
            <h3 style="color:#60a5fa">🔻 하락 TOP 5 <small style="font-size:11px;color:var(--text-light);font-weight:500;margin-left:6px">24h</small></h3>
            <div id="cxMoversDown"><div style="padding:14px;color:var(--text-light);font-size:13px;text-align:center">불러오는 중...</div></div>
        </div>
    </div>
</section>
<script>
(function () {
    function fmtPrice(p) {
        p = Number(p);
        if (p >= 1000) return p.toLocaleString('ko-KR', { maximumFractionDigits: 0 });
        if (p >= 1)    return p.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
        return p.toLocaleString('ko-KR', { maximumFractionDigits: 6 });
    }
    function rowHtml(items, cls) {
        if (!items || !items.length) return '<div style="padding:14px;color:var(--text-light);font-size:13px">데이터 없음</div>';
        return items.map(function (t, i) {
            var sym = (t.market || '').split('-')[1] || '';
            var name = t.korean_name || sym;
            var rate = (t.signed_change_rate || 0) * 100;
            var sign = rate >= 0 ? '+' : '';
            return '<a class="cx-mover-row" href="<?= nb_url("coin") ?>/' + t.market + '">' +
                '<span class="cx-mover-rank">' + (i + 1) + '</span>' +
                '<span class="cx-mover-name"><b>' + sym + '</b>' + name + '</span>' +
                '<span class="cx-mover-price">' + fmtPrice(t.trade_price) + '원</span>' +
                '<span class="cx-mover-rate ' + cls + '">' + sign + rate.toFixed(2) + '%</span>' +
                '</a>';
        }).join('');
    }
    function refresh() {
        fetch('<?= nb_url("api/movers") ?>', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) return;
                document.getElementById('cxMoversUp').innerHTML = rowHtml(j.up, 'up');
                document.getElementById('cxMoversDown').innerHTML = rowHtml(j.down, 'down');
            }).catch(function () {});
    }
    refresh();
    setInterval(refresh, 30000);
})();
</script>

<!-- 사이드 카드 스파크라인 -->
<script>
(function () {
    function drawSpark(canvas, prices, cls) {
        if (!prices || prices.length < 2) return;
        const dpr = window.devicePixelRatio || 1;
        const W = canvas.offsetWidth, H = canvas.offsetHeight;
        canvas.width = W * dpr; canvas.height = H * dpr;
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr); ctx.clearRect(0,0,W,H);
        const min = Math.min.apply(null, prices), max = Math.max.apply(null, prices);
        const pad = (max - min) * 0.15 || 1;
        const lo = min - pad, hi = max + pad;
        const x = i => (W - 2) * i / (prices.length - 1) + 1;
        const y = p => H - 2 - (H - 4) * (p - lo) / (hi - lo);
        const color = cls === 'up' ? '#ff5577' : (cls === 'down' ? '#4499ff' : '#7fa3c5');
        const grad = ctx.createLinearGradient(0,0,0,H);
        grad.addColorStop(0, color + '33');
        grad.addColorStop(1, color + '00');
        ctx.beginPath();
        prices.forEach((p,i) => i===0 ? ctx.moveTo(x(i), y(p)) : ctx.lineTo(x(i), y(p)));
        ctx.lineTo(x(prices.length-1), H); ctx.lineTo(x(0), H); ctx.closePath();
        ctx.fillStyle = grad; ctx.fill();
        ctx.beginPath();
        prices.forEach((p,i) => i===0 ? ctx.moveTo(x(i), y(p)) : ctx.lineTo(x(i), y(p)));
        ctx.strokeStyle = color; ctx.lineWidth = 1.6; ctx.stroke();
    }
    function init() {
        document.querySelectorAll('.btc-hero-side').forEach(function (card) {
            const canvas = card.querySelector('.btc-hero-side-spark');
            const cls = card.dataset.cls;
            try {
                const prices = JSON.parse(card.dataset.spark || '[]');
                drawSpark(canvas, prices, cls);
            } catch (e) {}
        });
    }
    init();
    window.addEventListener('resize', init);
})();
</script>
<?php endif; endif; ?>

<!-- 메인 2단 레이아웃 -->
<div class="container">
    <div class="main-layout">

        <!-- ===== 왼쪽 메인 콘텐츠 ===== -->
        <div class="main-content">

            <?php if (nb_setting('main_section_gallery', '1') === '1'): ?>
            <?php
                $galleryPosts = Post::galleryPosts((int)nb_setting('main_count_gallery', '9'));
                $galleryBoards = array_filter($boards, function($b){ return ($b['board_type'] ?? 'normal') === 'gallery'; });
                $galleryTitle = '포토갤러리';
                if (!empty($galleryBoards)) {
                    $gBoard = reset($galleryBoards);
                    // 메뉴에서 해당 게시판에 연결된 메뉴명 찾기
                    if ($useCustomMenu) {
                        foreach ($menuTree as $_m) {
                            if (($_m['board_id'] ?? '') === $gBoard['board_id']) { $galleryTitle = $_m['title']; break; }
                            foreach ($_m['children'] ?? [] as $_c) {
                                if (($_c['board_id'] ?? '') === $gBoard['board_id']) { $galleryTitle = $_c['title']; break 2; }
                            }
                        }
                    } else {
                        $galleryTitle = $gBoard['title'];
                    }
                }
            ?>
            <!-- 이미지 갤러리 -->
            <div class="gallery-section">
                <?php if (!empty($galleryPosts)): ?>
                <div class="gallery-grid">
                    <?php foreach ($galleryPosts as $gp): ?>
                    <?php $gLink = !empty($gp['link1']) ? $gp['link1'] : nb_url("board/{$gp['board_id']}/{$gp['id']}"); ?>
                    <?php $gTarget = !empty($gp['link1']) ? ' target="_blank"' : ''; ?>
                    <a href="<?= nb_e($gLink) ?>"<?= $gTarget ?> class="gallery-item<?= !empty($gp['is_video']) ? ' is-video' : '' ?>">
                        <?php if ($gp['thumbnail']): ?>
                            <?php if (!empty($gp['is_video'])): ?>
                                <img src="<?= nb_e($gp['thumbnail']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <img src="<?= nb_url($gp['thumbnail']) ?>" alt="" loading="lazy">
                            <?php endif; ?>
                            <?php if (!empty($gp['is_video'])): ?>
                                <span class="gallery-play" aria-hidden="true">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/></svg>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="gallery-noimg">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state-msg">이미지 게시판에 글을 작성하면 여기에 표시됩니다.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (nb_setting('main_section_popular', '1') === '1'): ?>
            <!-- 탭: 인기글 / 최신글 / 최신댓글 -->
            <div class="tab-box">
                <div class="tab-header">
                    <button class="tab-btn active" onclick="switchTab(this,'tab-popular')">🔥 인기글</button>
                    <button class="tab-btn" onclick="switchTab(this,'tab-latest')">📝 최신글</button>
                    <button class="tab-btn" onclick="switchTab(this,'tab-comments')">💬 최신댓글</button>
                </div>

                <!-- 인기글 탭 -->
                <div class="tab-panel active" id="tab-popular">
                    <ol class="popular-list">
                        <?php foreach (array_slice($popularPosts, 0, (int)nb_setting('main_count_popular', '8')) as $i => $p): ?>
                        <li>
                            <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                            <a href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>" class="pop-title"><?= nb_e($p['title']) ?></a>
                            <span class="pop-meta">
                                <?php if (($p['vote_up'] ?? 0) > 0): ?><span class="pop-vote">▲<?= $p['vote_up'] ?></span><?php endif; ?>
                                <span class="pop-hit">👁 <?= number_format($p['hit'] ?? 0) ?></span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($popularPosts)): ?><li class="empty-row">등록된 게시글이 없습니다.</li><?php endif; ?>
                    </ol>
                </div>

                <!-- 최신글 탭 -->
                <div class="tab-panel" id="tab-latest">
                    <div class="latest-table">
                        <?php foreach (array_slice($latestPosts, 0, (int)nb_setting('main_count_popular', '8')) as $p): ?>
                        <div class="lt-row">
                            <a href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>" class="lt-title">
                                <?= nb_e($p['title']) ?>
                                <?php if ($p['comment_count'] > 0): ?><span class="lt-cmt">[<?= $p['comment_count'] ?>]</span><?php endif; ?>
                                <?php if (strtotime($p['created_at']) > strtotime('-3 hours')): ?><span class="icon-new">N</span><?php endif; ?>
                            </a>
                            <span class="lt-date"><?= date('H:i', strtotime($p['created_at'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($latestPosts)): ?><div class="lt-row empty-row">등록된 게시글이 없습니다.</div><?php endif; ?>
                    </div>
                </div>

                <!-- 최신댓글 탭 -->
                <div class="tab-panel" id="tab-comments">
                    <ul class="comment-list-widget">
                        <?php foreach (array_slice($latestComments, 0, (int)nb_setting('main_count_comments', '5')) as $c): ?>
                        <li>
                            <a href="<?= nb_url("board/{$c['board_id']}/{$c['post_id']}") ?>#comments">
                                <span class="clw-text"><?= nb_e(mb_strimwidth(strip_tags($c['content']), 0, 40, '...')) ?></span>
                                <span class="clw-post"><?= nb_e(mb_strimwidth($c['post_title'] ?? '', 0, 18, '..')) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($latestComments)): ?><li class="empty-row">댓글이 없습니다.</li><?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <?php if (nb_setting('main_section_boards', '1') === '1'): ?>
            <!-- 게시판별 최신글 카드 그리드 -->
            <?php if (!empty($boards)): ?>
            <?php
                // 실제 렌더링될 게시판만 미리 추려서 중간 지점 계산
                $renderBoards = [];
                foreach ($boards as $_rb) {
                    if (($_rb['board_type'] ?? 'normal') === 'gallery') continue;
                    $_bpCnt = DB::count(DB::getPrefix().'posts', 'board_id = ?', [$_rb['board_id']]);
                    if ($_bpCnt < 1) continue;
                    $renderBoards[] = $_rb;
                }
                $middleBanners = Banner::listByPosition('middle');
                $bottomBanners = Banner::listByPosition('bottom');
                $midIdx = $renderBoards ? (int)floor(count($renderBoards) / 2) - 1 : -1;
            ?>
            <div class="board-grid-wrap">
                <div class="section-title">📋 게시판</div>
                <div class="board-grid">
                    <?php foreach ($renderBoards as $_i => $board): ?>
                    <div class="board-card">
                        <div class="board-card-header">
                            <a href="<?= nb_url("board/{$board['board_id']}") ?>" class="board-card-title"><?= nb_e($board['title']) ?></a>
                            <a href="<?= nb_url("board/{$board['board_id']}") ?>" class="board-card-more">더보기 →</a>
                        </div>
                        <ul class="post-list-mini">
                            <?php $recentPosts = Post::recentPosts((int)nb_setting('main_count_board', '5'), $board['board_id']); foreach ($recentPosts as $post): ?>
                            <li>
                                <a href="<?= nb_url("board/{$board['board_id']}/{$post['id']}") ?>">
                                    <span class="post-title"><?= nb_e($post['title']) ?></span>
                                    <?php if ($post['comment_count'] > 0): ?><span class="comment-count">[<?= $post['comment_count'] ?>]</span><?php endif; ?>
                                    <span class="post-date"><?= date('m.d', strtotime($post['created_at'])) ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                            <?php if (empty($recentPosts)): ?><li class="empty">등록된 글이 없습니다.</li><?php endif; ?>
                        </ul>
                    </div>
                    <?php if ($_i === $midIdx && !empty($middleBanners)): ?>
                        <?php foreach ($middleBanners as $mbn): ?>
                        <a href="<?= nb_e($mbn['link']) ?>" target="<?= nb_e($mbn['target']) ?>" class="board-inline-banner">
                            <img src="<?= nb_url($mbn['image']) ?>" alt="<?= nb_e($mbn['title']) ?>">
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($bottomBanners as $bbn): ?>
                    <a href="<?= nb_e($bbn['link']) ?>" target="<?= nb_e($bbn['target']) ?>" class="board-inline-banner">
                        <img src="<?= nb_url($bbn['image']) ?>" alt="<?= nb_e($bbn['title']) ?>">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- 중앙 위젯 -->
            
        </div><!-- /main-content -->

        <!-- ===== 우측 사이드바 ===== -->
        <aside class="main-sidebar">

            <!-- 로그인 / 내정보 -->
            <div class="side-box myinfo-box-wrap">
                <?php if (Auth::check()): ?>
                    <div class="side-box-title">👤 내 정보</div>
                    <div class="side-box-body myinfo-box">
                        <div class="myinfo-avatar"><?php if (!empty(Auth::user()['profile_image'])): ?><img src="<?= nb_url(Auth::user()['profile_image']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: ?><?= strtoupper(mb_substr(Auth::user()['nickname'], 0, 1)) ?><?php endif; ?></div>
                        <div class="myinfo-name"><?= nb_level_icon(Auth::level()) ?> <strong><?= nb_e(Auth::user()['nickname']) ?></strong>님</div>
                        <div class="myinfo-stats">
                            <div class="myinfo-stat-item"><span class="stat-val">Lv.<?= Auth::level() ?></span><span class="stat-lbl">레벨</span></div>
                            <div class="myinfo-stat-item"><span class="stat-val"><?= number_format(Auth::user()['point'] ?? 0) ?></span><span class="stat-lbl">포인트</span></div>
                        </div>
                        <div class="myinfo-links">
                            <a href="<?= nb_url('profile') ?>">내정보</a>
                            <a href="<?= nb_url('messages') ?>" style="position:relative">
                                쪽지
                                <?php $_unread = Message::unreadCount(Auth::id()); if ($_unread > 0): ?>
                                <span style="position:absolute;top:-4px;right:-6px;background:#dc2626;color:#fff;border-radius:8px;font-size:10px;padding:0 3px;font-weight:700;line-height:16px"><?= $_unread ?></span>
                                <?php endif; ?>
                            </a>
                            <?php if (Auth::isAdmin()): ?><a href="<?= nb_url('admin/') ?>">관리자</a><?php endif; ?>
                            <a href="<?= nb_url('logout') ?>">로그아웃</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="side-box-title">🔐 로그인</div>
                    <div class="side-box-body">
                        <form method="post" action="<?= nb_url('login') ?>">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="redirect" value="<?= nb_e($_SERVER['REQUEST_URI']) ?>">
                            <input type="text" name="user_id" placeholder="아이디" required class="side-input">
                            <input type="password" name="password" placeholder="비밀번호" required class="side-input">
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#94a3b8;margin:6px 0 10px;cursor:pointer"><input type="checkbox" name="remember" value="1"> 자동로그인</label>
                            <button type="submit" class="btn btn-primary btn-full">로그인</button>
                            <div class="side-login-links">
                                <a href="<?= nb_url('register') ?>">회원가입</a>
                            </div>
                        </form>
                        <?php if (nb_setting('social_login_enabled') === '1'): ?>
                        <div class="side-social">
                            <?php $hk=!empty(nb_setting('kakao_client_id'));$hn=!empty(nb_setting('naver_client_id'));$hg=!empty(nb_setting('google_client_id')); ?>
                            <a href="<?= $hk?nb_url('oauth/kakao'):'#' ?>" <?= !$hk?'onclick="alert(\'카카오 로그인 미설정\');return false;"':'' ?> title="카카오 로그인"><img src="<?= nb_asset('img/kakao.png') ?>" width="36" height="36" style="border-radius:8px"></a>
                            <a href="<?= $hn?nb_url('oauth/naver'):'#' ?>" <?= !$hn?'onclick="alert(\'네이버 로그인 미설정\');return false;"':'' ?> title="네이버 로그인"><img src="<?= nb_asset('img/naver.png') ?>" width="36" height="36" style="border-radius:8px"></a>
                            <a href="<?= $hg?nb_url('oauth/google'):'#' ?>" <?= !$hg?'onclick="alert(\'구글 로그인 미설정\');return false;"':'' ?> title="구글 로그인"><img src="<?= nb_asset('img/google.png') ?>" width="36" height="36" style="border-radius:8px"></a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (nb_setting('main_section_notice', '1') === '1'): ?>
            <!-- 운영 공지 -->
            <div class="side-box">
                <div class="side-box-title">📢 운영 공지</div>
                <ul class="side-list">
                    <?php
                    $notices = DB::fetchAll("SELECT id, board_id, title FROM {$prefix}posts WHERE is_notice = 1 ORDER BY id DESC LIMIT 5");
                    foreach ($notices as $n): ?>
                        <li><a href="<?= nb_url("board/{$n['board_id']}/{$n['id']}") ?>"><?= nb_e(mb_strimwidth($n['title'], 0, 22, '...')) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (empty($notices)): ?><li style="color:var(--text-light);font-size:12px;padding:8px 14px">공지사항이 없습니다.</li><?php endif; ?>
                </ul>
            </div>

            <?php endif; ?>

            <?php if (nb_setting('main_section_bestmember', '1') === '1'): ?>
            <!-- 랭킹 -->
            <?php
                $topByLevel = DB::fetchAll("SELECT nickname, level, point, profile_image FROM {$prefix}members ORDER BY level DESC, point DESC LIMIT 10");
                $topByPoint = DB::fetchAll("SELECT nickname, level, point, profile_image FROM {$prefix}members ORDER BY point DESC LIMIT 10");
            ?>
            <div class="side-box">
                <div class="ranking-tabs">
                    <button class="ranking-tab active" onclick="switchRanking(this,'rank-level')">레벨 랭킹</button>
                    <button class="ranking-tab" onclick="switchRanking(this,'rank-point')">포인트 랭킹</button>
                </div>
                <!-- 레벨 랭킹 -->
                <ol class="best-members ranking-panel active" id="rank-level">
                    <?php foreach ($topByLevel as $i => $tm): ?>
                    <li>
                        <span class="bm-rank"><?= $i + 1 ?></span>
                        <?php if (!empty($tm['profile_image'])): ?>
                            <img src="<?= nb_url($tm['profile_image']) ?>" style="width:20px;height:20px;border-radius:50%;object-fit:cover;vertical-align:middle">
                        <?php else: ?>
                            <span style="display:inline-flex;width:20px;height:20px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#7c3aed);color:#fff;font-size:9px;font-weight:700;align-items:center;justify-content:center;vertical-align:middle"><?= strtoupper(mb_substr($tm['nickname'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <?= nb_level_icon($tm['level']) ?> <?= nb_e($tm['nickname']) ?>
                        <span class="bm-point">Lv.<?= $tm['level'] ?></span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($topByLevel)): ?><li style="color:var(--text-light);font-size:12px;padding:8px 14px">회원이 없습니다.</li><?php endif; ?>
                </ol>
                <!-- 포인트 랭킹 -->
                <ol class="best-members ranking-panel" id="rank-point" style="display:none">
                    <?php foreach ($topByPoint as $i => $tm): ?>
                    <li>
                        <span class="bm-rank"><?= $i + 1 ?></span>
                        <?php if (!empty($tm['profile_image'])): ?>
                            <img src="<?= nb_url($tm['profile_image']) ?>" style="width:20px;height:20px;border-radius:50%;object-fit:cover;vertical-align:middle">
                        <?php else: ?>
                            <span style="display:inline-flex;width:20px;height:20px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#7c3aed);color:#fff;font-size:9px;font-weight:700;align-items:center;justify-content:center;vertical-align:middle"><?= strtoupper(mb_substr($tm['nickname'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <?= nb_level_icon($tm['level']) ?> <?= nb_e($tm['nickname']) ?>
                        <span class="bm-point"><?= number_format($tm['point']) ?>P</span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($topByPoint)): ?><li style="color:var(--text-light);font-size:12px;padding:8px 14px">회원이 없습니다.</li><?php endif; ?>
                </ol>
            </div>

            <?php endif; ?>

            <?php if (nb_setting('main_section_attendance', '1') === '1'): ?>
            <!-- 출석체크 -->
            <div class="side-box">
                <div class="side-box-title">출석체크</div>
                <div style="padding:12px 14px;text-align:center">
                    <?php if (Auth::check()): ?>
                    <?php
                    $atdToday = DB::fetch("SELECT id FROM " . DB::getPrefix() . "attendance WHERE member_id = ? AND attend_date = CURDATE()", [Auth::id()]);
                    ?>
                    <?php if ($atdToday): ?>
                        <div style="font-size:14px;color:#059669;font-weight:600;margin-bottom:6px">오늘 출석 완료!</div>
                    <?php else: ?>
                        <div style="font-size:13px;color:#475569;margin-bottom:8px">오늘 아직 출석 안했어요!</div>
                    <?php endif; ?>
                    <a href="<?= nb_url('attendance') ?>" class="btn btn-primary btn-full" style="font-size:13px"><?= $atdToday ? '출석 현황 보기' : '출석하기' ?></a>
                    <?php else: ?>
                        <div style="font-size:13px;color:#475569;margin-bottom:8px">출석하고 포인트를 받아보세요!</div>
                        <a href="<?= nb_url('attendance') ?>" class="btn btn-primary btn-full" style="font-size:13px">출석체크 하러 가기</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (nb_setting('main_section_latestlist') === '1'): ?>
            <!-- 최신글 -->
            <div class="side-box">
                <div class="side-box-title">📝 최신글</div>
                <ul class="side-list">
                    <?php foreach (array_slice($latestPosts, 0, (int)nb_setting('main_count_latest', '8')) as $lp): ?>
                    <li>
                        <a href="<?= nb_url("board/{$lp['board_id']}/{$lp['id']}") ?>">
                            <?= nb_e(mb_strimwidth($lp['title'], 0, 22, '...')) ?>
                            <?php if ($lp['comment_count'] > 0): ?><span style="color:var(--primary);font-size:11px">[<?= $lp['comment_count'] ?>]</span><?php endif; ?>
                            <?php if (strtotime($lp['created_at']) > strtotime('-3 hours')): ?><span class="icon-new">N</span><?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($latestPosts)): ?><li style="color:var(--text-light);font-size:12px;padding:8px 14px">게시글이 없습니다.</li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (nb_setting('main_section_recentcomments', '1') === '1'): ?>
            <!-- 최근 댓글 -->
            <div class="side-box">
                <div class="side-box-title">💬 최근 댓글</div>
                <ul class="side-comments">
                    <?php foreach (array_slice($latestComments, 0, (int)nb_setting('main_count_comments', '5')) as $rc): ?>
                    <li>
                        <a href="<?= nb_url("board/{$rc['board_id']}/{$rc['post_id']}") ?>#comments">
                            <span class="sc-text"><?= nb_e(mb_strimwidth(strip_tags($rc['content']), 0, 30, '...')) ?></span>
                            <span class="sc-post"><?= nb_e(mb_strimwidth($rc['post_title'] ?? '', 0, 18, '..')) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($latestComments)): ?><li class="sc-empty">댓글이 없습니다.</li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (nb_setting('main_section_stats', '1') === '1'): ?>
            <!-- 커뮤니티 현황 V2 -->
            <?php
                $icoChart  = nb_asset('img/chart_increasing_3d.png');
                $icoPeople = nb_asset('img/busts_in_silhouette_3d.png');
                $icoMemo   = nb_asset('img/memo_3d.png');
                $icoChat   = nb_asset('img/speech_balloon_3d.png');
                $icoScroll = nb_asset('img/scroll_3d.png');
            ?>
            <div class="stats-box-v2">
                <div class="stats-v2-title">
                    <img src="<?= $icoChart ?>" alt="" class="stats-v2-title-icon"> 커뮤니티 현황
                </div>

                <!-- 상단 큰 카드 2개 -->
                <div class="stats-v2-hero">
                    <div class="stats-v2-hero-card stats-v2-live">
                        <div class="stats-v2-hero-label">현재 접속자</div>
                        <div class="stats-v2-hero-body">
                            <span class="stats-v2-live-badge"><span class="stats-v2-live-dot"></span>Live</span>
                            <div class="stats-v2-hero-num"><?= number_format($onlineCount) ?> <small>명</small></div>
                        </div>
                        <div class="stats-v2-hero-sub">Live now</div>
                    </div>
                    <div class="stats-v2-hero-card stats-v2-joined">
                        <div class="stats-v2-hero-label">전체 회원</div>
                        <div class="stats-v2-hero-body">
                            <img src="<?= $icoPeople ?>" alt="" class="stats-v2-hero-icon">
                            <div class="stats-v2-hero-num"><?= number_format($totalMembers) ?> <small>명</small></div>
                        </div>
                        <div class="stats-v2-hero-sub">Joined</div>
                    </div>
                </div>

                <!-- 중간 카드 2개 -->
                <div class="stats-v2-mid">
                    <div class="stats-v2-mid-card">
                        <div class="stats-v2-mid-label">오늘 새 글</div>
                        <div class="stats-v2-mid-body">
                            <img src="<?= $icoMemo ?>" alt="" class="stats-v2-mid-icon">
                            <div class="stats-v2-mid-num"><?= number_format($todayPosts) ?> <small>개</small></div>
                        </div>
                        <div class="stats-v2-mid-sub">Posts</div>
                    </div>
                    <div class="stats-v2-mid-card">
                        <div class="stats-v2-mid-label">오늘 새 댓글</div>
                        <div class="stats-v2-mid-body">
                            <img src="<?= $icoChat ?>" alt="" class="stats-v2-mid-icon">
                            <div class="stats-v2-mid-num"><?= number_format($todayComments) ?> <small>개</small></div>
                        </div>
                        <div class="stats-v2-mid-sub">Comments</div>
                    </div>
                </div>

                <!-- 하단 리스트 3줄 -->
                <div class="stats-v2-list">
                    <div class="stats-v2-list-row">
                        <img src="<?= $icoScroll ?>" alt="" class="stats-v2-list-icon">
                        <span class="stats-v2-list-name">전체 게시물</span>
                        <span class="stats-v2-list-val"><?= number_format($totalPosts) ?> 개</span>
                    </div>
                    <div class="stats-v2-list-row">
                        <img src="<?= $icoChat ?>" alt="" class="stats-v2-list-icon">
                        <span class="stats-v2-list-name">전체 댓글</span>
                        <span class="stats-v2-list-val"><?= number_format($totalComments) ?> 개</span>
                    </div>
                    <div class="stats-v2-list-row">
                        <img src="<?= $icoPeople ?>" alt="" class="stats-v2-list-icon">
                        <span class="stats-v2-list-name">전체 회원</span>
                        <span class="stats-v2-list-val"><?= number_format($totalMembers) ?> 명</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 우측 배너 -->
            <?php $rightBanners = Banner::listByPosition('right'); foreach ($rightBanners as $bn): ?>
                <a href="<?= nb_e($bn['link']) ?>" target="<?= nb_e($bn['target']) ?>" class="side-banner"><img src="<?= nb_url($bn['image']) ?>" alt="<?= nb_e($bn['title']) ?>"></a>
            <?php endforeach; ?>

            <!-- 우측 위젯 -->
            
        </aside>

    </div><!-- /main-layout -->
</div>

<script>
// 탭 전환
function switchTab(btn, panelId) {
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById(panelId).classList.add('active');
}

// 배너 슬라이더
(function() {
    var slides = document.querySelectorAll('.mbs-slide');
    var dots   = document.querySelectorAll('.mbs-dot');
    if (slides.length <= 1) return;
    var cur = 0;
    window.goSlide = function(i) {
        slides[cur].classList.remove('active');
        dots[cur] && dots[cur].classList.remove('active');
        cur = i;
        slides[cur].classList.add('active');
        dots[cur] && dots[cur].classList.add('active');
    };
    setInterval(function() { goSlide((cur + 1) % slides.length); }, 4000);
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>

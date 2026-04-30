<?php
/**
 * 코인 마켓 플러그인
 *
 * - /coin               : 시세 목록 (KRW/BTC 마켓)
 * - /coin/{market}      : 코인 상세 (예: KRW-BTC)
 * - /coin/api/tickers   : JSON 티커 (헤더 자동갱신용)
 * - /coin/api/markets   : JSON 마켓 목록
 *
 * 데이터 소스: 업비트 공개 API (인증 불필요)
 *   - https://api.upbit.com/v1/market/all
 *   - https://api.upbit.com/v1/ticker?markets=...
 *   - https://api.upbit.com/v1/candles/days
 */

const CM_API_BASE       = 'https://api.upbit.com/v1';
const CM_CACHE_DIR      = __DIR__ . '/cache';
const CM_TICKER_TTL     = 30;     // 30초
const CM_MARKETS_TTL    = 86400;  // 1일
const CM_CANDLES_TTL    = 300;    // 5분

if (!is_dir(CM_CACHE_DIR)) @mkdir(CM_CACHE_DIR, 0755, true);

// ========== 캐시 헬퍼 ==========
function cm_cache_get(string $key, int $ttl): ?array {
    $f = CM_CACHE_DIR . '/' . md5($key) . '.json';
    if (!is_file($f)) return null;
    if ((time() - filemtime($f)) > $ttl) return null;
    $raw = @file_get_contents($f);
    $data = json_decode($raw ?: 'null', true);
    return is_array($data) ? $data : null;
}

function cm_cache_put(string $key, array $data): void {
    $f = CM_CACHE_DIR . '/' . md5($key) . '.json';
    @file_put_contents($f, json_encode($data, JSON_UNESCAPED_UNICODE));
}

// ========== 업비트 API 호출 ==========
function cm_fetch(string $path, int $ttl = 30): ?array {
    $key = $path;
    $cached = cm_cache_get($key, $ttl);
    if ($cached !== null) return $cached;

    $url = CM_API_BASE . $path;
    $ctx = stream_context_create([
        'http' => [
            'timeout'    => 5,
            'user_agent' => 'NuriBoard-CryptoMarket/1.0',
            'header'     => "Accept: application/json\r\n",
        ],
        'ssl'  => ['verify_peer' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        // 실패 시 stale cache 사용
        $stale = cm_cache_get($key, PHP_INT_MAX);
        return $stale;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;
    cm_cache_put($key, $data);
    return $data;
}

function cm_markets(string $quote = 'KRW'): array {
    $all = cm_fetch('/market/all?isDetails=false', CM_MARKETS_TTL) ?? [];
    return array_values(array_filter($all, function ($m) use ($quote) {
        return strpos($m['market'] ?? '', $quote . '-') === 0;
    }));
}

function cm_tickers(array $markets): array {
    if (!$markets) return [];
    // 유효한 마켓만 통과 — 폐지/오타된 마켓 한 개로 전체 호출이 실패하는 것 방지
    static $validKrw = null;
    if ($validKrw === null) {
        $all = cm_fetch('/market/all?isDetails=false', CM_MARKETS_TTL) ?? [];
        $validKrw = array_flip(array_column(array_filter($all, fn($m) => strpos($m['market'] ?? '', 'KRW-') === 0), 'market'));
    }
    $markets = array_values(array_filter($markets, fn($m) => isset($validKrw[$m]) || strpos($m, 'KRW-') !== 0));
    if (!$markets) return [];
    $param = implode(',', $markets);
    return cm_fetch('/ticker?markets=' . $param, CM_TICKER_TTL) ?? [];
}

function cm_candles_days(string $market, int $count = 30): array {
    return cm_fetch("/candles/days?market={$market}&count={$count}", CM_CANDLES_TTL) ?? [];
}

// ========== 포맷터 ==========
function cm_fmt_price($p): string {
    if ($p === null) return '-';
    $p = (float)$p;
    if ($p >= 1000) return number_format($p, 0);
    if ($p >= 100)  return number_format($p, 1);
    if ($p >= 1)    return number_format($p, 2);
    if ($p >= 0.01) return number_format($p, 4);
    return number_format($p, 8);
}
function cm_fmt_volume($v): string {
    $v = (float)$v;
    if ($v >= 1e12) return number_format($v / 1e12, 2) . '조';
    if ($v >= 1e8)  return number_format($v / 1e8,  2) . '억';
    if ($v >= 1e4)  return number_format($v / 1e4,  2) . '만';
    return number_format($v, 0);
}
function cm_fmt_pct($r): string {
    $r = (float)$r * 100;
    $s = $r >= 0 ? '+' : '';
    return $s . number_format($r, 2) . '%';
}

// ========== 라우트 등록 ==========
// 관리자 페이지(admin/common.php)는 Router 클래스를 로드하지 않으므로 가드
if (class_exists('Router')):

// 메인 시세 목록
Router::get('/coin', function () {
    $quote = ($_GET['quote'] ?? 'KRW') === 'BTC' ? 'BTC' : 'KRW';
    $sort  = $_GET['sort']  ?? 'volume';
    $q     = trim($_GET['q'] ?? '');

    $markets = cm_markets($quote);
    $codes   = array_map(fn($m) => $m['market'], $markets);
    // 업비트는 100개씩 제한 권장 -> 청크
    $rows = [];
    foreach (array_chunk($codes, 100) as $chunk) {
        foreach (cm_tickers($chunk) as $t) $rows[] = $t;
    }
    // market -> 한글명 매핑
    $nameMap = [];
    foreach ($markets as $m) $nameMap[$m['market']] = $m;

    foreach ($rows as &$r) {
        $info = $nameMap[$r['market']] ?? [];
        $r['korean_name']  = $info['korean_name']  ?? '';
        $r['english_name'] = $info['english_name'] ?? '';
    }
    unset($r);

    // 검색
    if ($q !== '') {
        $needle = mb_strtolower($q);
        $rows = array_values(array_filter($rows, function ($r) use ($needle) {
            return mb_strpos(mb_strtolower($r['market']),       $needle) !== false
                || mb_strpos(mb_strtolower($r['korean_name']),  $needle) !== false
                || mb_strpos(mb_strtolower($r['english_name']), $needle) !== false;
        }));
    }

    // 정렬
    $cmp = [
        'volume' => fn($a, $b) => ($b['acc_trade_price_24h'] ?? 0) <=> ($a['acc_trade_price_24h'] ?? 0),
        'change' => fn($a, $b) => ($b['signed_change_rate'] ?? 0) <=> ($a['signed_change_rate'] ?? 0),
        'fall'   => fn($a, $b) => ($a['signed_change_rate'] ?? 0) <=> ($b['signed_change_rate'] ?? 0),
        'price'  => fn($a, $b) => ($b['trade_price'] ?? 0) <=> ($a['trade_price'] ?? 0),
        'name'   => fn($a, $b) => strcmp($a['market'] ?? '', $b['market'] ?? ''),
    ][$sort] ?? null;
    if ($cmp) usort($rows, $cmp);

    SEO::setTitle('코인 시세');
    SEO::setDescription('업비트 실시간 코인 시세, 변동률, 거래대금을 한눈에.');

    require __DIR__ . '/views/list.php';
});

// 코인 상세
Router::get('/coin/{market}', function ($params) {
    $market = strtoupper($params['market']);
    if (!preg_match('/^(KRW|BTC|USDT)-[A-Z0-9]+$/', $market)) {
        http_response_code(404);
        Router::loadTheme('error/404');
        return;
    }
    $tickers = cm_tickers([$market]);
    if (empty($tickers)) {
        http_response_code(404);
        Router::loadTheme('error/404');
        return;
    }
    $ticker = $tickers[0];

    // 마켓 메타
    [$quote, $base] = explode('-', $market);
    $allMarkets = cm_markets($quote);
    $info = [];
    foreach ($allMarkets as $m) {
        if ($m['market'] === $market) { $info = $m; break; }
    }
    $candles = cm_candles_days($market, 30);

    SEO::setTitle(($info['korean_name'] ?? $market) . ' 시세');
    SEO::setDescription(($info['korean_name'] ?? $market) . ' 실시간 가격, 차트, 거래대금');

    require __DIR__ . '/views/detail.php';
});

// JSON API: 헤더 티커
Router::get('/coin/api/tickers', function () {
    $markets = explode(',', $_GET['markets'] ?? 'KRW-BTC,KRW-ETH,KRW-XRP,KRW-SOL,KRW-DOGE');
    $markets = array_slice(array_filter(array_map('trim', $markets)), 0, 30);
    $rows = cm_tickers($markets);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=10');
    echo json_encode(['ok' => true, 'tickers' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
});

// JSON API: 마켓 목록
Router::get('/coin/api/markets', function () {
    $quote = ($_GET['quote'] ?? 'KRW') === 'BTC' ? 'BTC' : 'KRW';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo json_encode(['ok' => true, 'markets' => cm_markets($quote)], JSON_UNESCAPED_UNICODE);
    exit;
});

endif; // class_exists('Router')

// ========== 헤더 시세 티커 (모든 페이지 상단) ==========
// ticker.css는 모든 페이지에 로드 - after_header 훅이 모든 페이지에서 티커를 렌더링하므로
// 관리자에서는 ticker가 안 보이지만 안전하게 가드
if (function_exists('nb_url')) {
    Plugin::queueHeaderAsset('<link rel="stylesheet" href="' . nb_url('plugins/crypto-market/assets/ticker.css') . '?v=' . filemtime(__DIR__ . '/assets/ticker.css') . '">');
}

Plugin::addHook('after_header', function () {
    $defaultMarkets = 'KRW-BTC,KRW-ETH,KRW-XRP,KRW-SOL,KRW-DOGE,KRW-ADA,KRW-TRX,KRW-AVAX,KRW-LINK,KRW-DOT,KRW-ATOM,KRW-NEAR,KRW-APT,KRW-ARB,KRW-SUI,KRW-INJ,KRW-SEI,KRW-PEPE,KRW-SHIB,KRW-BCH,KRW-ETC,KRW-HBAR,KRW-ALGO';
    ?>
    <div class="cm-ticker-bar" id="cmTickerBar" data-markets="<?= htmlspecialchars($defaultMarkets) ?>">
        <a href="<?= nb_url('coin') ?>" class="cm-ticker-label">실시간 시세</a>
        <div class="cm-ticker-track" id="cmTickerTrack"><span class="cm-ticker-loading">불러오는 중...</span></div>
    </div>
    <script>
    (function () {
        const bar   = document.getElementById('cmTickerBar');
        const track = document.getElementById('cmTickerTrack');
        if (!bar || !track) return;
        const markets = bar.dataset.markets || 'KRW-BTC';
        const url = '<?= nb_url("coin/api/tickers") ?>?markets=' + encodeURIComponent(markets);

        function fmt(n) {
            n = Number(n);
            if (n >= 1000) return n.toLocaleString('ko-KR', {maximumFractionDigits: 0});
            if (n >= 1)    return n.toLocaleString('ko-KR', {maximumFractionDigits: 2});
            return n.toLocaleString('ko-KR', {maximumFractionDigits: 6});
        }

        async function refresh() {
            try {
                const r = await fetch(url, {cache: 'no-store'});
                const j = await r.json();
                if (!j.ok) return;
                const items = j.tickers.map(function (t) {
                    const code = (t.market || '').split('-')[1] || '';
                    const rate = (t.signed_change_rate || 0) * 100;
                    const cls  = rate >= 0 ? 'up' : 'down';
                    const sign = rate >= 0 ? '+' : '';
                    return '<a class="cm-ticker-item ' + cls + '" href="<?= nb_url("coin") ?>/' + t.market + '">' +
                        '<b>' + code + '</b> ' +
                        '<span class="cm-ticker-price">' + fmt(t.trade_price) + '</span> ' +
                        '<span class="cm-ticker-rate">' + sign + rate.toFixed(2) + '%</span></a>';
                }).join('');
                // 무한 스크롤 효과를 위해 두 번 반복
                track.innerHTML = items + items;
            } catch (e) { /* 무시 */ }
        }
        refresh();
        setInterval(refresh, 30000);
    })();
    </script>
    <?php
});

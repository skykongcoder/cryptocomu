<?php
/**
 * 크립토 부가 기능 플러그인
 *
 * 라우트:
 *   /portfolio  포트폴리오 트래커 (localStorage)
 *   /news       코인 뉴스 (CryptoCompare)
 *   /events     이벤트 캘린더 (큐레이션)
 *   /glossary   코인 사전
 *   /predict    재미 가격 예측 (엔터테인먼트)
 *   /api/news        JSON: 뉴스 페치 + 캐시
 *   /api/movers      JSON: 상승/하락 TOP 5
 *   /api/predict     JSON: 재미 가격 예측 결과
 */

require_once __DIR__ . '/../crypto-market/plugin.php';   // cm_tickers, cm_markets, cm_fetch 등 재사용
require_once __DIR__ . '/../_openrouter_models.php';      // CA bundle

const CX_NEWS_TTL  = 1800;  // 30분
const CX_CACHE_DIR = __DIR__ . '/cache';
if (!is_dir(CX_CACHE_DIR)) @mkdir(CX_CACHE_DIR, 0755, true);

// ========== 캐시 헬퍼 ==========
function cx_cache_get(string $key, int $ttl): ?array {
    $f = CX_CACHE_DIR . '/' . md5($key) . '.json';
    if (!is_file($f)) return null;
    if ((time() - filemtime($f)) > $ttl) return null;
    $raw = @file_get_contents($f);
    $data = json_decode($raw ?: 'null', true);
    return is_array($data) ? $data : null;
}
function cx_cache_put(string $key, array $data): void {
    $f = CX_CACHE_DIR . '/' . md5($key) . '.json';
    @file_put_contents($f, json_encode($data, JSON_UNESCAPED_UNICODE));
}

// ========== 뉴스 페치 (한국어 RSS — TokenPost + CoinReaders) ==========
function cx_fetch_rss(string $url, string $sourceName): array {
    $ch = curl_init($url);
    if (function_exists('nb_ca_bundle') && ($ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'Mozilla/5.0 CryptoCommunity-NewsBot',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) return [];

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    if (!$xml) return [];

    $items = [];
    foreach (($xml->channel->item ?? []) as $item) {
        $description = (string)($item->description ?? '');
        // RSS description 안의 <img src=""> 추출
        $img = '';
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $m)) $img = $m[1];
        // media:content 도 시도
        $media = $item->children('media', true);
        if (!$img && isset($media->content)) $img = (string)$media->content->attributes()['url'];
        $items[] = [
            'title'       => trim((string)$item->title),
            'url'         => trim((string)$item->link),
            'image'       => $img,
            'source'      => $sourceName,
            'body'        => mb_substr(trim(strip_tags($description)), 0, 200),
            'published'   => strtotime((string)$item->pubDate) ?: 0,
            'categories'  => '',
        ];
    }
    return $items;
}

function cx_fetch_news(): array {
    $cached = cx_cache_get('news_v2', CX_NEWS_TTL);
    if ($cached !== null) return $cached;

    // 한국어 코인 뉴스 RSS 소스들
    $sources = [
        ['url' => 'https://www.tokenpost.kr/rss',   'name' => '토큰포스트'],
        ['url' => 'https://coinreaders.com/rss/all_news_section.xml', 'name' => '코인리더스'],
    ];

    $all = [];
    foreach ($sources as $src) {
        $items = cx_fetch_rss($src['url'], $src['name']);
        foreach ($items as $it) $all[] = $it;
    }

    // 최신순 정렬 (published 내림차순)
    usort($all, fn($a, $b) => ($b['published'] ?? 0) <=> ($a['published'] ?? 0));
    $all = array_slice($all, 0, 30);

    if ($all) cx_cache_put('news_v2', $all);
    return $all;
}

// ========== 상승/하락 TOP 5 ==========
function cx_movers(): array {
    if (!function_exists('cm_markets') || !function_exists('cm_tickers')) return ['up' => [], 'down' => []];

    $markets = cm_markets('KRW');
    $codes = array_map(fn($m) => $m['market'], $markets);
    $nameMap = [];
    foreach ($markets as $m) $nameMap[$m['market']] = $m['korean_name'] ?? '';

    $rows = [];
    foreach (array_chunk($codes, 100) as $chunk) {
        foreach (cm_tickers($chunk) as $t) $rows[] = $t;
    }
    // 의미 있는 거래대금 있는 코인만 (5천만원 이상)
    $rows = array_filter($rows, fn($r) => ($r['acc_trade_price_24h'] ?? 0) >= 5e7);
    foreach ($rows as &$r) $r['korean_name'] = $nameMap[$r['market']] ?? '';
    unset($r);

    // 변동률 정렬
    $up = $rows;
    $down = $rows;
    usort($up, fn($a, $b) => ($b['signed_change_rate'] ?? 0) <=> ($a['signed_change_rate'] ?? 0));
    usort($down, fn($a, $b) => ($a['signed_change_rate'] ?? 0) <=> ($b['signed_change_rate'] ?? 0));
    return [
        'up'   => array_slice($up, 0, 5),
        'down' => array_slice($down, 0, 5),
    ];
}

// ========== 🐋 고래 신호 탐지 ==========
// 업비트 ticker + 7일 candle 데이터로 거래량 급증·가격 급변동·신고가 돌파 신호 탐지
// 진짜 on-chain 고래 지갑 추적은 유료 API(Whale Alert) 필요해서, 우리는 거래소 단위 신호 탐지로 대체
function cx_detect_whales(): array {
    $cached = cx_cache_get('whales_v2', 60);   // 1분 캐시 (실시간성 향상)
    if ($cached !== null) return $cached;

    if (!function_exists('cm_markets') || !function_exists('cm_tickers') || !function_exists('cm_candles_days')) {
        return [];
    }

    $markets = cm_markets('KRW');
    $codes = array_map(fn($m) => $m['market'], $markets);
    $nameMap = [];
    foreach ($markets as $m) $nameMap[$m['market']] = $m['korean_name'] ?? '';

    // 거래대금 상위 코인만 분석 (의미있는 시장만)
    $tickers = [];
    foreach (array_chunk($codes, 100) as $chunk) {
        foreach (cm_tickers($chunk) as $t) $tickers[] = $t;
    }
    usort($tickers, fn($a, $b) => ($b['acc_trade_price_24h'] ?? 0) <=> ($a['acc_trade_price_24h'] ?? 0));
    $tickers = array_slice($tickers, 0, 80);   // 상위 80개만 분석 (API 호출 제한)

    $signals = [];
    foreach ($tickers as $t) {
        $market   = $t['market'];
        $today    = (float)($t['acc_trade_price_24h'] ?? 0);
        if ($today < 1e9) continue;   // 24h 거래대금 10억원 미만은 스킵

        $candles = cm_candles_days($market, 7);
        if (count($candles) < 5) continue;
        $weekVols = array_map(fn($c) => (float)($c['candle_acc_trade_price'] ?? 0), $candles);
        $weekAvg = array_sum($weekVols) / count($weekVols);
        if ($weekAvg < 1) continue;

        $multiple   = $today / $weekAvg;
        $changeRate = ((float)($t['signed_change_rate'] ?? 0)) * 100;
        $current    = (float)$t['trade_price'];
        $high52     = (float)($t['highest_52_week_price'] ?? 0);

        $type = null; $severity = 0; $desc = '';

        // 1) 52주 신고가 근접/돌파 (가장 강한 신호)
        if ($high52 > 0 && $current >= $high52 * 0.98) {
            $type = 'ATH'; $severity = 90;
            $desc = $current >= $high52 ? '🚀 52주 신고가 돌파!' : '📈 52주 신고가 근접 (98%)';
        }
        // 2) 폭등 (강한 매수 + 큰 폭 상승)
        elseif ($changeRate > 12 && $multiple > 3) {
            $type = 'PUMP'; $severity = min(100, (int)(70 + $changeRate));
            $desc = sprintf('🔥 +%.1f%% 폭등 · 거래량 %.1fx 급증', $changeRate, $multiple);
        }
        // 3) 폭락
        elseif ($changeRate < -12 && $multiple > 3) {
            $type = 'DUMP'; $severity = min(100, (int)(70 + abs($changeRate)));
            $desc = sprintf('🔻 %.1f%% 폭락 · 거래량 %.1fx 급증', $changeRate, $multiple);
        }
        // 4) 강한 매수 신호
        elseif ($changeRate > 5 && $multiple > 3) {
            $type = 'BUY'; $severity = min(100, (int)(35 + $changeRate * 3 + $multiple * 4));
            $desc = sprintf('💰 +%.1f%% 상승 · 거래량 %.1fx (매수세 강함)', $changeRate, $multiple);
        }
        // 5) 강한 매도 신호
        elseif ($changeRate < -5 && $multiple > 3) {
            $type = 'SELL'; $severity = min(100, (int)(35 + abs($changeRate) * 3 + $multiple * 4));
            $desc = sprintf('💸 %.1f%% 하락 · 거래량 %.1fx (매도세 강함)', $changeRate, $multiple);
        }
        // 6) 거래량만 급증 (방향 모호 — 관심 집중)
        elseif ($multiple > 5) {
            $type = 'ACCUM'; $severity = min(100, (int)(25 + $multiple * 5));
            $desc = sprintf('👀 거래량 %.1fx 급증 (시장 관심 집중)', $multiple);
        }
        else continue;

        $signals[] = [
            'market'        => $market,
            'name'          => $nameMap[$market] ?? '',
            'symbol'        => explode('-', $market)[1] ?? '',
            'type'          => $type,
            'severity'      => $severity,
            'desc'          => $desc,
            'price'         => $current,
            'change_rate'   => $changeRate,
            'volume_24h'    => $today,
            'vol_multiple'  => round($multiple, 2),
            'high_52w'      => $high52,
        ];
    }

    // 심각도 높은 순
    usort($signals, fn($a, $b) => $b['severity'] <=> $a['severity']);
    $signals = array_slice($signals, 0, 30);

    cx_cache_put('whales_v2', $signals);
    return $signals;
}

// ========== 재미 가격 예측 (엔터테인먼트 ONLY) ==========
// 실제 가격 예측 불가능. 단순한 통계적 추세 + 무작위 노이즈로 "운세" 식 결과 제공.
function cx_fun_predict(string $market = 'KRW-BTC', int $days = 30): array {
    $candles = function_exists('cm_candles_days') ? cm_candles_days($market, 60) : [];
    if (count($candles) < 10) return ['ok' => false, 'error' => '데이터 부족'];

    $candles = array_reverse($candles);  // 시간순
    $prices = array_map(fn($c) => (float)$c['trade_price'], $candles);
    $current = end($prices);

    // 단순 선형 회귀 (least squares)
    $n = count($prices);
    $sumX = $sumY = $sumXY = $sumX2 = 0;
    foreach ($prices as $i => $p) {
        $sumX += $i; $sumY += $p; $sumXY += $i * $p; $sumX2 += $i * $i;
    }
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;

    // 미래 예측 + 결정적 의사난수(코인별로 일관된 "운세")
    mt_srand(crc32($market . date('Y-m-d')));   // 같은 코인은 하루 동안 같은 예측
    $future = [];
    for ($i = 0; $i < $days; $i++) {
        $futureX = $n + $i;
        $base = $slope * $futureX + $intercept;
        // 노이즈: 가격의 ±3%
        $noise = (mt_rand(-300, 300) / 10000) * $current;
        $price = max(0, $base + $noise);
        $future[] = [
            'day'   => $i + 1,
            'date'  => date('Y-m-d', strtotime('+' . ($i + 1) . ' days')),
            'price' => $price,
        ];
    }

    $finalPrice = end($future)['price'];
    $changePct = ($finalPrice - $current) / max($current, 1) * 100;

    // 운세풍 메시지
    $messages = [
        ['threshold' =>  20, 'msg' => '🚀 별빛이 강합니다 — 큰 상승의 기운이 보입니다 (재미로만 보세요)'],
        ['threshold' =>  10, 'msg' => '🌟 긍정의 기운이 흐릅니다 — 따뜻한 햇살처럼'],
        ['threshold' =>   3, 'msg' => '☀️ 잔잔한 상승의 흐름이 느껴집니다'],
        ['threshold' =>  -3, 'msg' => '🌤 횡보의 시간 — 관망의 미덕'],
        ['threshold' => -10, 'msg' => '☁️ 차가운 바람의 기운 — 신중함이 필요'],
        ['threshold' => -20, 'msg' => '🌧 폭풍의 기운 — 흔들리지 마세요'],
        ['threshold' => -100,'msg' => '🌪 격동의 시기 — 그러나 새벽은 옵니다'],
    ];
    $message = '🌙 별의 기운이 모호합니다';
    foreach ($messages as $m) {
        if ($changePct >= $m['threshold']) { $message = $m['msg']; break; }
    }

    return [
        'ok'         => true,
        'market'     => $market,
        'current'    => $current,
        'final'      => $finalPrice,
        'change_pct' => $changePct,
        'days'       => $days,
        'history'    => array_map(fn($p, $i) => ['day' => $i - $n + 1, 'price' => $p], $prices, array_keys($prices)),
        'future'     => $future,
        'message'    => $message,
    ];
}

// ========== 라우트 등록 ==========
if (class_exists('Router')):

// 페이지 라우트들
Router::get('/portfolio', function () {
    SEO::setTitle('포트폴리오 트래커');
    SEO::setDescription('보유 코인의 실시간 손익을 자동으로 계산합니다.');
    require __DIR__ . '/views/portfolio.php';
});

Router::get('/news', function () {
    $news = cx_fetch_news();
    SEO::setTitle('코인 속보');
    SEO::setDescription('실시간 암호화폐 속보 — 토큰포스트, 코인리더스에서 자동 수집한 한국어 코인 뉴스.');
    require __DIR__ . '/views/news.php';
});

Router::get('/events', function () {
    SEO::setTitle('코인 이벤트 캘린더');
    SEO::setDescription('다가오는 ETF 승인, 하드포크, 상장 등 주요 코인 이벤트.');
    require __DIR__ . '/views/events.php';
});

Router::get('/glossary', function () {
    SEO::setTitle('암호화폐 용어 사전');
    SEO::setDescription('비트코인, DeFi, NFT, L2 등 코인 용어를 카테고리별로 정리.');
    require __DIR__ . '/views/glossary.php';
});

Router::get('/predict', function () {
    SEO::setTitle('재미로 보는 코인 운세');
    SEO::setDescription('재미로 보는 코인 가격 예측 — 투자 조언 아닙니다.');
    require __DIR__ . '/views/predict.php';
});

Router::get('/whales', function () {
    SEO::setTitle('🐋 고래 신호 탐지기');
    SEO::setDescription('업비트 거래량 급증·가격 급변동·신고가 돌파 등 시장의 큰 손 움직임을 자동 감지합니다.');
    require __DIR__ . '/views/whales.php';
});

// JSON API
Router::get('/api/movers', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=30');
    echo json_encode(['ok' => true] + cx_movers(), JSON_UNESCAPED_UNICODE);
    exit;
});

Router::get('/api/news', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=900');
    echo json_encode(['ok' => true, 'news' => cx_fetch_news()], JSON_UNESCAPED_UNICODE);
    exit;
});

Router::get('/api/whales', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=30');
    echo json_encode([
        'ok' => true,
        'whales' => cx_detect_whales(),
        'detected_at' => time(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

Router::get('/api/predict', function () {
    $market = strtoupper($_GET['market'] ?? 'KRW-BTC');
    if (!preg_match('/^(KRW|BTC|USDT)-[A-Z0-9]+$/', $market)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid market']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode(cx_fun_predict($market, (int)($_GET['days'] ?? 30)), JSON_UNESCAPED_UNICODE);
    exit;
});

endif; // class_exists('Router')

// ========== assets 자동 로드 ==========
if (function_exists('nb_url') && !defined('NB_ADMIN')) {
    Plugin::queueHeaderAsset(
        '<link rel="stylesheet" href="' . nb_url('plugins/crypto-extras/assets/extras.css') . '?v=' . filemtime(__DIR__ . '/assets/extras.css') . '">' .
        // 사이트 전역 고래 알림 — 모든 페이지에서 60초마다 새 신호 폴링 → 토스트 + 데스크톱 알림
        '<script defer src="' . nb_url('plugins/crypto-extras/assets/whale-alerts.js') . '?v=' . filemtime(__DIR__ . '/assets/whale-alerts.js') . '"></script>'
    );
}

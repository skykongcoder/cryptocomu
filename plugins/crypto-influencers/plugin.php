<?php
/**
 * 크립토 인플루언서 X 피드 플러그인
 *
 * 페치 폴백 체인:
 *   1. RSSHub 공개 인스턴스 (rsshub.app)
 *   2. RSSHub 미러 (rss.shab.fun, rsshub.atgw.io 등)
 *   3. Twitter 신디케이션 (syndication.twitter.com — 비공식)
 *
 * AI 번역: OpenRouter 무료 모델 (openai/gpt-oss-120b:free)
 *   - 캐시 24시간 (번역 결과는 변하지 않음)
 *
 * 라우트:
 *   /influencers          전체 피드
 *   /influencers/{user}   개별 인플루언서
 *   /api/influencers      JSON 피드
 *   /api/translate-tweet  특정 트윗 번역 (POST {text})
 */

require_once __DIR__ . '/../crypto-market/plugin.php';   // 안 쓰지만 종속성 로드
require_once __DIR__ . '/../_openrouter_models.php';      // CA bundle + OpenRouter

const CXI_FETCH_TTL    = 900;       // 15분 사용자 피드 캐시
const CXI_TRANS_TTL    = 86400;     // 24시간 번역 캐시
const CXI_CACHE_DIR    = __DIR__ . '/cache';
const CXI_STATE_FILE   = __DIR__ . '/cache/state.json';

if (!is_dir(CXI_CACHE_DIR)) @mkdir(CXI_CACHE_DIR, 0755, true);

// ========== 캐시 헬퍼 ==========
function cxi_cache_get(string $key, int $ttl): ?array {
    $f = CXI_CACHE_DIR . '/' . md5($key) . '.json';
    if (!is_file($f) || (time() - filemtime($f)) > $ttl) return null;
    $raw = @file_get_contents($f);
    $data = json_decode($raw ?: 'null', true);
    return is_array($data) ? $data : null;
}
function cxi_cache_put(string $key, array $data): void {
    @file_put_contents(CXI_CACHE_DIR . '/' . md5($key) . '.json',
                       json_encode($data, JSON_UNESCAPED_UNICODE));
}

// ========== HTTP 헬퍼 (UA + CA bundle 적용) ==========
function cxi_http_get(string $url, int $timeout = 8): ?string {
    $ch = curl_init($url);
    if (function_exists('nb_ca_bundle') && ($ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        // 진짜 브라우저 UA (Chrome 130) — bot 차단 회피
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
        ],
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($res && $http === 200) ? $res : null;
}

// ========== 트윗 페치 (폴백 체인 + 레이트리밋 회피) ==========
function cxi_fetch_tweets(string $username, bool $allowFetch = true): array {
    // 1차 캐시 (15분, 정상 응답)
    $cached = cxi_cache_get('user:' . $username, CXI_FETCH_TTL);
    if ($cached !== null) return $cached;

    // 2차 fallback 캐시 (소스 실패 시 stale 데이터 사용 — 최대 24시간)
    $stale = cxi_cache_get('user:' . $username, 86400);

    if (!$allowFetch) return $stale ?? [];

    // 실패 throttle 캐시 (60초 — 연속 실패 시 페이지마다 재시도 안 함)
    $failGate = cxi_cache_get('fail:' . $username, 60);
    if ($failGate !== null) return $stale ?? [];

    $tweets = [];
    $sources = [
        // Twitter 신디케이션 (가장 안정적)
        ['url' => "https://syndication.twitter.com/srv/timeline-profile/screen-name/{$username}?dnt=1", 'parse' => 'syndication'],
        // RSSHub 공식
        ['url' => "https://rsshub.app/twitter/user/{$username}",          'parse' => 'rss'],
        ['url' => "https://rsshub.atgw.io/twitter/user/{$username}",      'parse' => 'rss'],
    ];
    foreach ($sources as $src) {
        $body = cxi_http_get($src['url'], 6);
        if (!$body) continue;
        $parsed = $src['parse'] === 'rss' ? cxi_parse_rss($body) : cxi_parse_syndication($body);
        if ($parsed) {
            $tweets = $parsed;
            break;
        }
    }

    if (empty($tweets)) {
        // 실패 — 60초간 재시도 차단 + stale 캐시 반환
        cxi_cache_put('fail:' . $username, ['failed_at' => time()]);
        return $stale ?? [];
    }

    $tweets = array_slice($tweets, 0, 20);
    cxi_cache_put('user:' . $username, $tweets);
    return $tweets;
}

// 페이지 로드 시 사용 — 1회 호출당 fetch 횟수 제한 (rate limit 회피)
function cxi_fetch_with_budget(array $influencers, int $budget = 3): array {
    $results = [];
    $fetched = 0;
    foreach ($influencers as $inf) {
        // 캐시가 있으면 무료 페치
        $cacheHit = cxi_cache_get('user:' . $inf['handle'], CXI_FETCH_TTL) !== null;
        if ($cacheHit || $fetched < $budget) {
            $tweets = cxi_fetch_tweets($inf['handle'], !$cacheHit);
            if (!$cacheHit && !empty($tweets)) {
                $fetched++;
                usleep(300 * 1000);   // 0.3초 텀
            }
        } else {
            // 예산 소진 — stale 캐시만 (페치 안 함)
            $tweets = cxi_fetch_tweets($inf['handle'], false);
        }
        $results[$inf['handle']] = $tweets;
    }
    return $results;
}

function cxi_parse_rss(string $xml): array {
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc) return [];
    $items = $doc->channel->item ?? [];
    $out = [];
    foreach ($items as $it) {
        $title = trim((string)$it->title);
        if (!$title) continue;
        $link  = trim((string)$it->link);
        $body  = (string)($it->description ?? '');
        $img   = '';
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $body, $m)) $img = $m[1];
        // RSSHub의 description은 HTML 포함 — 텍스트만 추출
        $text = trim(strip_tags($body));
        $text = preg_replace('/\s+/u', ' ', $text);
        // RSSHub는 종종 title도 본문 첫 부분 — 더 긴 게 본문
        if (mb_strlen($text) < mb_strlen($title)) $text = $title;

        $out[] = [
            'text'      => mb_substr($text, 0, 600),
            'url'       => $link,
            'image'     => $img,
            'published' => strtotime((string)$it->pubDate) ?: 0,
        ];
    }
    return $out;
}

function cxi_parse_syndication(string $html): array {
    // syndication 페이지는 SSR JSON을 __NEXT_DATA__ 또는 window.__INITIAL_STATE__ 형태로 포함
    if (!preg_match('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.+?)<\/script>/s', $html, $m)) return [];
    $data = json_decode($m[1], true);
    if (!is_array($data)) return [];
    // 트윗 객체 위치는 시기에 따라 변함 — 깊이 탐색
    $tweets = [];
    array_walk_recursive($data, function ($v, $k) use (&$tweets) {
        if ($k === 'full_text' && is_string($v) && mb_strlen($v) > 0) $tweets[] = $v;
    });
    $tweets = array_unique($tweets);
    $out = [];
    foreach (array_slice($tweets, 0, 20) as $t) {
        $out[] = [
            'text'      => $t,
            'url'       => '',
            'image'     => '',
            'published' => 0,
        ];
    }
    return $out;
}

// ========== AI 한국어 번역 ==========
// 캐시만 조회 (호출 X)
function cxi_get_cached_translation(string $text): ?string {
    $cached = cxi_cache_get('tr:' . md5($text), CXI_TRANS_TTL);
    return ($cached !== null && isset($cached['ko'])) ? $cached['ko'] : null;
}

// 단일 번역 (캐시 hit 우선)
function cxi_translate(string $text, string $apiKey): string {
    $cached = cxi_get_cached_translation($text);
    if ($cached !== null) return $cached;
    if (!$apiKey) return '';
    $batch = cxi_translate_batch([$text], $apiKey);
    return $batch[0] ?? '';
}

// 배치 번역 — 여러 텍스트를 1회 OpenRouter 호출로 (속도·비용 최적화)
function cxi_translate_batch(array $texts, string $apiKey): array {
    if (!$texts || !$apiKey) return array_fill(0, count($texts), '');
    // 캐시 hit는 결과 배열에 담고, miss만 신규 호출
    $results = [];
    $toTranslate = [];      // index => text (호출 대상)
    foreach ($texts as $i => $t) {
        $cached = cxi_get_cached_translation($t);
        if ($cached !== null) {
            $results[$i] = $cached;
        } else {
            $toTranslate[$i] = $t;
            $results[$i] = '';
        }
    }
    if (!$toTranslate) return $results;

    // 한 번에 너무 많이 보내면 응답 잘림 → 5개씩 청크 (gpt-oss-20b 안정성 우선)
    foreach (array_chunk($toTranslate, 5, true) as $chunk) {
        $items = [];
        foreach ($chunk as $idx => $t) {
            $items[] = '[#' . $idx . '] ' . $t;
        }
        $userMsg = "다음 영문 트윗을 한국어로 번역하세요. 약어·해시태그·티커·@멘션은 그대로 유지.\n" .
                   "응답 형식 (각 번호 그대로 사용, 번역만 출력):\n" .
                   "[#0] 한국어 번역\n" .
                   "[#1] 한국어 번역\n\n" .
                   "트윗:\n" . implode("\n", $items);

        // 폴백 모델 체인 — rate limit 에 걸리면 다음 모델 시도
        $models = [
            'openai/gpt-oss-20b:free',
            'meta-llama/llama-3.3-70b-instruct:free',
            'google/gemma-3-27b-it:free',
            'qwen/qwen3-next-80b-a3b-instruct:free',
        ];
        $content = '';
        foreach ($models as $model) {
            $payload = [
                'model'       => $model,
                'messages'    => [['role' => 'user', 'content' => $userMsg]],
                'temperature' => 0.3,
                'max_tokens'  => 1500,
            ];
            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            if (function_exists('nb_ca_bundle') && ($ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $ca);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $res = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http !== 200 || !$res) continue;
            $data = json_decode($res, true);
            $content = trim($data['choices'][0]['message']['content'] ?? '');
            if ($content) break;
        }
        if (!$content) continue;

        // line-based 파서: "[#N] 번역" 패턴
        if (preg_match_all('/\[#(\d+)\]\s*(.+?)(?=\n\[#\d+\]|\z)/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $idx = (int)$m[1];
                $ko = trim(preg_replace('/\s+/', ' ', $m[2]));
                if (!$ko || !isset($chunk[$idx])) continue;
                $original = $chunk[$idx];
                $results[$idx] = $ko;
                cxi_cache_put('tr:' . md5($original), ['ko' => $ko, 'src' => $original]);
            }
        }
    }
    return $results;
}

// ========== 인플루언서 목록 로드 ==========
function cxi_load_influencers(): array {
    $f = __DIR__ . '/data/influencers.json';
    $raw = is_file($f) ? json_decode(file_get_contents($f), true) : [];
    return is_array($raw['influencers'] ?? null) ? $raw['influencers'] : [];
}

// ========== OpenRouter API 키 (ai-content-generator 와 공유) ==========
function cxi_get_api_key(): string {
    // ai-content-generator config.json의 key를 재사용 — 사용자가 한 곳에서 관리
    $cfg = is_file(__DIR__ . '/../ai-content-generator/config.json')
         ? json_decode(file_get_contents(__DIR__ . '/../ai-content-generator/config.json'), true)
         : [];
    return is_array($cfg) ? trim($cfg['api_key'] ?? '') : '';
}

// ========== 게시판 자동 등록 ==========
function cxi_auto_post_new_tweets(): array {
    if (!class_exists('DB')) return ['posted' => 0, 'errors' => ['DB unavailable']];
    $apiKey = cxi_get_api_key();
    if (!$apiKey) return ['posted' => 0, 'errors' => ['OpenRouter API 키 없음']];

    $state = is_file(CXI_STATE_FILE) ? json_decode(file_get_contents(CXI_STATE_FILE), true) : [];
    if (!is_array($state)) $state = [];
    $posted_urls = $state['posted_urls'] ?? [];
    $posted_set = array_flip($posted_urls);

    $prefix = DB::getPrefix();
    // 'news' 게시판으로 자동 등록 (없으면 첫 active 게시판)
    $boardId = 'news';
    $board = DB::fetch("SELECT board_id FROM {$prefix}boards WHERE board_id = ? AND is_active = 1", [$boardId]);
    if (!$board) {
        $board = DB::fetch("SELECT board_id FROM {$prefix}boards WHERE is_active = 1 LIMIT 1");
        if (!$board) return ['posted' => 0, 'errors' => ['활성 게시판 없음']];
        $boardId = $board['board_id'];
    }

    $adminId = (int)(DB::fetch("SELECT id FROM {$prefix}members WHERE is_admin = 1 ORDER BY id LIMIT 1")['id'] ?? 1);

    $posted = 0; $errors = [];
    foreach (cxi_load_influencers() as $inf) {
        if (($inf['priority'] ?? 9) > 1) continue;     // 우선순위 1 (상시 모니터링) 만 자동 등록
        $tweets = cxi_fetch_tweets($inf['handle']);
        foreach (array_slice($tweets, 0, 3) as $t) {   // 사용자당 최대 3개
            $key = $t['url'] ?: ($inf['handle'] . ':' . md5($t['text']));
            if (isset($posted_set[$key])) continue;
            // 12시간 이내 트윗만 (오래된 건 무시)
            if (!empty($t['published']) && (time() - $t['published']) > 43200) continue;
            // 너무 짧은 거 스킵
            if (mb_strlen($t['text']) < 30) continue;

            $ko = cxi_translate($t['text'], $apiKey);
            if (!$ko) { $errors[] = $inf['handle'] . ' 번역 실패'; continue; }

            $title = '[' . $inf['name'] . '] ' . mb_substr($ko, 0, 60) . (mb_strlen($ko) > 60 ? '...' : '');
            $body  = '<p><strong>' . htmlspecialchars($inf['name']) . '</strong> (@' . htmlspecialchars($inf['handle']) . ' · ' . htmlspecialchars($inf['role']) . ')</p>'
                   . '<blockquote style="background:#f9fafb;border-left:3px solid #6366f1;padding:12px 16px;margin:12px 0;border-radius:4px">'
                   . '<p style="margin:0 0 8px;font-weight:600">🇰🇷 ' . htmlspecialchars($ko) . '</p>'
                   . '<p style="margin:0;font-size:13px;color:#64748b">📝 원문: ' . htmlspecialchars($t['text']) . '</p>'
                   . '</blockquote>';
            if ($t['url']) $body .= '<p><a href="' . htmlspecialchars($t['url']) . '" target="_blank" rel="noopener">원본 트윗 보기 →</a></p>';
            $body .= '<p style="font-size:12px;color:#94a3b8">자동 번역: AI · 출처: X (구 Twitter)</p>';

            try {
                DB::insert("{$prefix}posts", [
                    'board_id'    => $boardId,
                    'member_id'   => $adminId,
                    'title'       => mb_substr($title, 0, 200),
                    'content'     => $body,
                    'slug'        => '',
                    'hit'         => 0,
                    'comment_count' => 0,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                $posted++;
                $posted_urls[] = $key;
            } catch (Exception $e) {
                $errors[] = $inf['handle'] . ': ' . $e->getMessage();
            }
        }
    }

    // 상태 저장 — 최근 200개만 유지
    $state['posted_urls'] = array_slice($posted_urls, -200);
    $state['last_run']    = date('Y-m-d H:i:s');
    @file_put_contents(CXI_STATE_FILE, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    return ['posted' => $posted, 'errors' => $errors];
}

// ========== 백그라운드 자동 갱신 (관리자 페이지 방문 시 트리거) ==========
// 사용자 페이지는 캐시만 사용해 즉시 응답. 데이터 갱신은 여기서 처리.
function cxi_maybe_tick(): void {
    $stateFile = CXI_CACHE_DIR . '/tick.json';
    $state = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    if (!is_array($state)) $state = [];

    $lastRun = $state['last_run'] ?? 0;
    if ((time() - $lastRun) < 60) return;     // 1분 간격 보장

    // 락 (동시 실행 방지)
    $lockFile = CXI_CACHE_DIR . '/tick.lock';
    $lock = @fopen($lockFile, 'c');
    if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) return;

    @ignore_user_abort(true);
    @set_time_limit(60);

    try {
        // 캐시 만료된 사용자 1명만 갱신 (rate limit 보호)
        $influencers = cxi_load_influencers();
        usort($influencers, fn($a, $b) => ($a['priority'] ?? 9) <=> ($b['priority'] ?? 9));

        $picked = null;
        foreach ($influencers as $inf) {
            if (cxi_cache_get('user:' . $inf['handle'], CXI_FETCH_TTL) === null) {
                // fail throttle 도 체크
                if (cxi_cache_get('fail:' . $inf['handle'], 60) !== null) continue;
                $picked = $inf;
                break;
            }
        }
        if ($picked) {
            $tweets = cxi_fetch_tweets($picked['handle'], true);
            if ($tweets) {
                // 신규 트윗 자동 번역 (캐시 채움)
                $apiKey = cxi_get_api_key();
                if ($apiKey) {
                    $texts = array_map(fn($t) => $t['text'], $tweets);
                    cxi_translate_batch($texts, $apiKey);
                }
            }
            $state['last_picked'] = $picked['handle'];
        }
        $state['last_run'] = time();
        @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    } catch (Exception $e) {
        // ignore
    }

    @flock($lock, LOCK_UN);
    @fclose($lock);
}

// admin 페이지 방문 시 1분마다 백그라운드 갱신
if (class_exists('Plugin')) {
    Plugin::addHook('admin_after_header', function () { cxi_maybe_tick(); });
}

// ========== 라우트 등록 ==========
if (class_exists('Router')):

Router::get('/influencers', function () {
    $influencers = cxi_load_influencers();
    SEO::setTitle('🐦 크립토 인플루언서 X');
    SEO::setDescription('Vitalik, CZ, Saylor 등 영향력 있는 크립토 인플루언서의 X(트위터) 활동 — AI 한국어 번역 제공');
    require __DIR__ . '/views/influencers.php';
});

Router::get('/influencers/{user}', function ($params) {
    $user = preg_replace('/[^a-zA-Z0-9_]/', '', $params['user']);
    if (!$user) { http_response_code(404); Router::loadTheme('error/404'); return; }
    $influencers = cxi_load_influencers();
    $influencer = null;
    foreach ($influencers as $inf) {
        if (strcasecmp($inf['handle'], $user) === 0) { $influencer = $inf; break; }
    }
    if (!$influencer) { http_response_code(404); Router::loadTheme('error/404'); return; }
    $tweets = cxi_fetch_tweets($user);
    SEO::setTitle($influencer['name'] . ' 트윗');
    require __DIR__ . '/views/influencer_detail.php';
});

Router::get('/api/influencers', function () {
    $influencers = cxi_load_influencers();
    $feed = [];
    foreach ($influencers as $inf) {
        $tweets = cxi_fetch_tweets($inf['handle']);
        foreach (array_slice($tweets, 0, 5) as $t) {
            $feed[] = [
                'handle'    => $inf['handle'],
                'name'      => $inf['name'],
                'role'      => $inf['role'],
                'priority'  => $inf['priority'],
                'tweet'     => $t,
            ];
        }
    }
    // 최신순
    usort($feed, fn($a, $b) => ($b['tweet']['published'] ?? 0) <=> ($a['tweet']['published'] ?? 0));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode(['ok' => true, 'feed' => array_slice($feed, 0, 50)], JSON_UNESCAPED_UNICODE);
    exit;
});

Router::post('/api/translate-tweet', function () {
    if (!class_exists('Auth') || !Auth::check()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => '로그인 필요']);
        exit;
    }
    $text = trim($_POST['text'] ?? '');
    if (!$text) { echo json_encode(['ok' => false, 'error' => 'no text']); exit; }
    $ko = cxi_translate($text, cxi_get_api_key());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => (bool)$ko, 'ko' => $ko], JSON_UNESCAPED_UNICODE);
    exit;
});

Router::get('/api/influencers-auto-post', function () {
    if (!class_exists('Auth') || !Auth::check() || !Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => '관리자 권한 필요']);
        exit;
    }
    @set_time_limit(300);
    $r = cxi_auto_post_new_tweets();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $r, JSON_UNESCAPED_UNICODE);
    exit;
});

endif; // class_exists('Router')

// ========== assets 자동 로드 ==========
if (function_exists('nb_url') && !defined('NB_ADMIN')) {
    Plugin::queueHeaderAsset(
        '<link rel="stylesheet" href="' . nb_url('plugins/crypto-influencers/assets/influencers.css') . '?v=' . filemtime(__DIR__ . '/assets/influencers.css') . '">'
    );
}

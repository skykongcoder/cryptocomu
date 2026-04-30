<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * Authority Connector — 위키백과 외부 링크 자동 삽입기
 * NuriBoard CMS Plugin v1.0
 *
 * 동작 방식: 게시글 저장 시 post.content 필터로 본문을 수정 → DB에 영구 저장
 * 방문자 열람 시에는 이미 완성된 본문을 그대로 출력 (서버 부하 0)
 */

// ============================================================
// 헬퍼
// ============================================================

function _ac_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/authority-connector';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _ac_cache_dir(): string {
    $dir = _ac_data_dir() . '/cache';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _ac_load_config(): array {
    $default = [
        'enabled'        => false,
        'openai_api_key' => '',
        'openai_model'   => 'openai/gpt-4o-mini',
        'allowed_boards' => '',
        'max_links'      => 2,   // 게시글당 최대 링크 수 (1 or 2)
    ];
    $file = _ac_data_dir() . '/config.json';
    if (!file_exists($file)) return $default;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge($default, $data) : $default;
}

function _ac_save_config(array $config): void {
    file_put_contents(
        _ac_data_dir() . '/config.json',
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function _ac_log(string $msg): void {
    $file = _ac_data_dir() . '/debug.log';
    if (file_exists($file) && filesize($file) > 1024 * 1024) {
        file_put_contents($file, '');
    }
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ============================================================
// 키워드 → Wikipedia URL 캐시 (keyword 기준, 7일)
// ============================================================

function _ac_get_kw_cache(string $keyword): ?string {
    $file = _ac_cache_dir() . '/' . md5($keyword) . '.json';
    if (!file_exists($file)) return null;
    if (filemtime($file) < time() - 86400 * 7) { @unlink($file); return null; }
    $data = json_decode(file_get_contents($file), true);
    return isset($data['url']) ? (string)$data['url'] : null;
}

function _ac_set_kw_cache(string $keyword, string $url): void {
    // url이 빈 문자열이면 "없음"을 캐시 (재조회 방지)
    file_put_contents(
        _ac_cache_dir() . '/' . md5($keyword) . '.json',
        json_encode(['keyword' => $keyword, 'url' => $url], JSON_UNESCAPED_UNICODE)
    );
}

function _ac_cache_count(): int {
    $files = glob(_ac_cache_dir() . '/*.json');
    return $files ? count($files) : 0;
}

function _ac_clear_all_cache(): void {
    $files = glob(_ac_cache_dir() . '/*.json');
    if ($files) foreach ($files as $f) @unlink($f);
}

// ============================================================
// 위키백과 API 조회 (한국어)
// ============================================================

function _ac_curl_get(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'NuriBoard-AuthorityConnector/1.0 (https://nuribd.com)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $resp) ? $resp : null;
}

function _ac_lookup_wikipedia(string $keyword): ?string {
    // 캐시 확인
    $cached = _ac_get_kw_cache($keyword);
    if ($cached !== null) {
        return $cached === '' ? null : $cached;
    }

    // 1단계: 정확한 제목으로 직접 조회
    $resp = _ac_curl_get(
        'https://ko.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($keyword)
    );
    if ($resp) {
        $data     = json_decode($resp, true);
        $wiki_url = $data['content_urls']['desktop']['page'] ?? '';
        if ($wiki_url !== '') {
            _ac_set_kw_cache($keyword, $wiki_url);
            return $wiki_url;
        }
    }

    // 2단계: 검색 API로 유사 문서 탐색 (정확한 제목이 없을 때)
    $search_url = 'https://ko.wikipedia.org/w/api.php?'
        . http_build_query([
            'action'   => 'query',
            'list'     => 'search',
            'srsearch' => $keyword,
            'format'   => 'json',
            'srlimit'  => 1,
            'srnamespace' => 0,
        ]);
    $resp = _ac_curl_get($search_url);
    if ($resp) {
        $data   = json_decode($resp, true);
        $hits   = $data['query']['search'] ?? [];
        $title  = $hits[0]['title'] ?? '';
        if ($title !== '') {
            // 찾은 제목으로 다시 summary 조회
            $resp2 = _ac_curl_get(
                'https://ko.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($title)
            );
            if ($resp2) {
                $data2    = json_decode($resp2, true);
                $wiki_url = $data2['content_urls']['desktop']['page'] ?? '';
                if ($wiki_url !== '') {
                    _ac_log("위키 검색 매칭: [{$keyword}] → [{$title}]");
                    _ac_set_kw_cache($keyword, $wiki_url);
                    return $wiki_url;
                }
            }
        }
    }

    _ac_set_kw_cache($keyword, ''); // 완전 없음 캐시
    return null;
}

// ============================================================
// 키워드 추출 — AI
// ============================================================

function _ac_extract_keywords_ai(string $text, array $cfg): ?array {
    @set_time_limit(120);
    $api_key = trim($cfg['openai_api_key'] ?? '');
    $model   = $cfg['openai_model'] ?? 'openai/gpt-4o-mini';
    $excerpt = mb_substr(strip_tags($text), 0, 600);
    $max     = (int)($cfg['max_links'] ?? 2);

    $prompt = "아래 글에서 한국어 위키백과 문서 제목으로 바로 검색되는 짧은 전문 용어나 고유명사를 {$max}개 이하로 추출해 쉼표로만 반환하세요.\n\n"
            . "핵심 규칙:\n"
            . "- 반드시 위키백과에 독립 문서가 있을 법한 단어만\n"
            . "- 2~6글자의 명사형 단어 또는 잘 알려진 전문 용어만\n"
            . "- 긴 문구·설명형 표현 절대 금지 (예: '아웃바운드 링크 SEO' X → '외부 링크' O)\n"
            . "- '제목', '색상', '날짜' 같은 너무 일반적인 단어 금지 — 글의 핵심 주제와 관련된 전문 용어만\n"
            . "- 단어만 출력, 설명·문장·사과 절대 금지\n"
            . "- 확실하지 않으면 1개만 반환. 적합한 단어가 없으면 빈 문자열 반환\n\n"
            . "좋은 예: 검색엔진 최적화, 스키마 마크업, 인공지능, 머신러닝, 링크 빌딩\n"
            . "나쁜 예: 아웃바운드 링크 SEO, 권위 있는 외부 링크, 제목, 색상, 내용\n\n"
            . "글:\n" . $excerpt;

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => 60,
            'temperature' => 0,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code !== 200 || !$resp) {
        _ac_log("AI 키워드 추출 실패 errno={$err} http={$code}");
        return null;
    }

    $data = json_decode($resp, true);
    $raw  = trim($data['choices'][0]['message']['content'] ?? '');
    if (empty($raw)) return null;

    $keywords = array_values(array_filter(
        array_map('trim', explode(',', $raw)),
        fn($k) => mb_strlen($k) >= 2
    ));

    return $keywords ?: null;
}

// ============================================================
// 키워드 추출 — Fallback (제목 단어 분리)
// ============================================================

function _ac_extract_keywords_fallback(string $title, int $max = 2): array {
    // 한국어 조사·불용어 제거를 위해 2음절 이상 단어만 추출
    $words  = preg_split('/[\s\p{P}\p{S}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
    $seen   = [];
    $result = [];
    // 불용어 목록 (짧고 의미 없는 단어들)
    $stopwords = ['이것', '저것', '그것', '무엇', '어떤', '하는', '있는', '없는', '위해', '대한', '관한'];
    foreach ($words as $w) {
        $w = trim($w);
        if (mb_strlen($w) < 2 || isset($seen[$w]) || in_array($w, $stopwords, true)) continue;
        $seen[$w]  = true;
        $result[]  = $w;
        if (count($result) >= $max) break;
    }
    return $result;
}

// ============================================================
// 본문에 링크 삽입 (기존 <a> 태그 내부 제외, 첫 등장만)
// ============================================================

function _ac_insert_link(string $content, string $keyword, string $url): string {
    $safe_url  = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $link_html = '<a href="' . $safe_url . '" target="_blank" rel="noopener noreferrer" '
               . 'style="color:#15803d;text-decoration:underline;text-underline-offset:2px">'
               . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '</a>';

    $kw_pattern = preg_quote($keyword, '/');
    $replaced   = false;

    // <a>...</a> 블록은 건드리지 않고, 그 외 텍스트에서 첫 등장 1회만 교체
    $result = preg_replace_callback(
        '/(<a[^>]*>.*?<\/a>)|(' . $kw_pattern . ')/isu',
        function ($m) use ($link_html, &$replaced) {
            if (!empty($m[1])) return $m[1];   // <a> 블록 → 그대로
            if ($replaced)     return $m[2];   // 이미 교체됨 → 원본
            $replaced = true;
            return $link_html;
        },
        $content
    );

    return $result ?? $content;
}

// ============================================================
// 핵심 처리 함수
// ============================================================

function _ac_process_content(string $content, string $title, array $cfg): string {
    $max     = max(1, min(2, (int)($cfg['max_links'] ?? 2)));
    $api_key = trim($cfg['openai_api_key'] ?? '');
    $text    = $content ?: $title;

    // 이미 위키백과 링크가 있으면 기존 링크 제거 후 재처리 (중복 방지)
    $content = preg_replace(
        '/<a([^>]+)href="[^"]*wikipedia\.org[^"]*"([^>]*)>(.*?)<\/a>/isu',
        '$3',
        $content
    );

    // 키워드 추출
    if ($api_key !== '' && mb_strlen(strip_tags($text)) >= 50) {
        $keywords = _ac_extract_keywords_ai($text, $cfg);
    } else {
        $keywords = null;
    }
    if (empty($keywords)) {
        $keywords = _ac_extract_keywords_fallback($title, $max);
    }
    if (empty($keywords)) {
        _ac_log("키워드 없음, 스킵");
        return $content;
    }

    // 키워드별 위키백과 조회 → 링크 삽입
    $inserted = 0;
    foreach ($keywords as $kw) {
        if ($inserted >= $max) break;
        $wiki_url = _ac_lookup_wikipedia($kw);
        if (!$wiki_url) {
            _ac_log("위키 없음: {$kw}");
            continue;
        }
        $before  = $content;
        $content = _ac_insert_link($content, $kw, $wiki_url);
        if ($content !== $before) {
            $inserted++;
            _ac_log("링크 삽입: [{$kw}] → {$wiki_url}");
        } else {
            _ac_log("키워드 본문에 없음: {$kw}");
        }
    }

    return $content;
}

// ============================================================
// 훅 등록 — 게시글 저장 시 post.content 필터
// ============================================================

Plugin::addFilter('post.content', function(string $content): string {
    try {
        $cfg = _ac_load_config();
        if (empty($cfg['enabled'])) return $content;

        // board_id는 절대 int 캐스팅 금지 — 문자열 슬러그
        $board_id = (string)trim($_POST['board_id'] ?? '');
        $title    = trim($_POST['title'] ?? '');

        // 허용 게시판 필터
        if (!empty($cfg['allowed_boards'])) {
            $allowed = array_values(array_filter(
                array_map('trim', explode(',', $cfg['allowed_boards'])),
                fn($b) => $b !== ''
            ));
            if ($board_id !== '' && !in_array($board_id, $allowed, true)) return $content;
        }

        return _ac_process_content($content, $title, $cfg);

    } catch (Throwable $e) {
        _ac_log("필터 오류: " . $e->getMessage());
        return $content;
    }
});

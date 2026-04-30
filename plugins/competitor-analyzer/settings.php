<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * 경쟁사 분석 & 콘텐츠 자동 생성 - 설정/실행 페이지
 */

// ===== 데이터 경로 =====
$_ca_base    = defined('NB_ROOT') ? NB_ROOT : dirname(dirname(__DIR__));
$_ca_dir     = $_ca_base . '/data/competitor-analyzer';
if (!is_dir($_ca_dir)) @mkdir($_ca_dir, 0755, true);
$_ca_cfg     = $_ca_dir . '/config.json';

$_ca_default_prompt = "당신은 대한민국 최고의 SEO 콘텐츠 전략가입니다.\n\n아래 경쟁사 페이지를 분석하여, 구글·네이버 검색에서 반드시 상위에 노출될 수 있는 월등히 우수한 블로그 포스트를 작성해 주세요.\n\n[경쟁사 페이지 정보]\n제목: {competitor_title}\n주요 소제목: {competitor_headings}\n핵심 내용: {competitor_content}\n\n[작성 전략 — 반드시 지키세요]\n\n■ 정보 완성도\n- 경쟁사가 언급하지 않은 정보, 놓친 관점을 반드시 추가\n- 독자의 다음 질문까지 미리 답하는 완결성 있는 구성\n- 경쟁사보다 더 풍부한 정보량 (불필요한 반복 금지)\n\n■ 구조 설계\n- h2 소제목 5~7개로 논리적 흐름 구성\n- 첫 번째 단락: 독자의 핵심 고민을 정확히 짚고 이 글이 해결해준다고 명시\n- 중반부: 구체적 방법, 단계별 가이드, 실전 팁\n- 마지막 단락: 핵심 요약 + 독자 행동 유도\n\n■ 품질 기준\n- 전문가가 직접 경험한 듯한 실용적 조언 포함\n- 수치, 예시, 비교 등 신뢰도를 높이는 요소 활용\n- 자연스러운 한국어 구어체 (딱딱한 보고서체 금지)\n- 각 단락 3~4문장, 핵심만 담아 읽기 쉽게\n\n■ SEO 최적화\n- 첫 번째 p 태그에 핵심 키워드 자연스럽게 포함\n- 각 h2 소제목에 키워드 변형 표현 활용\n- strong 태그로 핵심 문구 2~3개 강조\n\n■ 출력 형식 — 반드시 아래 형식 그대로 출력\n제목: 여기에 SEO 최적화된 매력적인 제목 작성\n\n[본문 시작]\n<h2>...</h2>\n<p>...</p>\n...\n[본문 끝]\n\n- 사용 태그: h2, h3, p, ul, li, strong 만 허용\n- 마크다운 절대 사용 금지 (```, **, # 등)\n- 코드블록 절대 사용 금지";

// ===== 설정 로드 =====
$_ca_raw = file_exists($_ca_cfg) ? json_decode(file_get_contents($_ca_cfg), true) : [];
if (!is_array($_ca_raw)) $_ca_raw = [];
$_ca = array_merge([
    'openai_api_key'   => '',
    'unsplash_api_key' => '',
    'board_id'         => '',
    'prompt'           => $_ca_default_prompt,
], $_ca_raw);

// ===== 설정 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ca_save'])) {
    $_ca['openai_api_key']   = trim($_POST['openai_api_key'] ?? '');
    $_ca['unsplash_api_key'] = trim($_POST['unsplash_api_key'] ?? '');
    $_ca['board_id']         = trim($_POST['board_id'] ?? '');
    $_ca['prompt']           = trim($_POST['prompt'] ?? $_ca_default_prompt);
    file_put_contents($_ca_cfg, json_encode($_ca, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="ca-alert ca-ok">설정이 저장되었습니다.</div>';
}

// ===== 사이트 스크래핑 함수 =====
function _ca_scrape(string $url): array {
    if (!preg_match('/^https?:\/\//i', $url)) {
        return ['success' => false, 'error' => '올바른 URL 형식이 아닙니다. https:// 로 시작하는 주소를 입력해 주세요.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
        ],
    ]);
    $html      = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 연결 오류
    if ($curlError) {
        if (strpos($curlError, 'timed out') !== false) {
            return ['success' => false, 'error' => '⏱ 사이트 응답 시간이 초과되었습니다. 사이트가 느리거나 일시적으로 접속이 안 될 수 있습니다. 잠시 후 다시 시도해 주세요.'];
        }
        if (strpos($curlError, 'SSL') !== false || strpos($curlError, 'certificate') !== false) {
            return ['success' => false, 'error' => '🔒 SSL 인증서 오류로 사이트에 접근할 수 없습니다. 사이트 주소가 맞는지 확인해 주세요.'];
        }
        return ['success' => false, 'error' => '🚫 사이트에 연결할 수 없습니다. 주소가 올바른지 확인해 주세요. (' . $curlError . ')'];
    }

    // HTTP 상태 코드 체크
    if ($httpCode === 403 || $httpCode === 401) {
        return ['success' => false, 'error' => '🚫 이 사이트는 외부 접근을 차단하고 있어 분석할 수 없습니다. 직접 방문하여 키워드를 참고해 주세요.'];
    }
    if ($httpCode === 404) {
        return ['success' => false, 'error' => '❌ 페이지를 찾을 수 없습니다. URL을 다시 확인해 주세요.'];
    }
    if ($httpCode >= 500) {
        return ['success' => false, 'error' => '⚠️ 해당 사이트 서버에 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.'];
    }

    if (empty($html)) {
        return ['success' => false, 'error' => '📭 사이트에서 내용을 가져올 수 없습니다. 잠시 후 다시 시도해 주세요.'];
    }

    // Cloudflare 감지
    if (
        strpos($html, 'cf-browser-verification') !== false ||
        strpos($html, 'Checking your browser') !== false ||
        strpos($html, 'Just a moment') !== false ||
        strpos($html, 'cloudflare') !== false && $httpCode === 403
    ) {
        return ['success' => false, 'error' => '🛡️ 이 사이트는 Cloudflare 보안 시스템으로 보호되어 있어 자동 분석이 불가합니다. 직접 방문하여 주요 내용을 파악한 뒤, 아래 "직접 입력 모드"를 활용해 주세요.'];
    }

    // JavaScript 전용 SPA 감지
    $textContent = strip_tags($html);
    $textContent = preg_replace('/\s+/', ' ', $textContent);
    if (strlen(trim($textContent)) < 200) {
        return ['success' => false, 'error' => '⚡ 이 사이트는 JavaScript로만 구성된 페이지라 텍스트를 직접 수집할 수 없습니다. 아래 "직접 입력 모드"를 활용해 주세요.'];
    }

    // ===== 콘텐츠 추출 =====
    $data = [];

    // 제목
    preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $tm);
    $data['title'] = trim(strip_tags($tm[1] ?? ''));

    // 메타 설명
    preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/si', $html, $mm);
    $data['meta_desc'] = trim($mm[1] ?? '');

    // 소제목 추출 (h1~h3)
    preg_match_all('/<h[1-3][^>]*>(.*?)<\/h[1-3]>/si', $html, $hm);
    $headings = [];
    foreach (($hm[1] ?? []) as $h) {
        $ht = trim(strip_tags($h));
        if ($ht && strlen($ht) > 2) $headings[] = $ht;
    }
    $data['headings'] = array_slice($headings, 0, 15);

    // 본문 텍스트 추출 (script/style/nav/footer 제거)
    $body = preg_replace('/<(script|style|nav|footer|header|aside|noscript)[^>]*>.*?<\/\1>/si', '', $html);
    $body = strip_tags($body);
    $body = preg_replace('/\s+/', ' ', $body);
    $body = trim($body);
    $data['content'] = mb_substr($body, 0, 3000);
    $data['word_count'] = mb_strlen($body);

    return ['success' => true, 'data' => $data];
}

// ===== OpenAI 콘텐츠 생성 =====
function _ca_generate(array $scraped, string $customPrompt, string $apiKey): array {
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'OpenRouter API 키가 설정되지 않았습니다.'];
    }

    $headingsStr = !empty($scraped['headings']) ? implode(', ', $scraped['headings']) : '(소제목 없음)';
    $contentStr  = !empty($scraped['content']) ? $scraped['content'] : '(본문 없음)';

    $prompt = str_replace(
        ['{competitor_title}', '{competitor_headings}', '{competitor_content}'],
        [$scraped['title'] ?? '', $headingsStr, $contentStr],
        $customPrompt
    );

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'       => 'openai/gpt-4o-mini',
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.75,
            'max_tokens'  => 3000,
        ]),
        CURLOPT_TIMEOUT        => 120,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr   = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['success' => false, 'error' => 'OpenAI 연결 오류: ' . $curlErr];
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        return ['success' => false, 'error' => 'OpenAI 오류 ' . $httpCode . ': ' . ($err['error']['message'] ?? $response)];
    }

    $result = json_decode($response, true);
    $content = $result['choices'][0]['message']['content'] ?? '';
    if (empty($content)) return ['success' => false, 'error' => 'AI 응답이 비어있습니다.'];

    // ===== 마크다운 찌꺼기 완전 제거 =====
    // 코드블록 제거 (```html ... ``` 또는 ``` ... ```)
    $content = preg_replace('/^```[a-z]*\s*/i', '', trim($content));
    $content = preg_replace('/\s*```\s*$/i', '', $content);
    // [본문 시작] / [본문 끝] 마커 제거
    $content = preg_replace('/\[본문\s*(시작|끝)\]\s*/u', '', $content);
    // 남은 마크다운 강조(**text**) → <strong>
    $content = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content);
    // # 헤딩 → h2
    $content = preg_replace('/^#{1,2}\s+(.+)$/m', '<h2>$1</h2>', $content);
    $content = preg_replace('/^#{3}\s+(.+)$/m', '<h3>$1</h3>', $content);
    // 마크다운 링크 → HTML
    $content = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $content);

    // ===== 제목 추출 =====
    $title = '';
    if (preg_match('/^제목\s*[:：]\s*(.+)/mu', $content, $tm)) {
        $title   = trim($tm[1]);
        $content = trim(preg_replace('/^제목\s*[:：].+\n?/mu', '', $content, 1));
    }

    $content = trim($content);
    return ['success' => true, 'content' => $content, 'title' => $title];
}

// ===== Unsplash 이미지 가져오기 =====
function _ca_get_images(string $keyword, string $apiKey, int $count = 3): array {
    if (empty($apiKey)) return [];
    $search = preg_replace('/[가-힣]+/u', '', $keyword);
    $search = trim($search);
    if (empty($search)) {
        $fallbacks = ['business', 'technology', 'office', 'creative', 'digital'];
        $search = $fallbacks[array_rand($fallbacks)];
    }
    $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($search) . '&per_page=10&client_id=' . urlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Accept-Version: v1'], CURLOPT_TIMEOUT => 10]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data   = json_decode($res, true);
    $images = [];
    if (!empty($data['results'])) {
        $pool = $data['results'];
        shuffle($pool);
        foreach (array_slice($pool, 0, $count) as $r) {
            if (!empty($r['urls']['regular'])) {
                $images[] = ['url' => $r['urls']['regular'], 'photographer' => $r['user']['name'] ?? '', 'alt' => $r['alt_description'] ?? $keyword];
            }
        }
    }
    return $images;
}

// ===== 이미지 본문 삽입 =====
function _ca_inject_images(string $html, array $images): string {
    if (empty($images)) return $html;
    $parts = preg_split('/(?=<h2[\s>])/i', $html);
    if (count($parts) <= 1) {
        $parts = preg_split('/(?=<p[\s>])/i', $html);
        $step  = max(2, (int)floor(count($parts) / (count($images) + 1)));
        $result = [];
        $idx = 0;
        foreach ($parts as $i => $p) {
            $result[] = $p;
            if ($idx < count($images) && $i > 0 && $i % $step === 0) {
                $img = $images[$idx++];
                $result[] = _ca_img_html($img);
            }
        }
        return implode('', $result);
    }
    $result = [];
    $idx = 0;
    foreach ($parts as $i => $part) {
        if ($i > 0 && $idx < count($images)) $result[] = _ca_img_html($images[$idx++]);
        $result[] = $part;
    }
    return implode('', $result);
}

function _ca_img_html(array $img): string {
    $url = htmlspecialchars($img['url']);
    $alt = htmlspecialchars($img['alt'] ?? '');
    return "\n<figure style=\"margin:28px auto;text-align:center;max-width:680px\">"
         . "<img src=\"{$url}\" alt=\"{$alt}\" style=\"width:100%;max-height:380px;object-fit:cover;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,0.08)\">"
         . "</figure>\n";
}

// ===== 분석 실행 =====
$_ca_result   = null;
$_ca_scrape_data = null;
$_ca_error    = '';
$_ca_comp_url = '';
$_ca_manual   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ca_analyze'])) {
    set_time_limit(180);
    $_ca_comp_url = trim($_POST['comp_url'] ?? '');
    $_ca_manual   = isset($_POST['manual_mode']);

    if ($_ca_manual) {
        // 직접 입력 모드
        $manualTitle    = trim($_POST['manual_title'] ?? '');
        $manualHeadings = trim($_POST['manual_headings'] ?? '');
        $manualContent  = trim($_POST['manual_content'] ?? '');
        $_ca_scrape_data = [
            'title'     => $manualTitle,
            'meta_desc' => '',
            'headings'  => array_filter(array_map('trim', explode("\n", $manualHeadings))),
            'content'   => $manualContent,
            'word_count'=> mb_strlen($manualContent),
        ];
    } else {
        if (empty($_ca_comp_url)) {
            $_ca_error = '분석할 URL을 입력해 주세요.';
        } else {
            $scraped = _ca_scrape($_ca_comp_url);
            if (!$scraped['success']) {
                $_ca_error = $scraped['error'];
            } else {
                $_ca_scrape_data = $scraped['data'];
            }
        }
    }

    if ($_ca_scrape_data && empty($_ca['openai_api_key'])) {
        $_ca_error = 'OpenRouter API 키를 먼저 설정하고 저장해 주세요.';
        $_ca_scrape_data = null;
    }

    if ($_ca_scrape_data) {
        $aiResult = _ca_generate($_ca_scrape_data, $_ca['prompt'], $_ca['openai_api_key']);
        if (!$aiResult['success']) {
            $_ca_error = $aiResult['error'];
        } else {
            $genContent = $aiResult['content'];
            $genTitle   = $aiResult['title'] ?: ($aiResult['title'] ?: $_ca_scrape_data['title']);

            // 이미지 삽입
            if (!empty($_ca['unsplash_api_key'])) {
                $imgs = _ca_get_images($genTitle ?: $_ca_scrape_data['title'], $_ca['unsplash_api_key'], 3);
                if (!empty($imgs)) $genContent = _ca_inject_images($genContent, $imgs);
            }

            $_ca_result = ['title' => $genTitle, 'content' => $genContent];
        }
    }
}

// ===== 발행 처리 =====
$_ca_publish_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ca_publish'])) {
    $pubTitle   = trim($_POST['pub_title'] ?? '');
    $pubContent = trim($_POST['pub_content'] ?? '');
    $pubBoard   = trim($_POST['pub_board_id'] ?? $_ca['board_id'] ?? '');

    if (empty($pubTitle) || empty($pubContent) || empty($pubBoard)) {
        $_ca_publish_msg = '<div class="ca-alert ca-err">제목, 내용, 게시판을 모두 확인해 주세요.</div>';
    } else {
        try {
            $prefix = DB::getPrefix();
            $slug   = preg_replace('/[^\p{L}\p{N}]+/u', '-', $pubTitle);
            $slug   = trim($slug, '-') ?: 'post-' . time();
            $postId = DB::insert("{$prefix}posts", [
                'board_id'   => $pubBoard,
                'member_id'  => 1,
                'title'      => $pubTitle,
                'content'    => $pubContent,
                'slug'       => $slug,
                'is_notice'  => 0,
                'is_hidden'  => 0,
                'is_secret'  => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($postId) {
                $_ca_publish_msg = '<div class="ca-alert ca-ok">✅ 게시판에 발행 완료! <a href="/board/' . htmlspecialchars($pubBoard) . '/' . $postId . '" target="_blank" style="color:#16a34a;font-weight:700">글 확인하기</a></div>';
                if (class_exists('Cache')) Cache::flush();
            } else {
                $_ca_publish_msg = '<div class="ca-alert ca-err">발행 중 오류가 발생했습니다.</div>';
            }
        } catch (Exception $e) {
            $_ca_publish_msg = '<div class="ca-alert ca-err">오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

$_allBoards = class_exists('Board') ? Board::listAll(true) : [];
?>
<style>
.ca-wrap { max-width:900px; }
.ca-section { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px; margin-bottom:16px; }
.ca-section h3 { margin:0 0 16px; font-size:15px; font-weight:700; color:#111827; display:flex; align-items:center; gap:8px; }
.ca-row { margin-bottom:14px; }
.ca-row label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
.ca-row input[type=text],.ca-row input[type=password],.ca-row input[type=url],.ca-row select {
    width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; box-sizing:border-box;
}
.ca-row input:focus,.ca-row textarea:focus,.ca-row select:focus { outline:none; border-color:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.15); }
.ca-flex { display:flex; gap:8px; align-items:center; }
.ca-flex input { flex:1; }
.ca-btn { padding:8px 18px; border:1px solid #d1d5db; border-radius:6px; background:#fff; font-size:13px; cursor:pointer; font-weight:600; white-space:nowrap; }
.ca-btn:hover { background:#f9fafb; }
.ca-btn-green { background:#22c55e; border-color:#22c55e; color:#fff; }
.ca-btn-green:hover { background:#16a34a; border-color:#16a34a; }
.ca-btn-lg { padding:11px 28px; font-size:14px; }
.ca-small { font-size:11px; color:#9ca3af; margin-top:4px; }
.ca-result-box { background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:16px; font-size:13px; }
.ca-result-box h4 { margin:0 0 10px; font-size:13px; font-weight:700; color:#374151; }
.ca-tag { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; margin-right:4px; }
.ca-alert { padding:12px 16px; border-radius:8px; font-size:13px; font-weight:600; margin-bottom:14px; }
.ca-ok { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.ca-err { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; font-weight:400; line-height:1.8; }
.ca-warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
.ca-result-content { border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#fff; font-size:13px; line-height:1.8; max-height:500px; overflow-y:auto; }
.ca-result-content h2 { font-size:16px; font-weight:700; margin:20px 0 8px; color:#111827; }
.ca-result-content h3 { font-size:14px; font-weight:700; margin:16px 0 6px; color:#374151; }
.ca-result-content p { margin:0 0 12px; color:#374151; }
.ca-toggle { display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; font-weight:600; color:#6b7280; margin-bottom:12px; }
.ca-result { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:20px; margin-bottom:16px; }
.ca-result h3 { margin:0 0 14px; font-size:15px; font-weight:700; color:#166534; }
.ca-scraped-info { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
</style>

<div class="ca-wrap">

<?php if (!empty($_ca_publish_msg)) echo $_ca_publish_msg; ?>

<!-- API 설정 -->
<form method="post">
<input type="hidden" name="ca_save" value="1">
<div class="ca-section">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
        API 설정
    </h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="ca-row">
            <label>OpenRouter API 키 *</label>
            <div class="ca-flex">
                <input type="password" name="openai_api_key" id="ca_openai_key" value="<?= htmlspecialchars($_ca['openai_api_key']) ?>" placeholder="sk-or-v1-...">
                <button type="button" class="ca-btn" onclick="caTestOpenai()">테스트</button>
                <span id="ca_res_openai" style="font-size:12px;font-weight:700;min-width:36px"></span>
            </div>
        </div>
        <div class="ca-row">
            <label>Unsplash API 키 (이미지 자동 삽입)</label>
            <div class="ca-flex">
                <input type="text" name="unsplash_api_key" id="ca_unsplash_key" value="<?= htmlspecialchars($_ca['unsplash_api_key']) ?>" placeholder="Access Key">
                <button type="button" class="ca-btn" onclick="caTestUnsplash()">테스트</button>
                <span id="ca_res_unsplash" style="font-size:12px;font-weight:700;min-width:36px"></span>
            </div>
        </div>
    </div>

    <div class="ca-row">
        <label>기본 발행 게시판</label>
        <select name="board_id">
            <option value="">-- 매번 선택 --</option>
            <?php foreach ($_allBoards as $b): ?>
            <option value="<?= htmlspecialchars($b['board_id']) ?>" <?= $_ca['board_id'] === $b['board_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['title']) ?> (<?= htmlspecialchars($b['board_id']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="ca-row">
        <label>AI 프롬프트 (경쟁사 압도 콘텐츠 생성 지시문)</label>
        <textarea name="prompt" rows="14" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-family:monospace;resize:vertical;box-sizing:border-box"><?= htmlspecialchars($_ca['prompt']) ?></textarea>
        <div class="ca-small">변수: <code style="background:#f3f4f6;padding:1px 4px;border-radius:3px">{competitor_title}</code> <code style="background:#f3f4f6;padding:1px 4px;border-radius:3px">{competitor_headings}</code> <code style="background:#f3f4f6;padding:1px 4px;border-radius:3px">{competitor_content}</code></div>
    </div>

    <button type="submit" class="ca-btn ca-btn-green">설정 저장</button>
</div>
</form>

<!-- 분석 실행 -->
<div class="ca-section">
    <h3>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        경쟁사 분석 실행
    </h3>

    <!-- 일반 모드 -->
    <form method="post" onsubmit="caShowLoading()">
        <input type="hidden" name="ca_analyze" value="1">
        <input type="hidden" name="manual_mode" value="0" id="ca_manual_hidden">

        <div id="ca_url_mode">
            <div class="ca-row">
                <label>경쟁사 URL</label>
                <input type="url" name="comp_url" id="ca_comp_url" value="<?= htmlspecialchars($_ca_comp_url) ?>" placeholder="https://competitor.com/post/..." style="font-size:14px;padding:10px 14px">
                <div class="ca-small">분석하고 싶은 경쟁사 글 주소를 붙여넣으세요.</div>
            </div>
        </div>

        <!-- 직접 입력 모드 (Cloudflare 등 차단 사이트용) -->
        <div id="ca_manual_mode" style="display:none">
            <div class="ca-alert ca-warn" style="margin-bottom:12px">🛡️ 직접 입력 모드: 경쟁사 사이트를 직접 방문하여 제목과 주요 내용을 아래에 입력해 주세요.</div>
            <div class="ca-row">
                <label>경쟁사 글 제목</label>
                <input type="text" name="manual_title" placeholder="경쟁사 글 제목 입력">
            </div>
            <div class="ca-row">
                <label>주요 소제목 (한 줄에 하나씩)</label>
                <textarea name="manual_headings" rows="4" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;resize:vertical;box-sizing:border-box" placeholder="서론&#10;방법 1&#10;방법 2&#10;결론"></textarea>
            </div>
            <div class="ca-row">
                <label>핵심 내용 요약</label>
                <textarea name="manual_content" rows="5" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;resize:vertical;box-sizing:border-box" placeholder="경쟁사 글의 핵심 내용을 간략히 입력하세요..."></textarea>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <button type="submit" class="ca-btn ca-btn-green ca-btn-lg" id="ca_analyze_btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:5px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                분석 시작
            </button>
            <button type="button" class="ca-btn" onclick="caToggleManual()">직접 입력 모드</button>
            <span id="ca_loading" style="display:none;font-size:13px;color:#6b7280">⏳ 분석 중... 30초~1분 소요됩니다.</span>
        </div>
    </form>
</div>

<!-- 오류 표시 -->
<?php if ($_ca_error): ?>
<div class="ca-alert ca-err">
    <?= nl2br(htmlspecialchars($_ca_error)) ?>
    <?php if (strpos($_ca_error, 'Cloudflare') !== false || strpos($_ca_error, 'JavaScript') !== false): ?>
    <div style="margin-top:10px">
        <button type="button" class="ca-btn ca-btn-green" onclick="caToggleManual(true)" style="font-size:12px;padding:5px 12px">직접 입력 모드로 전환</button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 분석 결과 -->
<?php if ($_ca_result): ?>
<div class="ca-result">
    <h3>✅ 콘텐츠 생성 완료 — 아래 내용을 확인 후 바로 발행하세요</h3>

    <!-- 스크래핑 정보 요약 -->
    <?php if ($_ca_scrape_data): ?>
    <div class="ca-scraped-info">
        <span class="ca-tag">경쟁사: <?= htmlspecialchars(mb_substr($_ca_scrape_data['title'] ?? '', 0, 30)) ?></span>
        <span class="ca-tag">소제목 <?= count($_ca_scrape_data['headings'] ?? []) ?>개 수집</span>
        <span class="ca-tag">본문 <?= number_format($_ca_scrape_data['word_count'] ?? 0) ?>자 수집</span>
    </div>
    <?php endif; ?>

    <!-- 미리보기 (기본 표시) -->
    <div style="margin-bottom:16px">
        <div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:8px">생성된 콘텐츠 미리보기</div>
        <div class="ca-result-content" style="max-height:400px">
            <?= $_ca_result['content'] ?>
        </div>
    </div>

    <!-- 발행 폼 (자동 입력) -->
    <form method="post">
        <input type="hidden" name="ca_publish" value="1">
        <input type="hidden" name="pub_content" value="<?= htmlspecialchars($_ca_result['content']) ?>">

        <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end">
            <div class="ca-row" style="margin-bottom:0">
                <label>제목 <span style="font-weight:400;color:#9ca3af">(수정 가능)</span></label>
                <input type="text" name="pub_title" value="<?= htmlspecialchars($_ca_result['title']) ?>" style="font-size:14px;font-weight:600;padding:10px 14px">
            </div>
            <div class="ca-row" style="margin-bottom:0">
                <label>게시판</label>
                <select name="pub_board_id" style="padding:10px 12px">
                    <option value="">-- 선택 --</option>
                    <?php foreach ($_allBoards as $b): ?>
                    <option value="<?= htmlspecialchars($b['board_id']) ?>" <?= $_ca['board_id'] === $b['board_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
            <button type="submit" class="ca-btn ca-btn-green ca-btn-lg">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:5px"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
                지금 바로 발행
            </button>
            <span style="font-size:12px;color:#9ca3af">제목만 확인하고 발행 버튼을 누르면 끝입니다.</span>
        </div>
    </form>
</div>
<?php endif; ?>

</div>

<script>
function caShowLoading() {
    var btn = document.getElementById('ca_analyze_btn');
    var loading = document.getElementById('ca_loading');
    if (btn) { btn.disabled = true; btn.innerHTML = '⏳ 분석 중...'; }
    if (loading) loading.style.display = 'inline';
}
function caToggleManual(force) {
    var urlMode    = document.getElementById('ca_url_mode');
    var manualMode = document.getElementById('ca_manual_mode');
    var hidden     = document.getElementById('ca_manual_hidden');
    var isManual   = force === true || manualMode.style.display === 'none';
    urlMode.style.display    = isManual ? 'none' : 'block';
    manualMode.style.display = isManual ? 'block' : 'none';
    hidden.value             = isManual ? '1' : '0';
}
function caTogglePreview() {
    var p = document.getElementById('ca_preview');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
function caTestOpenai() {
    var key = document.getElementById('ca_openai_key').value.trim();
    var res = document.getElementById('ca_res_openai');
    if (!key) { res.textContent = '키 없음'; res.style.color = '#dc2626'; return; }
    res.textContent = '...'; res.style.color = '#9ca3af';
    fetch('https://openrouter.ai/api/v1/models', { headers: { 'Authorization': 'Bearer ' + key } })
    .then(function(r) { res.textContent = r.ok ? '성공' : '실패'; res.style.color = r.ok ? '#22c55e' : '#dc2626'; })
    .catch(function() { res.textContent = '실패'; res.style.color = '#dc2626'; });
}
function caTestUnsplash() {
    var key = document.getElementById('ca_unsplash_key').value.trim();
    var res = document.getElementById('ca_res_unsplash');
    if (!key) { res.textContent = '키 없음'; res.style.color = '#dc2626'; return; }
    res.textContent = '...'; res.style.color = '#9ca3af';
    fetch('https://api.unsplash.com/search/photos?query=test&per_page=1&client_id=' + encodeURIComponent(key), { headers: { 'Accept-Version': 'v1' } })
    .then(function(r) { res.textContent = r.ok ? '성공' : '실패'; res.style.color = r.ok ? '#22c55e' : '#dc2626'; })
    .catch(function() { res.textContent = '실패'; res.style.color = '#dc2626'; });
}
</script>

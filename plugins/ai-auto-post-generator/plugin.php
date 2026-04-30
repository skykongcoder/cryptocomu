<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * AI 자동글 작성기 플러그인 v2.0
 *
 * OpenAI + Unsplash API를 사용해 자동으로 글을 작성하고 이미지를 추가합니다.
 * 설정한 간격마다 자동으로 글을 생성합니다.
 */

// ===== 설정 로드 =====
$_ai_config_file = __DIR__ . '/config.json';
$_ai_config_raw = file_exists($_ai_config_file)
    ? json_decode(file_get_contents($_ai_config_file), true)
    : [];
if (!is_array($_ai_config_raw)) $_ai_config_raw = [];

$_ai_config = array_merge([
    'openai_api_key' => '',
    'unsplash_api_key' => '',
    'boards' => [],
    'auto_enabled' => false,
    'interval_hours' => 6,
    'posts_per_run' => 1,
    'min_length' => 500,
    'max_length' => 1000,
    'tone' => 'informative',
    'custom_prompt' => '',
    'keywords' => [],
    'keyword_index' => 0,
    'must_include' => [],
    'auto_member' => true,
    'add_image' => true,
    'auto_mode' => false,
    'last_run' => '',
], $_ai_config_raw);

// ===== AI 글 생성 함수 =====
function _ai_generate_post($keyword, $config, $boardId = null) {
    if (empty($config['openai_api_key'])) {
        return ['success' => false, 'error' => 'OpenRouter API 키가 없습니다'];
    }

    // 게시판별 설정이 있으면 우선 적용 (boards_config[$boardId])
    $boardCfg = ($boardId && isset($config['boards_config'][$boardId])) ? $config['boards_config'][$boardId] : [];
    $boardPrompt = trim($boardCfg['custom_prompt'] ?? '');
    $boardTone   = trim($boardCfg['tone'] ?? '');

    // 프롬프트 우선순위: board custom_prompt > 글로벌 prompts 풀 > 기본
    if ($boardPrompt) {
        $selectedPrompt = $boardPrompt;
    } else {
        $prompts = $config['prompts'] ?? [];
        $prompts = array_filter($prompts, function ($p) { return trim($p) !== ''; });
        $selectedPrompt = !empty($prompts) ? $prompts[array_rand($prompts)] : '';
    }
    $effectiveTone = $boardTone ?: ($config['tone'] ?? 'informative');

    if ($selectedPrompt) {
        $prompt = str_replace(
            ['{keyword}', '{min_length}', '{max_length}', '{tone}', '{board_id}'],
            [$keyword, $config['min_length'], $config['max_length'], $effectiveTone, $boardId ?? ''],
            $selectedPrompt
        );
    } else {
        $prompt = "다음 키워드에 대해 SEO 최적화된 {$config['min_length']}자 이상 {$config['max_length']}자 이하의 고품질 글을 작성하세요.\n\n";
        $prompt .= "키워드: {$keyword}\n\n";
        $prompt .= "톤: {$effectiveTone}\n\n";
        $prompt .= "요구사항:\n";
        $prompt .= "- 자연스러운 한국어 문장\n";
        $prompt .= "- 키워드를 자연스럽게 2-3회 포함\n";
        $prompt .= "- 실용적이고 유용한 정보\n";
        $prompt .= "- 소제목을 5~7개 넣어 글의 구조를 명확하게 하세요\n";
        $prompt .= "- 사람이 직접 쓴 블로그 글처럼 자연스럽고 읽기 편하게 작성하세요\n";
        $prompt .= "- HTML 태그 사용 금지\n\n";
        $prompt .= "!!! 최우선 규칙 - 줄바꿈 !!!\n";
        $prompt .= "- 한 문장을 쓰고 반드시 빈 줄을 넣으세요\n";
        $prompt .= "- 절대로 2문장 이상을 붙여쓰지 마세요\n";
        $prompt .= "- 모든 문장 사이에 빈 줄이 있어야 합니다\n";
        $prompt .= "- 소제목 앞뒤로 빈 줄 2개씩 넣으세요\n";
        $prompt .= "- 이 규칙을 어기면 글 전체가 무효입니다";
    }

    // 삽입 링크 추가
    $insertLinks = $config['insert_links'] ?? [];
    if (!empty($insertLinks)) {
        $linkList = "";
        foreach ($insertLinks as $linkLine) {
            $parts = array_map('trim', explode('|', $linkLine, 2));
            $url = $parts[0] ?? '';
            $text = $parts[1] ?? $url;
            if ($url) $linkList .= "- {$text}: {$url}\n";
        }
        $prompt .= "\n\n다음 링크 중 1~2개를 골라서 본문 중간에 자연스럽게 삽입하세요. 형식은 [표시텍스트](URL) 마크다운 형식으로:\n" . $linkList;
    }

    // 제목 생성 지시 추가
    $prompt .= "\n\n중요: 반드시 첫 줄에 매력적이고 클릭하고 싶은 제목을 작성하세요. 제목은 매번 다르게, 키워드를 포함하되 다양한 표현을 사용하세요. 형식: 제목: ○○○○○\n그 다음 줄부터 본문을 작성하세요.";

    // 필수 포함 문구 추가
    $mustInclude = $config['must_include'] ?? [];
    if (!empty($mustInclude)) {
        $phrases = implode("\n", array_map(function ($p) { return "- " . $p; }, $mustInclude));
        $prompt .= "\n\n다음 문구를 반드시 자연스럽게 본문에 포함하세요:\n" . $phrases;
    }

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['openai_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'openai/gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.8,
            'max_tokens' => 2000,
        ]),
        CURLOPT_TIMEOUT => 90,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return ['success' => false, 'error' => 'OpenAI API 오류: ' . $http_code];
    }

    $data = json_decode($response, true);

    if (empty($data['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => '글 생성 실패'];
    }

    return [
        'success' => true,
        'content' => $data['choices'][0]['message']['content'],
    ];
}

// ===== Unsplash 이미지 검색 함수 (1~2장) =====
function _ai_get_unsplash_images($keyword, $api_key, $count = 2) {
    if (empty($api_key)) return [];

    // 한국어 키워드 → 영어로 간단 변환 (Unsplash는 영어 검색이 결과가 많음)
    $searchQuery = $keyword;
    if (preg_match('/[가-힣]/u', $keyword)) {
        // 흔한 키워드 매핑 + 범용 검색어 사용
        $searchQuery = preg_replace('/[가-힣]+/u', '', $keyword);
        $searchQuery = trim($searchQuery);
        if (empty($searchQuery)) {
            // 전부 한국어면 범용 검색어 사용
            $fallbacks = ['technology', 'business', 'website', 'computer', 'office', 'digital', 'communication', 'teamwork', 'creative', 'startup'];
            $searchQuery = $fallbacks[array_rand($fallbacks)];
        }
    }

    $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($searchQuery) . '&per_page=' . $count . '&client_id=' . urlencode($api_key);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept-Version: v1'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $images = [];

    if (!empty($data['results'])) {
        foreach ($data['results'] as $r) {
            if (!empty($r['urls']['regular'])) {
                $images[] = [
                    'url' => $r['urls']['regular'],
                    'photographer' => $r['user']['name'] ?? 'Unsplash',
                ];
            }
        }
    }
    return $images;
}

// ===== 가상 회원 선택 함수 =====
function _ai_get_virtual_member() {
    $nicknames = ['AI 기자', 'AI 작성팀', '스마트봇', '자동화 작성', '콘텐츠 AI'];
    $nickname = $nicknames[array_rand($nicknames)];

    $prefix = DB::getPrefix();
    $member = DB::fetch("SELECT id FROM {$prefix}members WHERE nickname = ?", [$nickname]);
    if ($member) return $member['id'];

    return DB::insert("{$prefix}members", [
        'user_id' => 'ai_' . strtolower(str_replace(' ', '_', $nickname)),
        'password' => password_hash('ai_auto_post_' . time(), PASSWORD_BCRYPT),
        'nickname' => $nickname,
        'email' => 'ai@nuriboard.local',
        'level' => 2,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

// ===== 자동 글 생성 실행 함수 =====
function _ai_auto_run($config, $configFile) {
    $globalKeywords = $config['keywords'] ?? [];
    if (empty($config['openai_api_key'])) return;

    $boards = $config['boards'] ?? [];
    if (empty($boards)) return;

    $postsPerRun = max(1, min(10, (int)($config['posts_per_run'] ?? 1)));
    $prefix = DB::getPrefix();
    $boardsConfig = $config['boards_config'] ?? [];

    for ($i = 0; $i < $postsPerRun; $i++) {
        // 게시판 랜덤 선택
        $boardId = $boards[array_rand($boards)];

        // 게시판별 키워드 우선 사용, 없으면 글로벌 키워드
        $boardKeywords = isset($boardsConfig[$boardId]['keywords']) && is_array($boardsConfig[$boardId]['keywords'])
            ? array_filter($boardsConfig[$boardId]['keywords'], fn($k) => trim($k) !== '')
            : [];
        $keywords = !empty($boardKeywords) ? array_values($boardKeywords) : $globalKeywords;
        if (empty($keywords)) continue;

        // 키워드 순서대로 선택 (게시판별 인덱스 분리)
        $idxKey = !empty($boardKeywords) ? "kwidx_{$boardId}" : 'keyword_index';
        $kwIndex = (int)($config[$idxKey] ?? 0);
        if ($kwIndex >= count($keywords)) $kwIndex = 0;
        $keyword = $keywords[$kwIndex];
        $config[$idxKey] = $kwIndex + 1;

        // 글 생성 (board별 prompt/tone 자동 적용)
        $result = _ai_generate_post($keyword, $config, $boardId);
        if (!$result['success']) continue;

        $content = $result['content'];

        // AI가 생성한 제목 추출
        $title = $keyword;
        if (preg_match('/^제목\s*[:：]\s*(.+)/m', $content, $tm)) {
            $title = trim($tm[1]);
            $content = trim(preg_replace('/^제목\s*[:：].+\n*/m', '', $content, 1));
        } elseif (preg_match('/^#\s*(.+)/m', $content, $tm)) {
            $title = trim($tm[1]);
            $content = trim(preg_replace('/^#\s*.+\n*/m', '', $content, 1));
        } elseif (preg_match('/^(.{10,50})\n/m', $content, $tm)) {
            $title = trim($tm[1]);
            $content = trim(substr($content, strlen($tm[0])));
        }

        // 줄바꿈을 HTML 단락으로 변환
        // 1단계: 빈 줄 기준으로 분리
        $paragraphs = array_filter(array_map('trim', preg_split('/\n{1,}/', $content)));

        // 2단계: 긴 단락을 2문장 단위로 강제 분리
        $splitParagraphs = [];
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            // 문장 단위로 분리 (. ! ? 뒤에 공백이 오는 경우)
            $sentences = preg_split('/(?<=[.!?。])\s+/', $p);
            $chunk = '';
            $count = 0;
            foreach ($sentences as $s) {
                $s = trim($s);
                if ($s) $splitParagraphs[] = $s;
            }
        }

        $htmlParagraphs = array_map(function ($p) {
            $escaped = htmlspecialchars($p);
            // 마크다운 링크 [텍스트](URL) → HTML <a> 태그로 변환
            $escaped = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $escaped);
            return '<p>' . $escaped . '</p>';
        }, $splitParagraphs);

        // 이미지 추가 (단락 수에 따라 2~5장 랜덤, 균등 배치)
        if ($config['add_image'] && !empty($config['unsplash_api_key'])) {
            $totalP = count($htmlParagraphs);
            if ($totalP <= 8) $imgCount = 2;
            elseif ($totalP <= 15) $imgCount = rand(2, 3);
            elseif ($totalP <= 25) $imgCount = rand(3, 4);
            else $imgCount = rand(4, 5);

            $images = _ai_get_unsplash_images($keyword, $config['unsplash_api_key'], $imgCount);
            $imgCount = count($images);

            if ($imgCount > 0) {
                // 균등 간격으로 배치
                $step = max(1, (int)floor($totalP / ($imgCount + 1)));
                $inserted = 0;
                foreach ($images as $idx => $img) {
                    $imgHtml = '<p style="text-align:center;margin:24px 0"><img src="' . htmlspecialchars($img['url']) . '" alt="' . htmlspecialchars($keyword) . '" style="max-width:100%;height:auto;border-radius:8px"></p>';
                    $pos = min($step * ($idx + 1) + $inserted, count($htmlParagraphs));
                    array_splice($htmlParagraphs, $pos, 0, [$imgHtml]);
                    $inserted++;
                }
            }
        }

        $content = implode("\n", $htmlParagraphs);

        // 회원
        $memberId = $config['auto_member'] ? _ai_get_virtual_member() : 1;

        // 글 저장
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $title);
        $slug = trim($slug, '-') ?: 'post';

        DB::insert("{$prefix}posts", [
            'board_id' => $boardId,
            'member_id' => $memberId,
            'title' => $title,
            'content' => $content,
            'slug' => $slug,
            'hit' => 0,
            'comment_count' => 0,
            'is_notice' => 0,
            'is_hidden' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($i < $postsPerRun - 1) sleep(2);
    }

    // 마지막 실행 시간 + 키워드 인덱스 저장
    $config['last_run'] = date('Y-m-d H:i:s');
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if (class_exists('Cache')) Cache::flush();
}

// ===== 자동 실행 체크 (누구든 접속 시 시간 체크) =====
Plugin::addHook('after_header', function () {
    global $_ai_config, $_ai_config_file;

    if (empty($_ai_config['auto_enabled'])) return;
    if (empty($_ai_config['openai_api_key'])) return;
    if (empty($_ai_config['keywords'])) return;
    if (empty($_ai_config['boards'])) return;

    $interval = max(1, (int)($_ai_config['interval_hours'] ?? 6));
    $lastRun = $_ai_config['last_run'] ?? '';

    if ($lastRun) {
        $nextRun = strtotime($lastRun) + ($interval * 3600);
        if (time() < $nextRun) return;
    }

    _ai_auto_run($_ai_config, $_ai_config_file);
}, 99);

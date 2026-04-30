<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * AI 토픽 빌더 플러그인 v1.0
 *
 * 주제 입력 → AI가 필러-클러스터 구조 설계 →
 * 간격을 두고 순차적으로 글 자동 생성/발행
 * OpenAI + Gemini 지원
 */

// ===== 설정 파일 경로 (플러그인 삭제해도 유지되도록 data/ 에 저장) =====
if (!function_exists('_tb_data_dir')) {
    function _tb_data_dir(): string {
        $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
        $dir = $base . '/data/ai-topic-builder';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // 구버전 호환: 플러그인 폴더에 있던 기존 파일을 data/ 로 이관 (1회성)
        foreach (['config.json', 'queue.json'] as $f) {
            $old = __DIR__ . '/' . $f;
            $new = $dir . '/' . $f;
            if (file_exists($old) && !file_exists($new)) {
                @copy($old, $new);
            }
        }
        return $dir;
    }
}
$_tb_config_file = _tb_data_dir() . '/config.json';
$_tb_queue_file  = _tb_data_dir() . '/queue.json';

$_tb_config_raw = file_exists($_tb_config_file) ? json_decode(file_get_contents($_tb_config_file), true) : [];
if (!is_array($_tb_config_raw)) $_tb_config_raw = [];

$_tb_config = array_merge([
    'ai_provider' => 'openai',
    'openai_api_key' => '',
    'openai_model' => 'openai/gpt-4o-mini',
    'image_source' => 'unsplash',
    'unsplash_api_key' => '',
    'image_enabled' => '1',
    'images_per_post' => 'auto',
    'interval_minutes' => 30,
    'promo_links' => [],
    'promo_links_per_post' => '0-2',
    'auto_mode' => false,
    'last_run' => '',
    'total_generated' => 0,
    // === 자동조종(autopilot) 설정 ===
    'autopilot' => false,                      // 완전 자동 ON/OFF
    'autopilot_boards' => [],                  // 대상 게시판 (board_id 배열)
    'autopilot_refill_count' => 2,             // 큐 비었을 때 생성할 프로젝트 수
    'autopilot_cluster_count' => 10,           // 프로젝트당 클러스터 수
    'autopilot_default_style' => '정보형',      // 기본 글 스타일
    'autopilot_daily_limit' => 20,             // 일일 발행 한도
    'autopilot_monthly_limit' => 300,          // 월간 발행 한도
    'autopilot_refill_cooldown_hours' => 6,    // 리필 최소 간격 (시간)
    'autopilot_dup_threshold' => 70,           // 중복 방지 유사도 (0-100)
    // === 자동 카운터 ===
    'autopilot_last_refill' => '',             // 마지막 리필 시각
    'autopilot_daily_posted' => 0,
    'autopilot_daily_date' => '',
    'autopilot_monthly_posted' => 0,
    'autopilot_monthly_month' => '',
    'autopilot_last_error' => '',
    'autopilot_error_at' => '',
], $_tb_config_raw);

// ===== 큐 읽기/쓰기 (data/ai-topic-builder/queue.json) =====
function _tb_read_queue() {
    $file = _tb_data_dir() . '/queue.json';
    if (!file_exists($file)) return ['projects' => []];
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) $data = ['projects' => []];
    if (!isset($data['projects'])) $data['projects'] = [];
    return $data;
}

function _tb_write_queue($data) {
    $file = _tb_data_dir() . '/queue.json';
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ===== AI 호출 통합 함수 (OpenAI) =====
function _tb_call_ai($prompt, $config, $maxTokens = 2500, $jsonMode = false) {
    return _tb_call_openai($prompt, $config, $maxTokens, $jsonMode);
}

// ===== OpenAI 호출 =====
function _tb_call_openai($prompt, $config, $maxTokens, $jsonMode) {
    if (empty($config['openai_api_key'])) {
        return ['success' => false, 'error' => 'OpenRouter API 키가 없습니다'];
    }

    $body = [
        'model' => $config['openai_model'] ?? 'openai/gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.8,
        'max_tokens' => $maxTokens,
    ];
    if ($jsonMode) {
        $body['response_format'] = ['type' => 'json_object'];
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
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 120,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "OpenAI API 오류: HTTP $httpCode"];
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    if (empty($content)) {
        return ['success' => false, 'error' => 'OpenAI 응답 비어있음'];
    }

    return ['success' => true, 'content' => $content];
}

// ===== Gemini 호출 =====
function _tb_call_gemini($prompt, $config, $maxTokens, $jsonMode) {
    if (empty($config['gemini_api_key'])) {
        return ['success' => false, 'error' => 'Gemini API 키가 없습니다'];
    }

    $model = $config['gemini_model'] ?? 'gemini-2.0-flash';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($config['gemini_api_key']);

    $genConfig = [
        'temperature' => 0.8,
        'maxOutputTokens' => $maxTokens,
    ];
    if ($jsonMode) {
        $genConfig['responseMimeType'] = 'application/json';
    }

    $body = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => $genConfig,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 120,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errData = json_decode($response, true);
        $errMsg = $errData['error']['message'] ?? $response;
        return ['success' => false, 'error' => "Gemini API 오류(HTTP $httpCode) 모델={$model}: " . mb_substr($errMsg, 0, 300)];
    }

    $data = json_decode($response, true);
    $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($content)) {
        $finishReason = $data['candidates'][0]['finishReason'] ?? '';
        return ['success' => false, 'error' => 'Gemini 응답 비어있음 (finishReason: ' . $finishReason . ')'];
    }

    return ['success' => true, 'content' => $content];
}

// ===== Unsplash 이미지 검색 =====
function _tb_get_unsplash_images($keyword, $apiKey, $count = 2) {
    if (empty($apiKey)) return [];

    // 한글 키워드 영어로 변환 (Unsplash는 영어 검색이 결과 좋음)
    $searchQuery = $keyword;
    if (preg_match('/[가-힣]/u', $keyword)) {
        $searchQuery = trim(preg_replace('/[가-힣]+/u', '', $keyword));
        if (empty($searchQuery)) {
            $fallbacks = ['lifestyle', 'technology', 'business', 'nature', 'urban', 'creative', 'abstract', 'professional', 'modern', 'minimal'];
            $searchQuery = $fallbacks[array_rand($fallbacks)];
        }
    }

    $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($searchQuery) . '&per_page=' . $count . '&orientation=landscape&client_id=' . urlencode($apiKey);

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
                    'alt' => $r['alt_description'] ?? $keyword,
                    'photographer' => $r['user']['name'] ?? 'Unsplash',
                    'profile' => $r['user']['links']['html'] ?? '',
                ];
            }
        }
    }
    return $images;
}

// ===== DALL-E 이미지 생성 =====
function _tb_generate_dalle_image($prompt, $apiKey) {
    if (empty($apiKey)) return null;

    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard',
        ]),
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;
    $data = json_decode($response, true);
    $url = $data['data'][0]['url'] ?? null;
    return $url ? ['url' => $url, 'alt' => $prompt, 'photographer' => 'DALL-E 3', 'profile' => ''] : null;
}

// ===== 글에 이미지 삽입 (H2 뒤마다 분산 배치) =====
function _tb_inject_images_html($html, $images) {
    if (empty($images)) return $html;

    // H2 기준으로 분할
    $parts = preg_split('/(<h2[^>]*>.*?<\/h2>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    // [0: 머리부분, 1: H2, 2: 내용, 3: H2, 4: 내용 ...]

    $imgIdx = 0;
    $totalImages = count($images);
    $h2Count = 0;
    foreach ($parts as $p) {
        if (preg_match('/<h2/', $p)) $h2Count++;
    }

    // H2 개수가 이미지보다 적으면 모든 H2 뒤에 하나씩, 아니면 균등 배치
    $result = '';
    $h2Seen = 0;
    foreach ($parts as $i => $p) {
        $result .= $p;
        // H2 태그면, 그 뒤에 이미지 삽입 (단, 이미지 인덱스 안 넘음)
        if (preg_match('/<h2/', $p) && $imgIdx < $totalImages) {
            $h2Seen++;
            if ($h2Count > 0) {
                $expectedIdx = (int)floor(($h2Seen - 1) * $totalImages / max(1, $h2Count));
                if ($expectedIdx === $imgIdx) {
                    $img = $images[$imgIdx];
                    $result .= "\n" . '<figure style="margin:20px 0;text-align:center">'
                            . '<img src="' . htmlspecialchars($img['url']) . '" alt="' . htmlspecialchars($img['alt'] ?: '') . '" style="max-width:100%;height:auto;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
                            . '</figure>' . "\n";
                    $imgIdx++;
                }
            }
        }
    }

    // 남은 이미지는 본문 끝에 붙임
    while ($imgIdx < $totalImages) {
        $img = $images[$imgIdx];
        $result .= "\n" . '<figure style="margin:20px 0;text-align:center">'
                . '<img src="' . htmlspecialchars($img['url']) . '" alt="' . htmlspecialchars($img['alt'] ?: '') . '" style="max-width:100%;height:auto;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
                . '</figure>' . "\n";
        $imgIdx++;
    }

    return $result;
}

// ===== 이미지 개수 결정 =====
function _tb_decide_image_count($config, $isPillar) {
    $setting = $config['images_per_post'] ?? 'auto';
    if ($setting === 'auto') {
        return $isPillar ? 3 : 2;  // 필러 3장, 클러스터 2장
    }
    return max(0, min(10, (int)$setting));
}

// ===== 토픽 맵 생성 (AI로 필러+클러스터 구조 설계) =====
function _tb_design_topic_map($topic, $clusterCount, $style, $config) {
    $prompt = "당신은 SEO 전문가입니다. 다음 주제로 웹사이트 콘텐츠 전략을 설계하세요.\n\n";
    $prompt .= "주제: {$topic}\n";
    $prompt .= "스타일: {$style}\n";
    $prompt .= "클러스터 글 개수: {$clusterCount}개\n\n";
    $prompt .= "요구사항:\n";
    $prompt .= "1. 필러 글(pillar) 1개: 주제 전체를 포괄하는 큰 가이드\n";
    $prompt .= "2. 클러스터 글(cluster) {$clusterCount}개: 필러의 세부 주제들\n";
    $prompt .= "3. 각 글은 서로 겹치지 않고 SEO 키워드 경쟁을 피해야 함\n";
    $prompt .= "4. 각 클러스터는 필러와 의미적으로 연결되어야 함\n\n";
    $prompt .= "반드시 다음 JSON 형식으로만 응답하세요 (다른 설명 없이):\n";
    $prompt .= "{\n";
    $prompt .= "  \"pillar\": {\n";
    $prompt .= "    \"title\": \"필러 글 제목 (매력적이고 SEO에 좋은)\",\n";
    $prompt .= "    \"keyword\": \"핵심 키워드\",\n";
    $prompt .= "    \"description\": \"이 글에서 다룰 내용 요약 (2-3문장)\"\n";
    $prompt .= "  },\n";
    $prompt .= "  \"clusters\": [\n";
    $prompt .= "    {\n";
    $prompt .= "      \"title\": \"클러스터 글 제목\",\n";
    $prompt .= "      \"keyword\": \"롱테일 키워드\",\n";
    $prompt .= "      \"description\": \"이 글에서 다룰 내용 요약 (1-2문장)\"\n";
    $prompt .= "    }\n";
    $prompt .= "  ]\n";
    $prompt .= "}";

    $result = _tb_call_ai($prompt, $config, 3000, true);
    if (!$result['success']) return $result;

    // JSON 파싱
    $content = $result['content'];
    // 혹시 코드블록 감싼 경우 제거
    $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));

    $map = json_decode($content, true);
    if (!is_array($map) || empty($map['pillar']) || !isset($map['clusters'])) {
        return ['success' => false, 'error' => 'AI가 올바른 형식으로 응답하지 않았습니다'];
    }

    return ['success' => true, 'map' => $map];
}

// ===== 개별 글 생성 =====
function _tb_generate_article($item, $topic, $style, $pillarUrl, $clusterLinks, $config) {
    $isPillar = ($item['type'] === 'pillar');
    $title = $item['title'];
    $keyword = $item['keyword'];
    $description = $item['description'];

    $targetLength = $isPillar ? 2500 : 1500;

    $prompt = "당신은 한국어로 글을 쓰는 SEO 전문 블로거입니다.\n\n";
    $prompt .= "주제: {$topic}\n";
    $prompt .= "글 스타일: {$style}\n";
    $prompt .= ($isPillar ? "글 유형: 필러 글 (주제 전체를 포괄하는 완벽 가이드)\n" : "글 유형: 클러스터 글 (세부 주제 심층 다루기)\n");
    $prompt .= "제목: {$title}\n";
    $prompt .= "핵심 키워드: {$keyword}\n";
    $prompt .= "내용 요약: {$description}\n";
    $prompt .= "글자 수: 약 {$targetLength}자\n\n";

    $prompt .= "작성 규칙:\n";
    $prompt .= "1. 자연스러운 한국어, 사람이 쓴 블로그처럼\n";
    $prompt .= "2. 키워드를 본문에 자연스럽게 3-5회 포함\n";
    $prompt .= "3. H2 소제목 5개 이상 (## 로 표시)\n";
    $prompt .= "4. 각 문단은 2-3문장으로 짧게\n";
    $prompt .= "5. 모든 문장 사이 빈 줄 필수\n";
    $prompt .= "6. HTML 태그 금지 (마크다운만)\n";
    $prompt .= "7. 글 마지막에 FAQ 3개 추가 (## 자주 묻는 질문 섹션)\n\n";

    if ($isPillar) {
        $prompt .= "필러 글 특화 규칙:\n";
        $prompt .= "- 전체 개요 → 핵심 섹션 → 요약 구조\n";
        $prompt .= "- 다양한 세부 주제를 골고루 언급\n";
        $prompt .= "- 깊이보다는 넓이 중심\n\n";
    } else {
        $prompt .= "클러스터 글 특화 규칙:\n";
        $prompt .= "- 한 가지 세부 주제만 깊게 파기\n";
        $prompt .= "- 구체적인 팁/방법/예시 포함\n";
        $prompt .= "- 실용적 정보 중심\n\n";
    }

    // 광고 링크 랜덤 선택 후 프롬프트에 주입
    $promoLinks = $config['promo_links'] ?? [];
    $promoLinks = array_filter($promoLinks, function($l) {
        return !empty($l['anchor']) && !empty($l['url']);
    });
    if (!empty($promoLinks)) {
        $linksPerPost = $config['promo_links_per_post'] ?? '0-2';
        // 0 포함 옵션 → 일부 글은 링크 없음
        if ($linksPerPost === '0-1') $pickCount = rand(0, 1);
        elseif ($linksPerPost === '0-2') $pickCount = rand(0, 2);
        elseif ($linksPerPost === '0-3') $pickCount = rand(0, 3);
        elseif ($linksPerPost === '1-2') $pickCount = rand(1, 2);
        elseif ($linksPerPost === '2-3') $pickCount = rand(2, 3);
        elseif ($linksPerPost === '3-5') $pickCount = rand(3, 5);
        elseif ($linksPerPost === '0') $pickCount = 0;
        else $pickCount = max(0, (int)$linksPerPost);

        if ($pickCount > 0) {
            shuffle($promoLinks);
            $selected = array_slice($promoLinks, 0, min($pickCount, count($promoLinks)));

            $linkList = '';
            foreach ($selected as $l) {
                $linkList .= "- {$l['anchor']}: {$l['url']}\n";
            }
            $prompt .= "\n\n중요 - 외부 링크 자연스럽게 삽입:\n";
            $prompt .= "다음 링크를 본문 중간에 자연스럽게 녹여서 삽입하세요. 형식: [앵커텍스트](URL) 마크다운 형식.\n";
            $prompt .= "광고처럼 보이지 않도록 본문 흐름에 자연스럽게 연결하세요.\n";
            $prompt .= "이 링크들은 반드시 모두 포함해야 합니다:\n" . $linkList;
        }
        // pickCount === 0이면 링크 안 넣음 (일부 글은 자연스럽게 링크 없음)
    }

    $prompt .= "\n\n반드시 다음 형식으로 응답하세요:\n";
    $prompt .= "제목: (이 글의 최종 제목)\n";
    $prompt .= "요약: (TL;DR 2-3문장 요약)\n";
    $prompt .= "(빈 줄)\n";
    $prompt .= "(본문 시작 — 위 규칙대로)";

    $result = _tb_call_ai($prompt, $config, 4000, false);
    return $result;
}

// ===== AI 응답을 파싱해서 제목/요약/본문 분리 =====
function _tb_parse_article($rawContent) {
    $title = '';
    $summary = '';
    $body = $rawContent;

    // 제목 추출
    if (preg_match('/^제목\s*[:：]\s*(.+)$/m', $rawContent, $m)) {
        $title = trim($m[1]);
        $body = preg_replace('/^제목\s*[:：].+\n?/m', '', $body, 1);
    }

    // 요약 추출
    if (preg_match('/^요약\s*[:：]\s*(.+(?:\n(?!##|제목|요약).+)*)/m', $body, $m)) {
        $summary = trim($m[1]);
        $body = preg_replace('/^요약\s*[:：].+(?:\n(?!##|제목).+)*\n?/m', '', $body, 1);
    }

    return ['title' => $title, 'summary' => $summary, 'body' => trim($body)];
}

// ===== 마크다운/텍스트를 HTML로 변환 =====
function _tb_to_html($summary, $body) {
    $html = '';

    // TL;DR 요약 박스
    if (!empty($summary)) {
        $html .= '<div style="background:#f0f9ff;border-left:4px solid #3b82f6;padding:16px 20px;margin-bottom:24px;border-radius:4px">';
        $html .= '<strong style="color:#1e40af;display:block;margin-bottom:8px;font-size:14px">📌 한 줄 요약</strong>';
        $html .= '<div style="color:#334155;line-height:1.7">' . nl2br(htmlspecialchars($summary)) . '</div>';
        $html .= '</div>';
    }

    // 본문 처리
    $lines = explode("\n", $body);
    $html .= "\n";
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // H2
        if (preg_match('/^##\s+(.+)/', $line, $m)) {
            $html .= '<h2 style="font-size:20px;font-weight:700;margin:32px 0 16px;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:8px">' . htmlspecialchars($m[1]) . '</h2>' . "\n";
            continue;
        }
        // H3
        if (preg_match('/^###\s+(.+)/', $line, $m)) {
            $html .= '<h3 style="font-size:16px;font-weight:600;margin:20px 0 12px;color:#334155">' . htmlspecialchars($m[1]) . '</h3>' . "\n";
            continue;
        }
        // 리스트
        if (preg_match('/^[-*]\s+(.+)/', $line, $m)) {
            $html .= '<p style="margin:4px 0 4px 16px">• ' . htmlspecialchars($m[1]) . '</p>' . "\n";
            continue;
        }
        // 일반 단락
        $escaped = htmlspecialchars($line);
        // 마크다운 링크 → HTML
        $escaped = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" style="color:#2563eb;text-decoration:underline">$1</a>', $escaped);
        $html .= '<p style="line-height:1.8;margin:12px 0">' . $escaped . '</p>' . "\n";
    }

    return $html;
}

// ===== 가상 회원 가져오기/생성 =====
function _tb_get_ai_member() {
    $prefix = DB::getPrefix();
    $nickname = 'AI 큐레이터';
    $member = DB::fetch("SELECT id FROM {$prefix}members WHERE nickname = ?", [$nickname]);
    if ($member) return $member['id'];

    return DB::insert("{$prefix}members", [
        'user_id' => 'ai_topic_builder',
        'password' => password_hash('topic_builder_' . time(), PASSWORD_BCRYPT),
        'nickname' => $nickname,
        'email' => 'topic@nuriboard.local',
        'level' => 2,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

// ===== 게시글 발행 (DB 저장) =====
function _tb_publish_post($title, $htmlContent, $boardId) {
    $prefix = DB::getPrefix();
    $memberId = _tb_get_ai_member();

    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $title);
    $slug = trim($slug, '-') ?: 'post-' . time();

    $postId = DB::insert("{$prefix}posts", [
        'board_id' => $boardId,
        'member_id' => $memberId,
        'title' => $title,
        'content' => $htmlContent,
        'slug' => $slug,
        'hit' => 0,
        'comment_count' => 0,
        'is_notice' => 0,
        'is_hidden' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    return $postId;
}

// ===== 내부링크 자동 연결: 필러 글에 클러스터 링크 섹션 추가 =====
function _tb_update_pillar_links($pillarPostId, $pillarBoardId, $clusters) {
    if (!$pillarPostId || empty($clusters)) return;

    $prefix = DB::getPrefix();
    $pillar = DB::fetch("SELECT content FROM {$prefix}posts WHERE id = ?", [$pillarPostId]);
    if (!$pillar) return;

    $links = '<div style="background:#fefce8;border:1px solid #fde047;padding:20px;margin-top:32px;border-radius:8px">';
    $links .= '<strong style="color:#854d0e;display:block;margin-bottom:12px;font-size:15px">📚 이 주제의 세부 가이드</strong>';
    $links .= '<ul style="list-style:none;padding:0;margin:0">';
    foreach ($clusters as $c) {
        if (empty($c['post_id'])) continue;
        $url = '/board/' . $c['board_id'] . '/' . $c['post_id'];
        $links .= '<li style="margin:8px 0"><a href="' . htmlspecialchars($url) . '" style="color:#2563eb;text-decoration:none;font-weight:500">→ ' . htmlspecialchars($c['title']) . '</a></li>';
    }
    $links .= '</ul></div>';

    // 기존 링크 섹션 제거하고 새로 추가
    $content = preg_replace('/<div style="background:#fefce8[^>]*>.*?<\/div>/s', '', $pillar['content']);
    $content = trim($content) . "\n" . $links;

    DB::query("UPDATE {$prefix}posts SET content = ? WHERE id = ?", [$content, $pillarPostId]);
}

// ===== 관련글 블록 제거 (마커 기반 + 레거시 폴백) =====
function _tb_strip_related_blocks($content) {
    $content = preg_replace('/<!--tb-related-start-->.*?<!--tb-related-end-->/s', '', $content);
    // 레거시: _tb_update_pillar_links 가 남긴 #fefce8 박스
    $content = preg_replace('/<div style="background:#fefce8[^>]*>.*?<\/div>\s*<\/div>/s', '', $content);
    $content = preg_replace('/<div style="background:#fefce8[^>]*>.*?<\/div>/s', '', $content);
    // 레거시: _tb_append_pillar_link 가 남긴 #f0fdf4 박스
    $content = preg_replace('/<div style="background:#f0fdf4[^>]*>.*?<\/div>\s*<\/div>/s', '', $content);
    $content = preg_replace('/<div style="background:#f0fdf4[^>]*>.*?<\/div>/s', '', $content);
    // 레거시: data-tb-related 속성 기반 블록 (중첩 2단계까지)
    $content = preg_replace('/<div[^>]*data-tb-related="1"[^>]*>.*?<\/div>\s*<\/div>/s', '', $content);
    $content = preg_replace('/<div[^>]*data-tb-related="1"[^>]*>.*?<\/div>/s', '', $content);
    return $content;
}

// ===== 큐 프로젝트 기준으로 관련글 링크 재적용 =====
// 발행된 필러/클러스터 글을 DB에서 읽어 마커 기반으로 재삽입
function _tb_apply_project_links($project) {
    if (empty($project['board_id'])) return 0;
    $prefix = DB::getPrefix();
    $fallbackBoardId = $project['board_id'];

    // 발행된 클러스터 수집 - board_id/title 모두 DB에서 실제 값 가져오기
    $clusters = [];
    foreach (($project['items'] ?? []) as $it) {
        if (($it['type'] ?? '') !== 'cluster') continue;
        if (empty($it['post_id'])) continue;
        $row = DB::fetch("SELECT id, board_id, title, content FROM {$prefix}posts WHERE id = ?", [$it['post_id']]);
        if (!$row) continue; // DB에 없으면 skip (삭제된 글)
        $clusters[] = [
            'id' => $row['id'],
            'board_id' => $row['board_id'] ?: $fallbackBoardId, // DB 우선, 없으면 폴백
            'title' => $row['title'] ?: ($it['title'] ?? ''),
            'content' => $row['content'],
        ];
    }

    $group = ['clusters' => $clusters];

    // 필러 로드 - DB에서 실제 board_id/title 가져오기 (404 방지 핵심)
    $pillarPostId = (int)($project['pillar_post_id'] ?? 0);
    if ($pillarPostId) {
        $prow = DB::fetch("SELECT id, board_id, title, content FROM {$prefix}posts WHERE id = ?", [$pillarPostId]);
        if ($prow) {
            $group['pillar'] = [
                'id' => $prow['id'],
                'board_id' => $prow['board_id'] ?: $fallbackBoardId,
                'title' => $prow['title'] ?: ($project['pillar_title'] ?? ''),
                'content' => $prow['content'],
            ];
        }
        // DB에 없으면 pillar 생성 안 함 → 404 링크 달리지 않음
    }

    return _tb_apply_internal_links($group);
}

// ===== 클러스터 글 본문에 필러 링크 첨부 =====
function _tb_append_pillar_link($clusterContent, $pillarTitle, $pillarBoardId, $pillarPostId) {
    if (!$pillarPostId) return $clusterContent;
    $url = '/board/' . $pillarBoardId . '/' . $pillarPostId;
    $link = '<div style="background:#f0fdf4;border-left:4px solid #22c55e;padding:16px 20px;margin-top:32px;border-radius:4px">';
    $link .= '<strong style="color:#166534;display:block;margin-bottom:8px;font-size:14px">🎯 전체 가이드 보기</strong>';
    $link .= '<a href="' . htmlspecialchars($url) . '" style="color:#15803d;text-decoration:underline;font-weight:500">' . htmlspecialchars($pillarTitle) . '</a>';
    $link .= '</div>';
    return $clusterContent . "\n" . $link;
}

// ===== 자동조종 카운터 리셋 (일/월 경계 감지) =====
function _tb_autopilot_reset_counters_if_needed(&$config) {
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    $changed = false;
    if (($config['autopilot_daily_date'] ?? '') !== $today) {
        $config['autopilot_daily_date'] = $today;
        $config['autopilot_daily_posted'] = 0;
        $changed = true;
    }
    if (($config['autopilot_monthly_month'] ?? '') !== $thisMonth) {
        $config['autopilot_monthly_month'] = $thisMonth;
        $config['autopilot_monthly_posted'] = 0;
        $changed = true;
    }
    return $changed;
}

// ===== 자동조종 한도 체크 (true = 더 발행 가능, false = 한도 초과) =====
function _tb_autopilot_can_publish($config) {
    if (empty($config['autopilot'])) return true; // autopilot OFF면 한도 미적용
    $dailyLimit = (int)($config['autopilot_daily_limit'] ?? 20);
    $monthlyLimit = (int)($config['autopilot_monthly_limit'] ?? 300);
    if ($dailyLimit > 0 && (int)($config['autopilot_daily_posted'] ?? 0) >= $dailyLimit) return false;
    if ($monthlyLimit > 0 && (int)($config['autopilot_monthly_posted'] ?? 0) >= $monthlyLimit) return false;
    return true;
}

// ===== 큐 처리: 다음 발행할 글 1개 생성 =====
function _tb_process_queue($config, $configFile) {
    $queueData = _tb_read_queue();

    // 카운터 리셋 체크 (일/월 넘어가면 0으로)
    $resetChanged = _tb_autopilot_reset_counters_if_needed($config);
    if ($resetChanged) file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 큐 비었고 autopilot ON이면 리필 시도
    if (empty($queueData['projects']) || !_tb_has_pending_projects($queueData)) {
        if (!empty($config['autopilot'])) {
            _tb_autopilot_refill($config, $configFile);
            $queueData = _tb_read_queue(); // 리필 후 다시 읽기
        }
        if (empty($queueData['projects'])) return;
    }

    // 자동조종 한도 체크
    if (!_tb_autopilot_can_publish($config)) return;

    $intervalMinutes = max(5, (int)($config['interval_minutes'] ?? 30));
    $lastRun = $config['last_run'] ?? '';
    if ($lastRun) {
        $diffMin = (time() - strtotime($lastRun)) / 60;
        if ($diffMin < $intervalMinutes) return;
    }

    // 활성 프로젝트 찾기
    foreach ($queueData['projects'] as $pidx => $project) {
        if ($project['status'] !== 'active') continue;

        // 다음 생성할 아이템
        $nextItem = null;
        $nextIdx = -1;
        foreach ($project['items'] as $idx => $item) {
            if ($item['status'] === 'pending') {
                $nextItem = $item;
                $nextIdx = $idx;
                break;
            }
        }

        if (!$nextItem) {
            // 모든 아이템 완료 → 프로젝트 완료 처리
            $queueData['projects'][$pidx]['status'] = 'completed';
            $queueData['projects'][$pidx]['completed_at'] = date('Y-m-d H:i:s');
            // 필러 글에 클러스터 링크 최종 업데이트 (마커 기반 통합 링크)
            _tb_apply_project_links($queueData['projects'][$pidx]);
            continue;
        }

        // 글 생성
        $result = _tb_generate_article(
            $nextItem,
            $project['topic'],
            $project['style'],
            '',
            [],
            $config
        );

        if (!$result['success']) {
            $queueData['projects'][$pidx]['items'][$nextIdx]['status'] = 'failed';
            $queueData['projects'][$pidx]['items'][$nextIdx]['error'] = $result['error'];
            _tb_write_queue($queueData);
            break;
        }

        // 파싱
        $parsed = _tb_parse_article($result['content']);
        $finalTitle = $parsed['title'] ?: $nextItem['title'];
        $htmlContent = _tb_to_html($parsed['summary'], $parsed['body']);

        // 이미지 자동 삽입
        if (($config['image_enabled'] ?? '1') === '1') {
            $imgCount = _tb_decide_image_count($config, $nextItem['type'] === 'pillar');
            $images = [];
            $imgSource = $config['image_source'] ?? 'unsplash';

            if ($imgSource === 'dalle' && !empty($config['openai_api_key'])) {
                // DALL-E: 이미지 개수만큼 AI 프롬프트로 생성
                $dallePrompt = "Professional photography illustration for blog article: {$nextItem['keyword']}, {$nextItem['title']}, high quality, detailed, no text";
                for ($i = 0; $i < $imgCount; $i++) {
                    $img = _tb_generate_dalle_image($dallePrompt, $config['openai_api_key']);
                    if ($img) $images[] = $img;
                }
            } elseif (!empty($config['unsplash_api_key'])) {
                $images = _tb_get_unsplash_images($nextItem['keyword'] ?: $nextItem['title'], $config['unsplash_api_key'], $imgCount);
            }

            if (!empty($images)) {
                $htmlContent = _tb_inject_images_html($htmlContent, $images);
            }
        }

        // 발행 (관련글 링크는 발행 직후 _tb_apply_project_links 에서 일괄 처리)
        $postId = _tb_publish_post($finalTitle, $htmlContent, $project['board_id']);

        // 큐 업데이트
        $queueData['projects'][$pidx]['items'][$nextIdx]['status'] = 'done';
        $queueData['projects'][$pidx]['items'][$nextIdx]['post_id'] = $postId;
        $queueData['projects'][$pidx]['items'][$nextIdx]['published_at'] = date('Y-m-d H:i:s');

        // 필러 글이면 ID 기억
        if ($nextItem['type'] === 'pillar') {
            $queueData['projects'][$pidx]['pillar_post_id'] = $postId;
            $queueData['projects'][$pidx]['pillar_title'] = $finalTitle;
        }

        _tb_write_queue($queueData);

        // 발행 직후 관련글 링크 재적용 (필러/클러스터 모두, 매번 최신 상태로 갱신)
        _tb_apply_project_links($queueData['projects'][$pidx]);

        // config에 last_run 업데이트 + 자동조종 카운터 증가
        $config['last_run'] = date('Y-m-d H:i:s');
        $config['total_generated'] = (int)($config['total_generated'] ?? 0) + 1;
        if (!empty($config['autopilot'])) {
            $config['autopilot_daily_posted'] = (int)($config['autopilot_daily_posted'] ?? 0) + 1;
            $config['autopilot_monthly_posted'] = (int)($config['autopilot_monthly_posted'] ?? 0) + 1;
        }
        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if (class_exists('Cache')) Cache::flush();

        // 한 번에 하나만 생성
        break;
    }
}

// ===== 큐에 처리 대기(pending) 프로젝트가 있는지 =====
function _tb_has_pending_projects($queueData) {
    foreach ($queueData['projects'] ?? [] as $p) {
        if (($p['status'] ?? '') !== 'active') continue;
        foreach ($p['items'] ?? [] as $it) {
            if (($it['status'] ?? '') === 'pending') return true;
        }
    }
    return false;
}

// ===== 자동조종: 큐 리필 (AI가 주제 자동 생성 + 승인 + 큐 등록) =====
function _tb_autopilot_refill($config, $configFile) {
    // 쿨다운 체크
    $cooldownHours = max(1, (int)($config['autopilot_refill_cooldown_hours'] ?? 6));
    $lastRefill = $config['autopilot_last_refill'] ?? '';
    if ($lastRefill) {
        $diffHrs = (time() - strtotime($lastRefill)) / 3600;
        if ($diffHrs < $cooldownHours) return false;
    }
    // 에러 쿨다운 (에러 나면 1시간 스킵)
    $lastError = $config['autopilot_error_at'] ?? '';
    if ($lastError) {
        $diffMin = (time() - strtotime($lastError)) / 60;
        if ($diffMin < 60) return false;
    }
    // 한도 초과 시 리필 스킵
    if (!_tb_autopilot_can_publish($config)) return false;
    // API 키 없으면 스킵
    if (empty($config['openai_api_key'])) return false;

    // 대상 게시판
    $boards = $config['autopilot_boards'] ?? [];
    if (!is_array($boards)) $boards = [];
    if (empty($boards)) {
        _tb_autopilot_log_error($config, $configFile, '대상 게시판이 설정되지 않음');
        return false;
    }

    // AI에게 새 프로젝트 제안 받기
    $suggestions = _tb_suggest_new_projects_by_boards($boards, $config, 50);
    if (!$suggestions['success']) {
        _tb_autopilot_log_error($config, $configFile, '제안 생성 실패: ' . ($suggestions['error'] ?? ''));
        return false;
    }

    $refillCount = max(1, (int)($config['autopilot_refill_count'] ?? 2));
    $dupThreshold = max(0, min(100, (int)($config['autopilot_dup_threshold'] ?? 70)));
    $clusterCount = max(5, (int)($config['autopilot_cluster_count'] ?? 10));
    $style = $config['autopilot_default_style'] ?? '정보형';

    // 기존 프로젝트 주제 + 최근 글 제목 수집 (중복 방지용)
    $queueData = _tb_read_queue();
    $existingTopics = [];
    foreach ($queueData['projects'] as $p) {
        if (!empty($p['topic'])) $existingTopics[] = $p['topic'];
    }
    $prefix = DB::getPrefix();
    $placeholders = implode(',', array_fill(0, count($boards), '?'));
    try {
        $recentPosts = DB::fetchAll("SELECT title FROM {$prefix}posts WHERE board_id IN ($placeholders) ORDER BY id DESC LIMIT 100", $boards) ?: [];
    } catch (Exception $e) { $recentPosts = []; }
    foreach ($recentPosts as $rp) $existingTopics[] = $rp['title'] ?? '';

    // 제안 중 중복 아닌 것 필터링
    $selected = [];
    foreach ($suggestions['suggestions'] as $s) {
        if (count($selected) >= $refillCount) break;
        $topic = trim($s['topic'] ?? '');
        if ($topic === '') continue;
        // 중복 체크
        $maxSim = 0;
        foreach ($existingTopics as $t) {
            if (empty($t)) continue;
            similar_text(mb_strtolower($topic), mb_strtolower($t), $sim);
            if ($sim > $maxSim) $maxSim = $sim;
            if ($maxSim >= $dupThreshold) break;
        }
        if ($maxSim >= $dupThreshold) continue; // 유사도 너무 높으면 skip
        $selected[] = [
            'topic' => $topic,
            'style' => !empty($s['style']) ? $s['style'] : $style,
            'cluster_count' => !empty($s['cluster_count']) ? max(5, min(20, (int)$s['cluster_count'])) : $clusterCount,
        ];
        $existingTopics[] = $topic; // 다음 후보 비교 시 포함
    }

    if (empty($selected)) {
        _tb_autopilot_log_error($config, $configFile, '중복 없는 새 주제를 찾지 못함 (유사도 ' . $dupThreshold . '%+ 필터)');
        return false;
    }

    // 각 선택된 주제를 토픽맵 설계 → 큐 등록
    $targetBoardId = $boards[array_rand($boards)]; // 랜덤 게시판 (여러 개면 분산)
    $addedCount = 0;
    foreach ($selected as $sel) {
        $map = _tb_design_topic_map($sel['topic'], $sel['cluster_count'], $sel['style'], $config);
        if (!$map['success']) continue;
        $m = $map['map'] ?? $map;
        if (empty($m['pillar']) || empty($m['clusters'])) continue;

        // 매 프로젝트마다 랜덤 게시판 (여러 개 선택된 경우 분산)
        $thisBoardId = $boards[array_rand($boards)];
        $items = [];
        $items[] = [
            'type' => 'pillar',
            'title' => $m['pillar']['title'] ?? $sel['topic'],
            'keyword' => $m['pillar']['keyword'] ?? '',
            'description' => $m['pillar']['description'] ?? '',
            'status' => 'pending',
            'post_id' => 0,
        ];
        foreach ($m['clusters'] as $c) {
            $items[] = [
                'type' => 'cluster',
                'title' => $c['title'] ?? '',
                'keyword' => $c['keyword'] ?? '',
                'description' => $c['description'] ?? '',
                'status' => 'pending',
                'post_id' => 0,
            ];
        }
        $queueData['projects'][] = [
            'id' => uniqid('p_'),
            'topic' => $sel['topic'],
            'style' => $sel['style'],
            'board_id' => $thisBoardId,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'pillar_post_id' => 0,
            'pillar_title' => '',
            'items' => $items,
            'source' => 'autopilot',
        ];
        $addedCount++;
    }

    if ($addedCount > 0) {
        _tb_write_queue($queueData);
        $config['autopilot_last_refill'] = date('Y-m-d H:i:s');
        $config['autopilot_last_error'] = '';
        $config['autopilot_error_at'] = '';
        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $addedCount;
    } else {
        _tb_autopilot_log_error($config, $configFile, '토픽맵 설계 실패');
        return false;
    }
}

function _tb_autopilot_log_error(&$config, $configFile, $msg) {
    $config['autopilot_last_error'] = $msg;
    $config['autopilot_error_at'] = date('Y-m-d H:i:s');
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ===== SVG 토픽 맵 렌더링 (방사형 그래프) =====
function _tb_render_topic_map_svg($pillarTitle, $clusters, $options = []) {
    $width = $options['width'] ?? 560;
    $height = $options['height'] ?? 420;
    $showStatus = $options['show_status'] ?? false; // 각 노드의 상태(pending/done/failed) 표시

    $cx = $width / 2;
    $cy = $height / 2;
    $pillarR = 64;
    $clusterR = 38;
    $orbitR = min($width, $height) / 2 - $clusterR - 20;

    $n = count($clusters);
    if ($n === 0) $n = 1;

    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;display:block;margin:auto;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0">';

    // 연결선 (필러 → 각 클러스터)
    $positions = [];
    for ($i = 0; $i < $n; $i++) {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $x = $cx + $orbitR * cos($angle);
        $y = $cy + $orbitR * sin($angle);
        $positions[] = [$x, $y];

        // 선
        $svg .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x . '" y2="' . $y . '" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="3,3"/>';
    }

    // 필러 노드 (중앙)
    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $pillarR . '" fill="#3b82f6" stroke="#1e40af" stroke-width="2.5"/>';
    $svg .= '<text x="' . $cx . '" y="' . ($cy - 6) . '" text-anchor="middle" fill="white" font-size="10" font-weight="700">PILLAR</text>';

    // 필러 제목 (줄바꿈)
    $pillarLines = _tb_wrap_text($pillarTitle, 10);
    $startY = $cy + 8;
    foreach (array_slice($pillarLines, 0, 3) as $idx => $line) {
        $svg .= '<text x="' . $cx . '" y="' . ($startY + $idx * 11) . '" text-anchor="middle" fill="white" font-size="10" font-weight="600">' . htmlspecialchars($line) . '</text>';
    }

    // 클러스터 노드
    foreach ($clusters as $i => $c) {
        [$x, $y] = $positions[$i];
        $status = $c['status'] ?? '';
        $color = '#64748b';
        $stroke = '#475569';
        if ($showStatus) {
            if ($status === 'done') { $color = '#22c55e'; $stroke = '#15803d'; }
            elseif ($status === 'failed') { $color = '#ef4444'; $stroke = '#991b1b'; }
            elseif ($status === 'pending') { $color = '#f59e0b'; $stroke = '#b45309'; }
        }

        $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . $clusterR . '" fill="' . $color . '" stroke="' . $stroke . '" stroke-width="2"/>';

        $title = is_array($c) ? ($c['title'] ?? '') : $c;
        $lines = _tb_wrap_text($title, 7);
        $baseY = $y - (min(count($lines), 3) - 1) * 5;
        foreach (array_slice($lines, 0, 3) as $lineIdx => $line) {
            $svg .= '<text x="' . $x . '" y="' . ($baseY + $lineIdx * 10) . '" text-anchor="middle" fill="white" font-size="9" font-weight="600">' . htmlspecialchars($line) . '</text>';
        }
    }

    $svg .= '</svg>';
    return $svg;
}

// 텍스트를 지정 길이로 잘라서 줄바꿈 (한글 고려)
function _tb_wrap_text($text, $maxLen) {
    $text = trim($text);
    $lines = [];
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $current = '';
    foreach ($chars as $char) {
        $current .= $char;
        if (mb_strlen($current) >= $maxLen) {
            $lines[] = $current;
            $current = '';
        }
    }
    if ($current !== '') $lines[] = $current;
    return $lines;
}

// ===== AI 추천 프로젝트 제안 (제목만 분석, 보드 지정 가능) =====
function _tb_suggest_new_projects_by_boards($boardIds, $config, $maxPosts = 50) {
    $prefix = DB::getPrefix();

    if (empty($boardIds)) {
        return _tb_suggest_new_projects($config);
    }

    $placeholders = implode(',', array_fill(0, count($boardIds), '?'));
    $posts = DB::fetchAll(
        "SELECT title FROM {$prefix}posts WHERE board_id IN ($placeholders) AND is_hidden = 0 ORDER BY id DESC LIMIT ?",
        array_merge($boardIds, [$maxPosts])
    );

    if (count($posts) < 3) {
        return ['success' => false, 'error' => '추천을 위해 최소 3개 이상의 글이 필요합니다 (현재 ' . count($posts) . '개)'];
    }

    $titles = array_column($posts, 'title');
    $titleList = implode("\n- ", $titles);

    $prompt = "당신은 SEO 콘텐츠 전략가입니다. 아래는 한 사이트의 최근 글 제목 목록입니다.\n\n";
    $prompt .= "글 목록:\n- " . $titleList . "\n\n";
    $prompt .= "이 사이트의 주제를 파악한 뒤, 지금 쓰면 좋은 새로운 토픽 프로젝트 3개를 제안하세요.\n";
    $prompt .= "각 프로젝트는 '빠진 주제'나 '확장 가능한 주제'여야 합니다.\n\n";
    $prompt .= "반드시 다음 JSON 형식으로만 응답:\n";
    $prompt .= "{\n";
    $prompt .= '  "suggestions": [' . "\n";
    $prompt .= '    {' . "\n";
    $prompt .= '      "topic": "추천 주제명",' . "\n";
    $prompt .= '      "reason": "왜 이 주제가 필요한지 (1문장)",' . "\n";
    $prompt .= '      "cluster_count": 10,' . "\n";
    $prompt .= '      "style": "정보형 또는 후기형 또는 튜토리얼 또는 리스트형",' . "\n";
    $prompt .= '      "seo_score": 85' . "\n";
    $prompt .= '    }' . "\n";
    $prompt .= '  ]' . "\n";
    $prompt .= "}";

    $result = _tb_call_ai($prompt, $config, 2000, true);
    if (!$result['success']) return $result;

    $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($result['content']));
    $data = json_decode($content, true);

    if (!is_array($data) || empty($data['suggestions'])) {
        return ['success' => false, 'error' => 'AI 응답 파싱 실패'];
    }

    return ['success' => true, 'suggestions' => $data['suggestions'], 'total_posts' => count($posts)];
}

// ===== AI 추천 프로젝트 제안 (전체 사이트) =====
function _tb_suggest_new_projects($config) {
    $prefix = DB::getPrefix();
    $posts = DB::fetchAll("SELECT title FROM {$prefix}posts WHERE is_hidden = 0 ORDER BY id DESC LIMIT 50");

    if (count($posts) < 3) {
        return ['success' => false, 'error' => '추천을 위해 최소 3개 이상의 글이 필요합니다 (현재 ' . count($posts) . '개)'];
    }

    $titles = array_column($posts, 'title');
    $titleList = implode("\n- ", array_slice($titles, 0, 50));

    $prompt = "당신은 SEO 콘텐츠 전략가입니다. 아래는 한 사이트의 최근 글 제목 목록입니다.\n\n";
    $prompt .= "글 목록:\n- " . $titleList . "\n\n";
    $prompt .= "이 사이트의 주제를 파악한 뒤, 지금 쓰면 좋은 새로운 토픽 프로젝트 3개를 제안하세요.\n";
    $prompt .= "각 프로젝트는 '빠진 주제'나 '확장 가능한 주제'여야 합니다.\n\n";
    $prompt .= "반드시 다음 JSON 형식으로만 응답:\n";
    $prompt .= "{\n";
    $prompt .= '  "suggestions": [' . "\n";
    $prompt .= '    {' . "\n";
    $prompt .= '      "topic": "추천 주제명",' . "\n";
    $prompt .= '      "reason": "왜 이 주제가 필요한지 (1문장)",' . "\n";
    $prompt .= '      "cluster_count": 10,' . "\n";
    $prompt .= '      "style": "정보형 또는 후기형 또는 튜토리얼 또는 리스트형",' . "\n";
    $prompt .= '      "seo_score": 85  // 예상 SEO 잠재력 0-100' . "\n";
    $prompt .= '    }' . "\n";
    $prompt .= '  ]' . "\n";
    $prompt .= "}";

    $result = _tb_call_ai($prompt, $config, 2000, true);
    if (!$result['success']) return $result;

    $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($result['content']));
    $data = json_decode($content, true);

    if (!is_array($data) || empty($data['suggestions'])) {
        return ['success' => false, 'error' => 'AI 응답 파싱 실패'];
    }

    return ['success' => true, 'suggestions' => $data['suggestions']];
}

// ===== 기존 글 AI 분석 (토픽 클러스터 구조 자동 감지) =====
function _tb_analyze_existing_posts($boardIds, $config, $maxPosts = 50) {
    $prefix = DB::getPrefix();

    $placeholders = implode(',', array_fill(0, count($boardIds), '?'));
    $posts = DB::fetchAll(
        "SELECT id, board_id, title, content FROM {$prefix}posts WHERE board_id IN ($placeholders) AND is_hidden = 0 ORDER BY id DESC LIMIT ?",
        array_merge($boardIds, [$maxPosts])
    );

    if (empty($posts)) return ['success' => false, 'error' => '분석할 글이 없습니다'];
    if (count($posts) < 3) return ['success' => false, 'error' => '분석하려면 최소 3개 이상의 글이 필요합니다 (현재 ' . count($posts) . '개)'];

    // AI에 보낼 데이터 간소화 (토큰 절약)
    $postList = [];
    foreach ($posts as $p) {
        $cleanText = trim(strip_tags($p['content']));
        $postList[] = [
            'id' => (int)$p['id'],
            'title' => $p['title'],
            'excerpt' => mb_substr($cleanText, 0, 200),
        ];
    }

    $prompt = "당신은 SEO 콘텐츠 전략 전문가입니다. 아래 블로그 글 목록을 분석하여 토픽 클러스터 구조로 재구성하세요.\n\n";
    $prompt .= "글 목록:\n" . json_encode($postList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    $prompt .= "작업:\n";
    $prompt .= "1. 글들을 공통 주제별로 그룹화 (토픽 클러스터)\n";
    $prompt .= "2. 각 그룹에서 가장 포괄적인 글 1개를 필러(pillar) 후보로 지정\n";
    $prompt .= "3. 나머지는 클러스터(cluster) 글로 분류\n";
    $prompt .= "4. 각 그룹에서 빠진 세부 주제 제안 (추가 작성하면 좋을 글)\n";
    $prompt .= "5. 같은 그룹 내 글끼리 내부 링크 연결 제안\n\n";
    $prompt .= "반드시 다음 JSON 형식으로만 응답:\n";
    $prompt .= "{\n";
    $prompt .= '  "groups": [' . "\n";
    $prompt .= '    {' . "\n";
    $prompt .= '      "topic": "그룹 주제명",' . "\n";
    $prompt .= '      "pillar_id": 123,' . "\n";
    $prompt .= '      "cluster_ids": [124, 125, 126],' . "\n";
    $prompt .= '      "missing_topics": ["빠진 세부 주제 1", "빠진 세부 주제 2"]' . "\n";
    $prompt .= '    }' . "\n";
    $prompt .= '  ],' . "\n";
    $prompt .= '  "orphan_ids": [999]  // 어느 그룹에도 속하지 않는 글' . "\n";
    $prompt .= "}";

    $result = _tb_call_ai($prompt, $config, 4000, true);
    if (!$result['success']) return $result;

    $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($result['content']));
    $data = json_decode($content, true);

    if (!is_array($data) || empty($data['groups'])) {
        return ['success' => false, 'error' => 'AI 응답 파싱 실패'];
    }

    // 글 정보를 그룹에 붙여서 반환
    $postMap = [];
    foreach ($posts as $p) $postMap[(int)$p['id']] = $p;

    foreach ($data['groups'] as &$group) {
        $group['pillar'] = $postMap[(int)($group['pillar_id'] ?? 0)] ?? null;
        $group['clusters'] = [];
        foreach (($group['cluster_ids'] ?? []) as $cid) {
            if (isset($postMap[(int)$cid])) $group['clusters'][] = $postMap[(int)$cid];
        }
    }

    return ['success' => true, 'analysis' => $data, 'total_posts' => count($posts)];
}

// ===== 내부 링크 자동 연결 (기존 글 본문에 관련글 박스 추가) =====
function _tb_apply_internal_links($group) {
    $pillar = $group['pillar'] ?? null;
    $clusters = $group['clusters'] ?? [];
    if (!$pillar && empty($clusters)) return 0;

    $prefix = DB::getPrefix();
    $updateCount = 0;

    // 필러 글 DB 존재 확인 (삭제된 경우 링크 달지 않음)
    if ($pillar && $pillar['id']) {
        $exists = DB::fetch("SELECT id FROM {$prefix}posts WHERE id = ?", [$pillar['id']]);
        if (!$exists) $pillar = null;
    }

    // 클러스터 글 DB 존재 확인 (삭제된 것 제거)
    $clusters = array_values(array_filter($clusters, function($c) use ($prefix) {
        if (empty($c['id'])) return false;
        return (bool)DB::fetch("SELECT id FROM {$prefix}posts WHERE id = ?", [$c['id']]);
    }));

    if (!$pillar && empty($clusters)) return 0;

    // 1. 필러 글에 클러스터 링크 섹션 추가 (클러스터 1개 이상 있을 때만)
    if ($pillar && !empty($clusters)) {
        $links = '<!--tb-related-start--><div style="background:#fefce8;border:1px solid #fde047;padding:20px;margin-top:32px;border-radius:8px" data-tb-related="1">';
        $links .= '<strong style="color:#854d0e;display:block;margin-bottom:12px;font-size:15px">📚 이 주제의 관련 글</strong>';
        $links .= '<ul style="list-style:none;padding:0;margin:0">';
        foreach ($clusters as $c) {
            $url = '/board/' . $c['board_id'] . '/' . $c['id'];
            $links .= '<li style="margin:8px 0"><a href="' . htmlspecialchars($url) . '" style="color:#2563eb;text-decoration:none;font-weight:500">→ ' . htmlspecialchars($c['title']) . '</a></li>';
        }
        $links .= '</ul></div><!--tb-related-end-->';

        $pillarContent = _tb_strip_related_blocks($pillar['content']);
        $pillarContent = trim($pillarContent) . "\n" . $links;
        DB::query("UPDATE {$prefix}posts SET content = ? WHERE id = ?", [$pillarContent, $pillar['id']]);
        $updateCount++;
    }

    // 2. 각 클러스터 글에 필러 링크 + 다른 클러스터 링크 추가
    $pillarUrl = $pillar ? ('/board/' . $pillar['board_id'] . '/' . $pillar['id']) : '';
    $pillarTitle = $pillar['title'] ?? '';
    foreach ($clusters as $c) {
        // 다른 클러스터 목록
        $others = array_filter($clusters, function($x) use ($c) { return $x['id'] !== $c['id']; });
        $others = array_values($others);

        // 필러도 없고 다른 클러스터도 없으면 스킵
        if (!$pillar && empty($others)) continue;

        $linkBox = '<!--tb-related-start--><div style="background:#f0fdf4;border-left:4px solid #22c55e;padding:16px 20px;margin-top:32px;border-radius:4px" data-tb-related="1">';

        if ($pillar) {
            $linkBox .= '<strong style="color:#166534;display:block;margin-bottom:8px;font-size:14px">🎯 전체 가이드 보기</strong>';
            $linkBox .= '<a href="' . htmlspecialchars($pillarUrl) . '" style="color:#15803d;text-decoration:underline;font-weight:500">' . htmlspecialchars($pillarTitle) . '</a>';
        }

        if (!empty($others)) {
            shuffle($others);
            $others = array_slice($others, 0, 2);
            $linkBox .= '<div style="' . ($pillar ? 'margin-top:12px;padding-top:12px;border-top:1px solid #bbf7d0' : '') . '">';
            $linkBox .= '<strong style="color:#166534;display:block;margin-bottom:6px;font-size:13px">🔗 관련 세부 글</strong>';
            foreach ($others as $o) {
                $ourl = '/board/' . $o['board_id'] . '/' . $o['id'];
                $linkBox .= '<div style="margin:4px 0"><a href="' . htmlspecialchars($ourl) . '" style="color:#15803d;text-decoration:none;font-size:13px">→ ' . htmlspecialchars($o['title']) . '</a></div>';
            }
            $linkBox .= '</div>';
        }
        $linkBox .= '</div><!--tb-related-end-->';

        $cContent = _tb_strip_related_blocks($c['content']);
        $cContent = trim($cContent) . "\n" . $linkBox;
        DB::query("UPDATE {$prefix}posts SET content = ? WHERE id = ?", [$cContent, $c['id']]);
        $updateCount++;
    }

    return $updateCount;
}

// ===== 빠진 주제를 새 프로젝트로 큐에 추가 =====
function _tb_queue_missing_topics($groupTopic, $missingTopics, $boardId, $style, $existingPillar) {
    if (empty($missingTopics)) return false;

    $queueData = _tb_read_queue();
    if (!is_array($queueData)) $queueData = ['projects' => []];

    $items = [];
    foreach ($missingTopics as $topic) {
        $items[] = [
            'type' => 'cluster',
            'title' => $topic,
            'keyword' => $topic,
            'description' => "{$groupTopic} 주제의 세부 글 - {$topic}",
            'status' => 'pending',
            'post_id' => 0,
        ];
    }

    $newProject = [
        'id' => uniqid('p_'),
        'topic' => $groupTopic . ' (빠진 주제 보충)',
        'style' => $style,
        'board_id' => $boardId,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'pillar_post_id' => (int)($existingPillar['id'] ?? 0),
        'pillar_title' => $existingPillar['title'] ?? '',
        'items' => $items,
        'from_analysis' => true,
    ];

    $queueData['projects'][] = $newProject;
    _tb_write_queue($queueData);
    return $newProject['id'];
}

// ===== Hook: 접속 시마다 큐 체크 (프론트엔드) =====
Plugin::addHook('after_header', function() {
    global $_tb_config, $_tb_config_file;
    if (empty($_tb_config)) return;

    if (empty($_tb_config['openai_api_key'])) return;

    _tb_process_queue($_tb_config, $_tb_config_file);
});

// ===== Hook: 관리자 페이지에서도 큐 체크 =====
Plugin::addHook('admin_after_header', function() {
    global $_tb_config, $_tb_config_file;
    if (empty($_tb_config)) return;

    if (empty($_tb_config['openai_api_key'])) return;

    _tb_process_queue($_tb_config, $_tb_config_file);
});

// ===== 수동 실행: 강제로 1개 생성 (settings.php에서 호출) =====
function _tb_force_run_one() {
    global $_tb_config, $_tb_config_file;
    if (empty($_tb_config)) return ['success' => false, 'error' => '설정 없음'];

    if (empty($_tb_config['openai_api_key'])) return ['success' => false, 'error' => 'OpenRouter API 키 없음'];

    // last_run 임시 리셋 → 간격 무시하고 실행
    $originalLastRun = $_tb_config['last_run'];
    $_tb_config['last_run'] = '';

    $queueData = _tb_read_queue();
    if (empty($queueData['projects'])) return ['success' => false, 'error' => '큐에 프로젝트 없음'];

    // 활성 프로젝트 + 대기 글 찾기
    $hasPending = false;
    foreach ($queueData['projects'] as $project) {
        if ($project['status'] !== 'active') continue;
        foreach ($project['items'] as $item) {
            if ($item['status'] === 'pending') { $hasPending = true; break 2; }
        }
    }
    if (!$hasPending) return ['success' => false, 'error' => '대기 중인 글 없음 (모두 완료 또는 실패)'];

    _tb_process_queue($_tb_config, $_tb_config_file);

    return ['success' => true, 'message' => '글 1개 생성 시도 완료 (큐 관리에서 결과 확인)'];
}

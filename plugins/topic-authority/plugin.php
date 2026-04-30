<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * 토픽 어소리티 빌더 v1.0
 *
 * 타겟 키워드 하나만 입력하면
 * AI가 필러-클러스터 구조를 설계하고 글을 자동 발행합니다.
 */

if (!function_exists('_ta_data_dir')) {
    function _ta_data_dir(): string {
        $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
        $dir = $base . '/data/topic-authority';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

function _ta_log(string $msg): void {
    $file = _ta_data_dir() . '/debug.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents($file, $line, FILE_APPEND);
    // 로그 파일이 너무 커지면 자름 (100KB 이상)
    if (@filesize($file) > 102400) {
        $content = @file_get_contents($file);
        @file_put_contents($file, substr($content, -51200));
    }
}

$_ta_config_file = _ta_data_dir() . '/config.json';
$_ta_queue_file  = _ta_data_dir() . '/queue.json';

$_ta_config_raw = file_exists($_ta_config_file) ? json_decode(file_get_contents($_ta_config_file), true) : [];
if (!is_array($_ta_config_raw)) $_ta_config_raw = [];

// 플러그인 폴더 기본 config 병합
$_ta_plugin_config_raw = file_exists(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
if (!is_array($_ta_plugin_config_raw)) $_ta_plugin_config_raw = [];

$_ta_config = array_merge([
    'openai_api_key'  => '',
    'openai_model'    => 'openai/gpt-4o-mini',
    'unsplash_api_key'=> '',
    'image_enabled'   => '1',
    'images_per_post' => '2',
    'interval_minutes'=> 30,
    'promo_links'     => [],
    'last_run'        => '',
    'total_generated' => 0,
], $_ta_plugin_config_raw, $_ta_config_raw);

// ===== 큐 읽기/쓰기 =====
function _ta_read_queue(): array {
    $file = _ta_data_dir() . '/queue.json';
    if (!file_exists($file)) return ['projects' => []];
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return ['projects' => []];
    if (!isset($data['projects'])) $data['projects'] = [];
    return $data;
}
function _ta_write_queue(array $data): void {
    file_put_contents(_ta_data_dir() . '/queue.json',
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ===== OpenAI 호출 =====
function _ta_call_openai(string $prompt, array $config, int $maxTokens = 3000, bool $jsonMode = false): array {
    if (empty($config['openai_api_key'])) {
        return ['success' => false, 'error' => 'OpenRouter API 키를 먼저 설정하세요.'];
    }
    $body = [
        'model'    => $config['openai_model'] ?? 'openai/gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.8,
        'max_tokens'  => $maxTokens,
    ];
    if ($jsonMode) $body['response_format'] = ['type' => 'json_object'];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['openai_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT    => 120,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return ['success' => false, 'error' => "OpenAI HTTP {$httpCode}"];
    $parsed  = json_decode($resp, true);
    $content = $parsed['choices'][0]['message']['content'] ?? '';
    if (empty($content)) return ['success' => false, 'error' => 'OpenAI 응답이 비어있습니다.'];
    return ['success' => true, 'content' => $content];
}

// ===== Unsplash 이미지 검색 =====
function _ta_get_unsplash_images(string $keyword, string $apiKey, int $count = 2): array {
    if (empty($apiKey)) return [];
    $q = $keyword;
    if (preg_match('/[가-힣]/u', $keyword)) {
        $q = trim(preg_replace('/[가-힣]+/u', '', $keyword));
        if (empty($q)) {
            $fallbacks = ['technology', 'business', 'professional', 'modern', 'creative'];
            $q = $fallbacks[array_rand($fallbacks)];
        }
    }
    $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($q)
         . '&per_page=' . $count . '&orientation=landscape&client_id=' . urlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Accept-Version: v1'], CURLOPT_TIMEOUT => 15]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    $images = [];
    foreach ($data['results'] ?? [] as $r) {
        if (!empty($r['urls']['regular'])) {
            $images[] = [
                'url' => $r['urls']['regular'],
                'alt' => $r['alt_description'] ?? $keyword,
            ];
        }
    }
    return $images;
}

// ===== 토픽 맵 설계 =====
function _ta_design_topic_map(string $keyword, int $clusterCount, array $config): array {
    $prompt  = "당신은 구글 SEO 전문가입니다. 아래 타겟 키워드로 토픽 어소리티를 구축하는 콘텐츠 전략을 설계하세요.\n\n";
    $prompt .= "타겟 키워드: {$keyword}\n";
    $prompt .= "클러스터 글 수: {$clusterCount}개\n\n";
    $prompt .= "요구사항:\n";
    $prompt .= "1. 필러 글(pillar) 1개: 타겟 키워드를 메인으로 하는 종합 가이드\n";
    $prompt .= "2. 클러스터 글(cluster) {$clusterCount}개: 필러의 세부 주제 (롱테일 키워드 중심)\n";
    $prompt .= "3. 클러스터는 서로 겹치지 않고 타겟 키워드와 의미적으로 연결\n";
    $prompt .= "4. 구글에서 실제 검색될 법한 한국어 키워드 사용\n\n";
    $prompt .= "반드시 아래 JSON 형식으로만 응답하세요:\n";
    $prompt .= '{"pillar":{"title":"필러 글 제목","keyword":"핵심 키워드","description":"이 글에서 다룰 내용 2문장"},"clusters":[{"title":"클러스터 제목","keyword":"롱테일 키워드","description":"이 글에서 다룰 내용 1문장"}]}';

    $result = _ta_call_openai($prompt, $config, 3000, true);
    if (!$result['success']) return $result;

    $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($result['content']));
    $map = json_decode($content, true);
    if (!is_array($map) || empty($map['pillar']) || empty($map['clusters'])) {
        return ['success' => false, 'error' => 'AI가 올바른 형식으로 응답하지 않았습니다.'];
    }
    return ['success' => true, 'map' => $map];
}

// ===== 개별 글 생성 =====
function _ta_generate_article(array $item, string $mainKeyword, array $config): array {
    $isPillar = ($item['type'] === 'pillar');
    $targetLen = $isPillar ? 2500 : 1500;

    $prompt  = "당신은 한국어 SEO 블로거입니다. 아래 조건에 맞는 글을 작성하세요.\n\n";
    $prompt .= "메인 타겟 키워드: {$mainKeyword}\n";
    $prompt .= "이 글 제목: {$item['title']}\n";
    $prompt .= "이 글 키워드: {$item['keyword']}\n";
    $prompt .= "내용 방향: {$item['description']}\n";
    $prompt .= "글 유형: " . ($isPillar ? "필러 글 (주제 전체 포괄 가이드)" : "클러스터 글 (세부 주제 심층 분석)") . "\n";
    $prompt .= "목표 글자 수: {$targetLen}자 이상\n\n";
    $prompt .= "작성 규칙:\n";
    $prompt .= "1. 자연스러운 한국어, 사람이 쓴 것처럼\n";
    $prompt .= "2. 키워드를 본문에 자연스럽게 4~6회 포함\n";
    $prompt .= "3. H2 소제목 5개 이상 (## 형식)\n";
    $prompt .= "4. 마지막에 자주 묻는 질문(FAQ) 3개 추가\n";
    $prompt .= "5. HTML 금지, 마크다운만 사용\n";
    $prompt .= "6. 각 문단은 2~3문장, 문단 사이 빈 줄\n\n";

    // 홍보 링크 주입 (앵커 랜덤 선택, 삽입 수량 랜덤)
    $promoLinks = array_filter($config['promo_links'] ?? [], fn($l) => !empty($l['anchor']) && !empty($l['url']));
    if (!empty($promoLinks)) {
        shuffle($promoLinks);
        $maxLinks = rand(1, min(2, count($promoLinks)));
        $selected = array_slice($promoLinks, 0, $maxLinks);
        $linkList = '';
        foreach ($selected as $l) {
            $anchorList = array_values(array_filter(array_map('trim', explode(',', $l['anchor']))));
            $anchor = !empty($anchorList) ? $anchorList[array_rand($anchorList)] : $l['anchor'];
            $linkList .= "- {$anchor}: {$l['url']}\n";
        }
        $prompt .= "아래 링크를 본문에 자연스럽게 1회만 삽입하세요 (마크다운 형식):\n{$linkList}\n";
    }

    $prompt .= "\n응답 형식:\n제목: (최종 제목)\n요약: (2~3문장 요약)\n\n(본문)";

    return _ta_call_openai($prompt, $config, 4000, false);
}

// ===== AI 응답 파싱 =====
function _ta_parse_article(string $raw): array {
    $title = $summary = '';
    $body = $raw;
    if (preg_match('/^제목\s*[:：]\s*(.+)$/m', $raw, $m)) {
        $title = trim($m[1]);
        $body  = preg_replace('/^제목\s*[:：].+\n?/m', '', $body, 1);
    }
    if (preg_match('/^요약\s*[:：]\s*(.+(?:\n(?!##).+)*)/m', $body, $m)) {
        $summary = trim($m[1]);
        $body    = preg_replace('/^요약\s*[:：].+(?:\n(?!##).+)*\n?/m', '', $body, 1);
    }
    return ['title' => $title, 'summary' => $summary, 'body' => trim($body)];
}

// ===== 마크다운 → HTML =====
function _ta_to_html(string $summary, string $body): string {
    $html = '';
    if (!empty($summary)) {
        $html .= '<div style="background:#f0f9ff;border-left:4px solid #3b82f6;padding:16px 20px;margin-bottom:24px;border-radius:4px">';
        $html .= '<strong style="color:#1e40af;display:block;margin-bottom:6px;font-size:14px">📌 핵심 요약</strong>';
        $html .= '<div style="color:#334155;line-height:1.7">' . nl2br(htmlspecialchars($summary)) . '</div>';
        $html .= '</div>';
    }
    foreach (explode("\n", $body) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^##\s+(.+)/', $line, $m)) {
            $html .= '<h2 style="font-size:20px;font-weight:700;margin:32px 0 14px;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:8px">' . htmlspecialchars($m[1]) . '</h2>' . "\n";
        } elseif (preg_match('/^###\s+(.+)/', $line, $m)) {
            $html .= '<h3 style="font-size:16px;font-weight:600;margin:20px 0 10px;color:#334155">' . htmlspecialchars($m[1]) . '</h3>' . "\n";
        } elseif (preg_match('/^[-*]\s+(.+)/', $line, $m)) {
            $html .= '<p style="margin:4px 0 4px 16px">• ' . htmlspecialchars($m[1]) . '</p>' . "\n";
        } else {
            $escaped = htmlspecialchars($line);
            $escaped = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" style="color:#2563eb;text-decoration:underline">$1</a>', $escaped);
            $html .= '<p style="line-height:1.8;margin:12px 0">' . $escaped . '</p>' . "\n";
        }
    }
    return $html;
}

// ===== 이미지 삽입 =====
function _ta_inject_images(string $html, array $images): string {
    if (empty($images)) return $html;
    $parts  = preg_split('/(<h2[^>]*>.*?<\/h2>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $imgIdx = 0;
    $result = '';
    foreach ($parts as $p) {
        $result .= $p;
        if (preg_match('/<h2/', $p) && $imgIdx < count($images)) {
            $img     = $images[$imgIdx++];
            $result .= '<figure style="margin:20px 0;text-align:center">'
                     . '<img src="' . htmlspecialchars($img['url']) . '" alt="' . htmlspecialchars($img['alt']) . '" style="max-width:100%;height:auto;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
                     . '</figure>' . "\n";
        }
    }
    while ($imgIdx < count($images)) {
        $img     = $images[$imgIdx++];
        $result .= '<figure style="margin:20px 0;text-align:center">'
                 . '<img src="' . htmlspecialchars($img['url']) . '" alt="' . htmlspecialchars($img['alt']) . '" style="max-width:100%;height:auto;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
                 . '</figure>' . "\n";
    }
    return $result;
}

// ===== 관련글 링크 박스 제거 =====
function _ta_strip_link_boxes(string $content): string {
    $content = preg_replace('/<!--ta-links-start-->.*?<!--ta-links-end-->/s', '', $content);
    return $content;
}

// ===== 내부링크 적용 =====
function _ta_apply_internal_links(array $project): void {
    $prefix = DB::getPrefix();
    $pillarPostId = (int)($project['pillar_post_id'] ?? 0);
    $clusters = [];
    foreach ($project['items'] ?? [] as $it) {
        if (($it['type'] ?? '') !== 'cluster' || empty($it['post_id'])) continue;
        $row = DB::fetch("SELECT id, board_id, title FROM {$prefix}posts WHERE id = ?", [$it['post_id']]);
        if ($row) $clusters[] = $row;
    }

    // 필러에 클러스터 목록 추가
    if ($pillarPostId && !empty($clusters)) {
        $pillar = DB::fetch("SELECT content, board_id FROM {$prefix}posts WHERE id = ?", [$pillarPostId]);
        if ($pillar) {
            $links  = '<!--ta-links-start--><div style="background:#fefce8;border:1px solid #fde047;padding:20px;margin-top:32px;border-radius:8px">';
            $links .= '<strong style="color:#854d0e;display:block;margin-bottom:12px">📚 이 주제의 세부 가이드</strong>';
            $links .= '<ul style="list-style:none;padding:0;margin:0">';
            foreach ($clusters as $c) {
                $url    = '/board/' . $c['board_id'] . '/' . $c['id'];
                $links .= '<li style="margin:8px 0"><a href="' . htmlspecialchars($url) . '" style="color:#2563eb;text-decoration:none;font-weight:500">→ ' . htmlspecialchars($c['title']) . '</a></li>';
            }
            $links .= '</ul></div><!--ta-links-end-->';
            $newContent = trim(_ta_strip_link_boxes($pillar['content'])) . "\n" . $links;
            DB::query("UPDATE {$prefix}posts SET content = ? WHERE id = ?", [$newContent, $pillarPostId]);
        }
    }

    // 각 클러스터에 필러 링크 추가
    if ($pillarPostId && !empty($clusters)) {
        $pillarRow = DB::fetch("SELECT board_id, title FROM {$prefix}posts WHERE id = ?", [$pillarPostId]);
        foreach ($clusters as $c) {
            $cRow = DB::fetch("SELECT content FROM {$prefix}posts WHERE id = ?", [$c['id']]);
            if (!$cRow || !$pillarRow) continue;
            $pUrl   = '/board/' . $pillarRow['board_id'] . '/' . $pillarPostId;
            $box    = '<!--ta-links-start--><div style="background:#f0fdf4;border-left:4px solid #22c55e;padding:16px 20px;margin-top:32px;border-radius:4px">';
            $box   .= '<strong style="color:#166534;display:block;margin-bottom:8px;font-size:14px">🎯 전체 가이드 보기</strong>';
            $box   .= '<a href="' . htmlspecialchars($pUrl) . '" style="color:#15803d;text-decoration:underline;font-weight:500">' . htmlspecialchars($pillarRow['title']) . '</a>';
            $box   .= '</div><!--ta-links-end-->';
            $newContent = trim(_ta_strip_link_boxes($cRow['content'])) . "\n" . $box;
            DB::query("UPDATE {$prefix}posts SET content = ? WHERE id = ?", [$newContent, $c['id']]);
        }
    }
}

// ===== 가상 작성자 =====
function _ta_get_author_id(): int {
    $prefix   = DB::getPrefix();
    $nickname = 'SEO 에디터';
    $member   = DB::fetch("SELECT id FROM {$prefix}members WHERE nickname = ?", [$nickname]);
    if ($member) return (int)$member['id'];
    return (int)DB::insert("{$prefix}members", [
        'user_id'    => 'ta_seo_editor',
        'password'   => password_hash('ta_' . time(), PASSWORD_BCRYPT),
        'nickname'   => $nickname,
        'email'      => 'seo@nuriboard.local',
        'level'      => 2,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

// ===== 게시글 발행 =====
function _ta_publish_post(string $title, string $html, string $boardId): int {
    $prefix   = DB::getPrefix();
    $memberId = _ta_get_author_id();
    $slug     = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $title), '-') ?: 'post-' . time();
    $now = date('Y-m-d H:i:s');
    return (int)DB::insert("{$prefix}posts", [
        'board_id'      => $boardId,
        'member_id'     => $memberId,
        'title'         => $title,
        'content'       => $html,
        'slug'          => $slug,
        'hit'           => 0,
        'comment_count' => 0,
        'vote_up'       => 0,
        'vote_down'     => 0,
        'is_notice'     => 0,
        'is_hidden'     => 0,
        'created_at'    => $now,
        'updated_at'    => $now,
    ]);
}

// ===== 큐 처리 (방문자 접속 시마다 실행) =====
function _ta_process_queue(array $config, string $configFile): void {
    if (empty($config['openai_api_key'])) return;

    // OpenAI 호출은 60~120초 걸릴 수 있으므로 PHP 실행시간 연장
    @set_time_limit(300);
    @ini_set('max_execution_time', '300');

    _ta_log('process_queue 시작');

    $intervalMin = max(5, (int)($config['interval_minutes'] ?? 30));
    $lastRun     = $config['last_run'] ?? '';
    if ($lastRun && (time() - strtotime($lastRun)) / 60 < $intervalMin) return;

    $queueData = _ta_read_queue();
    if (empty($queueData['projects'])) return;

    foreach ($queueData['projects'] as $pidx => $project) {
        $pStatus = $project['status'] ?? 'active';

        // 토픽 맵 설계 단계 (즉시 등록 후 백그라운드에서 AI 설계)
        if ($pStatus === 'designing') {
            $mapResult = _ta_design_topic_map($project['keyword'], (int)($project['cluster_count'] ?? 10), $config);
            if (!$mapResult['success']) {
                // 실패 시 다음 접속 때 재시도
                break;
            }
            $map   = $mapResult['map'];
            $items = [];
            $items[] = [
                'type'        => 'pillar',
                'title'       => $map['pillar']['title'],
                'keyword'     => $map['pillar']['keyword'],
                'description' => $map['pillar']['description'],
                'status'      => 'pending',
                'post_id'     => 0,
            ];
            foreach ($map['clusters'] as $c) {
                $items[] = [
                    'type'        => 'cluster',
                    'title'       => $c['title'],
                    'keyword'     => $c['keyword'],
                    'description' => $c['description'],
                    'status'      => 'pending',
                    'post_id'     => 0,
                ];
            }
            $queueData['projects'][$pidx]['status'] = 'active';
            $queueData['projects'][$pidx]['items']  = $items;
            _ta_write_queue($queueData);
            $config['last_run'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            break; // 이번 접속은 설계만, 다음 접속에서 첫 글 발행
        }

        if ($pStatus !== 'active') continue;

        $nextItem = null;
        $nextIdx  = -1;
        foreach ($project['items'] as $idx => $item) {
            if (($item['status'] ?? '') === 'pending') { $nextItem = $item; $nextIdx = $idx; break; }
        }

        if (!$nextItem) {
            $queueData['projects'][$pidx]['status']       = 'completed';
            $queueData['projects'][$pidx]['completed_at'] = date('Y-m-d H:i:s');
            _ta_apply_internal_links($queueData['projects'][$pidx]);
            _ta_write_queue($queueData);
            continue;
        }

        // 글 생성
        _ta_log('글 생성 시작: ' . $nextItem['title']);
        $result = _ta_generate_article($nextItem, $project['keyword'], $config);
        if (!$result['success']) {
            _ta_log('글 생성 실패: ' . $result['error']);
            $queueData['projects'][$pidx]['items'][$nextIdx]['status'] = 'failed';
            $queueData['projects'][$pidx]['items'][$nextIdx]['error']  = $result['error'];
            _ta_write_queue($queueData);
            break;
        }
        _ta_log('글 생성 성공: ' . mb_strlen($result['content']) . '자');

        $parsed      = _ta_parse_article($result['content']);
        $finalTitle  = $parsed['title'] ?: $nextItem['title'];
        $htmlContent = _ta_to_html($parsed['summary'], $parsed['body']);

        // 이미지 삽입 (범위 지정 시 랜덤 수량)
        if (($config['image_enabled'] ?? '1') === '1' && !empty($config['unsplash_api_key'])) {
            $imgRange = (string)($config['images_per_post'] ?? '2');
            if (strpos($imgRange, '-') !== false) {
                [$imgMin, $imgMax] = explode('-', $imgRange);
                $imgCount = rand(max(1, (int)$imgMin), min(5, (int)$imgMax));
            } else {
                $imgCount = max(1, min(5, (int)$imgRange));
            }
            $images = _ta_get_unsplash_images($nextItem['keyword'], $config['unsplash_api_key'], $imgCount);
            if (!empty($images)) $htmlContent = _ta_inject_images($htmlContent, $images);
        }

        // 발행
        _ta_log('발행 시도: board_id=' . $project['board_id']);
        $postId = _ta_publish_post($finalTitle, $htmlContent, $project['board_id']);
        _ta_log('발행 완료: post_id=' . $postId);

        // 큐 업데이트
        $queueData['projects'][$pidx]['items'][$nextIdx]['status']       = 'done';
        $queueData['projects'][$pidx]['items'][$nextIdx]['post_id']      = $postId;
        $queueData['projects'][$pidx]['items'][$nextIdx]['published_at'] = date('Y-m-d H:i:s');
        if ($nextItem['type'] === 'pillar') {
            $queueData['projects'][$pidx]['pillar_post_id'] = $postId;
            $queueData['projects'][$pidx]['pillar_title']   = $finalTitle;
        }
        _ta_write_queue($queueData);
        _ta_apply_internal_links($queueData['projects'][$pidx]);

        // config 업데이트
        $config['last_run']        = date('Y-m-d H:i:s');
        $config['total_generated'] = (int)($config['total_generated'] ?? 0) + 1;
        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if (class_exists('Cache')) Cache::flush();
        break;
    }
}

// ===== 수동 즉시 실행 =====
function _ta_force_run(): array {
    // 매번 파일에서 다시 읽음 (캐시된 글로벌 사용 X)
    $configFile = _ta_data_dir() . '/config.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    if (!is_array($config)) $config = [];
    $pluginDefault = file_exists(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
    if (!is_array($pluginDefault)) $pluginDefault = [];
    $config = array_merge($pluginDefault, $config);

    if (empty($config['openai_api_key'])) return ['success' => false, 'error' => 'OpenRouter API 키를 먼저 설정하세요.'];
    $queue = _ta_read_queue();
    if (empty($queue['projects'])) return ['success' => false, 'error' => '큐에 프로젝트가 없습니다.'];
    $hasPending = false;
    foreach ($queue['projects'] as $p) {
        $status = $p['status'] ?? '';
        // designing 상태면 진행 가능, active면 pending 아이템 확인
        if ($status === 'designing') { $hasPending = true; break; }
        if ($status !== 'active') continue;
        foreach ($p['items'] ?? [] as $it) {
            if (($it['status'] ?? '') === 'pending') { $hasPending = true; break 2; }
        }
    }
    if (!$hasPending) return ['success' => false, 'error' => '대기 중인 글이 없습니다.'];
    $config['last_run'] = '';
    _ta_process_queue($config, $configFile);
    return ['success' => true, 'message' => '글 1개 생성 완료! 큐 현황에서 확인하세요.'];
}

// ===== Hook: 페이지 접속마다 큐 처리 =====
function _ta_load_config_fresh(): array {
    $configFile = _ta_data_dir() . '/config.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    if (!is_array($config)) $config = [];
    $pluginDefault = file_exists(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
    if (!is_array($pluginDefault)) $pluginDefault = [];
    return array_merge($pluginDefault, $config);
}

Plugin::addHook('after_header', function() {
    $config = _ta_load_config_fresh();
    if (empty($config['openai_api_key'])) return;
    _ta_process_queue($config, _ta_data_dir() . '/config.json');
});

Plugin::addHook('admin_after_header', function() {
    $config = _ta_load_config_fresh();
    if (empty($config['openai_api_key'])) return;
    _ta_process_queue($config, _ta_data_dir() . '/config.json');
});

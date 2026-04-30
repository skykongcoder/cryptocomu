<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * 워드프레스 동시 포스팅 플러그인 v2.0
 *
 * 누리보드 글 작성 시 워드프레스에 자동 동시 포스팅.
 * OpenAI(gpt-4o-mini) 원문 변형 + Unsplash 이미지 본문 삽입 + 앵커텍스트 자동 삽입.
 */

// ===== 데이터 폴더 (플러그인 삭제해도 설정/로그 유지) =====
if (!function_exists('_wps_data_dir')) {
    function _wps_data_dir(): string {
        $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
        $dir  = $base . '/data/wp-sync-post';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

$_wps_config_file = _wps_data_dir() . '/config.json';
$_wps_log_file    = _wps_data_dir() . '/log.json';

$_wps_config_raw = file_exists($_wps_config_file)
    ? json_decode(file_get_contents($_wps_config_file), true)
    : [];
if (!is_array($_wps_config_raw)) $_wps_config_raw = [];

$_wps_config = array_merge([
    'enabled'          => '0',
    'post_type'        => 'posts',
    'wp_url'           => '',
    'wp_username'      => '',
    'wp_password'      => '',
    'openai_api_key'   => '',
    'unsplash_api_key' => '',
    'prompts'          => [],
    'anchor_links'     => [],
], $_wps_config_raw);

// ===== 로그 기록 =====
if (!function_exists('_wps_write_log')) {
    function _wps_write_log(string $logFile, string $title, string $status, string $message, string $wpPostUrl = ''): void {
        $logs = [];
        if (file_exists($logFile)) {
            $raw = json_decode(file_get_contents($logFile), true);
            if (is_array($raw)) $logs = $raw;
        }
        array_unshift($logs, [
            'time'       => date('Y-m-d H:i:s'),
            'title'      => $title,
            'status'     => $status,
            'message'    => $message,
            'wp_post_url'=> $wpPostUrl,
        ]);
        $logs = array_slice($logs, 0, 50);
        file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

// ===== Unsplash 이미지 여러 장 가져오기 =====
if (!function_exists('_wps_get_unsplash_images')) {
    function _wps_get_unsplash_images(string $keyword, string $apiKey, int $count = 5): array {
        if (empty($apiKey)) return [];

        $search = preg_replace('/[가-힣]+/u', '', $keyword);
        $search = trim($search);
        if (empty($search)) {
            $fallbacks = ['technology', 'business', 'digital', 'creative', 'office', 'nature', 'lifestyle'];
            $search = $fallbacks[array_rand($fallbacks)];
        }

        $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($search)
             . '&per_page=' . min($count * 2, 20) . '&client_id=' . urlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept-Version: v1'],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data   = json_decode($response, true);
        $images = [];
        if (!empty($data['results'])) {
            $pool = $data['results'];
            shuffle($pool);
            foreach (array_slice($pool, 0, $count) as $r) {
                if (!empty($r['urls']['regular'])) {
                    $images[] = [
                        'url'          => $r['urls']['regular'],
                        'photographer' => $r['user']['name'] ?? 'Unsplash',
                        'alt'          => $r['alt_description'] ?? $keyword,
                    ];
                }
            }
        }
        return $images;
    }
}

// ===== 이미지를 WordPress 미디어 라이브러리에 업로드 =====
if (!function_exists('_wps_upload_image_to_wp')) {
    function _wps_upload_image_to_wp(string $imageUrl, string $wpUrl, string $wpUser, string $wpPass): int {
        if (empty($imageUrl)) return 0;

        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $imageData = curl_exec($ch);
        curl_close($ch);
        if (empty($imageData)) return 0;

        $wpUrl = rtrim($wpUrl, '/');
        $ch    = curl_init($wpUrl . '/wp-json/wp/v2/media');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($wpUser . ':' . $wpPass),
                'Content-Type: image/jpeg',
                'Content-Disposition: attachment; filename=img-' . time() . '.jpg',
            ],
            CURLOPT_POSTFIELDS     => $imageData,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 201) return 0;
        $media = json_decode($response, true);
        return (int)($media['id'] ?? 0);
    }
}

// ===== 이미지를 HTML 본문 사이사이에 삽입 =====
if (!function_exists('_wps_inject_images_into_html')) {
    function _wps_inject_images_into_html(string $html, array $images): string {
        if (empty($images)) return $html;

        // h2 태그 기준으로 섹션 분리
        $parts = preg_split('/(?=<h2[\s>])/i', $html);
        if (count($parts) <= 1) {
            // h2 없으면 p 태그 기준으로 N단락마다 삽입
            $paragraphs = preg_split('/(?=<p[\s>])/i', $html);
            $step       = max(2, (int)floor(count($paragraphs) / (count($images) + 1)));
            $imgIdx     = 0;
            $result     = [];
            foreach ($paragraphs as $i => $p) {
                $result[] = $p;
                if ($imgIdx < count($images) && $i > 0 && $i % $step === 0) {
                    $img       = $images[$imgIdx++];
                    $result[] = _wps_image_html($img);
                }
            }
            return implode('', $result);
        }

        // h2 섹션 사이마다 이미지 삽입
        $result = [];
        $imgIdx = 0;
        foreach ($parts as $i => $part) {
            if ($i > 0 && $imgIdx < count($images)) {
                $result[] = _wps_image_html($images[$imgIdx++]);
            }
            $result[] = $part;
        }
        return implode('', $result);
    }
}

if (!function_exists('_wps_image_html')) {
    function _wps_image_html(array $img): string {
        $url  = htmlspecialchars($img['url']);
        $alt  = htmlspecialchars($img['alt'] ?? '');
        $by   = htmlspecialchars($img['photographer'] ?? '');
        return "\n<figure style=\"margin:32px 0;text-align:center\">"
             . "<img src=\"{$url}\" alt=\"{$alt}\" style=\"max-width:100%;height:auto;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,0.10)\">"
             . ($by ? "<figcaption style=\"font-size:12px;color:#9ca3af;margin-top:6px\">Photo by {$by} on Unsplash</figcaption>" : '')
             . "</figure>\n";
    }
}

// ===== OpenAI로 워드프레스용 글 생성 (원문 기반 + 프롬프트 + 앵커텍스트) =====
if (!function_exists('_wps_generate_content')) {
    function _wps_generate_content(string $title, string $content, array $config): string {
        if (empty($config['openai_api_key'])) return $content;

        $originalText = strip_tags($content);

        // 커스텀 프롬프트 선택
        $prompts = array_filter($config['prompts'] ?? [], fn($p) => trim($p) !== '');
        if (!empty($prompts)) {
            $customInstruction = $prompts[array_rand($prompts)];
            $customInstruction = str_replace(['{title}', '{content}'], [$title, $originalText], $customInstruction);
        } else {
            $customInstruction = '';
        }

        // 앵커텍스트 목록
        $anchorLinks  = $config['anchor_links'] ?? [];
        $anchorBlock  = '';
        if (!empty($anchorLinks)) {
            $lines = [];
            foreach ($anchorLinks as $line) {
                $parts = array_map('trim', explode('|', $line, 2));
                $url   = $parts[0] ?? '';
                $text  = $parts[1] ?? $url;
                if ($url) $lines[] = "- [{$text}]({$url})";
            }
            if ($lines) {
                $anchorBlock = "\n\n다음 앵커텍스트 링크를 본문 중간에 1~2개 자연스럽게 삽입하세요 (마크다운 형식 그대로 유지):\n" . implode("\n", $lines);
            }
        }

        // 최종 프롬프트 조립
        $prompt  = "아래 원본 게시글을 워드프레스 블로그 포스트로 재작성해주세요.\n\n";
        $prompt .= "제목: {$title}\n\n";
        $prompt .= "원본 내용:\n{$originalText}\n\n";
        $prompt .= "=== 작성 규칙 ===\n";
        $prompt .= "- 원본 내용을 바탕으로 더 풍부하고 자세하게 확장해서 작성\n";
        $prompt .= "- HTML 형식으로 작성 (h2, h3, p 태그 사용, 다른 태그 금지)\n";
        $prompt .= "- h2 소제목을 3~5개 넣어 구조화\n";
        $prompt .= "- 각 단락은 <p>...</p> 하나에 2~3문장만 담을 것\n";
        $prompt .= "- 자연스럽고 읽기 좋은 블로그 말투로 작성\n";
        $prompt .= "- 제목(<h1>)은 포함하지 말고 본문만 작성\n";
        $prompt .= "- 한국어로 작성\n";

        if ($customInstruction) {
            $prompt .= "\n=== 추가 지시 ===\n{$customInstruction}\n";
        }
        $prompt .= $anchorBlock;

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $config['openai_api_key'],
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                'model'       => 'openai/gpt-4o-mini',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.75,
                'max_tokens'  => 2500,
            ]),
            CURLOPT_TIMEOUT        => 90,
        ]);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) return $content;
        $data = json_decode($response, true);
        $generated = $data['choices'][0]['message']['content'] ?? '';
        if (empty($generated)) return $content;

        // 마크다운 링크 → HTML <a> 변환
        $generated = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/',
            '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
            $generated
        );

        return $generated;
    }
}

// ===== WordPress REST API로 포스팅 =====
if (!function_exists('_wps_post_to_wordpress')) {
    function _wps_post_to_wordpress(string $title, string $content, int $mediaId, array $config): array {
        $wpUrl    = rtrim($config['wp_url'], '/');
        $auth     = base64_encode($config['wp_username'] . ':' . $config['wp_password']);
        $postType = ($config['post_type'] ?? 'posts') === 'pages' ? 'pages' : 'posts';
        $endpoint = $wpUrl . '/wp-json/wp/v2/' . $postType;

        $body = [
            'title'   => $title,
            'content' => $content,
            'status'  => 'publish',
        ];
        if ($mediaId > 0) {
            $body['featured_media'] = $mediaId;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        $postUrl = $result['link'] ?? '';
        $wpMsg   = $result['message'] ?? ($result['code'] ?? '');

        if ($http_code === 201) {
            return ['success' => true, 'url' => $postUrl, 'message' => 'HTTP 201 포스팅 성공'];
        }
        $errMsg = $curlError ?: "HTTP {$http_code}" . ($wpMsg ? " / {$wpMsg}" : '');
        return ['success' => false, 'url' => '', 'message' => $errMsg];
    }
}

// ===== post_created 훅: 동시 포스팅 =====
Plugin::addHook('post_created', function ($id, $data) {
    // 설정 파일 직접 읽기 (글로벌 변수 의존 제거)
    $dataDir   = (defined('NB_ROOT') ? NB_ROOT : dirname(dirname(__DIR__))) . '/data/wp-sync-post';
    if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
    $cfgFile   = $dataDir . '/config.json';
    $logFile   = $dataDir . '/log.json';

    $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];
    if (!is_array($cfg)) $cfg = [];
    $cfg = array_merge([
        'enabled' => '0', 'post_type' => 'posts',
        'wp_url' => '', 'wp_username' => '', 'wp_password' => '',
        'openai_api_key' => '', 'unsplash_api_key' => '',
        'prompts' => [], 'anchor_links' => [],
    ], $cfg);

    // 활성화 체크
    if (($cfg['enabled'] ?? '0') !== '1') {
        _wps_write_log($logFile, $data['title'] ?? '(제목없음)', 'skip', '플러그인 비활성화 상태');
        return;
    }
    if (empty($cfg['wp_url']) || empty($cfg['wp_username']) || empty($cfg['wp_password'])) {
        _wps_write_log($logFile, $data['title'] ?? '(제목없음)', 'error', 'WP 연결 정보 미입력');
        return;
    }

    $title   = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    if (empty($title)) return;

    try {
        // 1. OpenAI 원문 변형
        $wpContent = _wps_generate_content($title, $content, $cfg);

        // 2. Unsplash 이미지
        $mediaId    = 0;
        $bodyImages = [];
        if (!empty($cfg['unsplash_api_key'])) {
            $images = _wps_get_unsplash_images($title, $cfg['unsplash_api_key'], 4);
            if (!empty($images[0])) {
                $mediaId = _wps_upload_image_to_wp(
                    $images[0]['url'], $cfg['wp_url'], $cfg['wp_username'], $cfg['wp_password']
                );
            }
            $bodyImages = array_slice($images, 1);
        }

        // 3. 본문 이미지 삽입
        if (!empty($bodyImages)) {
            $wpContent = _wps_inject_images_into_html($wpContent, $bodyImages);
        }

        // 4. WordPress 포스팅
        $result = _wps_post_to_wordpress($title, $wpContent, $mediaId, $cfg);

        _wps_write_log($logFile, $title,
            $result['success'] ? 'success' : 'error',
            $result['message'],
            $result['url'] ?? ''
        );
    } catch (Exception $e) {
        _wps_write_log($logFile, $title, 'error', $e->getMessage());
    }
}, 10);

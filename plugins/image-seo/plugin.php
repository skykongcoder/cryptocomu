<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * 이미지 SEO 자동 생성 플러그인
 * 이미지 1장 = 글 1개 → 각 이미지가 독립적인 SEO 페이지로 발행
 */

// ===== 헬퍼 함수 =====

function _iseo_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/image-seo';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _iseo_upload_dir(): string {
    $dir = _iseo_data_dir() . '/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _iseo_load_config(): array {
    $file = _iseo_data_dir() . '/config.json';
    if (!file_exists($file)) {
        $file = __DIR__ . '/config.json';
    }
    $raw = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    return array_merge([
        'target_board'   => '',
        'openai_api_key' => '',
    ], is_array($raw) ? $raw : []);
}

function _iseo_json_exit(array $data): void {
    while (ob_get_level()) ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function _iseo_get_author_id(): int {
    $prefix = DB::getPrefix();
    $row = DB::fetch("SELECT id FROM {$prefix}members WHERE level >= 10 ORDER BY id ASC LIMIT 1");
    return (int)($row['id'] ?? 0);
}

function _iseo_keyword_slug(string $keyword): string {
    $slug = preg_replace('/\s+/', '-', trim($keyword));
    $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug);
    return $slug ?: 'image-' . time();
}

// 이미지 1장짜리 글 1개 생성
function _iseo_create_single_post(string $keyword, int $index, array $img, string $description, string $boardId): int {
    $prefix   = DB::getPrefix();
    $memberId = _iseo_get_author_id();
    $now      = date('Y-m-d H:i:s');
    $title    = $keyword . ' ' . $index;
    $alt      = $keyword;
    $slug     = _iseo_keyword_slug($keyword) . '-' . $index . '-' . time();

    $html  = '<figure style="margin:0 0 20px;">';
    $html .= '<img src="' . htmlspecialchars($img['url']) . '"';
    $html .= ' alt="' . htmlspecialchars($alt) . '"';
    $html .= ' title="' . htmlspecialchars($alt) . '"';
    $html .= ' style="width:100%;height:auto;border-radius:8px;">';
    $html .= '</figure>';

    if (!empty($description)) {
        $paragraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', $description)));
        foreach ($paragraphs as $p) {
            $html .= '<p>' . nl2br(htmlspecialchars($p)) . '</p>';
        }
    } else {
        $html .= '<p>' . htmlspecialchars($keyword) . ' 관련 이미지입니다.</p>';
    }

    $postId = (int)DB::insert("{$prefix}posts", [
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

    if ($postId > 0) {
        Plugin::runHook('post_created', $postId, ['board_id' => $boardId]);
    }

    return $postId;
}

// AI 설명글 생성 (키워드당 1회 호출 → 전체 글에 재사용)
function _iseo_call_openai(string $apiKey, string $keyword): string {
    $prompt = "'{$keyword}' 이미지를 소개하는 짧은 소개글을 작성해주세요.\n조건:\n- 100~150자 분량\n- '{$keyword}' 키워드를 자연스럽게 2~3회 포함\n- 이미지를 보러 온 사람에게 유용하다는 느낌을 줄 것\n- 마크다운 없이 순수 텍스트로만 작성\n- 광고성 문구 금지";

    $payload = json_encode([
        'model'       => 'openai/gpt-4o-mini',
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'max_tokens'  => 300,
        'temperature' => 0.7,
    ]);

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

// ===== head 훅: ImageObject 스키마 주입 =====
Plugin::addHook('head', function () {
    $config = _iseo_load_config();
    if (empty($config['target_board'])) return;

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!preg_match('#/board/([^/]+)/(\d+)#', $uri, $m)) return;
    if ($m[1] !== $config['target_board']) return;

    $postId = (int)$m[2];
    $post   = Post::find($postId);
    if (!$post) return;

    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post['content'], $imgMatches);
    if (empty($imgMatches[1])) return;

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'ImageObject',
        'url'         => $imgMatches[1][0],
        'name'        => $post['title'],
        'description' => $post['title'] . ' 이미지',
    ];

    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
});

// ===== 관리자 API 라우터 =====
if (class_exists('Router')) {
    Router::post('/admin/plugin/image-seo/api', function () {
        if (!Auth::check() || !Auth::isAdmin()) {
            _iseo_json_exit(['success' => false, 'message' => '권한 없음']);
        }

        $action = $_POST['action'] ?? '';
        $config = _iseo_load_config();
        $prefix = DB::getPrefix();

        switch ($action) {

            // ---- 글 생성 (이미지 1장 = 글 1개) ----
            case 'create_post':
                $keyword = trim($_POST['keyword'] ?? '');
                $useAi   = !empty($_POST['use_ai']);
                $boardId = $config['target_board'];

                if (!$keyword) _iseo_json_exit(['success' => false, 'message' => '키워드를 입력하세요']);
                if (!$boardId) _iseo_json_exit(['success' => false, 'message' => '전용 게시판을 먼저 설정에서 선택하세요']);
                if (empty($_FILES['images']['name'][0])) _iseo_json_exit(['success' => false, 'message' => '이미지를 1장 이상 업로드하세요']);

                $keywordSlug = _iseo_keyword_slug($keyword);
                $uploadDir   = _iseo_upload_dir();
                $siteUrl     = rtrim(nb_setting('site_url', ''), '/');

                // 이미지 저장
                $imageInfos = [];
                $files      = $_FILES['images'];
                $fileCount  = count($files['name']);

                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) continue;
                    $newName  = $keywordSlug . '-' . (count($imageInfos) + 1) . '.' . $ext;
                    $savePath = $uploadDir . '/' . $newName;
                    if (move_uploaded_file($files['tmp_name'][$i], $savePath)) {
                        $imageInfos[] = [
                            'filename' => $newName,
                            'url'      => $siteUrl . '/data/image-seo/uploads/' . $newName,
                        ];
                    }
                }

                if (empty($imageInfos)) _iseo_json_exit(['success' => false, 'message' => '이미지 저장에 실패했습니다']);

                // AI 설명글 1회 생성 → 모든 글에 재사용
                $description = '';
                if ($useAi && !empty($config['openai_api_key'])) {
                    @set_time_limit(60);
                    $description = _iseo_call_openai($config['openai_api_key'], $keyword);
                }

                // 이미지 1장 = 글 1개 발행
                $createdIds = [];
                foreach ($imageInfos as $idx => $img) {
                    $postId = _iseo_create_single_post($keyword, $idx + 1, $img, $description, $boardId);
                    if ($postId > 0) $createdIds[] = $postId;
                }

                if (empty($createdIds)) _iseo_json_exit(['success' => false, 'message' => '글 생성에 실패했습니다']);

                _iseo_json_exit([
                    'success' => true,
                    'message' => count($createdIds) . '개의 글이 생성되었습니다',
                    'count'   => count($createdIds),
                    'post_ids' => $createdIds,
                    'first_url' => $siteUrl . '/board/' . $boardId . '/' . $createdIds[0],
                ]);
                break;

            // ---- 글 삭제 ----
            case 'delete_post':
                $postId = (int)($_POST['post_id'] ?? 0);
                if (!$postId) _iseo_json_exit(['success' => false, 'message' => '잘못된 요청']);
                DB::query("DELETE FROM {$prefix}posts WHERE id = ?", [$postId]);
                _iseo_json_exit(['success' => true, 'message' => '삭제되었습니다']);
                break;

            default:
                _iseo_json_exit(['success' => false, 'message' => '알 수 없는 요청']);
        }
    });
}

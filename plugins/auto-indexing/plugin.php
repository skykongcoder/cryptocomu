<?php
/**
 * 자동 색인 요청 플러그인
 * 글 발행 시 구글 Indexing API / IndexNow(네이버, Bing) 자동 호출
 */

$_aiConfigFile = __DIR__ . '/config.json';
$_aiConfig = file_exists($_aiConfigFile) ? json_decode(file_get_contents($_aiConfigFile), true) : [];

// 기본값
$_aiConfig = array_merge([
    'enabled' => true,
    'google_enabled' => false,
    'google_json_key' => '',
    'indexnow_enabled' => true,
    'indexnow_key' => '',
    'auto_on_create' => true,
    'auto_on_update' => true,
    'daily_limit' => 200,
    'exclude_boards' => '',
], $_aiConfig);

// DB 로그 테이블 생성
$_aiPrefix = DB::getPrefix();
try {
    DB::fetch("SELECT 1 FROM {$_aiPrefix}indexing_log LIMIT 1");
} catch (Exception $e) {
    DB::query("CREATE TABLE IF NOT EXISTS {$_aiPrefix}indexing_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id INT UNSIGNED NOT NULL DEFAULT 0,
        url VARCHAR(500) NOT NULL DEFAULT '',
        service ENUM('google','indexnow') NOT NULL DEFAULT 'indexnow',
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        response TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date (created_at),
        INDEX idx_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ===== IndexNow 키 파일 서빙 =====
if ($_aiConfig['indexnow_enabled'] && !empty($_aiConfig['indexnow_key'])) {
    $__inKey = $_aiConfig['indexnow_key'];
    if (class_exists('Router')) {
        Router::get('/' . $__inKey . '.txt', function() use ($__inKey) {
            header('Content-Type: text/plain');
            echo $__inKey;
            exit;
        });
    }
}

// ===== 글 작성 시 자동 색인 =====
if ($_aiConfig['enabled'] && $_aiConfig['auto_on_create']) {
    Plugin::addHook('post_created', function($postId, $data) use ($_aiConfig) {
        _ai_requestIndex($postId, $data['board_id'] ?? '', $_aiConfig);
    });
}

// ===== 글 수정 시 자동 색인 =====
if ($_aiConfig['enabled'] && $_aiConfig['auto_on_update']) {
    Plugin::addHook('post_updated', function($postId, $data) use ($_aiConfig) {
        $post = Post::find($postId);
        if ($post) {
            _ai_requestIndex($postId, $post['board_id'], $_aiConfig, 'URL_UPDATED');
        }
    });
}

// ===== 관리자 API =====
if (class_exists('Router')) {
    Router::post('/admin/plugin/auto-indexing/api', function() use ($_aiConfigFile, &$_aiConfig) {
        if (!Auth::check() || !Auth::isAdmin()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '권한 없음']);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? '';
        $prefix = DB::getPrefix();

        switch ($action) {
            // 설정 저장
            case 'save':
                $_aiConfig['enabled'] = !empty($input['enabled']);
                $_aiConfig['google_enabled'] = !empty($input['google_enabled']);
                $_aiConfig['google_json_key'] = trim($input['google_json_key'] ?? '');
                $_aiConfig['indexnow_enabled'] = !empty($input['indexnow_enabled']);
                $_aiConfig['indexnow_key'] = trim($input['indexnow_key'] ?? '');
                $_aiConfig['auto_on_create'] = !empty($input['auto_on_create']);
                $_aiConfig['auto_on_update'] = !empty($input['auto_on_update']);
                $_aiConfig['daily_limit'] = max(1, (int)($input['daily_limit'] ?? 200));
                $_aiConfig['exclude_boards'] = trim($input['exclude_boards'] ?? '');
                file_put_contents($_aiConfigFile, json_encode($_aiConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true]);
                break;

            // 수동 색인 요청
            case 'manual':
                $url = trim($input['url'] ?? '');
                if (!$url) {
                    echo json_encode(['success' => false, 'message' => 'URL을 입력하세요']);
                    break;
                }
                $results = [];
                if ($_aiConfig['indexnow_enabled'] && !empty($_aiConfig['indexnow_key'])) {
                    $results['indexnow'] = _ai_sendIndexNow($url, $_aiConfig['indexnow_key']);
                }
                if ($_aiConfig['google_enabled'] && !empty($_aiConfig['google_json_key'])) {
                    $results['google'] = _ai_sendGoogle($url, $_aiConfig['google_json_key'], 'URL_UPDATED');
                }
                // 로그 저장
                foreach ($results as $svc => $res) {
                    DB::insert("{$prefix}indexing_log", [
                        'post_id' => 0,
                        'url' => $url,
                        'service' => $svc,
                        'status' => $res['success'] ? 'success' : 'fail',
                        'response' => mb_substr($res['response'] ?? '', 0, 1000),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                echo json_encode(['success' => true, 'results' => $results]);
                break;

            // 로그 조회
            case 'logs':
                $page = max(1, (int)($input['page'] ?? 1));
                $limit = 30;
                $offset = ($page - 1) * $limit;
                $total = DB::count("{$prefix}indexing_log");
                $rows = DB::fetchAll("SELECT * FROM {$prefix}indexing_log ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}");
                echo json_encode(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
                break;

            // 오늘 요청 수
            case 'today_count':
                $cnt = DB::fetch("SELECT COUNT(*) as cnt FROM {$prefix}indexing_log WHERE DATE(created_at) = CURDATE()");
                echo json_encode(['success' => true, 'count' => (int)($cnt['cnt'] ?? 0), 'limit' => $_aiConfig['daily_limit']]);
                break;

            // JSON 키 파일 업로드
            case 'upload_key':
                if (empty($_FILES['google_key_file']) || $_FILES['google_key_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => '파일을 선택하세요']);
                    break;
                }
                $file = $_FILES['google_key_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'json') {
                    echo json_encode(['success' => false, 'message' => 'JSON 파일만 업로드 가능합니다']);
                    break;
                }
                // 파일 내용 검증
                $content = file_get_contents($file['tmp_name']);
                $keyData = json_decode($content, true);
                if (!$keyData || empty($keyData['private_key']) || empty($keyData['client_email'])) {
                    echo json_encode(['success' => false, 'message' => '올바른 구글 서비스 계정 JSON 파일이 아닙니다']);
                    break;
                }
                // 기존 키 파일 삭제
                if (!empty($_aiConfig['google_json_key']) && file_exists(__DIR__ . '/' . $_aiConfig['google_json_key'])) {
                    unlink(__DIR__ . '/' . $_aiConfig['google_json_key']);
                }
                // 저장
                $saveName = 'google-key-' . substr(md5(time()), 0, 8) . '.json';
                move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $saveName);
                $_aiConfig['google_json_key'] = $saveName;
                file_put_contents($_aiConfigFile, json_encode($_aiConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true, 'message' => '업로드 완료', 'email' => $keyData['client_email'], 'filename' => $saveName]);
                break;

            // JSON 키 파일 삭제
            case 'delete_key':
                if (!empty($_aiConfig['google_json_key']) && file_exists(__DIR__ . '/' . $_aiConfig['google_json_key'])) {
                    unlink(__DIR__ . '/' . $_aiConfig['google_json_key']);
                }
                $_aiConfig['google_json_key'] = '';
                file_put_contents($_aiConfigFile, json_encode($_aiConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true, 'message' => '키 파일이 삭제되었습니다']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => '알 수 없는 요청']);
        }
        exit;
    });
}

// ===== 색인 요청 메인 함수 =====
function _ai_requestIndex(int $postId, string $boardId, array $config, string $type = 'URL_UPDATED'): void
{
    // 제외 게시판 체크
    if (!empty($config['exclude_boards'])) {
        $excluded = array_map('trim', explode(',', $config['exclude_boards']));
        if (in_array($boardId, $excluded)) return;
    }

    // 일일 한도 체크
    $prefix = DB::getPrefix();
    $todayCount = DB::fetch("SELECT COUNT(*) as cnt FROM {$prefix}indexing_log WHERE DATE(created_at) = CURDATE()");
    if (($todayCount['cnt'] ?? 0) >= $config['daily_limit']) return;

    // 비밀글/숨김글은 색인하지 않음
    $post = Post::find($postId);
    if (!$post) return;
    if (!empty($post['is_secret']) || !empty($post['is_hidden'])) return;

    // URL 생성
    $siteUrl = rtrim(nb_setting('site_url', ''), '/');
    $url = $siteUrl . "/board/{$boardId}/{$postId}";

    // IndexNow 요청
    if ($config['indexnow_enabled'] && !empty($config['indexnow_key'])) {
        $result = _ai_sendIndexNow($url, $config['indexnow_key']);
        DB::insert("{$prefix}indexing_log", [
            'post_id' => $postId,
            'url' => $url,
            'service' => 'indexnow',
            'status' => $result['success'] ? 'success' : 'fail',
            'response' => mb_substr($result['response'] ?? '', 0, 1000),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Google Indexing API 요청
    if ($config['google_enabled'] && !empty($config['google_json_key'])) {
        $result = _ai_sendGoogle($url, $config['google_json_key'], $type);
        DB::insert("{$prefix}indexing_log", [
            'post_id' => $postId,
            'url' => $url,
            'service' => 'google',
            'status' => $result['success'] ? 'success' : 'fail',
            'response' => mb_substr($result['response'] ?? '', 0, 1000),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

// ===== IndexNow 전송 (네이버, Bing, Yandex 동시) =====
function _ai_sendIndexNow(string $url, string $key): array
{
    $host = parse_url($url, PHP_URL_HOST);
    $payload = json_encode([
        'host' => $host,
        'key' => $key,
        'keyLocation' => "https://{$host}/{$key}.txt",
        'urlList' => [$url],
    ]);

    // IndexNow 엔드포인트 (네이버, Bing 모두 지원)
    $endpoints = [
        'https://api.indexnow.org/indexnow',
    ];

    $lastResponse = '';
    $success = false;

    foreach ($endpoints as $endpoint) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $lastResponse = "HTTP {$httpCode}: " . ($response ?: $error);

        // 200 또는 202 = 성공
        if ($httpCode >= 200 && $httpCode < 300) {
            $success = true;
        }
    }

    return ['success' => $success, 'response' => $lastResponse];
}

// ===== Google Indexing API 전송 =====
function _ai_sendGoogle(string $url, string $jsonKeyPath, string $type = 'URL_UPDATED'): array
{
    // JSON 키 파일 경로 (plugins/auto-indexing/ 기준)
    $keyFile = $jsonKeyPath;
    if (!file_exists($keyFile)) {
        $keyFile = __DIR__ . '/' . $jsonKeyPath;
    }
    if (!file_exists($keyFile)) {
        return ['success' => false, 'response' => '구글 서비스 계정 JSON 키 파일을 찾을 수 없습니다: ' . $jsonKeyPath];
    }

    $keyData = json_decode(file_get_contents($keyFile), true);
    if (!$keyData || empty($keyData['private_key']) || empty($keyData['client_email'])) {
        return ['success' => false, 'response' => 'JSON 키 파일 형식이 올바르지 않습니다'];
    }

    // JWT 토큰 생성
    $now = time();
    $header = _ai_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = _ai_base64url(json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/indexing',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));

    $signInput = $header . '.' . $claim;
    $signature = '';
    $privateKey = openssl_pkey_get_private($keyData['private_key']);
    if (!$privateKey) {
        return ['success' => false, 'response' => '개인 키를 로드할 수 없습니다'];
    }
    openssl_sign($signInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $jwt = $signInput . '.' . _ai_base64url($signature);

    // OAuth2 토큰 교환
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $tokenResponse = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($tokenResponse, true);
    if (empty($tokenData['access_token'])) {
        return ['success' => false, 'response' => '토큰 발급 실패: ' . ($tokenResponse ?: 'empty')];
    }

    // Indexing API 호출
    $payload = json_encode([
        'url' => $url,
        'type' => $type, // URL_UPDATED or URL_DELETED
    ]);

    $ch = curl_init('https://indexing.googleapis.com/v3/urlNotifications:publish');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $tokenData['access_token'],
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'response' => "HTTP {$httpCode}: {$response}",
    ];
}

function _ai_base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

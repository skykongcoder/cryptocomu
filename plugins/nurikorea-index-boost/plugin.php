<?php
/**
 * 누리코리아 색인 가속기
 *
 * 새 글·수정글을 IndexNow API 로 검색엔진에 즉시 제출.
 * 네이버 / Bing / Yandex 통합 제출 (endpoint 하나로 전파).
 * (옵션) Google Indexing API 지원.
 *
 * 설정 키:
 *   nib_enabled_indexnow   (1/0, 기본 1)
 *   nib_enabled_google     (1/0, 기본 0)
 *   nib_google_key_json    (Service Account JSON 내용, 업로드 시 저장)
 *   nib_indexnow_key       (최초 실행 시 랜덤 32자 자동 생성, site root 에 검증 파일 배치)
 *
 * 저장소:
 *   submissions.log        (최근 100건, JSON lines)
 *   indexnow_key           (인증 키 텍스트, site root 에 복사됨)
 */

// ==================== 설정 헬퍼 (loadAll 시점 안전) ====================
if (!function_exists('nib_get_setting')) {
    function nib_get_setting(string $key, string $default = ''): string
    {
        if (defined('NB_SETTINGS')) {
            $s = NB_SETTINGS;
            return isset($s[$key]) ? (string)$s[$key] : $default;
        }
        if (!class_exists('DB')) return $default;
        try {
            $prefix = DB::getPrefix();
            $row = DB::fetch("SELECT setting_value FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            return $row ? (string)$row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('nib_set_setting')) {
    function nib_set_setting(string $key, string $value): void
    {
        if (!class_exists('DB')) return;
        try {
            $prefix = DB::getPrefix();
            $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                DB::update("{$prefix}settings", ['setting_value' => $value], 'setting_key = ?', [$key]);
            } else {
                DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $value]);
            }
        } catch (Exception $e) {}
    }
}

// ==================== IndexNow 인증 키 보장 ====================
if (!function_exists('nib_ensure_indexnow_key')) {
    function nib_ensure_indexnow_key(): string
    {
        $key = nib_get_setting('nib_indexnow_key', '');
        if ($key === '' || strlen($key) < 8) {
            $key = bin2hex(random_bytes(16)); // 32자
            nib_set_setting('nib_indexnow_key', $key);
        }
        // 사이트 루트에 검증 파일 배치 (IndexNow 요구사항)
        if (defined('NB_ROOT')) {
            $filePath = NB_ROOT . '/' . $key . '.txt';
            if (!file_exists($filePath)) {
                @file_put_contents($filePath, $key);
            }
        }
        return $key;
    }
}

// ==================== URL 생성 ====================
if (!function_exists('nib_post_url')) {
    function nib_post_url(int $post_id): string
    {
        $site_url = rtrim(nib_get_setting('site_url', ''), '/');
        if ($site_url === '' && isset($_SERVER['HTTP_HOST'])) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $site_url = $proto . '://' . $_SERVER['HTTP_HOST'];
        }
        // 누리보드 기본 게시글 URL 패턴: /board/{board_id}/{post_id}
        if (class_exists('DB')) {
            try {
                $prefix = DB::getPrefix();
                $row = DB::fetch("SELECT board_id, slug FROM {$prefix}posts WHERE id = ?", [$post_id]);
                if ($row && !empty($row['board_id'])) {
                    $tail = (string)$post_id;
                    return $site_url . '/board/' . $row['board_id'] . '/' . $tail;
                }
            } catch (Exception $e) {}
        }
        return $site_url . '/?p=' . $post_id;
    }
}

// ==================== 제출 로그 ====================
if (!function_exists('nib_log')) {
    function nib_log(array $entry): void
    {
        $file = __DIR__ . '/submissions.log';
        $entry['at'] = date('c');
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @file_put_contents($file, $line . "\n", FILE_APPEND);
        // 100 줄 넘으면 뒤 100줄만 유지
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if (is_array($lines) && count($lines) > 200) {
            $keep = array_slice($lines, -100);
            @file_put_contents($file, implode("\n", $keep) . "\n");
        }
    }
}

// ==================== IndexNow 제출 (네이버/Bing/Yandex 통합) ====================
if (!function_exists('nib_submit_indexnow')) {
    function nib_submit_indexnow(array $urls): array
    {
        if (empty($urls)) return ['ok' => false, 'error' => 'empty_urls'];

        $key = nib_ensure_indexnow_key();
        $host = parse_url($urls[0], PHP_URL_HOST);
        if (!$host) return ['ok' => false, 'error' => 'invalid_host'];

        $payload = [
            'host'    => $host,
            'key'     => $key,
            'keyLocation' => (parse_url($urls[0], PHP_URL_SCHEME) ?: 'https') . '://' . $host . '/' . $key . '.txt',
            'urlList' => array_values(array_unique($urls)),
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl_unavailable'];
        }

        $ch = curl_init('https://api.indexnow.org/IndexNow');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT      => 'NuriBoard-IndexBoost/1.0',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        // IndexNow 응답 코드: 200/202 = 성공, 400 = 키 매치 실패, 403 = 키 누락, 422 = URL 오류
        $ok = in_array((int)$code, [200, 202], true);
        return [
            'ok'    => $ok,
            'http'  => (int)$code,
            'error' => $ok ? '' : ($err ?: trim((string)$resp)),
            'count' => count($urls),
        ];
    }
}

// ==================== Google Indexing API (선택) ====================
if (!function_exists('nib_get_google_token')) {
    function nib_get_google_token(): array
    {
        $json = nib_get_setting('nib_google_key_json', '');
        if ($json === '') return ['ok' => false, 'skipped' => true, 'reason' => 'no_key'];

        $sa = json_decode($json, true);
        if (!is_array($sa) || empty($sa['private_key']) || empty($sa['client_email'])) {
            return ['ok' => false, 'error' => 'invalid_service_account'];
        }

        $now = time();
        $claims = [
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claim  = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        $pkey = openssl_pkey_get_private($sa['private_key']);
        if (!$pkey) return ['ok' => false, 'error' => 'pkey_invalid'];

        $signature = '';
        openssl_sign("{$header}.{$claim}", $signature, $pkey, OPENSSL_ALGO_SHA256);
        $jwt = $header . '.' . $claim . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $tokenResp = curl_exec($ch);
        curl_close($ch);
        $tok = json_decode($tokenResp, true);

        if (empty($tok['access_token'])) {
            return ['ok' => false, 'error' => 'token_failed', 'resp' => substr((string)$tokenResp, 0, 300)];
        }
        return ['ok' => true, 'token' => $tok['access_token']];
    }
}

if (!function_exists('nib_submit_google_with_token')) {
    function nib_submit_google_with_token(string $url, string $token, string $type = 'URL_UPDATED'): array
    {
        $ch = curl_init('https://indexing.googleapis.com/v3/urlNotifications:publish');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['url' => $url, 'type' => $type]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $code === 200, 'http' => (int)$code, 'resp' => substr((string)$resp, 0, 200)];
    }
}

if (!function_exists('nib_submit_google')) {
    function nib_submit_google(string $url, string $type = 'URL_UPDATED'): array
    {
        $tok = nib_get_google_token();
        if (empty($tok['ok'])) return $tok;
        return nib_submit_google_with_token($url, $tok['token'], $type);
    }
}

if (!function_exists('nib_submit_google_batch')) {
    function nib_submit_google_batch(array $urls, string $type = 'URL_UPDATED'): array
    {
        if (empty($urls)) return ['ok' => false, 'error' => 'empty_urls'];
        $tok = nib_get_google_token();
        if (empty($tok['ok'])) return $tok;

        $success = 0;
        $fail = 0;
        $last_error = '';
        foreach ($urls as $i => $url) {
$r = nib_submit_google_with_token($url, $tok['token'], $type);
            if ($r['ok']) {
                $success++;
            } else {
                $fail++;
                $last_error = 'HTTP ' . $r['http'] . ': ' . substr($r['resp'], 0, 100);
            }
        }
        return ['ok' => $success > 0 && $fail === 0, 'http' => 200, 'success' => $success, 'fail' => $fail, 'error' => $last_error];
    }
}

// ==================== 통합 제출 함수 (후크에서 호출) ====================
if (!function_exists('nib_dispatch')) {
    function nib_dispatch(int $post_id, string $event): void
    {
        $url = nib_post_url($post_id);
        if (!preg_match('#^https?://#', $url)) return;

        $result = ['post_id' => $post_id, 'event' => $event, 'url' => $url];

        // IndexNow
        if (nib_get_setting('nib_enabled_indexnow', '1') === '1') {
            $r = nib_submit_indexnow([$url]);
            $result['indexnow'] = $r;
        }
        // Google (옵션)
        if (nib_get_setting('nib_enabled_google', '0') === '1') {
            $type = ($event === 'post_deleted') ? 'URL_DELETED' : 'URL_UPDATED';
            $r = nib_submit_google($url, $type);
            $result['google'] = $r;
        }

        nib_log($result);
    }
}

// ==================== 누리보드 훅 연결 ====================
if (class_exists('Plugin') && method_exists('Plugin', 'addHook')) {
    Plugin::addHook('post_created', function ($post_id) {
        nib_dispatch((int)$post_id, 'post_created');
    });
    Plugin::addHook('post_updated', function ($post_id) {
        nib_dispatch((int)$post_id, 'post_updated');
    });
}

// ==================== 활성화 시 키 미리 생성 ====================
nib_ensure_indexnow_key();

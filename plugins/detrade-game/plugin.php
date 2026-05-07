<?php
/**
 * Detrade 코인 게임 플러그인 (재미용 가상 포인트)
 *
 * 라우트:
 *   /game            — 게임 iframe 페이지 (로그인 필수)
 *   /wallet          — 내 포인트 지갑 + 베팅 내역 + 일일 보너스
 *   /api/wallet/amount/deduction   — Detrade 가 호출 (베팅 시 차감)
 *   /api/wallet/amount/add         — Detrade 가 호출 (정산 시 추가)
 *   /api/wallet/balance/{currency} — Detrade 가 호출 (잔액 조회)
 *   /api/order/push                — Detrade 가 호출 (게임 결과 푸시)
 *
 * 핵심 원칙:
 *   - 가상 포인트만 사용 (KRW/USDT 아님)
 *   - 환금/출금 절대 불가능 (해당 기능 자체가 없음)
 *   - 일일 보너스 + 글쓰기 보상으로 포인트 획득
 *   - 모든 webhook 은 JWS RS256 서명 검증 + IP 화이트리스트 + 멱등성
 */

const DT_DIR = __DIR__;
const DT_CONFIG_FILE = __DIR__ . '/config.json';

// === 설정 로드 ===
function dt_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $defaults = file_exists(__DIR__ . '/config.example.json')
        ? json_decode(@file_get_contents(__DIR__ . '/config.example.json'), true) ?: []
        : [];
    $user = file_exists(DT_CONFIG_FILE)
        ? json_decode(@file_get_contents(DT_CONFIG_FILE), true) ?: []
        : [];
    $cfg = array_merge($defaults, $user);
    return $cfg;
}

// === DB 마이그레이션 (자동) ===
function dt_install_schema(): void {
    if (!class_exists('DB')) return;
    try {
        $prefix = DB::getPrefix();
        DB::query("CREATE TABLE IF NOT EXISTS {$prefix}dt_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            biz_id VARCHAR(80) NOT NULL,
            biz_type VARCHAR(40) DEFAULT '',
            biz_sub_id VARCHAR(80) DEFAULT '',
            op_type ENUM('deduction','add','order_push') NOT NULL,
            currency VARCHAR(20) NOT NULL DEFAULT 'PT',
            amount DECIMAL(20,4) NOT NULL DEFAULT 0,
            balance_before DECIMAL(20,4) NULL,
            balance_after DECIMAL(20,4) NULL,
            status ENUM('ok','fail') NOT NULL DEFAULT 'ok',
            raw_payload MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_user (user_id),
            INDEX idx_biz (biz_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        DB::query("CREATE TABLE IF NOT EXISTS {$prefix}dt_idempotency (
            biz_id VARCHAR(80) NOT NULL,
            op_type VARCHAR(20) NOT NULL,
            response_json TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (biz_id, op_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        DB::query("CREATE TABLE IF NOT EXISTS {$prefix}dt_daily_bonus (
            user_id INT UNSIGNED NOT NULL,
            claimed_date DATE NOT NULL,
            amount DECIMAL(20,4) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, claimed_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log('[detrade] schema install fail: ' . $e->getMessage());
    }
}
dt_install_schema();

// =====================================================================
// JWS RS256 (raw — 외부 라이브러리 의존 X)
// =====================================================================

function dt_b64url(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function dt_b64url_decode(string $s): string {
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($s, '-_', '+/')) ?: '';
}

/** payload(JSON 또는 임의 string) 를 RS256 으로 sign → JWS Compact (header.payload.sig) */
function dt_jws_sign(string $payload, string $privateKeyPem): ?string {
    $key = openssl_pkey_get_private($privateKeyPem);
    if (!$key) return null;
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWS']);
    $signing = dt_b64url($header) . '.' . dt_b64url($payload);
    $sig = '';
    if (!openssl_sign($signing, $sig, $key, OPENSSL_ALGO_SHA256)) return null;
    return $signing . '.' . dt_b64url($sig);
}

/** JWS 검증 → 검증 통과 시 payload 반환, 실패 시 null */
function dt_jws_verify(string $jws, string $publicKeyPem): ?string {
    $parts = explode('.', $jws);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $signing = $h . '.' . $p;
    $sig = dt_b64url_decode($s);
    $key = openssl_pkey_get_public($publicKeyPem);
    if (!$key) return null;
    $ok = openssl_verify($signing, $sig, $key, OPENSSL_ALGO_SHA256);
    return $ok === 1 ? dt_b64url_decode($p) : null;
}

// =====================================================================
// HTTP 요청 헬퍼 (Detrade 로 송신)
// =====================================================================

function dt_http_post_json(string $url, array $body, array $headers = [], int $timeout = 8): array {
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    $defaultHeaders = ['Content-Type: application/json; charset=utf-8'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    if (function_exists('nb_ca_bundle') && ($ca = nb_ca_bundle())) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [
        'http_code' => $code,
        'body'      => $raw ?: '',
        'error'     => $err,
        'json'      => $raw ? (json_decode($raw, true) ?: null) : null,
    ];
}

// =====================================================================
// Detrade Login API 호출 → 임베드 URL 받기
// =====================================================================

function dt_login_get_embed_url(array $member): array {
    $cfg = dt_config();
    if (empty($cfg['enabled']) || $cfg['enabled'] !== '1') {
        return ['ok' => false, 'error' => '플러그인이 비활성화되어 있습니다.'];
    }
    if (empty($cfg['api_key']) || empty($cfg['private_key_pem']) || empty($cfg['baseurl'])) {
        return ['ok' => false, 'error' => 'Detrade 설정이 완료되지 않았습니다 (apiKey/privateKey/baseurl).'];
    }

    $body = [
        'userId'       => (string)$member['id'],
        'userName'     => $member['nickname'] ?: ('user_' . $member['id']),
        'avatar'       => $member['profile_image'] ?: '',
        'currency'     => $cfg['currency'] ?: 'PT',
        'minAmaount'   => (float)($cfg['min_amount'] ?? 1000),  // 문서 오타 그대로 (minAmaount)
        'maxAmount'    => (float)($cfg['max_amount'] ?? 1000000),
        'exchangeRate' => (float)($cfg['exchange_rate'] ?? 1),
        'balanceType'  => 1,
    ];
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    $sign = dt_jws_sign($payload, $cfg['private_key_pem']);
    if (!$sign) return ['ok' => false, 'error' => 'JWS 서명 생성 실패 — privateKey 확인'];

    $headers = [
        'apiKey: ' . $cfg['api_key'],
        'sign: '   . $sign,
    ];
    $url = rtrim($cfg['baseurl'], '/') . '/api/tob/thirdParty/login';
    $resp = dt_http_post_json($url, $body, $headers, 10);
    if ($resp['http_code'] !== 200 || !$resp['json']) {
        return [
            'ok'    => false,
            'error' => 'Detrade 응답 실패 (HTTP ' . $resp['http_code'] . ')',
            'raw'   => $resp['body'],
        ];
    }
    if (($resp['json']['code'] ?? 0) !== 200) {
        return [
            'ok'    => false,
            'error' => $resp['json']['msg'] ?? '알 수 없는 응답',
        ];
    }
    return [
        'ok'        => true,
        'embed_url' => $resp['json']['data'] ?? '',
    ];
}

// =====================================================================
// Webhook 검증: JWS 서명 + IP 화이트리스트
// =====================================================================

function dt_webhook_verify(): array {
    $cfg = dt_config();

    // 1) IP 화이트리스트
    $whitelist = array_filter(array_map('trim', explode(',', $cfg['ip_whitelist'] ?? '')));
    $clientIp  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $clientIp  = trim(explode(',', $clientIp)[0]);
    if (!empty($whitelist) && !in_array($clientIp, $whitelist, true)) {
        return ['ok' => false, 'http_code' => 403, 'error' => 'IP not whitelisted: ' . $clientIp];
    }

    // 2) 서명 검증
    $sign = $_SERVER['HTTP_SIGN'] ?? '';
    $apiKey = $_SERVER['HTTP_APIKEY'] ?? '';
    if (!$sign) return ['ok' => false, 'http_code' => 401, 'error' => 'missing sign header'];
    if (!$apiKey || $apiKey !== ($cfg['api_key'] ?? '')) {
        return ['ok' => false, 'http_code' => 401, 'error' => 'invalid apiKey'];
    }

    $rawBody = file_get_contents('php://input') ?: '';
    if (!empty($cfg['detrade_public_key_pem'])) {
        $verifiedPayload = dt_jws_verify($sign, $cfg['detrade_public_key_pem']);
        if ($verifiedPayload === null) {
            return ['ok' => false, 'http_code' => 401, 'error' => 'JWS verify failed'];
        }
        // payload 가 body 와 일치하는지 비교 (loose)
        if ($verifiedPayload && $rawBody && trim($verifiedPayload) !== trim($rawBody)) {
            return ['ok' => false, 'http_code' => 401, 'error' => 'JWS payload mismatch'];
        }
    }
    // detrade_public_key_pem 이 비어있으면 서명 검증 스킵 (테스트 모드)

    $body = $rawBody ? json_decode($rawBody, true) : [];
    if (!is_array($body)) $body = [];

    return ['ok' => true, 'body' => $body, 'raw' => $rawBody];
}

// =====================================================================
// 멱등성 체크
// =====================================================================

function dt_idempotency_get(string $bizId, string $opType): ?array {
    if (!class_exists('DB') || !$bizId) return null;
    $prefix = DB::getPrefix();
    try {
        $row = DB::fetch(
            "SELECT response_json FROM {$prefix}dt_idempotency WHERE biz_id = ? AND op_type = ?",
            [$bizId, $opType]
        );
        if (!$row) return null;
        $j = json_decode($row['response_json'] ?? 'null', true);
        return is_array($j) ? $j : null;
    } catch (Exception $e) {
        return null;
    }
}

function dt_idempotency_save(string $bizId, string $opType, array $response): void {
    if (!class_exists('DB') || !$bizId) return;
    $prefix = DB::getPrefix();
    try {
        DB::query(
            "INSERT IGNORE INTO {$prefix}dt_idempotency (biz_id, op_type, response_json, created_at)
             VALUES (?, ?, ?, NOW())",
            [$bizId, $opType, json_encode($response, JSON_UNESCAPED_UNICODE)]
        );
    } catch (Exception $e) {}
}

// =====================================================================
// 포인트(=잔액) 조작 - nb_members.point 직접
// =====================================================================

function dt_get_point(int $userId): float {
    if (!class_exists('DB') || !$userId) return 0;
    $prefix = DB::getPrefix();
    $row = DB::fetch("SELECT point FROM {$prefix}members WHERE id = ?", [$userId]);
    return (float)($row['point'] ?? 0);
}

/**
 * 포인트 차감 (트랜잭션 안에서 호출).
 * 부족 시 false 반환.
 */
function dt_deduct_point(int $userId, float $amount): array {
    if ($amount <= 0) return ['ok' => false, 'error' => 'amount must be positive'];
    $prefix = DB::getPrefix();
    DB::query("START TRANSACTION");
    try {
        $row = DB::fetch("SELECT point FROM {$prefix}members WHERE id = ? FOR UPDATE", [$userId]);
        if (!$row) {
            DB::query("ROLLBACK");
            return ['ok' => false, 'error' => 'user not found'];
        }
        $before = (float)$row['point'];
        if ($before < $amount) {
            DB::query("ROLLBACK");
            return ['ok' => false, 'error' => 'insufficient balance', 'balance' => $before];
        }
        $after = $before - $amount;
        DB::query("UPDATE {$prefix}members SET point = ? WHERE id = ?", [$after, $userId]);
        DB::query("COMMIT");
        return ['ok' => true, 'before' => $before, 'after' => $after];
    } catch (Exception $e) {
        DB::query("ROLLBACK");
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function dt_add_point(int $userId, float $amount): array {
    if ($amount <= 0) return ['ok' => false, 'error' => 'amount must be positive'];
    $prefix = DB::getPrefix();
    DB::query("START TRANSACTION");
    try {
        $row = DB::fetch("SELECT point FROM {$prefix}members WHERE id = ? FOR UPDATE", [$userId]);
        if (!$row) {
            DB::query("ROLLBACK");
            return ['ok' => false, 'error' => 'user not found'];
        }
        $before = (float)$row['point'];
        $after = $before + $amount;
        DB::query("UPDATE {$prefix}members SET point = ? WHERE id = ?", [$after, $userId]);
        DB::query("COMMIT");
        return ['ok' => true, 'before' => $before, 'after' => $after];
    } catch (Exception $e) {
        DB::query("ROLLBACK");
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function dt_log_order(int $userId, string $bizId, string $opType, float $amount, array $body, array $balance, string $status = 'ok'): void {
    if (!class_exists('DB')) return;
    $prefix = DB::getPrefix();
    try {
        DB::query(
            "INSERT INTO {$prefix}dt_orders (user_id, biz_id, biz_type, biz_sub_id, op_type, currency, amount, balance_before, balance_after, status, raw_payload, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                $bizId,
                $body['bizType'] ?? '',
                $body['bizSubId'] ?? '',
                $opType,
                $body['currency'] ?? 'PT',
                $amount,
                $balance['before'] ?? null,
                $balance['after'] ?? null,
                $status,
                json_encode($body, JSON_UNESCAPED_UNICODE),
            ]
        );
    } catch (Exception $e) {}
}

// =====================================================================
// 일일 보너스 청구
// =====================================================================

function dt_claim_daily_bonus(int $userId): array {
    $cfg = dt_config();
    $bonus = (float)($cfg['daily_bonus'] ?? 10000);
    if ($bonus <= 0) return ['ok' => false, 'error' => '일일 보너스가 비활성화되어 있습니다.'];
    $prefix = DB::getPrefix();
    $today = date('Y-m-d');
    $exists = DB::fetch(
        "SELECT amount FROM {$prefix}dt_daily_bonus WHERE user_id = ? AND claimed_date = ?",
        [$userId, $today]
    );
    if ($exists) return ['ok' => false, 'error' => '오늘 이미 보너스를 받으셨어요. 내일 다시 오세요!'];
    $r = dt_add_point($userId, $bonus);
    if (!$r['ok']) return $r;
    DB::query(
        "INSERT INTO {$prefix}dt_daily_bonus (user_id, claimed_date, amount, created_at) VALUES (?, ?, ?, NOW())",
        [$userId, $today, $bonus]
    );
    return ['ok' => true, 'amount' => $bonus, 'balance' => $r['after']];
}

// =====================================================================
// JSON 응답 헬퍼
// =====================================================================

function dt_json(array $data, int $code = 200): void {
    while (ob_get_level()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================================
// 라우트 등록
// =====================================================================

if (class_exists('Router')):

// === 페이지 라우트 ===
Router::get('/game', function () {
    if (!Auth::check()) {
        Router::redirect(Router::url('login?redirect=' . urlencode('/game')));
        return;
    }
    SEO::setTitle('🎮 코인 트레이딩 게임 (재미용)');
    SEO::setDescription('사이트 포인트로 즐기는 코인 가격 예측 미니게임 — 환금 불가, 100% 재미용입니다.');
    require __DIR__ . '/views/embed.php';
});

Router::get('/wallet', function () {
    if (!Auth::check()) {
        Router::redirect(Router::url('login?redirect=' . urlencode('/wallet')));
        return;
    }
    SEO::setTitle('내 포인트 지갑');
    SEO::setDescription('내가 보유한 사이트 포인트와 게임 베팅 내역을 확인합니다.');
    require __DIR__ . '/views/wallet.php';
});

// 일일 보너스 청구
Router::post('/api/wallet/daily-bonus', function () {
    if (!Auth::check()) dt_json(['ok' => false, 'error' => '로그인이 필요합니다'], 401);
    $r = dt_claim_daily_bonus(Auth::id());
    dt_json($r, $r['ok'] ? 200 : 400);
});

// === Detrade webhook 라우트 ===
Router::post('/api/wallet/amount/deduction', function () {
    require __DIR__ . '/webhooks/deduction.php';
});

Router::post('/api/wallet/amount/add', function () {
    require __DIR__ . '/webhooks/add.php';
});

Router::post('/api/wallet/balance/{currency}', function ($params) {
    $GLOBALS['DT_PARAM_CURRENCY'] = $params['currency'] ?? 'PT';
    require __DIR__ . '/webhooks/balance.php';
});

Router::post('/api/order/push', function () {
    require __DIR__ . '/webhooks/push.php';
});

endif; // class_exists('Router')

// =====================================================================
// 메인 페이지 히어로 배너 (필터)
// =====================================================================

if (class_exists('Plugin')) {
    Plugin::addFilter('home.hero.extra', function ($html) {
        $cfg = dt_config();
        if (empty($cfg['enabled']) || $cfg['enabled'] !== '1') return $html;
        if (empty($cfg['hero_enabled']) || $cfg['hero_enabled'] !== '1') return $html;
        ob_start();
        require __DIR__ . '/views/hero.php';
        return $html . ob_get_clean();
    });
}

<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * AI 자동 댓글 플러그인
 * 관리자 > 플러그인 > AI 자동 댓글 > 설정에서 구성하세요.
 */

$_aicConfigFile = __DIR__ . '/config.json';
$_aicStateFile  = __DIR__ . '/state.json';

$_aicRaw = file_exists($_aicConfigFile) ? json_decode(file_get_contents($_aicConfigFile), true) : [];
if (!is_array($_aicRaw)) $_aicRaw = [];

$_aicDefault = [
    'enabled'             => '0',
    'openai_api_key'      => '',
    'openai_model'        => 'openai/gpt-4o-mini',
    'system_prompt'       => '당신은 한국어 커뮤니티 사이트의 평범한 방문자로서 게시글에 달리는 자연스러운 댓글을 작성하는 작가입니다. 실제 사람이 쓴 것처럼 다양한 어투/말투/반응을 섞어서 작성하세요. 광고나 홍보성 멘트는 절대 넣지 말고, 과한 이모지 남발도 피하세요.',
    'target_all_boards'   => '1',
    'target_board_ids'    => '',
    'comment_min'         => '2',
    'comment_max'         => '5',
    'length_mode'         => 'random',
    'target_days'         => '7',
    'target_max_comments' => '5',
    'auto_interval_minutes' => '30',
    'batch_size'          => '3',
    'skip_own_comments'   => '1',
    'reply_enabled'       => '1',
    'reply_ratio'         => '30',
];
$_aicConfig = array_merge($_aicDefault, $_aicRaw);

// ============================================================
// 수동 실행 AJAX (관리자 설정 페이지에서 호출)
// ============================================================
if (isset($_REQUEST['aic_run_now'])) {
    while (ob_get_level()) { @ob_end_clean(); }
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

    $isAdmin = false;
    if (class_exists('Auth')) {
        if (method_exists('Auth', 'isAdmin') && method_exists('Auth', 'check')) {
            $isAdmin = Auth::check() && Auth::isAdmin();
        }
    }
    if (!$isAdmin) { echo json_encode(['ok'=>false,'error'=>'관리자 권한 필요']); exit; }

    $result = _aic_run_batch($_aicConfig, (int)$_aicConfig['batch_size']);
    echo json_encode(['ok'=>true] + $result, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 자동 실행 tick (관리자 페이지 방문 시)
// ============================================================
if ($_aicConfig['enabled'] === '1' && class_exists('Plugin')) {
    Plugin::addHook('admin_after_header', function() use ($_aicConfig) {
        _aic_maybe_tick($_aicConfig);
    });
}

// ============================================================
// 배치 실행 가능한지 확인 후 백그라운드 tick
// ============================================================
function _aic_maybe_tick($config) {
    $stateFile = __DIR__ . '/state.json';
    $state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    if (!is_array($state)) $state = [];

    $lastRun = $state['last_run'] ?? '';
    $interval = max(1, (int)($config['auto_interval_minutes'] ?? 30));

    if ($lastRun && (time() - strtotime($lastRun)) < ($interval * 60)) return;
    if (empty($config['openai_api_key'])) return;

    // 락 파일로 동시 실행 방지
    $lockFile = __DIR__ . '/aic.lock';
    $lock = @fopen($lockFile, 'c');
    if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) return;

    @ignore_user_abort(true);
    @set_time_limit(120);

    try {
        _aic_run_batch($config, (int)$config['batch_size']);
    } catch (Exception $e) {
        // 실패해도 다음 tick 기다림
    }

    @flock($lock, LOCK_UN);
    @fclose($lock);
}

// ============================================================
// 1배치 실행 - N개 게시글에 댓글 생성
// ============================================================
function _aic_run_batch($config, $batchSize = 3) {
    $result = ['processed' => 0, 'comments_added' => 0, 'errors' => []];
    if (!class_exists('DB') || empty($config['openai_api_key'])) {
        $result['errors'][] = 'DB 또는 API 키 없음';
        return $result;
    }

    $posts = _aic_get_target_posts($config, $batchSize);
    foreach ($posts as $post) {
        try {
            $count = rand(
                max(1, (int)$config['comment_min']),
                max((int)$config['comment_min'], (int)$config['comment_max'])
            );
            $added = _aic_generate_comments_for_post($post, $count, $config);
            $result['comments_added'] += $added;
            $result['processed']++;
        } catch (Exception $e) {
            $result['errors'][] = '게시글 #' . $post['id'] . ': ' . $e->getMessage();
        }
    }

    // 상태 저장
    _aic_save_state($result);
    return $result;
}

function _aic_save_state($batchResult) {
    $stateFile = __DIR__ . '/state.json';
    $state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    if (!is_array($state)) $state = [];
    $state['last_run'] = date('Y-m-d H:i:s');
    $state['total_comments_generated'] = (int)($state['total_comments_generated'] ?? 0) + (int)$batchResult['comments_added'];

    $runs = $state['recent_runs'] ?? [];
    array_unshift($runs, [
        'at' => $state['last_run'],
        'processed' => $batchResult['processed'],
        'comments' => $batchResult['comments_added'],
        'errors' => count($batchResult['errors'] ?? []),
    ]);
    $state['recent_runs'] = array_slice($runs, 0, 20);

    @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ============================================================
// 대상 게시글 조회
// ============================================================
function _aic_get_target_posts($config, $limit) {
    $prefix = DB::getPrefix();

    // 게시판 필터
    $boardFilter = '';
    $params = [];
    if ($config['target_all_boards'] !== '1' && !empty($config['target_board_ids'])) {
        $ids = array_filter(array_map('trim', explode(',', $config['target_board_ids'])));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $boardFilter = " AND board_id IN ({$placeholders})";
            $params = array_merge($params, $ids);
        } else {
            return []; // 선택된 게시판 없음
        }
    }

    // 기간 필터
    $days = max(1, (int)$config['target_days']);
    $dateLimit = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    $params[] = $dateLimit;

    // 이미 댓글이 target_max_comments 이상인 글은 제외
    $maxComments = max(1, (int)$config['target_max_comments']);
    $params[] = $maxComments;

    try {
        $sql = "SELECT id, board_id, title, content, member_id, comment_count, created_at
                FROM {$prefix}posts
                WHERE (is_hidden = 0 OR is_hidden IS NULL)
                  {$boardFilter}
                  AND created_at >= ?
                  AND comment_count < ?
                ORDER BY id DESC
                LIMIT " . (int)$limit;
        $rows = DB::fetchAll($sql, $params) ?: [];
    } catch (Exception $e) {
        return [];
    }
    return $rows;
}

// ============================================================
// 게시글 1개에 N개 댓글 생성 (OpenAI 1회 호출)
// ============================================================
function _aic_generate_comments_for_post($post, $count, $config) {
    $prefix = DB::getPrefix();

    // 길이 모드
    $lengthMode = $config['length_mode'] ?? 'random';
    $lengthMap = [
        'short'  => '각 댓글은 5~15자 내외로 아주 짧게 (예: "오 이거 좋네요", "ㅋㅋㅋ 저도요", "정보 감사합니다")',
        'medium' => '각 댓글은 15~40자 내외로 적당한 길이 (예: "저도 이거 궁금했는데 딱 좋은 정보네요!")',
        'long'   => '각 댓글은 40~100자 내외로 약간 길게, 본인 경험이나 추가 질문을 섞어서',
        'random' => '댓글마다 길이를 자유롭게 섞어주세요 (짧은 것 / 중간 / 긴 것 혼합)',
    ];
    $lengthInstruction = $lengthMap[$lengthMode] ?? $lengthMap['random'];

    // 게시글 본문 정리 (HTML 제거, 1500자 자름)
    $body = strip_tags($post['content'] ?? '');
    $body = preg_replace('/\s+/u', ' ', $body);
    $body = mb_substr(trim($body), 0, 1500);

    // 대댓글 섞기 안내
    $replyEnabled = ($config['reply_enabled'] ?? '1') === '1';
    $replyRatio = max(0, min(100, (int)($config['reply_ratio'] ?? 30)));
    $replyInstruction = '';
    $formatExample = '[{"text":"댓글1"},{"text":"댓글2"}]';
    if ($replyEnabled && $replyRatio > 0 && $count >= 2) {
        $replyInstruction = "- 전체 중 약 {$replyRatio}%는 앞선 댓글에 대한 대댓글(답글)로 자연스럽게 섞어주세요. 대댓글은 본 댓글에 공감·동의·추가질문 형태로.\n";
        $formatExample = '[{"text":"정보 감사해요!"},{"text":"저도 그 부분 궁금했는데 덕분에 알았네요"},{"text":"공감 저도요","reply_to":1},{"text":"글 잘 봤습니다"}]';
    }

    $userPrompt = "다음 게시글을 읽고, 이 게시글에 달릴만한 자연스러운 한국어 댓글 {$count}개를 작성해주세요.\n\n" .
        "[규칙]\n" .
        "- {$lengthInstruction}\n" .
        "- 이모지는 0~3개를 자연스럽게 섞되 과하지 않게. 일부 댓글은 이모지 없이 작성 가능.\n" .
        "- 각 댓글은 다른 사람이 쓴 것처럼 말투/어투를 다양하게. 존댓말, 반말, 섞어서.\n" .
        "- 공감 / 질문 / 경험 공유 / 짧은 리액션 등 다양한 톤으로 섞기.\n" .
        "- 광고·홍보·외부 링크 절대 금지.\n" .
        $replyInstruction .
        "[응답 형식]\n" .
        "반드시 JSON 배열로만 응답. 각 원소는 객체:\n" .
        "- 일반 댓글: {\"text\":\"내용\"}\n" .
        ($replyEnabled ? "- 대댓글: {\"text\":\"내용\", \"reply_to\":N}  ← N은 앞선 댓글의 index(0부터 시작). 대댓글은 본 댓글보다 뒤에 와야 함.\n" : '') .
        "예시: {$formatExample}\n" .
        "다른 설명 일체 금지. JSON 배열만.\n\n" .
        "[게시글 제목]\n" . ($post['title'] ?? '') . "\n\n" .
        "[게시글 내용]\n" . $body;

    $messages = [
        ['role' => 'system', 'content' => $config['system_prompt']],
        ['role' => 'user', 'content' => $userPrompt],
    ];

    $ai = _aic_call_openai($messages, $config);
    if (!$ai['success']) {
        throw new Exception($ai['error']);
    }

    // JSON 배열 파싱
    $content = trim($ai['content']);
    $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content);
    $parsed = json_decode($content, true);

    // 폴백: 예전 형식 또는 줄 단위
    if (!is_array($parsed)) {
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $parsed = array_map(function($line) {
            $t = preg_replace('/^\d+[\.\)]\s*|^[-*]\s*|^"|"$/u', '', $line);
            return ['text' => $t];
        }, array_values($lines));
    }

    // 각 원소를 {text, reply_to} 형태로 정규화
    $items = [];
    foreach ($parsed as $i => $p) {
        $text = '';
        $replyTo = null;
        if (is_string($p)) {
            $text = trim($p);
        } elseif (is_array($p)) {
            $text = trim($p['text'] ?? '');
            if (isset($p['reply_to']) && is_numeric($p['reply_to'])) {
                $ri = (int)$p['reply_to'];
                if ($ri >= 0 && $ri < $i) $replyTo = $ri;
            }
        }
        if ($text === '') continue;
        $items[] = ['text' => $text, 'reply_to' => $replyTo];
        if (count($items) >= $count) break;
    }
    if (empty($items)) throw new Exception('AI 응답 파싱 실패');

    // 기존 댓글자 닉네임 수집 (같은 글에 중복 방지)
    $existingNicks = [];
    try {
        $rows = DB::fetchAll("SELECT m.nickname FROM {$prefix}comments c LEFT JOIN {$prefix}members m ON c.member_id = m.id WHERE c.post_id = ?", [$post['id']]) ?: [];
        foreach ($rows as $r) {
            if (!empty($r['nickname'])) $existingNicks[$r['nickname']] = true;
        }
    } catch (Exception $e) {}

    // 작성 시간 기준 (글 작성 + 10분~6시간 후, but not future)
    $baseTime = strtotime($post['created_at'] ?? 'now');
    $now = time();

    $added = 0;
    $insertedIds = []; // index → DB id (대댓글용 parent_id 참조)
    $insertedTimes = []; // index → 작성 시간 (대댓글은 부모보다 뒤)

    foreach ($items as $idx => $item) {
        $text = $item['text'];
        if (mb_strlen($text) > 500) $text = mb_substr($text, 0, 500);

        // 중복 안 되는 닉네임 뽑기
        $nickname = _aic_pick_nickname($existingNicks);
        if (!$nickname) continue;
        $existingNicks[$nickname] = true;

        $memberId = _aic_get_or_create_member($nickname);
        if (!$memberId) continue;

        // parent_id 결정
        $parentId = 0;
        $baseForTime = $baseTime;
        if ($item['reply_to'] !== null && isset($insertedIds[$item['reply_to']])) {
            $parentId = $insertedIds[$item['reply_to']];
            $baseForTime = $insertedTimes[$item['reply_to']]; // 부모 댓글 시간 이후
        }

        // 작성 시간: 부모 시간(또는 글 시간) + 10분~6시간, 단 현재 이하
        $offset = rand(600, 21600);
        $commentTime = min($baseForTime + $offset, $now);
        $createdAt = date('Y-m-d H:i:s', $commentTime);

        try {
            $commentId = DB::insert("{$prefix}comments", [
                'post_id' => $post['id'],
                'member_id' => $memberId,
                'parent_id' => $parentId,
                'content' => $text,
                'created_at' => $createdAt,
            ]);
            $insertedIds[$idx] = (int)$commentId;
            $insertedTimes[$idx] = $commentTime;
            $added++;
        } catch (Exception $e) {
            continue;
        }
    }

    // posts.comment_count 갱신
    if ($added > 0) {
        try {
            DB::query("UPDATE {$prefix}posts SET comment_count = comment_count + ? WHERE id = ?", [$added, $post['id']]);
        } catch (Exception $e) {}
    }

    return $added;
}

// ============================================================
// 한국어 닉네임 풀 + 중복 없이 선택
// ============================================================
function _aic_nickname_pool() {
    return [
        '바람돌이', '초코라떼', '연필깎이', '오늘의날씨', '작은거북이',
        '라일락향기', '별빛가루', '물방울무늬', '초록잎새', '바다소리',
        '밤하늘별', '가을하늘', '따뜻한햇살', '포근한바람', '달빛정원',
        '구름산책', '봄날의꿈', '수국정원', '호랑이발자국', '고양이발바닥',
        '토끼의꿈', '사슴의노래', '여우비가', '눈송이하나', '빗소리좋아',
        '커피한잔', '차한잔의여유', '책읽는오후', '노을지는길', '아침이슬',
        '민들레홀씨', '장미향기', '벚꽃엔딩', '해바라기씨', '코스모스길',
        '단풍잎색깔', '새벽안개', '무지개다리', '오로라빛', '별똥별소원',
    ];
}

function _aic_pick_nickname($exclude = []) {
    $pool = _aic_nickname_pool();
    $available = array_values(array_filter($pool, function($n) use ($exclude) {
        return !isset($exclude[$n]);
    }));
    if (empty($available)) {
        // 풀 소진 시 숫자 붙여서 생성
        $n = $pool[array_rand($pool)] . rand(2, 99);
        return $n;
    }
    return $available[array_rand($available)];
}

// ============================================================
// 회원 조회/생성 (닉네임 기준)
// ============================================================
function _aic_get_or_create_member($nickname) {
    $prefix = DB::getPrefix();
    try {
        $m = DB::fetch("SELECT id FROM {$prefix}members WHERE nickname = ?", [$nickname]);
        if ($m) return (int)$m['id'];

        // 신규 생성
        $userId = 'aic_' . strtolower(substr(md5($nickname . microtime(true)), 0, 10));
        return (int)DB::insert("{$prefix}members", [
            'user_id'    => $userId,
            'password'   => password_hash('aicomment' . rand(1000, 9999), PASSWORD_BCRYPT),
            'nickname'   => $nickname,
            'email'      => '',
            'level'      => rand(2, 6),
            'point'      => rand(30, 300),
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(30, 365) . ' days')),
        ]);
    } catch (Exception $e) {
        return 0;
    }
}

// ============================================================
// OpenAI 호출
// ============================================================
function _aic_call_openai($messages, $config, $maxTokens = 2000) {
    if (empty($config['openai_api_key'])) {
        return ['success' => false, 'error' => 'OpenRouter API 키가 없습니다.'];
    }
    $payload = [
        'model' => $config['openai_model'] ?: 'openai/gpt-4o-mini',
        'messages' => $messages,
        'temperature' => 0.95,
        'max_tokens' => $maxTokens,
    ];
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['openai_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['success' => false, 'error' => 'OpenAI 요청 실패: ' . $err];
    $data = json_decode($res, true);
    if ($http !== 200 || empty($data['choices'][0]['message']['content'])) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $http);
        return ['success' => false, 'error' => 'OpenAI 오류: ' . $msg];
    }
    return ['success' => true, 'content' => trim($data['choices'][0]['message']['content'])];
}

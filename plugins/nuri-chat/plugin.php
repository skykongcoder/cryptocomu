<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * 누리챗 상담봇 플러그인
 * 관리자 > 플러그인 > 누리챗 상담봇 > 설정에서 구성하세요.
 */

$_ncConfigFile = __DIR__ . '/config.json';
$_ncFaqsFile   = __DIR__ . '/faqs.json';

$_ncConfigRaw = file_exists($_ncConfigFile) ? json_decode(file_get_contents($_ncConfigFile), true) : [];
if (!is_array($_ncConfigRaw)) $_ncConfigRaw = [];

$_ncDefaultConfig = [
    'enabled'         => '1',
    'bot_name'        => '누리챗',
    'bot_subtitle'    => '24시간 운영해요',
    'greeting'        => '안녕하세요, 무엇을 도와드릴까요?',
    'offline_text'    => '지금은 상담 가능 시간이 아니에요. 메시지를 남겨주시면 확인 후 빠르게 답변드릴게요!',
    'offline_hours'   => '',
    'openai_api_key'  => '',
    'openai_model'    => 'openai/gpt-4o-mini',
    'system_prompt'   => '당신은 이 사이트의 친절하고 유능한 상담 도우미입니다. 존댓말로 1~4문장으로 답하세요. 제공된 [관련 글]에 조금이라도 관련 정보가 있으면 반드시 그걸 인용해서 구체적으로 답하고 링크를 제시하세요. 회피성 답변("확인이 필요해요")은 정말 관련 정보가 하나도 없을 때만 쓰세요.',
    'site_knowledge'  => '',
    'use_board_rag'   => '1',
    'rag_board_ids'   => '',
    'accent_color'    => '#22c55e',
    'position'        => 'right',
    'bottom'          => '24',
    'offset'          => '24',
    'hide_admin'      => '1',
    'ban_words'       => '',
];
$_ncConfig = array_merge($_ncDefaultConfig, $_ncConfigRaw);

// ===== DB 테이블 설치 (최초 1회) =====
if (class_exists('DB')) {
    try {
        $prefix = DB::getPrefix();
        DB::query("CREATE TABLE IF NOT EXISTS {$prefix}nc_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visitor_key VARCHAR(64) NOT NULL UNIQUE,
            member_id INT UNSIGNED NULL,
            user_agent VARCHAR(255) NULL,
            started_at DATETIME NOT NULL,
            last_active_at DATETIME NOT NULL,
            unread_for_visitor INT NOT NULL DEFAULT 0,
            unread_for_admin INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            INDEX idx_last_active (last_active_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS {$prefix}nc_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id INT UNSIGNED NOT NULL,
            sender VARCHAR(10) NOT NULL,
            content TEXT NOT NULL,
            meta TEXT NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME NULL,
            INDEX idx_session (session_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // 무시 — 이미 존재
    }
}

// ===== AJAX 엔드포인트 라우팅 =====
// 프론트가 ?nc_api=... 로 요청 보내면 api.php 로 위임
if (isset($_REQUEST['nc_api'])) {
    // 이미 출력된 HTML 버퍼 모두 날림 (JSON 순수 출력 보장)
    while (ob_get_level()) { @ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    require __DIR__ . '/api.php';
    exit;
}

// ===== 비활성화/관리자 숨김 처리 =====
if ($_ncConfig['enabled'] !== '1') return;
if ($_ncConfig['hide_admin'] === '1' && function_exists('nb_isAdmin') && nb_isAdmin()) return;

// ===== 위젯 렌더링 =====
if (class_exists('Plugin')) {
    Plugin::addHook('body_end', function() use ($_ncConfig) {
        require __DIR__ . '/widget.php';
    });
}

// ===== AI 호출 (OpenAI) =====
function _nc_call_openai($messages, $config, $maxTokens = 600) {
    if (empty($config['openai_api_key'])) {
        return ['success' => false, 'error' => 'OpenRouter API 키가 설정되지 않았습니다.'];
    }
    $payload = [
        'model' => $config['openai_model'] ?: 'openai/gpt-4o-mini',
        'messages' => $messages,
        'temperature' => 0.5,
        'max_tokens' => $maxTokens,
    ];
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
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

// ===== 게시판 자동 검색 (경량 RAG - 관련도 순위 + 긴 본문 전달) =====
function _nc_search_board_posts($query, $config, $limit = 5) {
    if ($config['use_board_rag'] !== '1') return [];
    if (!class_exists('DB')) return [];
    $query = trim($query);
    if ($query === '' || mb_strlen($query) < 2) return [];

    $prefix = DB::getPrefix();
    $boardFilter = '';

    // 키워드 분리 (공백 기준, 2글자 이상만)
    $words = preg_split('/\s+/u', $query);
    $words = array_filter($words, function($w) { return mb_strlen($w) >= 2; });
    $words = array_slice(array_values($words), 0, 6);
    // 전체 쿼리도 추가 (띄어쓰기 없이 한 단어인 경우)
    if (!in_array($query, $words, true) && mb_strlen($query) >= 2) {
        array_unshift($words, $query);
    }
    if (empty($words)) return [];

    // 보드 필터 (문자열 board_id 지원)
    if (!empty($config['rag_board_ids'])) {
        $ids = array_filter(array_map('trim', explode(',', $config['rag_board_ids'])));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $boardFilter = " AND board_id IN ({$placeholders})";
        }
    }

    // 점수 기반 랭킹: 제목 매칭 10점, 본문 매칭 3점, 여러 단어 매칭 시 가산
    $scoreParts = [];
    $whereParts = [];
    $params = [];
    foreach ($words as $w) {
        $like = '%' . $w . '%';
        $scoreParts[] = "(CASE WHEN title LIKE ? THEN 10 ELSE 0 END) + (CASE WHEN content LIKE ? THEN 3 ELSE 0 END)";
        $whereParts[] = "(title LIKE ? OR content LIKE ?)";
        $params[] = $like; // score title
        $params[] = $like; // score content
    }
    // WHERE 용 파라미터 별도 추가
    foreach ($words as $w) {
        $like = '%' . $w . '%';
        $params[] = $like; // where title
        $params[] = $like; // where content
    }
    if (!empty($ids)) $params = array_merge($params, $ids);

    $scoreExpr = implode(' + ', $scoreParts);
    $where = '(' . implode(' OR ', $whereParts) . ')' . $boardFilter;

    try {
        $sql = "SELECT id, board_id, title, LEFT(content, 3000) AS excerpt, created_at,
                       ({$scoreExpr}) AS score
                FROM {$prefix}posts
                WHERE {$where}
                  AND (is_hidden = 0 OR is_hidden IS NULL)
                ORDER BY score DESC, created_at DESC
                LIMIT " . (int)$limit;
        $rows = DB::fetchAll($sql, $params);
    } catch (Exception $e) {
        return [];
    }

    $out = [];
    foreach ($rows ?: [] as $r) {
        if ((int)($r['score'] ?? 0) <= 0) continue;
        $excerpt = strip_tags($r['excerpt']);
        $excerpt = preg_replace('/\s+/u', ' ', $excerpt);
        // AI가 실제 내용을 보고 답할 수 있도록 1000자까지 전달
        $excerpt = mb_substr(trim($excerpt), 0, 1000);
        $out[] = [
            'id' => (int)$r['id'],
            'board_id' => $r['board_id'],
            'title' => $r['title'],
            'excerpt' => $excerpt,
            'url' => '/board/' . $r['board_id'] . '/' . $r['id'],
        ];
    }
    return $out;
}

// ===== 금칙어 체크 =====
function _nc_has_banword($text, $config) {
    $list = trim($config['ban_words'] ?? '');
    if ($list === '') return false;
    $words = preg_split('/[,\n]+/u', $list);
    foreach ($words as $w) {
        $w = trim($w);
        if ($w === '') continue;
        if (mb_stripos($text, $w) !== false) return true;
    }
    return false;
}

// ===== 사이트 자동 컨텍스트 (게시판 목록 + 최근 공지) =====
// 매 질문마다 AI한테 자동 주입됨. 1시간 캐시.
function _nc_get_site_context($forceRefresh = false) {
    $cacheFile = __DIR__ . '/site_context.cache';
    $ttl = 3600; // 1시간
    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return @file_get_contents($cacheFile);
    }
    if (!class_exists('DB')) return '';

    $prefix = DB::getPrefix();
    $ctx = '';
    try {
        // 1. 활성 게시판 목록
        $boards = DB::fetchAll("SELECT board_id, title, board_type FROM {$prefix}boards WHERE is_active = 1 LIMIT 30");
        if (!empty($boards)) {
            $ctx .= "[이 사이트의 게시판 구조]\n";
            foreach ($boards as $b) {
                $type = $b['board_type'] ?? '';
                $typeNote = $type === 'notice' ? ' (공지용)' : ($type === 'gallery' ? ' (갤러리)' : '');
                $ctx .= "- {$b['board_id']}: {$b['title']}{$typeNote}\n";
            }
        }

        // 2. 최근 공지 제목 5개 (공지게시판 또는 notice 타입)
        $notices = DB::fetchAll("SELECT p.title, p.board_id FROM {$prefix}posts p LEFT JOIN {$prefix}boards b ON p.board_id = b.board_id WHERE (b.board_type = 'notice' OR p.board_id LIKE '%notice%' OR p.board_id LIKE '%공지%') ORDER BY p.created_at DESC LIMIT 5");
        if (!empty($notices)) {
            $ctx .= "\n[최근 공지사항]\n";
            foreach ($notices as $n) {
                $ctx .= "- {$n['title']}\n";
            }
        }

        // 3. 최근 인기글 제목 5개 (조회수 기준)
        $popular = DB::fetchAll("SELECT title FROM {$prefix}posts WHERE is_hidden = 0 ORDER BY hit DESC LIMIT 5");
        if (!empty($popular)) {
            $ctx .= "\n[사이트에서 많이 본 글]\n";
            foreach ($popular as $p) {
                $ctx .= "- {$p['title']}\n";
            }
        }
    } catch (Exception $e) {
        return '';
    }

    @file_put_contents($cacheFile, $ctx);
    return $ctx;
}

// ===== AI가 사이트 전체를 스캔해서 site_knowledge 자동 생성 =====
function _nc_auto_analyze_site($config) {
    if (empty($config['openai_api_key'])) {
        return ['success' => false, 'error' => 'OpenRouter API 키를 먼저 설정해주세요.'];
    }
    if (!class_exists('DB')) {
        return ['success' => false, 'error' => 'DB 연결이 불가합니다.'];
    }

    $prefix = DB::getPrefix();

    try {
        $boards = DB::fetchAll("SELECT board_id, title, board_type FROM {$prefix}boards WHERE is_active = 1 LIMIT 30") ?: [];
        $posts  = DB::fetchAll("SELECT title, LEFT(content, 250) AS excerpt, board_id FROM {$prefix}posts WHERE is_hidden = 0 ORDER BY created_at DESC LIMIT 60") ?: [];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'DB 조회 실패: ' . $e->getMessage()];
    }

    if (empty($boards) && empty($posts)) {
        return ['success' => false, 'error' => '분석할 게시판/글이 없습니다. 글을 먼저 작성해주세요.'];
    }

    $data = "=== 게시판 목록 ===\n";
    foreach ($boards as $b) {
        $data .= "- {$b['board_id']}: {$b['title']} (" . ($b['board_type'] ?? '-') . ")\n";
    }

    $data .= "\n=== 최근 글 " . count($posts) . "개 샘플 ===\n";
    foreach ($posts as $i => $p) {
        $ex = strip_tags($p['excerpt']);
        $ex = preg_replace('/\s+/u', ' ', $ex);
        $ex = mb_substr(trim($ex), 0, 150);
        $data .= ($i + 1) . ". [{$p['board_id']}] \"{$p['title']}\"" . ($ex ? " — {$ex}" : '') . "\n";
    }

    $systemPrompt = "당신은 AI 상담 챗봇의 '사이트 지식' 문서를 작성하는 분석가입니다.\n\n" .
        "입력된 게시판 목록과 최근 글 샘플을 바탕으로, 이 사이트를 처음 보는 챗봇이 답변에 활용할 수 있는 구조화된 지식 문서를 만드세요.\n\n" .
        "다음 형식을 반드시 지키세요 (한국어, 1500~2500자):\n\n" .
        "[사이트 소개]\n(샘플에서 드러난 사이트 성격을 2~3문장으로)\n\n" .
        "[주요 게시판]\n- 각 게시판의 용도를 글 샘플로 추측해 한 줄씩\n\n" .
        "[주요 주제 및 관심사]\n(글에서 반복되는 키워드/주제 나열)\n\n" .
        "[운영 톤과 분위기]\n(자유로운 커뮤니티인지, 공식적인지, 전문가용인지)\n\n" .
        "[방문자가 자주 궁금해할 만한 내용]\n- 3~5개 예상 질문과 답할 때 참고할 근거\n\n" .
        "규칙:\n- 데이터에 없는 내용은 절대 지어내지 마세요\n- 가격, 연락처 같은 구체 정보는 '확인 필요'로 표기\n- 마지막에 \"※ 이 문서는 AI가 자동 생성했습니다. 관리자 확인 후 수정하세요.\" 추가";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $data],
    ];

    $result = _nc_call_openai($messages, $config, 2500);

    // 캐시 무효화 (다음 답변부터 새 지식 반영)
    $cacheFile = __DIR__ . '/site_context.cache';
    if (file_exists($cacheFile)) @unlink($cacheFile);

    return $result;
}

// ===== FAQ 목록 읽기 =====
function _nc_read_faqs() {
    $file = __DIR__ . '/faqs.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function _nc_write_faqs($faqs) {
    file_put_contents(__DIR__ . '/faqs.json', json_encode(array_values($faqs), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

<?php
/**
 * 누리챗 AJAX 엔드포인트
 * plugin.php 에서 ?nc_api=... 요청이 들어오면 라우팅됨
 */

// plugin.php 에서 이미 헤더 + 버퍼 정리함
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

if (!class_exists('DB')) {
    echo json_encode(['ok' => false, 'error' => 'DB unavailable']); exit;
}

$action = $_REQUEST['nc_api'] ?? '';
$prefix = DB::getPrefix();
$config = $_ncConfig; // plugin.php 에서 로드된 전역

// ===== 쿠키 기반 visitor_key =====
function _nc_visitor_key() {
    if (!empty($_COOKIE['nc_vkey'])) return $_COOKIE['nc_vkey'];
    $key = bin2hex(random_bytes(16));
    setcookie('nc_vkey', $key, [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'samesite' => 'Lax',
    ]);
    $_COOKIE['nc_vkey'] = $key;
    return $key;
}

// ===== 세션 조회 또는 생성 =====
function _nc_get_or_create_session() {
    $prefix = DB::getPrefix();
    $vkey = _nc_visitor_key();
    $now = date('Y-m-d H:i:s');

    $sess = DB::fetch("SELECT * FROM {$prefix}nc_sessions WHERE visitor_key = ?", [$vkey]);
    if ($sess) {
        DB::query("UPDATE {$prefix}nc_sessions SET last_active_at = ? WHERE id = ?", [$now, $sess['id']]);
        return $sess;
    }
    $memberId = null;
    if (class_exists('Auth') && method_exists('Auth', 'user')) {
        $u = Auth::user();
        if (!empty($u['id'])) $memberId = (int)$u['id'];
    }
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 240);
    $id = DB::insert("{$prefix}nc_sessions", [
        'visitor_key' => $vkey,
        'member_id' => $memberId,
        'user_agent' => $ua,
        'started_at' => $now,
        'last_active_at' => $now,
    ]);
    return DB::fetch("SELECT * FROM {$prefix}nc_sessions WHERE id = ?", [$id]);
}

function _nc_save_message($sessionId, $sender, $content, $meta = null) {
    $prefix = DB::getPrefix();
    $id = DB::insert("{$prefix}nc_messages", [
        'session_id' => $sessionId,
        'sender' => $sender,
        'content' => $content,
        'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    // 미읽음 카운트 업데이트
    if ($sender === 'user') {
        DB::query("UPDATE {$prefix}nc_sessions SET unread_for_admin = unread_for_admin + 1, last_active_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $sessionId]);
    } elseif ($sender === 'admin') {
        DB::query("UPDATE {$prefix}nc_sessions SET unread_for_visitor = unread_for_visitor + 1, last_active_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $sessionId]);
    }
    return $id;
}

function _nc_fmt_msg($row) {
    $meta = !empty($row['meta']) ? json_decode($row['meta'], true) : null;
    return [
        'id' => (int)$row['id'],
        'sender' => $row['sender'],
        'content' => $row['content'],
        'meta' => $meta,
        'created_at' => $row['created_at'],
    ];
}

// ============================================================
switch ($action) {

    // --- 초기 로드: 세션 + 봇 프로필 + FAQ 칩 + 기존 메시지 ---
    case 'init': {
        $sess = _nc_get_or_create_session();
        $faqs = _nc_read_faqs();

        // 인사말이 아직 없으면 첫 메시지로 저장
        $msgCount = DB::fetch("SELECT COUNT(*) AS c FROM {$prefix}nc_messages WHERE session_id = ?", [$sess['id']]);
        if ((int)($msgCount['c'] ?? 0) === 0) {
            _nc_save_message($sess['id'], 'bot', $config['greeting'], ['type' => 'greeting']);
        }

        $msgs = DB::fetchAll("SELECT * FROM {$prefix}nc_messages WHERE session_id = ? ORDER BY id ASC LIMIT 200", [$sess['id']]);
        $msgs = array_map('_nc_fmt_msg', $msgs ?: []);

        // 방문자가 열었으니 관리자 답장 읽음 처리
        DB::query("UPDATE {$prefix}nc_sessions SET unread_for_visitor = 0 WHERE id = ?", [$sess['id']]);

        echo json_encode([
            'ok' => true,
            'session_id' => (int)$sess['id'],
            'bot' => [
                'name' => $config['bot_name'],
                'subtitle' => $config['bot_subtitle'],
                'greeting' => $config['greeting'],
                'accent' => $config['accent_color'],
            ],
            'faqs' => array_values($faqs),
            'messages' => $msgs,
            'offline' => _nc_is_offline($config),
            'offline_text' => $config['offline_text'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- FAQ 칩 클릭 (저장된 답변 즉시 반환) ---
    case 'faq': {
        $sess = _nc_get_or_create_session();
        $faqId = $_POST['faq_id'] ?? '';
        $faqs = _nc_read_faqs();
        $matched = null;
        foreach ($faqs as $f) {
            if (($f['id'] ?? '') === $faqId) { $matched = $f; break; }
        }
        if (!$matched) { echo json_encode(['ok' => false, 'error' => 'FAQ not found']); exit; }

        _nc_save_message($sess['id'], 'user', $matched['label'], ['type' => 'faq_click', 'faq_id' => $faqId]);
        $botMsgId = _nc_save_message($sess['id'], 'bot', $matched['answer'], ['type' => 'faq_answer']);

        echo json_encode([
            'ok' => true,
            'bot_message' => _nc_fmt_msg(DB::fetch("SELECT * FROM {$prefix}nc_messages WHERE id = ?", [$botMsgId])),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 자유 입력 메시지 → AI 답변 ---
    case 'send': {
        $sess = _nc_get_or_create_session();
        $text = trim($_POST['message'] ?? '');
        if ($text === '') { echo json_encode(['ok' => false, 'error' => '메시지가 비었습니다.']); exit; }
        if (mb_strlen($text) > 1000) $text = mb_substr($text, 0, 1000);

        if (_nc_has_banword($text, $config)) {
            _nc_save_message($sess['id'], 'user', $text);
            $botMsgId = _nc_save_message($sess['id'], 'bot', '죄송합니다. 해당 내용은 답변드릴 수 없습니다.', ['type' => 'blocked']);
            echo json_encode(['ok' => true, 'bot_message' => _nc_fmt_msg(DB::fetch("SELECT * FROM {$prefix}nc_messages WHERE id = ?", [$botMsgId]))], JSON_UNESCAPED_UNICODE);
            exit;
        }

        _nc_save_message($sess['id'], 'user', $text);

        // 오프라인 시간대 → AI 호출 없이 안내만
        if (_nc_is_offline($config) && !empty($config['offline_text'])) {
            $botMsgId = _nc_save_message($sess['id'], 'bot', $config['offline_text'], ['type' => 'offline']);
            echo json_encode(['ok' => true, 'bot_message' => _nc_fmt_msg(DB::fetch("SELECT * FROM {$prefix}nc_messages WHERE id = ?", [$botMsgId]))], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // === 4단 하이브리드 프롬프트 조립 ===
        // 1. 시스템 톤
        $sysParts = [$config['system_prompt']];

        // 2. 사이트 자동 분석 컨텍스트 (게시판 목록 + 최근 공지 + 인기글)
        $autoCtx = _nc_get_site_context();
        if (!empty($autoCtx)) {
            $sysParts[] = $autoCtx;
        }

        // 3. 관리자가 입력한 사이트 지식 (또는 AI 자동 분석 결과)
        if (!empty($config['site_knowledge'])) {
            $sysParts[] = "[사이트 상세 정보]\n" . $config['site_knowledge'];
        }

        // 4. 게시판 자동 검색 (경량 RAG)
        $refs = _nc_search_board_posts($text, $config, 5);
        if (!empty($refs)) {
            $refText = "[사용자 질문과 관련된 사이트 글 " . count($refs) . "개 - 이것을 최우선 근거로 답하세요]\n\n";
            foreach ($refs as $i => $r) {
                $refText .= "### 글 " . ($i + 1) . ": " . $r['title'] . "\n";
                $refText .= "본문: " . $r['excerpt'] . "\n";
                $refText .= "링크: " . $r['url'] . "\n\n";
            }
            $refText .= "[중요 지침]\n";
            $refText .= "- 위 글들에 사용자 질문과 관련된 내용이 있으면 **반드시 그 내용을 바탕으로 구체적으로 답변**하세요.\n";
            $refText .= "- \"확인이 필요해요\", \"관리자에게 문의하세요\" 같은 회피성 답변은 **위 글에 진짜로 관련 정보가 전혀 없을 때만** 사용하세요.\n";
            $refText .= "- 글 제목이나 내용에 키워드가 조금이라도 걸리면 그 정보를 인용해 답변하고 끝에 링크를 제시하세요.\n";
            $refText .= "- 예: \"네, 가능합니다. [글 제목]에 따르면 ... 자세한 내용은 여기서 확인하세요: 링크\"";
            $sysParts[] = $refText;
        }

        // 3. 최근 대화 맥락 (최근 6개)
        $recent = DB::fetchAll("SELECT sender, content FROM {$prefix}nc_messages WHERE session_id = ? AND sender IN ('user','bot','admin') ORDER BY id DESC LIMIT 6", [$sess['id']]);
        $recent = array_reverse($recent ?: []);

        $messages = [['role' => 'system', 'content' => implode("\n\n", $sysParts)]];
        foreach ($recent as $m) {
            $role = ($m['sender'] === 'user') ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $m['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $text];

        $ai = _nc_call_openai($messages, $config);
        if (!$ai['success']) {
            $botMsgId = _nc_save_message($sess['id'], 'bot', '죄송해요, 지금 답변을 생성할 수 없어요. 잠시 후 다시 시도하거나 관리자에게 문의 남겨주세요.', ['type' => 'ai_error', 'error' => $ai['error']]);
            echo json_encode(['ok' => true, 'bot_message' => _nc_fmt_msg(DB::fetch("SELECT * FROM {$prefix}nc_messages WHERE id = ?", [$botMsgId]))], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $botMsgId = _nc_save_message($sess['id'], 'bot', $ai['content'], [
            'type' => 'ai_answer',
            'refs' => $refs,
        ]);

        echo json_encode([
            'ok' => true,
            'bot_message' => _nc_fmt_msg(DB::fetch("SELECT * FROM {$prefix}nc_messages WHERE id = ?", [$botMsgId])),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 관리자 답변 폴링 (방문자가 주기적으로 체크) ---
    case 'poll': {
        $sess = _nc_get_or_create_session();
        $afterId = (int)($_REQUEST['after_id'] ?? 0);
        $rows = DB::fetchAll("SELECT * FROM {$prefix}nc_messages WHERE session_id = ? AND id > ? ORDER BY id ASC LIMIT 20", [$sess['id'], $afterId]);
        $newMsgs = array_map('_nc_fmt_msg', $rows ?: []);
        if (!empty($newMsgs)) {
            DB::query("UPDATE {$prefix}nc_sessions SET unread_for_visitor = 0 WHERE id = ?", [$sess['id']]);
        }
        echo json_encode(['ok' => true, 'messages' => $newMsgs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 대화 초기화 ---
    case 'reset': {
        $sess = _nc_get_or_create_session();
        DB::query("DELETE FROM {$prefix}nc_messages WHERE session_id = ?", [$sess['id']]);
        DB::query("UPDATE {$prefix}nc_sessions SET unread_for_visitor = 0, unread_for_admin = 0 WHERE id = ?", [$sess['id']]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================================================
    // === 관리자 전용 액션 (nb_isAdmin 체크) ===
    // ============================================================
    case 'admin_sessions':
    case 'admin_messages':
    case 'admin_reply':
    case 'admin_delete':
    case 'admin_delete_all':
    case 'admin_mark_read': {
        $isAdmin = false;
        if (class_exists('Auth')) {
            if (method_exists('Auth', 'isAdmin') && method_exists('Auth', 'check')) {
                $isAdmin = Auth::check() && Auth::isAdmin();
            } elseif (method_exists('Auth', 'user')) {
                $u = Auth::user();
                $isAdmin = !empty($u) && (!empty($u['is_admin']) || (int)($u['level'] ?? 0) >= 10);
            }
        }
        if (!$isAdmin && function_exists('nb_isAdmin') && nb_isAdmin()) $isAdmin = true;
        if (!$isAdmin) {
            echo json_encode(['ok' => false, 'error' => '관리자만 접근 가능합니다.']); exit;
        }

        if ($action === 'admin_sessions') {
            $since = (int)($_REQUEST['since_unread_total'] ?? -1);
            $rows = DB::fetchAll(
                "SELECT s.*,
                    (SELECT content FROM {$prefix}nc_messages WHERE session_id = s.id AND sender='user' ORDER BY id DESC LIMIT 1) AS last_user_msg,
                    (SELECT created_at FROM {$prefix}nc_messages WHERE session_id = s.id ORDER BY id DESC LIMIT 1) AS last_msg_at
                 FROM {$prefix}nc_sessions s ORDER BY s.last_active_at DESC LIMIT 100"
            ) ?: [];
            $totalUnread = 0;
            foreach ($rows as &$r) {
                $r['id'] = (int)$r['id'];
                $r['unread_for_admin'] = (int)$r['unread_for_admin'];
                $totalUnread += $r['unread_for_admin'];
            }
            echo json_encode([
                'ok' => true,
                'sessions' => $rows,
                'total_unread' => $totalUnread,
                'changed' => ($since < 0 || $since !== $totalUnread),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'admin_messages') {
            $sid = (int)($_REQUEST['session_id'] ?? 0);
            if (!$sid) { echo json_encode(['ok'=>false,'error'=>'session_id required']); exit; }
            $sess = DB::fetch("SELECT * FROM {$prefix}nc_sessions WHERE id = ?", [$sid]);
            if (!$sess) { echo json_encode(['ok'=>false,'error'=>'세션 없음']); exit; }
            $rows = DB::fetchAll("SELECT * FROM {$prefix}nc_messages WHERE session_id = ? ORDER BY id ASC", [$sid]) ?: [];
            $msgs = array_map('_nc_fmt_msg', $rows);
            // 읽음 처리
            DB::query("UPDATE {$prefix}nc_sessions SET unread_for_admin = 0 WHERE id = ?", [$sid]);
            echo json_encode(['ok' => true, 'messages' => $msgs, 'session' => $sess], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'admin_reply') {
            $sid = (int)($_POST['session_id'] ?? 0);
            $reply = trim($_POST['reply'] ?? '');
            if (!$sid || $reply === '') { echo json_encode(['ok'=>false,'error'=>'session_id/reply required']); exit; }
            $id = _nc_save_message($sid, 'admin', $reply);
            $msg = DB::fetch("SELECT * FROM {$prefix}nc_messages WHERE id = ?", [$id]);
            DB::query("UPDATE {$prefix}nc_sessions SET unread_for_admin = 0 WHERE id = ?", [$sid]);
            echo json_encode(['ok' => true, 'message' => _nc_fmt_msg($msg)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'admin_delete') {
            $sid = (int)($_POST['session_id'] ?? 0);
            if (!$sid) { echo json_encode(['ok'=>false,'error'=>'session_id required']); exit; }
            DB::query("DELETE FROM {$prefix}nc_messages WHERE session_id = ?", [$sid]);
            DB::query("DELETE FROM {$prefix}nc_sessions WHERE id = ?", [$sid]);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'admin_delete_all') {
            DB::query("DELETE FROM {$prefix}nc_messages");
            DB::query("DELETE FROM {$prefix}nc_sessions");
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'admin_mark_read') {
            $sid = (int)($_POST['session_id'] ?? 0);
            if (!$sid) { echo json_encode(['ok'=>false,'error'=>'session_id required']); exit; }
            DB::query("UPDATE {$prefix}nc_sessions SET unread_for_admin = 0 WHERE id = ?", [$sid]);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        exit;
    }

    default:
        echo json_encode(['ok' => false, 'error' => 'unknown action']);
        exit;
}

// ===== 운영시간 체크 =====
function _nc_is_offline($config) {
    $hours = trim($config['offline_hours'] ?? '');
    if ($hours === '') return false;
    // 형식: "22:00-09:00" 또는 "22-9"
    if (!preg_match('/^(\d{1,2})(?::(\d{2}))?\s*-\s*(\d{1,2})(?::(\d{2}))?$/', $hours, $m)) return false;
    $startMin = (int)$m[1] * 60 + (int)($m[2] ?? 0);
    $endMin = (int)$m[3] * 60 + (int)($m[4] ?? 0);
    $nowMin = (int)date('G') * 60 + (int)date('i');
    if ($startMin <= $endMin) {
        return ($nowMin >= $startMin && $nowMin < $endMin);
    } else {
        return ($nowMin >= $startMin || $nowMin < $endMin);
    }
}

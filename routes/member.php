<?php
/**
 * NuriBoard - 회원 라우트
 * 프로필, 공개 프로필, 출석체크, 쪽지 관련 라우트
 */

// 프로필
Router::get('/profile', function () {
    Auth::requireLogin();
    $member = Member::find(Auth::id());
    Router::loadTheme('member/profile', ['member' => $member]);
});

// 공개 프로필
Router::get('/member/{id}', function ($params) {
    $member = Member::find((int)$params['id']);
    if (!$member) { http_response_code(404); Router::loadTheme('error/404'); return; }
    Router::loadTheme('member/public', ['member' => $member]);
});

// 팔로워/팔로잉 목록
Router::get('/member/{id}/followers', function ($params) {
    $member = Member::find((int)$params['id']);
    if (!$member) { http_response_code(404); Router::loadTheme('error/404'); return; }
    Follow::ensureTable();
    $page = max(1, (int)($_GET['p'] ?? 1));
    $data = Follow::listFollowers((int)$member['id'], $page);
    Router::loadTheme('member/followers', ['member' => $member, 'tab' => 'followers', 'data' => $data]);
});
Router::get('/member/{id}/following', function ($params) {
    $member = Member::find((int)$params['id']);
    if (!$member) { http_response_code(404); Router::loadTheme('error/404'); return; }
    Follow::ensureTable();
    $page = max(1, (int)($_GET['p'] ?? 1));
    $data = Follow::listFollowing((int)$member['id'], $page);
    Router::loadTheme('member/followers', ['member' => $member, 'tab' => 'following', 'data' => $data]);
});

// 출석체크
Router::get('/attendance', function () {
    $prefix      = DB::getPrefix();
    $year        = (int)($_GET['y'] ?? date('Y'));
    $month       = (int)($_GET['m'] ?? date('m'));
    $attendDates = [];
    $todayDone   = false;
    if (Auth::check()) {
        $myAttend    = DB::fetchAll("SELECT attend_date FROM {$prefix}attendance WHERE member_id = ? AND attend_date LIKE ?",
            [Auth::id(), "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-%"]);
        $attendDates = array_column($myAttend, 'attend_date');
        $todayDone   = in_array(date('Y-m-d'), $attendDates);
    }
    $todayList = DB::fetchAll(
        "SELECT a.created_at, a.message, m.nickname, m.level,
                (SELECT COUNT(*) FROM {$prefix}attendance WHERE member_id = a.member_id) AS total_days
         FROM {$prefix}attendance a
         LEFT JOIN {$prefix}members m ON a.member_id = m.id
         WHERE a.attend_date = CURDATE()
         ORDER BY a.created_at ASC"
    );
    $attendPoint = (int)nb_setting('point_attendance', '5');
    Router::loadTheme('member/attendance', [
        'year' => $year, 'month' => $month,
        'attendDates' => $attendDates, 'todayDone' => $todayDone,
        'todayList' => $todayList, 'attendPoint' => $attendPoint,
    ]);
});
// 출석체크 AJAX (도장 달력)
Router::post('/attendance/check', function () {
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::check()) {
        echo json_encode(['ok' => false, 'msg' => '로그인이 필요합니다', 'needLogin' => true]);
        return;
    }
    if (!Auth::verifyCsrf()) {
        echo json_encode(['ok' => false, 'msg' => '잘못된 요청입니다']);
        return;
    }
    $prefix = DB::getPrefix();
    $today  = date('Y-m-d');
    $date   = trim($_POST['date'] ?? '');
    if ($date !== $today) {
        echo json_encode(['ok' => false, 'msg' => '오늘만 출석 가능합니다']);
        return;
    }
    $exists = DB::fetch("SELECT id FROM {$prefix}attendance WHERE member_id = ? AND attend_date = ?", [Auth::id(), $today]);
    if ($exists) {
        echo json_encode(['ok' => false, 'msg' => '이미 출석했어요', 'already' => true]);
        return;
    }
    $msgs = ['출석!','오늘도 화이팅!','좋은 하루 보내세요 :)','즐거운 하루 되세요','열심히 살자!','오늘도 파이팅','반갑습니다!','굿모닝!','힘차게 출석!','오늘도 좋은 하루','행복한 하루 되세요','출석 완료!','하루의 시작!','오늘도 건강하게','씩씩하게 출석'];
    $raw = trim($_POST['message'] ?? '');
    $message = ($raw !== '' && mb_strlen($raw) <= 50) ? $raw : $msgs[array_rand($msgs)];
    DB::insert("{$prefix}attendance", ['member_id' => Auth::id(), 'message' => $message, 'attend_date' => $today]);
    $attendPoint = (int)nb_setting('point_attendance', '5');
    if ($attendPoint > 0) Point::give(Auth::id(), $attendPoint, '출석체크');
    $count = DB::fetch("SELECT COUNT(*) as cnt FROM {$prefix}attendance WHERE member_id = ? AND attend_date LIKE ?",
        [Auth::id(), date('Y-m') . '-%']);
    Cache::flush();
    echo json_encode(['ok' => true, 'date' => $today, 'count' => (int)($count['cnt'] ?? 0), 'point' => $attendPoint]);
});

Router::post('/profile', function () {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $data = ['nickname' => trim($_POST['nickname'] ?? ''), 'email' => trim($_POST['email'] ?? '')];
    if (!empty($_POST['password'])) $data['password'] = $_POST['password'];

    // 프로필 이미지 삭제
    if (!empty($_POST['delete_profile_image'])) {
        $current = Member::find(Auth::id())['profile_image'] ?? '';
        if ($current && file_exists(NB_ROOT . '/' . $current)) unlink(NB_ROOT . '/' . $current);
        $data['profile_image'] = '';
    }

    // 프로필 이미지 업로드
    if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            // 기존 이미지 삭제
            $current = Member::find(Auth::id())['profile_image'] ?? '';
            if ($current && file_exists(NB_ROOT . '/' . $current)) unlink(NB_ROOT . '/' . $current);
            $dir = NB_ROOT . '/uploads/profile';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $newName = 'pf_' . Auth::id() . '_' . time() . '.' . $ext;
            $savePath = $dir . '/' . $newName;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], $savePath);
            if ($ext !== 'webp' && function_exists('imagewebp')) {
                $webpPath = Upload::convertToWebpPublic($savePath, $ext);
                if ($webpPath) $newName = preg_replace('/\.[^.]+$/', '.webp', $newName);
            }
            $data['profile_image'] = 'uploads/profile/' . $newName;
        }
    }

    Member::update(Auth::id(), $data);
    // 세션 갱신
    $_SESSION['member']['nickname'] = $data['nickname'];
    $_SESSION['member']['email'] = $data['email'] ?? '';
    $_SESSION['member']['profile_image'] = $data['profile_image'] ?? ($_SESSION['member']['profile_image'] ?? '');
    Router::redirect(nb_url('profile') . '?tab=info&saved=1');
});

// ===== 쪽지 =====

// 쪽지함
Router::get('/messages', function () {
    Auth::requireLogin();
    $box  = $_GET['box'] ?? 'inbox';
    $page = max(1, (int)($_GET['p'] ?? 1));
    $data = $box === 'sent'
        ? Message::outbox(Auth::id(), $page)
        : Message::inbox(Auth::id(), $page);
    Router::loadTheme('member/messages', ['box' => $box, 'data' => $data]);
});

// 쪽지 보내기 (폼 페이지) — 고정 경로는 {id} 와일드카드보다 먼저 등록
Router::get('/messages/write', function () {
    Auth::requireLogin();
    $to = $_GET['to'] ?? '';
    Router::loadTheme('member/messages', ['write' => true, 'to' => $to]);
});

// 읽지 않은 쪽지 수 API
Router::get('/messages/unread-count', function () {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['count' => Message::unreadCount(Auth::id())]);
    exit;
});

// 쪽지 전송 처리
Router::post('/messages/send', function () {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::verifyCsrf()) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); exit; }

    $toNick  = trim($_POST['to'] ?? '');
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$toNick || !$title || !$content) {
        echo json_encode(['success' => false, 'message' => '모든 항목을 입력하세요.']); exit;
    }
    $receiver = Message::findMemberByNickname($toNick);
    if (!$receiver) {
        echo json_encode(['success' => false, 'message' => '존재하지 않는 닉네임입니다.']); exit;
    }
    if ($receiver['id'] === Auth::id()) {
        echo json_encode(['success' => false, 'message' => '자신에게는 쪽지를 보낼 수 없습니다.']); exit;
    }
    // 쪽지 포인트 소모 체크
    $msgCost = (int)nb_setting('point_message', '0');
    if ($msgCost > 0 && !Auth::isAdmin()) {
        $myPoint = (int)(Auth::user()['point'] ?? 0);
        if ($myPoint < $msgCost) {
            echo json_encode(['success' => false, 'message' => "포인트가 부족합니다. (필요: {$msgCost}P, 보유: {$myPoint}P)"]); exit;
        }
        Point::give(Auth::id(), -$msgCost, '쪽지 발송');
    }
    Message::send(Auth::id(), $receiver['id'], $title, $content);
    echo json_encode(['success' => true, 'message' => '쪽지를 보냈습니다.']);
    exit;
});

// 쪽지 상세 + 읽음 처리 — 와일드카드는 고정 경로 이후에 등록
Router::get('/messages/{id}', function ($params) {
    Auth::requireLogin();
    $msg = Message::find((int)$params['id']);
    if (!$msg) { http_response_code(404); return; }
    if ($msg['receiver_id'] !== Auth::id() && $msg['sender_id'] !== Auth::id()) {
        http_response_code(403); return;
    }
    if ($msg['receiver_id'] === Auth::id() && !$msg['is_read']) {
        Message::markRead((int)$params['id']);
        $msg['is_read'] = 1;
    }
    Router::loadTheme('member/messages', ['view' => $msg, 'box' => $_GET['box'] ?? 'inbox']);
});

// 쪽지 삭제
Router::post('/messages/{id}/delete', function ($params) {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청');
    $msg = Message::find((int)$params['id']);
    if (!$msg) { http_response_code(404); return; }
    $box = $_POST['box'] ?? 'inbox';
    if ($msg['receiver_id'] === Auth::id()) {
        Message::deleteForReceiver((int)$params['id']);
    } elseif ($msg['sender_id'] === Auth::id()) {
        Message::deleteForSender((int)$params['id']);
    }
    Router::redirect(nb_url('messages?box=' . $box));
});

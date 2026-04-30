<?php
/**
 * NuriBoard - API 라우트
 * 메뉴, 북마크, 알림, REST API v1 관련 라우트
 */

// 프론트 메뉴 API (관리자 전용)
Router::post('/api/menu/create', function () {
    header('Content-Type: application/json');
    if (!Auth::check() || !Auth::isAdmin()) { echo json_encode(['success' => false, 'message' => '권한 없음']); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !Auth::verifyCsrfValue($input['_token'] ?? '')) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); return; }
    $id = Menu::create([
        'parent_id' => (int)($input['parent_id'] ?? 0),
        'title' => trim($input['title'] ?? ''),
        'link' => trim($input['link'] ?? ''),
        'board_id' => trim($input['board_id'] ?? ''),
        'sort_order' => (int)($input['sort_order'] ?? 0),
    ]);
    echo json_encode(['success' => true, 'id' => $id]);
});
Router::post('/api/menu/delete', function () {
    header('Content-Type: application/json');
    if (!Auth::check() || !Auth::isAdmin()) { echo json_encode(['success' => false, 'message' => '권한 없음']); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !Auth::verifyCsrfValue($input['_token'] ?? '')) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); return; }
    Menu::delete((int)($input['id'] ?? 0));
    echo json_encode(['success' => true]);
});
Router::post('/api/menu/list', function () {
    header('Content-Type: application/json');
    if (!Auth::check() || !Auth::isAdmin()) { echo json_encode(['success' => false]); return; }
    echo json_encode(['success' => true, 'menus' => Menu::getTree(), 'all' => Menu::listAll()]);
});

// 북마크 토글
Router::post('/api/bookmark', function () {
    header('Content-Type: application/json');
    if (!Auth::check()) { echo json_encode(['success' => false]); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = (int)($input['post_id'] ?? 0);
    $prefix = DB::getPrefix();
    $exists = DB::fetch("SELECT id FROM {$prefix}bookmarks WHERE member_id = ? AND post_id = ?", [Auth::id(), $postId]);
    if ($exists) {
        DB::delete("{$prefix}bookmarks", "id = ?", [$exists['id']]);
        echo json_encode(['success' => true, 'bookmarked' => false]);
    } else {
        DB::insert("{$prefix}bookmarks", ['member_id' => Auth::id(), 'post_id' => $postId]);
        echo json_encode(['success' => true, 'bookmarked' => true]);
    }
});

// 회원 호버 카드 정보
Router::post('/api/member-card', function () {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $mid = (int)($input['id'] ?? 0);
    $member = Member::find($mid);
    if (!$member) { echo json_encode(['success' => false]); return; }

    Follow::ensureTable();
    $prefix = DB::getPrefix();

    $recent = DB::fetchAll(
        "SELECT id, board_id, title FROM {$prefix}posts WHERE member_id = ? AND is_hidden = 0 ORDER BY id DESC LIMIT 3",
        [$mid]
    );
    $postCount = DB::count("{$prefix}posts", 'member_id = ? AND is_hidden = 0', [$mid]);
    $commentCount = DB::count("{$prefix}comments", 'member_id = ? AND is_hidden = 0', [$mid]);
    $followerCount = Follow::followerCount($mid);

    $isFollowing = false;
    if (Auth::check() && Auth::id() !== $mid) {
        $isFollowing = Follow::isFollowing(Auth::id(), $mid);
    }

    echo json_encode([
        'success' => true,
        'id' => $mid,
        'nickname' => $member['nickname'],
        'level' => (int)$member['level'],
        'level_icon' => Level::getIcon((int)$member['level']),
        'joined' => date('Y.m', strtotime($member['created_at'])),
        'post_count' => (int)$postCount,
        'comment_count' => (int)$commentCount,
        'follower_count' => (int)$followerCount,
        'is_following' => $isFollowing,
        'is_me' => Auth::check() && Auth::id() === $mid,
        'logged_in' => Auth::check(),
        'recent_posts' => array_map(function ($p) {
            return [
                'title' => mb_strimwidth($p['title'], 0, 30, '...'),
                'url' => nb_url("board/{$p['board_id']}/{$p['id']}"),
            ];
        }, $recent),
        'profile_url' => nb_url("member/{$mid}"),
        'message_url' => nb_url("messages/write?to=") . urlencode($member['nickname']),
    ]);
});

// 팔로우 토글
Router::post('/api/follow', function () {
    header('Content-Type: application/json');
    if (!Auth::check()) { echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    $targetId = (int)($input['target_id'] ?? 0);
    if ($targetId === Auth::id()) { echo json_encode(['success' => false, 'message' => '본인은 팔로우할 수 없습니다.']); return; }
    if (!Member::find($targetId)) { echo json_encode(['success' => false, 'message' => '존재하지 않는 회원입니다.']); return; }

    Follow::ensureTable();

    if (Follow::isFollowing(Auth::id(), $targetId)) {
        Follow::unfollow(Auth::id(), $targetId);
        $following = false;
    } else {
        Follow::follow(Auth::id(), $targetId);
        $following = true;
    }

    echo json_encode([
        'success' => true,
        'is_following' => $following,
        'follower_count' => Follow::followerCount($targetId),
    ]);
});

// 알림 API
Router::post('/api/notifications', function () {
    header('Content-Type: application/json');
    if (!Auth::check()) { echo json_encode(['success' => false]); return; }
    $prefix = DB::getPrefix();
    $notifs = DB::fetchAll("SELECT * FROM {$prefix}notifications WHERE member_id = ? ORDER BY id DESC LIMIT 20", [Auth::id()]);
    $unread = DB::count("{$prefix}notifications", "member_id = ? AND is_read = 0", [Auth::id()]);
    echo json_encode(['success' => true, 'notifications' => $notifs, 'unread' => $unread]);
});
Router::post('/api/notifications/read', function () {
    header('Content-Type: application/json');
    if (!Auth::check()) { echo json_encode(['success' => false]); return; }
    $prefix = DB::getPrefix();
    DB::query("UPDATE {$prefix}notifications SET is_read = 1 WHERE member_id = ?", [Auth::id()]);
    echo json_encode(['success' => true]);
});

// ===== REST API v1 =====

// API 키 발급 (로그인 필요)
Router::post('/api/v1/key/generate', function () {
    header('Content-Type: application/json');
    if (!Auth::check()) { apiJson(['error' => '로그인 필요'], 401); }
    $prefix = DB::getPrefix();
    $key = bin2hex(random_bytes(32));
    $name = trim($_POST['name'] ?? 'My API Key');
    DB::insert("{$prefix}api_keys", ['member_id' => Auth::id(), 'api_key' => $key, 'name' => $name]);
    apiJson(['success' => true, 'api_key' => $key, 'message' => 'API 키가 발급되었습니다. 이 키는 다시 볼 수 없으니 안전하게 보관하세요.']);
});

// API 키 삭제
Router::post('/api/v1/key/delete', function () {
    header('Content-Type: application/json');
    if (!Auth::check()) { apiJson(['error' => '로그인 필요'], 401); }
    $id = (int)($_POST['id'] ?? 0);
    $prefix = DB::getPrefix();
    DB::delete("{$prefix}api_keys", "id = ? AND member_id = ?", [$id, Auth::id()]);
    apiJson(['success' => true]);
});

// API: 내 정보
Router::get('/api/v1/me', function () {
    $user = apiAuth();
    if (!$user) apiJson(['error' => '유효하지 않은 API 키'], 401);
    apiJson(['success' => true, 'user' => ['id' => $user['mid'], 'user_id' => $user['user_id'], 'nickname' => $user['nickname'], 'level' => $user['level'], 'point' => $user['point']]]);
});

// API: 게시판 목록
Router::get('/api/v1/boards', function () {
    $user = apiAuth();
    if (!$user) apiJson(['error' => '유효하지 않은 API 키'], 401);
    $boards = Board::listAll(true);
    apiJson(['success' => true, 'boards' => array_map(function($b) {
        return ['board_id' => $b['board_id'], 'title' => $b['title'], 'description' => $b['description'] ?? '', 'board_type' => $b['board_type'] ?? 'normal'];
    }, $boards)]);
});

// API: 글 목록
Router::get('/api/v1/posts', function () {
    $user = apiAuth();
    if (!$user) apiJson(['error' => '유효하지 않은 API 키'], 401);
    $boardId = $_GET['board_id'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    if ($boardId) {
        $posts = Post::list($boardId, $page, $limit);
    } else {
        $posts = ['posts' => Post::recentPosts($limit), 'total' => 0, 'page' => 1];
    }
    apiJson(['success' => true, 'posts' => $posts['posts'], 'page' => $posts['page'] ?? 1]);
});

// API: 글 상세
Router::get('/api/v1/posts/{id}', function ($params) {
    $user = apiAuth();
    if (!$user) apiJson(['error' => '유효하지 않은 API 키'], 401);
    $post = Post::find((int)$params['id']);
    if (!$post) apiJson(['error' => '게시글 없음'], 404);
    $attachments = Upload::listByPost($post['id']);
    apiJson(['success' => true, 'post' => $post, 'attachments' => $attachments]);
});

// API: 글 작성
Router::post('/api/v1/posts', function () {
    $user = apiAuth();
    if (!$user) apiJson(['error' => '유효하지 않은 API 키'], 401);
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $boardId = $input['board_id'] ?? '';
    $title = trim($input['title'] ?? '');
    $content = $input['content'] ?? '';
    if (!$boardId || !$title) apiJson(['error' => 'board_id와 title은 필수'], 400);
    $board = Board::findById($boardId);
    if (!$board) apiJson(['error' => '게시판 없음'], 404);
    $id = Post::create([
        'board_id' => $boardId,
        'member_id' => $user['mid'],
        'title' => $title,
        'content' => $content,
        'category' => trim($input['category'] ?? ''),
        'tags' => trim($input['tags'] ?? ''),
        'link1' => trim($input['link1'] ?? ''),
        'link2' => trim($input['link2'] ?? ''),
    ]);
    Point::onWrite($user['mid']);
    Cache::flush();
    apiJson(['success' => true, 'post_id' => $id, 'url' => nb_url("board/{$boardId}/{$id}")], 201);
});

// API: 댓글 작성
Router::post('/api/v1/comments', function () {
    $user = apiAuth();
    if (!$user) apiJson(['error' => '유효하지 않은 API 키'], 401);
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $postId = (int)($input['post_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    if (!$postId || !$content) apiJson(['error' => 'post_id와 content는 필수'], 400);
    $post = Post::find($postId);
    if (!$post) apiJson(['error' => '게시글 없음'], 404);
    $commentId = Comment::create(['post_id' => $postId, 'member_id' => $user['mid'], 'parent_id' => (int)($input['parent_id'] ?? 0), 'content' => $content]);
    Point::onComment($user['mid']);
    Cache::flush();
    apiJson(['success' => true, 'comment_id' => $commentId], 201);
});

// ===== 플러그인 마켓 API (공개, 전용 테이블) =====

// 마켓 플러그인 목록
Router::get('/api/v1/market/plugins', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $prefix = DB::getPrefix();
    $siteUrl = nb_setting('site_url');

    // ===== 필터링 파라미터 =====
    $type = $_GET['type'] ?? 'all'; // all, free, paid
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');

    // ===== WHERE 절 구성 =====
    $where = "WHERE is_active = 1";

    // 무료/유료 필터링
    if ($type === 'free') {
        $where .= " AND price = 0";
    } elseif ($type === 'paid') {
        $where .= " AND price > 0";
    }

    // 카테고리 필터링
    $params = [];
    if ($category) {
        $where .= " AND category = ?";
        $params[] = $category;
    }

    // 검색 필터링
    if ($search) {
        $where .= " AND (name LIKE ? OR description LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // ===== 데이터 조회 =====
    $sql = "SELECT * FROM {$prefix}market_plugins {$where} ORDER BY id DESC";
    $rows = empty($params) ? DB::fetchAll($sql) : DB::fetchAll($sql, $params);

    $plugins = [];
    foreach ($rows as $r) {
        $plugins[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'description' => mb_strimwidth($r['description'] ?? '', 0, 200, '...'),
            'version' => $r['version'] ?? '1.0',
            'author' => $r['author'] ?? '',
            'thumbnail' => $r['thumbnail'] ? $siteUrl . '/' . $r['thumbnail'] : '',
            'price' => (int)$r['price'],
            'downloads' => (int)$r['downloads'],
            'zip_size' => (int)$r['zip_size'],
            'category' => $r['category'] ?? '',
            'download_url' => $siteUrl . '/api/v1/market/download/' . $r['id'],
            'created_at' => $r['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'plugins' => $plugins, 'site_url' => $siteUrl], JSON_UNESCAPED_UNICODE);
});

// 마켓 플러그인 다운로드
Router::get('/api/v1/market/download/{id}', function ($params) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Expose-Headers: Content-Disposition');
    $prefix = DB::getPrefix();
    $id = (int)$params['id'];

    $mp = DB::fetch("SELECT * FROM {$prefix}market_plugins WHERE id = ? AND is_active = 1", [$id]);
    if (!$mp) { http_response_code(404); echo 'Plugin not found in DB (id=' . $id . ')'; return; }

    $path = NB_ROOT . '/' . $mp['zip_file'];
    if (!file_exists($path)) {
        http_response_code(404);
        echo 'File not found. DB zip_file="' . htmlspecialchars($mp['zip_file']) . '" — tried path: ' . htmlspecialchars($path);
        return;
    }

    // 유료 플러그인: 포인트 차감
    if ((int)$mp['price'] > 0) {
        if (!Auth::check()) { http_response_code(401); echo 'Login required'; return; }
        $purchased = DB::fetch("SELECT id FROM {$prefix}file_purchases WHERE member_id = ? AND attachment_id = ?", [Auth::id(), -$id]);
        if (!$purchased) {
            $myPoint = (int)(Auth::user()['point'] ?? 0);
            if ($myPoint < (int)$mp['price']) {
                echo "<script>alert('포인트 부족 (필요: {$mp['price']}P)');history.back();</script>";
                return;
            }
            Point::give(Auth::id(), -(int)$mp['price'], '마켓 플러그인 구매: ' . $mp['name']);
            DB::insert("{$prefix}file_purchases", ['member_id' => Auth::id(), 'attachment_id' => -$id, 'point' => (int)$mp['price']]);
        }
    }

    // 다운로드 수 증가
    DB::query("UPDATE {$prefix}market_plugins SET downloads = downloads + 1 WHERE id = ?", [$id]);

    $fileName = $mp['zip_orig_name'] ?: 'plugin.zip';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

// ============================================================
// 크로스사이트 구매 API (site_token 기반)
// ============================================================

// 특정 site_token 이 구매한 플러그인 목록 (페이지네이션 지원)
Router::get('/api/v1/market/my-purchases', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $prefix = DB::getPrefix();
    $token = trim($_GET['site_token'] ?? '');
    if (strlen($token) < 16) {
        echo json_encode(['success' => false, 'message' => 'invalid token', 'purchases' => []]);
        return;
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = max(1, min(100, (int)($_GET['per'] ?? 20)));
    $offset = ($page - 1) * $per;

    try {
        $total = (int)(DB::fetch(
            "SELECT COUNT(*) AS c FROM {$prefix}market_purchases WHERE site_token = ? AND status = 'paid'",
            [$token]
        )['c'] ?? 0);

        $rows = DB::fetchAll(
            "SELECT p.id, p.plugin_id, p.amount, p.paid_at, p.mbr_ref_no, p.card_no,
                    mp.name AS plugin_name, mp.version, mp.author, mp.thumbnail
             FROM {$prefix}market_purchases p
             LEFT JOIN {$prefix}market_plugins mp ON p.plugin_id = mp.id
             WHERE p.site_token = ? AND p.status = 'paid'
             ORDER BY p.id DESC
             LIMIT {$per} OFFSET {$offset}",
            [$token]
        );
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'purchases' => [], 'total' => 0]);
        return;
    }

    $siteUrl = rtrim(nb_setting('site_url'), '/');
    $purchases = [];
    foreach ($rows as $r) {
        $purchases[] = [
            'purchase_id' => (int)$r['id'],
            'plugin_id'   => (int)$r['plugin_id'],
            'amount'      => (int)$r['amount'],
            'paid_at'     => $r['paid_at'],
            'mbr_ref_no'  => $r['mbr_ref_no'],
            'card_no'     => $r['card_no'],
            'plugin_name' => $r['plugin_name'],
            'version'     => $r['version'],
            'author'      => $r['author'],
            'thumbnail'   => $r['thumbnail'] ? $siteUrl . '/' . $r['thumbnail'] : '',
        ];
    }
    echo json_encode([
        'success'   => true,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $per,
        'purchases' => $purchases,
    ], JSON_UNESCAPED_UNICODE);
});

// 배포 사이트가 본인 구매 기록 삭제 (환불 아님 - 목록 정리 용도)
Router::post('/api/v1/market/my-purchases/delete', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    $prefix = DB::getPrefix();
    $token = trim($_POST['site_token'] ?? '');
    $ids   = $_POST['ids'] ?? [];
    if (strlen($token) < 16) {
        echo json_encode(['success' => false, 'message' => 'invalid token']); return;
    }
    if (!is_array($ids)) $ids = [$ids];
    $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'no ids']); return;
    }
    // 해당 토큰의 레코드만 삭제 (다른 사이트 레코드는 건드리지 못하게)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = DB::getInstance()->prepare(
        "DELETE FROM {$prefix}market_purchases WHERE site_token = ? AND id IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$token], $ids));
    $count = $stmt->rowCount();
    echo json_encode(['success' => true, 'deleted' => $count]);
});

// CORS preflight
Router::get('/api/v1/market/my-purchases/delete', function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
});

// site_token 으로 유료 플러그인 ZIP 다운로드
Router::get('/api/v1/market/download-licensed/{id}', function ($params) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Expose-Headers: Content-Disposition');
    $prefix = DB::getPrefix();
    $id = (int)$params['id'];
    $token = trim($_GET['site_token'] ?? '');

    if (strlen($token) < 16) { http_response_code(401); echo 'Invalid token'; return; }

    $mp = DB::fetch("SELECT * FROM {$prefix}market_plugins WHERE id = ? AND is_active = 1", [$id]);
    if (!$mp) { http_response_code(404); echo 'Plugin not found'; return; }

    // 무료 플러그인은 이 엔드포인트로 올 필요 없음 (하위호환용으로 허용)
    if ((int)$mp['price'] > 0) {
        try {
            $purchase = DB::fetch(
                "SELECT id FROM {$prefix}market_purchases WHERE site_token = ? AND plugin_id = ? AND status = 'paid'",
                [$token, $id]
            );
        } catch (Exception $e) { $purchase = null; }
        if (!$purchase) { http_response_code(403); echo 'Not purchased'; return; }
    }

    $path = NB_ROOT . '/' . $mp['zip_file'];
    if (!file_exists($path)) { http_response_code(404); echo 'File missing'; return; }

    // 다운로드 수 증가
    DB::query("UPDATE {$prefix}market_plugins SET downloads = downloads + 1 WHERE id = ?", [$id]);
    try {
        DB::query("UPDATE {$prefix}market_purchases SET download_count = download_count + 1 WHERE site_token = ? AND plugin_id = ?", [$token, $id]);
    } catch (Exception $e) {}

    $fileName = $mp['zip_orig_name'] ?: 'plugin.zip';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

// 인기 검색 키워드
Router::get('/api/search/popular', function () {
    header('Content-Type: application/json');
    $prefix = DB::getPrefix();
    DB::query("CREATE TABLE IF NOT EXISTS {$prefix}search_keywords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(100) NOT NULL,
        count INT DEFAULT 1,
        searched_at DATETIME,
        UNIQUE KEY uk_keyword (keyword)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $keywords = DB::fetchAll("SELECT keyword, count FROM {$prefix}search_keywords ORDER BY count DESC LIMIT 10");
    echo json_encode(['success' => true, 'keywords' => $keywords]);
});

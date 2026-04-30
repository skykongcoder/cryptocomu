<?php
/**
 * NuriBoard - 게시판 라우트
 * 게시판 목록, 작성, 상세, 수정, 삭제, 일괄삭제, 댓글, 추천, 다운로드, 업로드, 신고 관련 라우트
 */

// 게시판 목록
Router::get('/board/{board_id}', function ($params) {
    $board = Board::findById($params['board_id']);
    if (!$board || !$board['is_active']) {
        http_response_code(404);
        Router::loadTheme('error/404');
        return;
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $posts = Post::list($params['board_id'], $page, $board['list_count'], $search, $category);
    Router::loadTheme('board/list', ['board' => $board, 'posts' => $posts, 'search' => $search, 'category' => $category]);
});

// 게시글 작성
Router::get('/board/{board_id}/write', function ($params) {
    Auth::requireLogin();
    $board = Board::findById($params['board_id']);
    if (!$board) { http_response_code(404); return; }
    Router::loadTheme('board/write', ['board' => $board]);
});

Router::post('/board/{board_id}/write', function ($params) {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    // 포인트 소모 체크
    $board = Board::findById($params['board_id']);
    $writeCost = (int)($board['point_write_cost'] ?? 0);
    if ($writeCost > 0 && !Auth::isAdmin()) {
        $currentPoint = (int)(Auth::user()['point'] ?? 0);
        if ($currentPoint < $writeCost) {
            Router::loadTheme('board/write', [
                'board' => $board,
                'post' => $_POST,
                'error' => "포인트가 부족합니다. (필요: {$writeCost}P, 보유: {$currentPoint}P)"
            ]);
            return;
        }
    }
    $id = Post::create([
        'board_id' => $params['board_id'],
        'member_id' => Auth::id(),
        'category' => trim($_POST['category'] ?? ''),
        'title' => Plugin::applyFilter('post.title', trim($_POST['title'] ?? '')),
        'content' => Plugin::applyFilter('post.content', $_POST['content'] ?? ''),
        'is_notice' => Auth::isAdmin() ? (int)($_POST['is_notice'] ?? 0) : 0,
        'title_color' => trim($_POST['title_color'] ?? ''),
        'title_bg' => trim($_POST['title_bg'] ?? ''),
        'is_secret' => (int)($_POST['is_secret'] ?? 0),
        'link1' => trim($_POST['link1'] ?? ''),
        'link2' => trim($_POST['link2'] ?? ''),
        'tags' => trim($_POST['tags'] ?? ''),
    ]);
    // 파일 첨부 처리
    if (!empty($_FILES['files'])) {
        $dlPoint = (int)($_POST['download_point'] ?? 0);
        for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $uploaded = Upload::upload([
                    'name' => $_FILES['files']['name'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'size' => $_FILES['files']['size'][$i],
                    'error' => $_FILES['files']['error'][$i],
                ], $id);
                // 유료 첨부파일 포인트 설정
                if ($uploaded && $dlPoint > 0 && ($board['allow_paid_file'] ?? 0)) {
                    DB::update(DB::getPrefix() . 'attachments', ['download_point' => $dlPoint], 'id = ?', [$uploaded['id']]);
                }
            }
        }
    }
    Point::onWrite(Auth::id());
    Cache::deletePattern('main_*');
    // 글쓰기 소모 포인트 차감
    if ($writeCost > 0 && !Auth::isAdmin()) {
        Point::give(Auth::id(), -$writeCost, '글 작성 소모');
    }
    Level::checkAndUpgrade(Auth::id());
    Router::redirect(nb_url("board/{$params['board_id']}/{$id}"));
});

// 게시글 상세
Router::get('/board/{board_id}/{post_id}', function ($params) {
    $board = Board::findById($params['board_id']);
    $post = Post::find((int)$params['post_id']);
    if (!$board || !$post || $post['board_id'] !== $params['board_id']) {
        http_response_code(404);
        Router::loadTheme('error/404');
        return;
    }
    // 비밀글 접근 제한: 작성자, 관리자만 열람 가능
    if (!empty($post['is_secret']) && (!Auth::check() || (Auth::id() !== $post['member_id'] && !Auth::isAdmin()))) {
        Router::loadTheme('board/list', [
            'board' => $board,
            'posts' => Post::list($params['board_id'], 1, $board['list_count']),
            'search' => '', 'category' => '',
            'error' => '비밀글은 작성자와 관리자만 볼 수 있습니다.',
        ]);
        return;
    }
    Post::incrementHit((int)$params['post_id']);
    $post['hit']++;
    $comments = Comment::listByPost((int)$params['post_id']);
    $writer = Member::find($post['member_id']);
    $prevPost = Post::getPrev((int)$params['post_id'], $params['board_id']);
    $nextPost = Post::getNext((int)$params['post_id'], $params['board_id']);
    Router::loadTheme('board/view', ['board' => $board, 'post' => $post, 'comments' => $comments, 'writer' => $writer, 'prevPost' => $prevPost, 'nextPost' => $nextPost]);
});

// 게시판 하단 목록 (지연 로드용 HTML 조각)
Router::get('/api/board-list-fragment/{board_id}', function ($params) {
    $board = Board::findById($params['board_id']);
    if (!$board) { http_response_code(404); return; }
    $page = max(1, (int)($_GET['p'] ?? 1));
    $currentId = (int)($_GET['current'] ?? 0);
    $boardList = Post::list($params['board_id'], $page, 20);
    header('Content-Type: text/html; charset=utf-8');
    Router::loadTheme('board/_list_fragment', ['board' => $board, 'boardList' => $boardList, 'currentId' => $currentId]);
});

// 게시글 수정
Router::get('/board/{board_id}/{post_id}/edit', function ($params) {
    Auth::requireLogin();
    $post = Post::find((int)$params['post_id']);
    if (!$post || ($post['member_id'] !== Auth::id() && !Auth::isAdmin())) {
        http_response_code(403);
        return;
    }
    $board = Board::findById($params['board_id']);
    $attachments = Upload::listByPost((int)$params['post_id']);
    Router::loadTheme('board/write', ['board' => $board, 'post' => $post, 'editing' => true, 'attachments' => $attachments]);
});

Router::post('/board/{board_id}/{post_id}/edit', function ($params) {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $post = Post::find((int)$params['post_id']);
    if (!$post || ($post['member_id'] !== Auth::id() && !Auth::isAdmin())) {
        http_response_code(403);
        return;
    }
    Post::update((int)$params['post_id'], [
        'category' => trim($_POST['category'] ?? ''),
        'title' => trim($_POST['title'] ?? ''),
        'content' => $_POST['content'] ?? '',
        'is_notice' => Auth::isAdmin() ? (int)($_POST['is_notice'] ?? 0) : $post['is_notice'],
        'title_color' => trim($_POST['title_color'] ?? ''),
        'title_bg' => trim($_POST['title_bg'] ?? ''),
        'is_secret' => (int)($_POST['is_secret'] ?? 0),
        'link1' => trim($_POST['link1'] ?? ''),
        'link2' => trim($_POST['link2'] ?? ''),
        'tags' => trim($_POST['tags'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    // 추가 파일 업로드
    if (!empty($_FILES['files'])) {
        for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                Upload::upload([
                    'name' => $_FILES['files']['name'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'size' => $_FILES['files']['size'][$i],
                    'error' => $_FILES['files']['error'][$i],
                ], (int)$params['post_id']);
            }
        }
    }
    // 기존 첨부파일 포인트 수정
    if (!empty($_POST['att_point'])) {
        $prefix = DB::getPrefix();
        foreach ($_POST['att_point'] as $attId => $pt) {
            DB::update("{$prefix}attachments", ['download_point' => max(0, (int)$pt)], 'id = ? AND post_id = ?', [(int)$attId, (int)$params['post_id']]);
        }
    }
    Router::redirect(nb_url("board/{$params['board_id']}/{$params['post_id']}"));
});

// 게시글 삭제
Router::post('/board/{board_id}/{post_id}/delete', function ($params) {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $post = Post::find((int)$params['post_id']);
    if (!$post || ($post['member_id'] !== Auth::id() && !Auth::isAdmin())) {
        http_response_code(403);
        return;
    }
    Point::onDeletePost($post['member_id']);
    Post::delete((int)$params['post_id']);
    Cache::deletePattern('main_*');
    Router::redirect(nb_url("board/{$params['board_id']}"));
});

// 일괄 삭제
Router::post('/board/{board_id}/delete-posts', function ($params) {
    Auth::requireLogin();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !Auth::verifyCsrfValue($input['_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청']);
        return;
    }
    $board = Board::findById($params['board_id']);
    if (!$board) { echo json_encode(['success' => false, 'message' => '게시판 없음']); return; }
    $ids = array_map('intval', $input['ids'] ?? []);
    $deleted = 0;
    foreach ($ids as $id) {
        $post = Post::find($id);
        if (!$post) continue;
        // 권한 체크: 관리자이거나 본인글이면서 allow_delete=1
        if (Auth::isAdmin() || ((int)$post['member_id'] === Auth::id() && ($board['allow_delete'] ?? 1))) {
            Point::onDeletePost($post['member_id']);
            Post::delete($id);
            $deleted++;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'deleted' => $deleted]);
});

// 게시글 단일 복사 (관리자 전용)
Router::post('/board/{board_id}/{post_id}/copy', function ($params) {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'message' => '관리자만 가능합니다.']); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!Auth::verifyCsrfValue($input['_token'] ?? '')) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); return; }
    $targetBoardId = $input['target_board_id'] ?? '';
    $targetBoard = Board::findById($targetBoardId);
    if (!$targetBoard) { echo json_encode(['success' => false, 'message' => '대상 게시판이 없습니다.']); return; }
    $newId = Post::copy((int)$params['post_id'], $targetBoardId);
    if ($newId) {
        Cache::deletePattern('main_*');
        echo json_encode(['success' => true, 'post_id' => $newId, 'message' => '게시글이 복사되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '복사에 실패했습니다.']);
    }
});

// 게시글 단일 이동 (관리자 전용)
Router::post('/board/{board_id}/{post_id}/move', function ($params) {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'message' => '관리자만 가능합니다.']); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!Auth::verifyCsrfValue($input['_token'] ?? '')) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); return; }
    $targetBoardId = $input['target_board_id'] ?? '';
    $targetBoard = Board::findById($targetBoardId);
    if (!$targetBoard) { echo json_encode(['success' => false, 'message' => '대상 게시판이 없습니다.']); return; }
    if ($targetBoardId === $params['board_id']) { echo json_encode(['success' => false, 'message' => '같은 게시판으로 이동할 수 없습니다.']); return; }
    $result = Post::move((int)$params['post_id'], $targetBoardId);
    if ($result) {
        Cache::deletePattern('main_*');
        echo json_encode(['success' => true, 'message' => '게시글이 이동되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '이동에 실패했습니다.']);
    }
});

// 일괄 복사 (관리자 전용)
Router::post('/board/{board_id}/copy-posts', function ($params) {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'message' => '관리자만 가능합니다.']); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!Auth::verifyCsrfValue($input['_token'] ?? '')) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); return; }
    $targetBoardId = $input['target_board_id'] ?? '';
    $targetBoard = Board::findById($targetBoardId);
    if (!$targetBoard) { echo json_encode(['success' => false, 'message' => '대상 게시판이 없습니다.']); return; }
    $ids = array_map('intval', $input['ids'] ?? []);
    $copied = 0;
    foreach ($ids as $id) {
        if (Post::copy($id, $targetBoardId)) $copied++;
    }
    Cache::deletePattern('main_*');
    echo json_encode(['success' => true, 'copied' => $copied, 'message' => "{$copied}개의 게시글이 복사되었습니다."]);
});

// 일괄 이동 (관리자 전용)
Router::post('/board/{board_id}/move-posts', function ($params) {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'message' => '관리자만 가능합니다.']); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!Auth::verifyCsrfValue($input['_token'] ?? '')) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); return; }
    $targetBoardId = $input['target_board_id'] ?? '';
    $targetBoard = Board::findById($targetBoardId);
    if (!$targetBoard) { echo json_encode(['success' => false, 'message' => '대상 게시판이 없습니다.']); return; }
    if ($targetBoardId === $params['board_id']) { echo json_encode(['success' => false, 'message' => '같은 게시판으로 이동할 수 없습니다.']); return; }
    $ids = array_map('intval', $input['ids'] ?? []);
    $moved = 0;
    foreach ($ids as $id) {
        if (Post::move($id, $targetBoardId)) $moved++;
    }
    Cache::deletePattern('main_*');
    echo json_encode(['success' => true, 'moved' => $moved, 'message' => "{$moved}개의 게시글이 이동되었습니다."]);
});

// 댓글 작성
Router::post('/comment/write', function () {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $postId = (int)($_POST['post_id'] ?? 0);
    $post = Post::find($postId);
    if (!$post) { http_response_code(404); return; }
    Comment::create([
        'post_id' => $postId,
        'member_id' => Auth::id(),
        'parent_id' => (int)($_POST['parent_id'] ?? 0),
        'content' => Plugin::applyFilter('comment.content', trim($_POST['content'] ?? '')),
    ]);
    Point::onComment(Auth::id());
    Cache::deletePattern('main_*');
    // 글 작성자에게 알림 (본인 제외)
    if ((int)$post['member_id'] !== Auth::id()) {
        Message::send(Auth::id(), $post['member_id'], '[알림] 새 댓글', Auth::user()['nickname'] . '님이 "' . mb_strimwidth($post['title'], 0, 20, '..') . '" 글에 댓글을 달았습니다.');
    }
    Level::checkAndUpgrade(Auth::id());
    Router::redirect(nb_url("board/{$post['board_id']}/{$postId}") . '#comments');
});

// 댓글 채택
Router::post('/comment/{comment_id}/adopt', function ($params) {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $commentId = (int)$params['comment_id'];
    $comment = Comment::find($commentId);
    if (!$comment) { http_response_code(404); return; }
    $post = Post::find((int)$comment['post_id']);
    if (!$post) { http_response_code(404); return; }

    $result = Comment::adopt($commentId, Auth::id());
    if (!$result['ok']) {
        $msg = addslashes($result['msg']);
        echo "<script>alert('{$msg}');history.back();</script>";
        return;
    }
    Cache::deletePattern('main_*');
    $okMsg = '댓글을 채택했습니다.' . ($result['points'] > 0 ? " (+{$result['points']}P 지급)" : '');
    $okMsg = addslashes($okMsg);
    $redirect = nb_url("board/{$post['board_id']}/{$post['id']}") . '#comment-' . $commentId;
    echo "<script>alert('{$okMsg}');location.href='{$redirect}';</script>";
});

// 댓글 삭제
Router::post('/comment/{comment_id}/delete', function ($params) {
    Auth::requireLogin();
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $comment = Comment::find((int)$params['comment_id']);
    if (!$comment || ($comment['member_id'] !== Auth::id() && !Auth::isAdmin())) {
        http_response_code(403);
        return;
    }
    $post = Post::find($comment['post_id']);
    Point::onDeleteComment($comment['member_id']);
    Comment::delete((int)$params['comment_id']);
    if ($post) {
        Router::redirect(nb_url("board/{$post['board_id']}/{$post['id']}") . '#comments');
    } else {
        Router::redirect(nb_url('/'));
    }
});

// 추천/비추천 API
Router::post('/vote', function () {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::verifyCsrf()) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); exit; }
    $postId = (int)($_POST['post_id'] ?? 0);
    $type = (int)($_POST['type'] ?? 0);
    if (!in_array($type, [1, -1])) { echo json_encode(['success' => false, 'message' => '잘못된 요청']); exit; }
    $result = Vote::vote($postId, Auth::id(), $type);
    // 추천 시 글 작성자에게 알림
    if ($result['success'] && $type === 1) {
        $post = Post::find($postId);
        if ($post && (int)$post['member_id'] !== Auth::id()) {
            Message::send(Auth::id(), $post['member_id'], '[알림] 추천', Auth::user()['nickname'] . '님이 "' . mb_strimwidth($post['title'], 0, 20, '..') . '" 글을 추천했습니다.');
        }
    }
    echo json_encode($result);
    exit;
});

// 파일 다운로드
Router::get('/download/{file_id}', function ($params) {
    // CORS for cross-site market plugin downloads
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Expose-Headers: Content-Disposition');

    $file = Upload::find((int)$params['file_id']);
    if (!$file) { http_response_code(404); return; }
    $path = NB_ROOT . '/' . $file['file_name'];
    if (!file_exists($path)) { http_response_code(404); return; }

    $dlPoint = (int)($file['download_point'] ?? 0);
    if ($dlPoint > 0) {
        if (!Auth::check()) { Router::redirect(nb_url('login?redirect=' . urlencode($_SERVER['REQUEST_URI']))); return; }
        $prefix = DB::getPrefix();
        $post = Post::find((int)$file['post_id']);
        $isOwner = $post && (int)$post['member_id'] === Auth::id();
        $isAdmin = Auth::isAdmin();
        $purchased = DB::fetch("SELECT id FROM {$prefix}file_purchases WHERE member_id = ? AND attachment_id = ?", [Auth::id(), $file['id']]);

        if (!$isOwner && !$isAdmin && !$purchased) {
            $myPoint = (int)(Auth::user()['point'] ?? 0);
            if ($myPoint < $dlPoint) {
                echo "<script>alert('포인트가 부족합니다. (필요: {$dlPoint}P, 보유: {$myPoint}P)');history.back();</script>";
                return;
            }
            // 포인트 차감 + 구매 기록
            Point::give(Auth::id(), -$dlPoint, '파일 다운로드');
            // 작성자에게 수익 지급
            if ($post) Point::give($post['member_id'], $dlPoint, '파일 판매 수익');
            DB::insert("{$prefix}file_purchases", ['member_id' => Auth::id(), 'attachment_id' => $file['id'], 'point' => $dlPoint]);
        }
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['orig_name'] . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

// 에디터 이미지 업로드 (AJAX)
Router::post('/upload/editor', function () {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_FILES['file'])) {
        echo json_encode(['error' => '파일 없음']);
        exit;
    }
    $url = Upload::uploadEditorImage($_FILES['file']);
    if ($url) {
        echo json_encode(['url' => $url]);
    } else {
        echo json_encode(['error' => '업로드 실패']);
    }
    exit;
});

// 첨부파일 개별 삭제 (AJAX)
Router::post('/upload/delete', function () {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::verifyCsrf()) { echo json_encode(['success' => false]); exit; }
    $id = (int)($_POST['id'] ?? 0);
    $file = Upload::find($id);
    if (!$file) { echo json_encode(['success' => false]); exit; }
    // 게시글 작성자 또는 관리자만 삭제 가능
    $post = Post::find($file['post_id']);
    if ($post && ($post['member_id'] === Auth::id() || Auth::isAdmin())) {
        Upload::delete($id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
});

<?php
/**
 * NuriBoard - 메인 웹 라우트
 * 메인, 로그인, 로그아웃, 약관, 비밀번호 찾기/재설정, 회원가입 관련 라우트
 */

// 파비콘 (구글봇 등 크롤러가 /favicon.ico 로 직접 접근할 때 서빙)
Router::get('/favicon.ico', function () {
    $faviconPath = nb_setting('site_favicon');
    if ($faviconPath && file_exists(NB_ROOT . '/' . $faviconPath)) {
        $ext  = strtolower(pathinfo($faviconPath, PATHINFO_EXTENSION));
        $mime = ['ico'=>'image/x-icon','png'=>'image/png','svg'=>'image/svg+xml',
                 'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'][$ext] ?? 'image/x-icon';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile(NB_ROOT . '/' . $faviconPath);
    } else {
        http_response_code(404);
    }
    exit;
});

// 메인
Router::get('/', function () {
    Router::loadTheme('main');
});

// 통합검색
Router::get('/search', function () {
    $q = trim($_GET['q'] ?? '');
    $stype = $_GET['stype'] ?? 'title_content';
    $sop = ($_GET['sop'] ?? 'and') === 'or' ? 'OR' : 'AND';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $results = [];
    $total = 0;
    if ($q) {
        $prefix = DB::getPrefix();
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $words = preg_split('/\s+/', $q);
        $where = [];
        $params = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') continue;
            switch ($stype) {
                case 'title':
                    $where[] = "p.title LIKE ?";
                    $params[] = "%{$w}%";
                    break;
                case 'content':
                    $where[] = "p.content LIKE ?";
                    $params[] = "%{$w}%";
                    break;
                case 'writer':
                    $where[] = "m.nickname LIKE ?";
                    $params[] = "%{$w}%";
                    break;
                default:
                    $where[] = "(p.title LIKE ? OR p.content LIKE ?)";
                    $params[] = "%{$w}%";
                    $params[] = "%{$w}%";
                    break;
            }
        }
        $whereStr = implode(" {$sop} ", $where);
        if ($whereStr) {
            $totalRow = DB::fetch("SELECT COUNT(*) as cnt FROM {$prefix}posts p LEFT JOIN {$prefix}members m ON p.member_id = m.id WHERE {$whereStr}", $params);
            $total = (int)($totalRow['cnt'] ?? 0);
            $results = DB::fetchAll("SELECT p.*, m.nickname as writer_name, m.level as writer_level, b.title as board_title FROM {$prefix}posts p LEFT JOIN {$prefix}members m ON p.member_id = m.id LEFT JOIN {$prefix}boards b ON p.board_id = b.board_id WHERE {$whereStr} ORDER BY p.id DESC LIMIT {$perPage} OFFSET {$offset}", $params);
        }

        $prefix2 = DB::getPrefix();
        $existing = DB::fetch("SELECT id, count FROM {$prefix2}search_keywords WHERE keyword = ?", [$q]);
        if ($existing) {
            DB::query("UPDATE {$prefix2}search_keywords SET count = count + 1, searched_at = NOW() WHERE id = ?", [$existing['id']]);
        } else {
            DB::insert($prefix2 . 'search_keywords', ['keyword' => $q, 'count' => 1, 'searched_at' => date('Y-m-d H:i:s')]);
        }
    }
    SEO::setTitle($q ? $q . ' - 검색결과' : '통합검색');
    Router::loadTheme('search', ['q' => $q, 'stype' => $stype, 'sop' => $sop === 'OR' ? 'or' : 'and', 'results' => $results, 'total' => $total, 'page' => $page]);
});

// 로그인
Router::get('/login', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    Router::loadTheme('member/login');
});

Router::post('/login', function () {
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $result = Auth::login($_POST['user_id'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        // 항상 자동로그인 토큰 저장 (로그인 유지)
        Auth::setRememberToken(Auth::id());
        Point::onLogin(Auth::id());
        Level::checkAndUpgrade(Auth::id());
        $redirect = $_POST['redirect'] ?? nb_url('/');
        // 외부 URL 리다이렉트 방지
        if (strpos($redirect, '/') !== 0 && strpos($redirect, nb_setting('site_url')) !== 0) {
            $redirect = nb_url('/');
        }
        Router::redirect($redirect);
    } else {
        Router::loadTheme('member/login', ['error' => $result['message']]);
    }
});

// 로그아웃
Router::get('/logout', function () {
    Auth::logout();
    Router::redirect(nb_url('/'));
});

// 이용약관
Router::get('/terms', function () {
    SEO::setTitle('이용약관');
    Router::loadTheme('member/terms');
});
// 개인정보처리방침
Router::get('/privacy', function () {
    SEO::setTitle('개인정보처리방침');
    Router::loadTheme('member/privacy');
});

// 비밀번호 찾기 (요청)
Router::get('/forgot', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    Router::loadTheme('member/forgot');
});

Router::post('/forgot', function () {
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $input = trim($_POST['user_id_or_email'] ?? '');
    $member = Member::findByUserId($input) ?? Member::findByEmail($input);

    if (!$member || empty($member['email'])) {
        Router::loadTheme('member/forgot', ['error' => '일치하는 계정 또는 이메일을 찾을 수 없습니다.']);
        return;
    }

    $token = Member::createResetToken($member['id']);
    $resetUrl = nb_setting('site_url', '') . Router::url("reset?token={$token}");
    $siteName = nb_setting('site_title', 'NuriBoard');

    $subject = "[{$siteName}] 비밀번호 재설정 안내";
    $body = "{$member['nickname']}님, 안녕하세요.\n\n"
          . "비밀번호 재설정 링크입니다 (1시간 유효):\n{$resetUrl}\n\n"
          . "본인이 요청하지 않은 경우 이 메일을 무시하세요.\n\n{$siteName}";
    $headers = "From: noreply@" . parse_url(nb_setting('site_url', 'http://localhost'), PHP_URL_HOST) . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    mail($member['email'], $subject, $body, $headers);

    Router::loadTheme('member/forgot', ['success' => '입력하신 이메일로 재설정 링크를 발송했습니다.']);
});

// 비밀번호 재설정 (실행)
Router::get('/reset', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    $token = trim($_GET['token'] ?? '');
    if (!$token || !Member::findByResetToken($token)) {
        Router::loadTheme('member/forgot', ['error' => '유효하지 않거나 만료된 링크입니다.']);
        return;
    }
    Router::loadTheme('member/reset', ['token' => $token]);
});

Router::post('/reset', function () {
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    $token = trim($_POST['token'] ?? '');
    $row = $token ? Member::findByResetToken($token) : null;
    if (!$row) {
        Router::loadTheme('member/forgot', ['error' => '유효하지 않거나 만료된 링크입니다.']);
        return;
    }
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';
    if (strlen($pw) < 6) {
        Router::loadTheme('member/reset', ['token' => $token, 'error' => '비밀번호는 6자 이상이어야 합니다.']);
        return;
    }
    if ($pw !== $pw2) {
        Router::loadTheme('member/reset', ['token' => $token, 'error' => '비밀번호가 일치하지 않습니다.']);
        return;
    }
    Member::update($row['member_id'], ['password' => $pw]);
    Member::deleteResetToken($token);
    Router::loadTheme('member/login', ['success' => '비밀번호가 변경되었습니다. 새 비밀번호로 로그인하세요.']);
});

// 회원가입
Router::get('/register', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    if (nb_setting('signup_enabled', '1') !== '1') {
        Router::loadTheme('member/register', ['error' => '회원가입이 중지되었습니다.']);
        return;
    }
    Router::loadTheme('member/register');
});

Router::post('/register', function () {
    if (!Auth::verifyCsrf()) die('잘못된 요청입니다.');
    if (nb_setting('signup_enabled', '1') !== '1') {
        Router::loadTheme('member/register', ['error' => '회원가입이 중지되었습니다.']);
        return;
    }
    $result = Member::register([
        'user_id' => trim($_POST['user_id'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'nickname' => trim($_POST['nickname'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ]);
    if ($result['success']) {
        // 프로필 이미지 업로드
        if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = NB_ROOT . '/uploads/profile';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $newName = 'pf_' . $result['id'] . '_' . time() . '.' . $ext;
                $savePath = $dir . '/' . $newName;
                move_uploaded_file($_FILES['profile_image']['tmp_name'], $savePath);
                // webp 변환
                if ($ext !== 'webp' && function_exists('imagewebp')) {
                    $webpPath = Upload::convertToWebpPublic($savePath, $ext);
                    if ($webpPath) {
                        $newName = preg_replace('/\.[^.]+$/', '.webp', $newName);
                    }
                }
                $path = 'uploads/profile/' . $newName;
                Member::update($result['id'], ['profile_image' => $path]);
            }
        }
        Auth::login($_POST['user_id'], $_POST['password']);
        Router::redirect(nb_url('/'));
    } else {
        Router::loadTheme('member/register', ['error' => $result['message']]);
    }
});

// 공개 플러그인 마켓
Router::get('/market', function () {
    $prefix = DB::getPrefix();
    try {
        $plugins = DB::fetchAll("SELECT id, name, description, version, author, thumbnail, price, downloads, access_tier, category, created_at FROM {$prefix}market_plugins WHERE is_active = 1 ORDER BY id DESC");
    } catch (Exception $e) {
        $plugins = [];
    }
    SEO::setTitle('플러그인 마켓');
    Router::loadTheme('market', ['plugins' => $plugins]);
});

// 설치 가이드
Router::get('/guide', function () {
    SEO::setTitle('설치 가이드');
    SEO::setDescription('누리보드 설치 방법을 단계별로 안내합니다. 5분이면 완료!');
    Router::loadTheme('guide');
});

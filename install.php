<?php
/**
 * NuriBoard v3.0.0 설치 마법사
 * 3단계: DB 연결 → 관리자 생성 → 완료
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 이미 설치된 경우
$installed = file_exists(__DIR__ . '/config/config.php');

// AJAX 설치 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['action'] === 'install') {
        try {
            $dbHost = trim($_POST['db_host'] ?? 'localhost');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';
            $dbName = trim($_POST['db_name'] ?? '');
            $dbPrefix = 'nb_';
            $adminId = trim($_POST['admin_id'] ?? 'admin');
            $adminPw = $_POST['admin_pw'] ?? '';
            $adminPw2 = $_POST['admin_pw2'] ?? '';
            $siteName = trim($_POST['site_name'] ?? 'NuriBoard');

            // 사이트 URL 자동 감지
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $siteUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];

            if (empty($dbUser) || empty($dbName)) throw new Exception('DB 정보를 입력하세요.');
            if (empty($adminId) || empty($adminPw)) throw new Exception('관리자 아이디와 비밀번호를 입력하세요.');
            if (mb_strlen($adminPw) < 4) throw new Exception('비밀번호를 4자 이상 입력하세요.');
            if ($adminPw !== $adminPw2) throw new Exception('비밀번호가 일치하지 않습니다.');

            // DB 연결
            $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // DB 생성 (없으면)
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            // ===== 테이블 생성 (각각 개별 실행) =====
            $p = $dbPrefix;

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                nickname VARCHAR(50) NOT NULL,
                email VARCHAR(100) DEFAULT '',
                profile_image VARCHAR(255) DEFAULT '',
                level TINYINT DEFAULT 2,
                point INT DEFAULT 0,
                warnings INT DEFAULT 0,
                ban_until DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME NULL,
                is_admin TINYINT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}boards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                board_id VARCHAR(50) UNIQUE NOT NULL,
                title VARCHAR(100) NOT NULL,
                description TEXT,
                board_type VARCHAR(20) DEFAULT 'normal',
                categories VARCHAR(500) DEFAULT '',
                list_count INT DEFAULT 20,
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1,
                write_level TINYINT DEFAULT 2,
                comment_level TINYINT DEFAULT 2,
                allow_delete TINYINT DEFAULT 1,
                allow_comment_delete TINYINT DEFAULT 1,
                point_write_cost INT DEFAULT 0,
                allow_paid_file TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                board_id VARCHAR(50) NOT NULL,
                member_id INT NOT NULL,
                category VARCHAR(50) DEFAULT '',
                title VARCHAR(255) NOT NULL,
                content LONGTEXT NOT NULL,
                slug VARCHAR(255) DEFAULT '',
                hit INT DEFAULT 0,
                comment_count INT DEFAULT 0,
                is_notice TINYINT DEFAULT 0,
                is_secret TINYINT DEFAULT 0,
                is_hidden TINYINT DEFAULT 0,
                link1 VARCHAR(500) DEFAULT '',
                link2 VARCHAR(500) DEFAULT '',
                tags VARCHAR(500) DEFAULT '',
                title_color VARCHAR(10) DEFAULT '',
                title_bg VARCHAR(10) DEFAULT '',
                vote_up INT DEFAULT 0,
                vote_down INT DEFAULT 0,
                adopted_comment_id INT DEFAULT NULL,
                adopted_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_board (board_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                member_id INT NOT NULL,
                parent_id INT DEFAULT 0,
                content TEXT NOT NULL,
                is_hidden TINYINT DEFAULT 0,
                is_adopted TINYINT DEFAULT 0,
                adopted_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_post (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}attachments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                orig_name VARCHAR(255) NOT NULL,
                file_size INT DEFAULT 0,
                file_type VARCHAR(50) DEFAULT '',
                is_image TINYINT DEFAULT 0,
                download_point INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_post (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}points (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                point INT DEFAULT 0,
                reason VARCHAR(200) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_member (member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}votes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                member_id INT NOT NULL,
                vote_type TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_vote (post_id, member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}menus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT DEFAULT 0,
                title VARCHAR(100) NOT NULL,
                link VARCHAR(500) DEFAULT '',
                board_id VARCHAR(50) DEFAULT '',
                target VARCHAR(10) DEFAULT '',
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1,
                color VARCHAR(10) DEFAULT '',
                badge VARCHAR(20) DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}banners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                position VARCHAR(20) DEFAULT 'main',
                title VARCHAR(100) DEFAULT '',
                image VARCHAR(500) NOT NULL,
                link VARCHAR(500) DEFAULT '',
                target VARCHAR(10) DEFAULT '_blank',
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}member_warnings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                admin_id INT NOT NULL DEFAULT 0,
                reason VARCHAR(255) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_member (member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                content TEXT NOT NULL,
                is_read TINYINT DEFAULT 0,
                sender_deleted TINYINT DEFAULT 0,
                receiver_deleted TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_receiver (receiver_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}admin_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                target_type VARCHAR(20) DEFAULT '',
                target_id INT DEFAULT 0,
                detail VARCHAR(500) DEFAULT '',
                ip VARCHAR(45) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}social_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                provider VARCHAR(20) NOT NULL,
                provider_id VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_provider (provider, provider_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}levels (
                level INT NOT NULL PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                icon VARCHAR(500) DEFAULT '',
                icon_type VARCHAR(10) DEFAULT 'emoji',
                min_point INT DEFAULT 0,
                min_posts INT DEFAULT 0,
                min_comments INT DEFAULT 0,
                can_write TINYINT DEFAULT 1,
                can_upload TINYINT DEFAULT 1,
                can_comment TINYINT DEFAULT 1,
                description VARCHAR(200) DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}remember_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                message VARCHAR(200) DEFAULT '',
                attend_date DATE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_member_date (member_id, attend_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}bookmarks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                post_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_member_post (member_id, post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}follows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                follower_id INT NOT NULL,
                target_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_follow (follower_id, target_id),
                INDEX idx_target (target_id),
                INDEX idx_follower (follower_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                type VARCHAR(30) NOT NULL,
                message VARCHAR(500) NOT NULL,
                link VARCHAR(500) DEFAULT '',
                is_read TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_member_read (member_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}file_purchases (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                attachment_id INT NOT NULL,
                point INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_member_file (member_id, attachment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                api_key VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(100) DEFAULT '',
                is_active TINYINT DEFAULT 1,
                request_count INT DEFAULT 0,
                last_used_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_key (api_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}mobile_banners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                image VARCHAR(500) NOT NULL,
                link VARCHAR(500) DEFAULT '',
                target VARCHAR(10) DEFAULT '_blank',
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}mobile_bottombar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(100) NOT NULL,
                icon VARCHAR(200) DEFAULT '',
                link VARCHAR(500) DEFAULT '',
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // ===== 관리자 계정 =====
            $stmt = $pdo->prepare("INSERT INTO {$p}members (user_id, password, nickname, email, profile_image, level, point, warnings, is_admin, created_at) VALUES (?, ?, ?, '', '', 10, 0, 0, 1, NOW())");
            $stmt->execute([$adminId, password_hash($adminPw, PASSWORD_BCRYPT), $adminId]);

            // ===== 기본 설정 =====
            $settings = [
                'site_title' => $siteName,
                'site_description' => $siteName . ' 커뮤니티',
                'site_url' => $siteUrl,
                'site_keywords' => '커뮤니티,게시판,' . $siteName,
                'theme' => 'default',
                'posts_per_page' => '20',
                'comments_per_page' => '50',
                'signup_enabled' => '1',
                'point_write' => '10',
                'point_comment' => '5',
                'point_login' => '3',
                'upload_max_size' => '10',
                'upload_extensions' => 'jpg,jpeg,png,gif,webp,pdf,zip,hwp,doc,docx,xls,xlsx,ppt,pptx,txt',
                'site_logo' => '',
                'site_favicon' => '',
                'nuri_version' => '3.0.0',
                // 누리보드 마켓 연동 고유 토큰 (플러그인 구매 식별)
                'market_site_token' => bin2hex(random_bytes(32)),
            ];
            $stmt = $pdo->prepare("INSERT INTO {$p}settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            // ===== 기본 게시판 (10개) =====
            $bStmt = $pdo->prepare("INSERT INTO {$p}boards (board_id, title, description, board_type, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            // 자유게시판 하위
            $bStmt->execute(['hello',     '가입인사',   '새로운 분들의 인사를 환영해요',        'normal',  1]);
            $bStmt->execute(['review',    '후기게시판', '사용 후기를 솔직하게 공유해요',        'normal',  2]);
            // 이미지게시판 하위
            $bStmt->execute(['cert',      '인증샷',     '자랑스러운 인증샷을 올려요',           'gallery', 3]);
            $bStmt->execute(['imgupload', '자유업로드', '자유롭게 이미지를 올려요',             'gallery', 4]);
            // 공지사항 하위
            $bStmt->execute(['notice_update',      '업데이트소식', '새로운 업데이트 소식을 알려드려요', 'normal', 5]);
            $bStmt->execute(['notice_event',       '이벤트공지',   '이벤트 및 혜택을 안내해요',         'normal', 6]);
            $bStmt->execute(['notice_maintenance', '점검안내',     '서버 점검 일정을 공지해요',          'normal', 7]);
            $bStmt->execute(['faq',                'FAQ',          '자주 묻는 질문을 모아뒀어요',       'normal', 8]);
            // 질문답변 하위
            $bStmt->execute(['qna_error',  '오류문의', '오류나 문제를 문의해요',          'normal', 9]);
            $bStmt->execute(['qna_howto',  '사용방법', '기능 사용 방법이 궁금할 때',      'normal', 10]);

            // ===== 회원등급 이미지 매핑 (INSERT 시점에 바로 반영) =====
            $lvImgs = [
                1  => 'uploads/levels/lv1_1775723126.png',
                2  => 'uploads/levels/lv2_1775723203.png',
                3  => 'uploads/levels/lv3_1775723399.png',
                4  => 'uploads/levels/lv4_1775723511.png',
                5  => 'uploads/levels/lv5_1775723928.png',
                6  => 'uploads/levels/lv6_1775723941.png',
                7  => 'uploads/levels/lv7_1775723989.png',
                8  => 'uploads/levels/lv8_1775724031.png',
                9  => 'uploads/levels/lv9_1775724082.png',
                10 => 'uploads/levels/lv10_1775724116.png',
            ];

            // ===== 기본 레벨 =====
            $lvStmt = $pdo->prepare("INSERT IGNORE INTO {$p}levels (level, name, icon, icon_type, min_point, min_posts, min_comments, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $defaultLevels = [
                [1, '방문자', 0, 0, 0, '갓 가입한 회원'],
                [2, '입문러', 30, 3, 5, '활동을 시작한 회원'],
                [3, '활동러', 100, 10, 20, '꾸준히 활동하는 회원'],
                [4, '실행러', 300, 30, 60, '활발한 회원'],
                [5, '성장러', 700, 70, 150, '핵심 회원'],
                [6, '실전러', 1500, 150, 300, '베테랑 회원'],
                [7, '최적화러', 3000, 300, 600, '고인물 회원'],
                [8, '전략가', 6000, 600, 1200, '전략적 회원'],
                [9, '마스터', 12000, 1200, 2500, '마스터 회원'],
                [10, '레전드', 20000, 2000, 5000, '전설의 회원'],
            ];
            foreach ($defaultLevels as $lv) {
                $levelNum = $lv[0];
                $img = $lvImgs[$levelNum] ?? '';
                $icon = '';
                $iconType = 'emoji';
                if ($img !== '' && file_exists(__DIR__ . '/' . $img)) {
                    $icon = $img;
                    $iconType = 'image';
                }
                $lvStmt->execute([$lv[0], $lv[1], $icon, $iconType, $lv[2], $lv[3], $lv[4], $lv[5]]);
            }

            // ===== 기본 메뉴 등록 =====
            // 부모 메뉴 (board_id 없음 → 클릭 시 드롭다운만 열림)
            $pMenuStmt = $pdo->prepare("INSERT INTO {$p}menus (parent_id, title, link, board_id, sort_order, is_active) VALUES (0, ?, '', '', ?, 1)");
            $pMenuStmt->execute(['자유게시판',  1]); $freeMenuId   = $pdo->lastInsertId();
            $pMenuStmt->execute(['이미지게시판', 2]); $imgMenuId    = $pdo->lastInsertId();
            $pMenuStmt->execute(['공지사항',    3]); $noticeMenuId = $pdo->lastInsertId();
            $pMenuStmt->execute(['질문답변',    4]); $qnaMenuId    = $pdo->lastInsertId();

            // 자식 메뉴
            $cMenuStmt = $pdo->prepare("INSERT INTO {$p}menus (parent_id, title, link, board_id, sort_order, is_active) VALUES (?, ?, '', ?, ?, 1)");
            // 자유게시판 하위
            $cMenuStmt->execute([$freeMenuId, '가입인사',   'hello',  1]);
            $cMenuStmt->execute([$freeMenuId, '후기게시판', 'review', 2]);
            // 이미지게시판 하위
            $cMenuStmt->execute([$imgMenuId, '인증샷',     'cert',      1]);
            $cMenuStmt->execute([$imgMenuId, '자유업로드', 'imgupload', 2]);
            // 공지사항 하위
            $cMenuStmt->execute([$noticeMenuId, '업데이트소식', 'notice_update',      1]);
            $cMenuStmt->execute([$noticeMenuId, '이벤트공지',   'notice_event',       2]);
            $cMenuStmt->execute([$noticeMenuId, '점검안내',     'notice_maintenance', 3]);
            $cMenuStmt->execute([$noticeMenuId, 'FAQ',          'faq',                4]);
            // 질문답변 하위
            $cMenuStmt->execute([$qnaMenuId, '오류문의', 'qna_error',  1]);
            $cMenuStmt->execute([$qnaMenuId, '사용방법', 'qna_howto',  2]);

            // ===== 데모 게시글 + 댓글 =====
            $postStmt = $pdo->prepare("INSERT INTO {$p}posts (board_id, member_id, title, content, hit, comment_count, vote_up, created_at, updated_at) VALUES (?, 1, ?, ?, ?, ?, 0, NOW(), NOW())");
            $cmtStmt = $pdo->prepare("INSERT INTO {$p}comments (post_id, member_id, content, created_at) VALUES (?, 1, ?, NOW())");

            // 업데이트소식 (공지)
            $postStmt->execute(['notice_update', '누리보드에 오신 것을 환영합니다!', '<p>안녕하세요! 누리보드(NuriBoard) 커뮤니티에 오신 것을 진심으로 환영합니다.</p><p>누리보드는 누구나 쉽게 커뮤니티를 만들 수 있는 한국형 CMS입니다.</p><p>궁금한 점이 있으시면 초보질문 게시판을 이용해주세요!</p>', rand(50,200), 2]);
            $noticeId = $pdo->lastInsertId();
            $pdo->exec("UPDATE {$p}posts SET is_notice = 1 WHERE id = {$noticeId}");
            $cmtStmt->execute([$noticeId, '환영합니다! 좋은 커뮤니티가 되길 바랍니다.']);
            $cmtStmt->execute([$noticeId, '누리보드 기대됩니다!']);

            // 가입인사
            $postStmt->execute(['hello', '안녕하세요, 잘 부탁드립니다!', '<p>처음 가입했습니다. 앞으로 잘 부탁드려요 :)</p>', rand(30,150), 2]);
            $helloId1 = $pdo->lastInsertId();
            $cmtStmt->execute([$helloId1, '환영합니다! 반가워요 ^^']);
            $cmtStmt->execute([$helloId1, '잘 오셨어요~!']);

            // 오류문의
            $postStmt->execute(['qna_error', '게시판은 어떻게 추가하나요?', '<p>관리자 페이지에서 게시판을 추가하고 싶습니다.</p><p>방법을 알려주세요!</p>', rand(30,100), 1]);
            $qnaId1 = $pdo->lastInsertId();
            $cmtStmt->execute([$qnaId1, '관리자 > 게시판 관리에서 추가할 수 있습니다!']);

            $postStmt->execute(['qna_howto', '플러그인은 어디서 설치하나요?', '<p>플러그인을 설치하고 싶은데 방법이 궁금합니다.</p>', rand(20,80), 1]);
            $qnaId2 = $pdo->lastInsertId();
            $cmtStmt->execute([$qnaId2, '관리자 > 플러그인에서 ZIP 파일로 설치할 수 있어요!']);

            // ===== 히어로 배너 설정 =====
            if (file_exists(__DIR__ . '/uploads/site/hero_1775714987.png')) {
                $addSetting = $pdo->prepare("INSERT INTO {$p}settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $addSetting->execute(['main_hero_enabled', '1']);
                $addSetting->execute(['main_hero_type', 'single']);
                $addSetting->execute(['main_hero_image', 'uploads/site/hero_1775714987.png']);
                $addSetting->execute(['main_hero_title', '']);
                $addSetting->execute(['main_hero_desc', '']);
                $addSetting->execute(['main_hero_link', '']);
            }

            // ===== 이미지 게시판 데모 이미지 (인증샷 게시판에 등록) =====
            $imgFiles = glob(__DIR__ . '/uploads/2026/04/img_*.webp');
            $imgCount = 0;
            foreach ($imgFiles as $imgFile) {
                $imgCount++;
                $imgPath = 'uploads/2026/04/' . basename($imgFile);
                $pdo->exec("INSERT INTO {$p}posts (board_id, member_id, title, content, hit, comment_count, vote_up, created_at, updated_at) VALUES ('cert', 1, '인증샷 {$imgCount}', '<p>인증샷 샘플 이미지입니다.</p>', " . rand(10,100) . ", 0, 0, NOW(), NOW())");
                $imgPostId = $pdo->lastInsertId();
                $pdo->exec("INSERT INTO {$p}attachments (post_id, file_name, orig_name, file_size, file_type, is_image, created_at) VALUES ({$imgPostId}, '{$imgPath}', 'image{$imgCount}.webp', 0, 'webp', 1, NOW())");
            }

            // ===== 기본 띠공지 =====
            $addSetting = $pdo->prepare("INSERT INTO {$p}settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $addSetting->execute(['ticker_enabled', '1']);
            $addSetting->execute(['ticker_text', $siteName . '에 오신 것을 환영합니다! 자유롭게 소통하고 정보를 공유하세요.']);
            $addSetting->execute(['ticker_bg_color', '#ec4899']);
            $addSetting->execute(['ticker_text_color', '#ffffff']);
            $addSetting->execute(['ticker_effect', 'scroll-left']);

            // ===== 메인 섹션 기본값 =====
            $addSetting->execute(['main_section_popular', '1']);
            $addSetting->execute(['main_section_boards', '1']);
            $addSetting->execute(['main_section_stats', '1']);
            $addSetting->execute(['main_section_notice', '1']);
            $addSetting->execute(['main_section_bestmember', '1']);
            $addSetting->execute(['main_section_attendance', '1']);
            $addSetting->execute(['main_section_recentcomments', '1']);
            $addSetting->execute(['main_section_latestlist', '1']);
            $addSetting->execute(['main_section_cta', '1']);
            $addSetting->execute(['main_section_gallery', '1']);
            $addSetting->execute(['main_hero_enabled', '0']);
            $addSetting->execute(['social_login_enabled', '0']);
            $addSetting->execute(['point_attendance', '5']);
            $addSetting->execute(['point_attendance_bonus', '20']);

            // ===== 누리코리아 알림 플러그인 자동 활성화 (필수 플러그인) =====
            $addSetting->execute(['plugin_nurikorea-announcements_enabled', '1']);

            // ===== config.php 생성 =====
            if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0755, true);
            $configContent = "<?php\nreturn [\n";
            $configContent .= "    'db_host' => " . var_export($dbHost, true) . ",\n";
            $configContent .= "    'db_user' => " . var_export($dbUser, true) . ",\n";
            $configContent .= "    'db_pass' => " . var_export($dbPass, true) . ",\n";
            $configContent .= "    'db_name' => " . var_export($dbName, true) . ",\n";
            $configContent .= "    'db_prefix' => 'nb_',\n";
            $configContent .= "    'site_url' => " . var_export($siteUrl, true) . ",\n";
            $configContent .= "    'secret_key' => " . var_export(bin2hex(random_bytes(32)), true) . ",\n";
            $configContent .= "];\n";
            file_put_contents(__DIR__ . '/config/config.php', $configContent);

            // ===== 디렉토리 생성 =====
            foreach (['uploads', 'uploads/site', 'uploads/profile', 'uploads/levels', 'data', 'data/cache', 'plugins'] as $dir) {
                if (!is_dir(__DIR__ . '/' . $dir)) mkdir(__DIR__ . '/' . $dir, 0755, true);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>누리보드 설치</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans KR', sans-serif; background: #f0f2f5; color: #333; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .install-box { background: #fff; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 8px 32px rgba(0,0,0,.08); overflow: hidden; margin: 20px; }
        .install-header { background: linear-gradient(135deg, #1e293b, #334155); color: #fff; padding: 32px; text-align: center; }
        .install-header h1 { font-size: 28px; font-weight: 800; }
        .install-header p { font-size: 14px; color: #94a3b8; margin-top: 8px; }
        .install-body { padding: 32px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .form-group small { display: block; margin-top: 4px; color: #94a3b8; font-size: 12px; }
        .btn { display: block; width: 100%; padding: 12px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .btn:disabled { background: #94a3b8; cursor: not-allowed; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
        .alert.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert.success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .step { display: none; }
        .step.active { display: block; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
    </style>
</head>
<body>
<div class="install-box">
    <div class="install-header">
        <h1>NuriBoard</h1>
        <p>한국형 커뮤니티 CMS v3.0.0</p>
    </div>
    <div class="install-body">
        <?php if ($installed): ?>
            <div class="alert success">이미 설치가 완료되었습니다.<br>재설치하려면 config/config.php 파일을 삭제하세요.</div>
            <a href="./" class="btn" style="text-decoration:none;text-align:center">사이트로 이동</a>
        <?php else: ?>

        <div id="msg"></div>

        <!-- Step 1: DB -->
        <div class="step active" id="step1">
            <h3 style="font-size:16px;margin-bottom:16px">1단계: 데이터베이스 연결</h3>
            <div class="form-group">
                <label>DB 호스트</label>
                <input type="text" id="db_host" value="localhost">
            </div>
            <div class="form-group">
                <label>DB 이름</label>
                <input type="text" id="db_name" placeholder="데이터베이스 이름">
            </div>
            <div class="form-group">
                <label>DB 사용자</label>
                <input type="text" id="db_user" placeholder="데이터베이스 사용자">
            </div>
            <div class="form-group">
                <label>DB 비밀번호</label>
                <input type="password" id="db_pass" placeholder="데이터베이스 비밀번호">
            </div>
            <small style="color:#94a3b8;display:block;margin-bottom:16px">※ 닷홈/카페24 등 호스팅은 제공받은 DB 정보를 입력하세요</small>
            <button class="btn" onclick="goStep2()">다음 →</button>
        </div>

        <!-- Step 2: Admin -->
        <div class="step" id="step2">
            <h3 style="font-size:16px;margin-bottom:16px">2단계: 관리자 계정</h3>
            <div class="form-group">
                <label>관리자 아이디</label>
                <input type="text" id="admin_id" value="admin">
            </div>
            <div class="form-group">
                <label>관리자 비밀번호</label>
                <input type="password" id="admin_pw" placeholder="4자 이상">
            </div>
            <div class="form-group">
                <label>비밀번호 확인</label>
                <input type="password" id="admin_pw2" placeholder="비밀번호 다시 입력">
            </div>
            <div class="form-group">
                <label>사이트 이름</label>
                <input type="text" id="site_name" value="NuriBoard">
            </div>
            <button class="btn" id="installBtn" onclick="doInstall()">설치하기</button>
        </div>

        <!-- Step 3: Done -->
        <div class="step" id="step3">
            <div style="text-align:center;padding:20px 0">
                <div style="font-size:48px;margin-bottom:16px">✅</div>
                <h3 style="font-size:18px;margin-bottom:8px">설치가 완료되었습니다!</h3>
                <p style="font-size:14px;color:#64748b;margin-bottom:24px">누리보드가 성공적으로 설치되었습니다.</p>
                <a href="./" class="btn" style="text-decoration:none;text-align:center;display:block;margin-bottom:10px">사이트로 이동</a>
                <a href="./admin/" class="btn" style="text-decoration:none;text-align:center;display:block;background:#1e293b">관리자 페이지</a>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
function goStep2() {
    if (!document.getElementById('db_name').value || !document.getElementById('db_user').value) {
        showMsg('DB 이름과 사용자를 입력하세요.', 'error');
        return;
    }
    document.getElementById('step1').classList.remove('active');
    document.getElementById('step2').classList.add('active');
    document.getElementById('msg').innerHTML = '';
}

function doInstall() {
    var pw = document.getElementById('admin_pw').value;
    var pw2 = document.getElementById('admin_pw2').value;
    if (pw.length < 4) { showMsg('비밀번호를 4자 이상 입력하세요.', 'error'); return; }
    if (pw !== pw2) { showMsg('비밀번호가 일치하지 않습니다.', 'error'); return; }

    var btn = document.getElementById('installBtn');
    btn.disabled = true;
    btn.textContent = '설치 중...';
    document.getElementById('msg').innerHTML = '';

    var fd = new FormData();
    fd.append('action', 'install');
    fd.append('db_host', document.getElementById('db_host').value);
    fd.append('db_name', document.getElementById('db_name').value);
    fd.append('db_user', document.getElementById('db_user').value);
    fd.append('db_pass', document.getElementById('db_pass').value);
    fd.append('admin_id', document.getElementById('admin_id').value);
    fd.append('admin_pw', pw);
    fd.append('admin_pw2', pw2);
    fd.append('site_name', document.getElementById('site_name').value);

    fetch('install.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                document.getElementById('step2').classList.remove('active');
                document.getElementById('step3').classList.add('active');
            } else {
                showMsg(res.message || '설치 실패', 'error');
                btn.disabled = false;
                btn.textContent = '설치하기';
            }
        })
        .catch(function(e) {
            showMsg('오류: ' + e.message, 'error');
            btn.disabled = false;
            btn.textContent = '설치하기';
        });
}

function showMsg(msg, type) {
    document.getElementById('msg').innerHTML = '<div class="alert ' + type + '">' + msg + '</div>';
}
</script>
</body>
</html>

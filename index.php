<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * index.php - 메인 라우터
 */

// 보안 헤더
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

define('NB_ROOT', __DIR__);
define('NB_VERSION', '3.0.0');

// 설치 확인
if (!file_exists(NB_ROOT . '/config/config.php')) {
    header('Location: install.php');
    exit;
}

// 코어 로드
require_once NB_ROOT . '/core/DB.php';
require_once NB_ROOT . '/core/Router.php';
require_once NB_ROOT . '/core/Auth.php';
require_once NB_ROOT . '/core/Member.php';
require_once NB_ROOT . '/core/Board.php';
require_once NB_ROOT . '/core/Post.php';
require_once NB_ROOT . '/core/Comment.php';
require_once NB_ROOT . '/core/SEO.php';
require_once NB_ROOT . '/core/Upload.php';
require_once NB_ROOT . '/core/Point.php';
require_once NB_ROOT . '/core/Vote.php';
require_once NB_ROOT . '/core/Follow.php';
require_once NB_ROOT . '/core/Menu.php';
require_once NB_ROOT . '/core/Banner.php';
require_once NB_ROOT . '/core/MobileMenu.php';
require_once NB_ROOT . '/core/AdminLog.php';
require_once NB_ROOT . '/core/Message.php';
require_once NB_ROOT . '/core/Social.php';
require_once NB_ROOT . '/core/Level.php';
require_once NB_ROOT . '/core/Cache.php';
require_once NB_ROOT . '/core/Plugin.php';

// 세션 시작
Auth::init();
Auth::tryRememberLogin();
Cache::init();
Plugin::loadAll();

// 사이트 설정 로드
$prefix = DB::getPrefix();
$siteSettings = [];
$rows = DB::fetchAll("SELECT setting_key, setting_value FROM {$prefix}settings");
foreach ($rows as $row) {
    $siteSettings[$row['setting_key']] = $row['setting_value'];
}
define('NB_SETTINGS', $siteSettings);

// 자동 DB 업그레이드 (버전 비교)
if ((NB_SETTINGS['nuri_version'] ?? '1.0.0') !== NB_VERSION) {
    $pdo = DB::getInstance();
    try { $pdo->exec("ALTER TABLE {$prefix}boards ADD COLUMN categories VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN category VARCHAR(50) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}comments ADD COLUMN parent_id INT DEFAULT 0"); } catch (Exception $e) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}attachments (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, orig_name VARCHAR(255) NOT NULL, file_size INT DEFAULT 0, file_type VARCHAR(50) DEFAULT '', is_image TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_post (post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}points (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, point INT NOT NULL, reason VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.2.0: 추천, 비밀글, 태그, 링크
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN is_secret TINYINT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN link1 VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN link2 VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN tags VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN title_color VARCHAR(10) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN title_bg VARCHAR(10) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN vote_up INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN vote_down INT DEFAULT 0"); } catch (Exception $e) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}votes (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, member_id INT NOT NULL, vote_type TINYINT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_vote (post_id, member_id), INDEX idx_post (post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.3.0: 메뉴, 배너
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}menus (id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT DEFAULT 0, title VARCHAR(100) NOT NULL, link VARCHAR(500) DEFAULT '', board_id VARCHAR(50) DEFAULT '', target VARCHAR(10) DEFAULT '', sort_order INT DEFAULT 0, is_active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}banners (id INT AUTO_INCREMENT PRIMARY KEY, position VARCHAR(20) NOT NULL, title VARCHAR(100) DEFAULT '', image VARCHAR(500) NOT NULL, link VARCHAR(500) DEFAULT '', target VARCHAR(10) DEFAULT '_blank', sort_order INT DEFAULT 0, is_active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.4.0: 위젯 시스템
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}widgets (id INT AUTO_INCREMENT PRIMARY KEY, widget_type VARCHAR(30) NOT NULL, position VARCHAR(20) NOT NULL, title VARCHAR(100) DEFAULT '', config TEXT, sort_order INT DEFAULT 0, is_active TINYINT DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_position (position, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.5.0: 비밀번호 재설정
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}password_resets (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_token (token), INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.6.0: 신고
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN is_hidden TINYINT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}comments ADD COLUMN is_hidden TINYINT DEFAULT 0"); } catch (Exception $e) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}reports (id INT AUTO_INCREMENT PRIMARY KEY, type VARCHAR(10) NOT NULL COMMENT 'post|comment', target_id INT NOT NULL, reporter_id INT NOT NULL, reason VARCHAR(100) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected', resolved_by INT DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_report (type, target_id, reporter_id), INDEX idx_status (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.7.0: 회원 경고/정지 + 관리자 로그
    try { $pdo->exec("ALTER TABLE {$prefix}members ADD COLUMN warnings INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}members ADD COLUMN ban_until DATETIME DEFAULT NULL"); } catch (Exception $e) {}
    // v2.1.0: 프로필 이미지
    try { $pdo->exec("ALTER TABLE {$prefix}members ADD COLUMN profile_image VARCHAR(255) DEFAULT ''"); } catch (Exception $e) {}
    // v2.2.0: 게시판 타입 (normal/gallery)
    try { $pdo->exec("ALTER TABLE {$prefix}boards ADD COLUMN board_type VARCHAR(20) DEFAULT 'normal'"); } catch (Exception $e) {}
    // v3.0.1: 메뉴 색상
    try { $pdo->exec("ALTER TABLE {$prefix}menus ADD COLUMN color VARCHAR(10) DEFAULT ''"); } catch (Exception $e) {}
    // v2.3.0: 게시판 삭제 권한
    try { $pdo->exec("ALTER TABLE {$prefix}boards ADD COLUMN allow_delete TINYINT DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}boards ADD COLUMN allow_comment_delete TINYINT DEFAULT 1"); } catch (Exception $e) {}
    // v2.4.0: 게시판별 포인트 소모
    try { $pdo->exec("ALTER TABLE {$prefix}boards ADD COLUMN point_write_cost INT DEFAULT 0"); } catch (Exception $e) {}
    // v2.4.0: 자동로그인 토큰
    // v2.9.0: API 키
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}api_keys (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, api_key VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(100) DEFAULT '', is_active TINYINT DEFAULT 1, request_count INT DEFAULT 0, last_used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_key (api_key), INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v3.1.0: 플러그인 마켓 전용 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}market_plugins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        version VARCHAR(20) DEFAULT '1.0',
        author VARCHAR(100) DEFAULT '',
        thumbnail VARCHAR(500) DEFAULT '',
        zip_file VARCHAR(500) NOT NULL,
        zip_orig_name VARCHAR(200) DEFAULT '',
        zip_size INT DEFAULT 0,
        price INT DEFAULT 0,
        downloads INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v3.2.0: 마켓 플러그인 카테고리
    try { $pdo->exec("ALTER TABLE {$prefix}market_plugins ADD COLUMN category VARCHAR(50) DEFAULT ''"); } catch (Exception $e) {}
    // v2.8.0: 유료 첨부파일
    try { $pdo->exec("ALTER TABLE {$prefix}boards ADD COLUMN allow_paid_file TINYINT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}attachments ADD COLUMN download_point INT DEFAULT 0"); } catch (Exception $e) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}file_purchases (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, attachment_id INT NOT NULL, point INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_member_file (member_id, attachment_id), INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v2.7.0: 북마크
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}bookmarks (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, post_id INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_member_post (member_id, post_id), INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v2.7.0: 알림
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}notifications (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, type VARCHAR(30) NOT NULL, message VARCHAR(500) NOT NULL, link VARCHAR(500) DEFAULT '', is_read TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_member_read (member_id, is_read)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v2.6.0: 출석체크
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}attendance (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, message VARCHAR(200) DEFAULT '', attend_date DATE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_member_date (member_id, attend_date), INDEX idx_date (attend_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}remember_tokens (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_token (token), INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}member_warnings (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, admin_id INT NOT NULL DEFAULT 0, reason VARCHAR(255) DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.8.0: 쪽지
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}messages (id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL, title VARCHAR(200) NOT NULL, content TEXT NOT NULL, is_read TINYINT DEFAULT 0, sender_deleted TINYINT DEFAULT 0, receiver_deleted TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_receiver (receiver_id, is_read), INDEX idx_sender (sender_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}admin_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, action VARCHAR(50) NOT NULL, target_type VARCHAR(20) DEFAULT '', target_id INT DEFAULT 0, detail VARCHAR(500) DEFAULT '', ip VARCHAR(45) DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_admin (admin_id), INDEX idx_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v1.9.0: 소셜 로그인
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}social_accounts (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, provider VARCHAR(20) NOT NULL, provider_id VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_provider (provider, provider_id), INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v2.0.0: 회원 등급
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}levels (level INT NOT NULL PRIMARY KEY, name VARCHAR(50) NOT NULL, icon VARCHAR(500) DEFAULT '🌿', icon_type VARCHAR(10) DEFAULT 'emoji', min_point INT DEFAULT 0, min_posts INT DEFAULT 0, min_comments INT DEFAULT 0, can_write TINYINT DEFAULT 1, can_upload TINYINT DEFAULT 1, can_comment TINYINT DEFAULT 1, description VARCHAR(200) DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    Level::initDefaults();
    // v2.0.1: 등급 이름 업데이트 (이전 기본값 → 새 이름)
    $oldToNew = [
        1=>['방문자','👋'],2=>['입문러','📚'],3=>['활동러','⚡'],4=>['실행러','🔧'],
        5=>['성장러','📈'],6=>['실전러','⚔️'],7=>['최적화러','🎯'],8=>['전략가','♟️'],
        9=>['마스터','💎'],10=>['레전드','🏆'],
    ];
    $oldNames = ['씨앗','새싹','나뭇잎','꽃봉오리','별','나무','보석','왕관','불꽃','신화'];
    foreach ($oldToNew as $lv => [$name, $icon]) {
        $row = DB::fetch("SELECT name, icon FROM {$prefix}levels WHERE level = ?", [$lv]);
        if ($row && in_array($row['name'], $oldNames)) {
            DB::update("{$prefix}levels", ['name' => $name, 'icon' => $icon], 'level = ?', [$lv]);
        }
    }
    $newSettings = ['point_write'=>'10','point_comment'=>'5','point_login'=>'3','upload_max_size'=>'10','upload_extensions'=>'jpg,jpeg,png,gif,webp,pdf,zip,hwp,doc,docx,xls,xlsx,ppt,pptx,txt','site_logo'=>'','site_favicon'=>'','kakao_client_id'=>'','naver_client_id'=>'','naver_client_secret'=>''];
    foreach ($newSettings as $k => $v) {
        $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$k]);
        if (!$exists) DB::insert("{$prefix}settings", ['setting_key' => $k, 'setting_value' => $v]);
    }
    DB::update("{$prefix}settings", ['setting_value' => NB_VERSION], "setting_key = ?", ['nuri_version']);
}

// 헬퍼 함수
function nb_setting(string $key, string $default = ''): string
{
    return NB_SETTINGS[$key] ?? $default;
}

function nb_e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function nb_level_icon(int $level): string
{
    return Level::getIcon($level);
}

function nb_url(string $path = ''): string
{
    return Router::url($path);
}

function nb_asset(string $path): string
{
    $theme = nb_setting('theme', 'default');
    $file = NB_ROOT . "/theme/{$theme}/assets/{$path}";
    $ver = file_exists($file) ? filemtime($file) : time();
    return nb_url("theme/{$theme}/assets/{$path}") . "?v={$ver}";
}

function nb_avatar(string $nickname, string $profileImage = '', string $size = '40', string $class = ''): string
{
    if ($profileImage) {
        return '<img src="' . nb_url($profileImage) . '" alt="" class="nb-avatar ' . nb_e($class) . '" style="width:' . $size . 'px;height:' . $size . 'px">';
    }
    $letter = strtoupper(mb_substr($nickname, 0, 1));
    return '<span class="nb-avatar nb-avatar-text ' . nb_e($class) . '" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size * 0.4) . 'px">' . nb_e($letter) . '</span>';
}

/**
 * XSS 방지 HTML 정제 함수
 * 허용 태그 외 제거, style 속성의 안전한 CSS(color 등)는 그대로 보존
 */
function nb_purify(string $html): string
{
    if (empty($html)) return '';

    $allowedTags = ['p','br','b','i','u','s','strong','em','a','img','ul','ol','li',
        'blockquote','h1','h2','h3','h4','h5','h6',
        'table','thead','tbody','tr','th','td','colgroup','col',
        'hr','span','div','pre','code','figure','figcaption','sub','sup','iframe'];

    $attrsByTag = [
        'a'   => ['href','title','target','rel'],
        'img' => ['src','alt','width','height'],
        'td'  => ['colspan','rowspan'],
        'th'  => ['colspan','rowspan'],
        'col' => ['span'],
    ];

    // style 속성에서 허용할 CSS 프로퍼티
    $allowedCss = ['color','background-color','background','font-size','font-weight',
        'font-style','text-decoration','text-align','border','border-color',
        'border-style','border-width','border-collapse','width','height',
        'max-width','min-width','padding','margin','float','vertical-align','line-height',
        'display','position','top','right','bottom','left',
        'overflow','overflow-x','overflow-y',
        'aspect-ratio','border-radius','box-sizing',
        'flex','flex-direction','flex-wrap','align-items','justify-content','gap'];

    // <font color="..."> → <span style="color: ..."> 변환 (Summernote 구버전 출력 호환)
    $html = preg_replace_callback(
        '/<font\b([^>]*)>/i',
        function($m) {
            $color = '';
            if (preg_match('/\bcolor\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+(?=>|\s)))/i', $m[1], $cm)) {
                $color = trim($cm[1] ?: $cm[2] ?: $cm[3], '"\'');
            }
            return $color ? '<span style="color: ' . htmlspecialchars($color, ENT_QUOTES) . '">' : '<span>';
        },
        $html
    );
    $html = preg_replace('/<\/font>/i', '</span>', $html);

    return preg_replace_callback(
        '/<(\/?)([a-z][a-z0-9]*)(\s[^>]*)?\s*>/i',
        function ($m) use ($allowedTags, $attrsByTag, $allowedCss) {
            $slash = $m[1];
            $tag   = strtolower($m[2]);
            $attrs = isset($m[3]) ? $m[3] : '';

            // 허용 안 된 태그 → 제거 (텍스트 내용은 유지됨)
            if (!in_array($tag, $allowedTags)) return '';

            // 닫는 태그
            if ($slash) return "</$tag>";

            // 속성 없는 단독 태그
            if (in_array($tag, ['br', 'hr'])) return "<$tag>";

            // iframe: 유튜브·비메오만 허용
            if ($tag === 'iframe') {
                if (!preg_match('/\bsrc\s*=\s*"([^"]+)"/i', $attrs, $sm)) return '';
                $src = $sm[1];
                if (!preg_match('#^https?://(www\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com|player\.vimeo\.com)#i', $src)) return '';
                return '<iframe src="' . htmlspecialchars($src, ENT_QUOTES) . '" frameborder="0" allowfullscreen>';
            }

            // 허용 속성 목록 (style·class 전체 공통)
            $allowed = array_merge(['style', 'class'], $attrsByTag[$tag] ?? []);
            $safeAttrs = [];

            // 속성 파싱 (큰따옴표·작은따옴표 모두 처리)
            preg_match_all('/\b([\w-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $attrs, $am, PREG_SET_ORDER);
            foreach ($am as $a) {
                $name = strtolower($a[1]);
                $val  = (($a[2] ?? '') !== '') ? $a[2] : ($a[3] ?? '');

                if (!in_array($name, $allowed)) continue;
                if (preg_match('/^on\w/i', $name)) continue; // onclick 등 이벤트 차단

                // href·src: javascript: 프로토콜 차단
                if (in_array($name, ['href', 'src']) && preg_match('/^\s*(javascript|vbscript|data)\s*:/i', $val)) continue;

                // style: 안전한 CSS 프로퍼티만 통과
                if ($name === 'style') {
                    $safe = [];
                    foreach (explode(';', $val) as $rule) {
                        $parts = array_pad(explode(':', trim($rule), 2), 2, '');
                        $prop  = strtolower(trim($parts[0]));
                        $cv    = trim($parts[1]);
                        if (!$prop || !in_array($prop, $allowedCss)) continue;
                        if (preg_match('/(javascript|expression|vbscript)/i', $cv)) continue;
                        $safe[] = $prop . ': ' . $cv;
                    }
                    if (!$safe) continue;
                    $val = implode('; ', $safe);
                }

                $safeAttrs[] = $name . '="' . htmlspecialchars($val, ENT_QUOTES) . '"';
            }

            return '<' . $tag . ($safeAttrs ? ' ' . implode(' ', $safeAttrs) : '') . '>';
        },
        $html
    );
}

// basePath 자동 감지
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDir !== '/') {
    Router::setBasePath($scriptDir);
}

// ===== REST API v1 헬퍼 함수 =====
function apiAuth(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($header, 'Bearer ') !== 0) return null;
    $key = substr($header, 7);
    $prefix = DB::getPrefix();
    $row = DB::fetch("SELECT ak.*, m.id as mid, m.user_id, m.nickname, m.level, m.point, m.is_admin FROM {$prefix}api_keys ak LEFT JOIN {$prefix}members m ON ak.member_id = m.id WHERE ak.api_key = ? AND ak.is_active = 1", [$key]);
    if (!$row) return null;
    // Rate limit: 분당 60회
    DB::query("UPDATE {$prefix}api_keys SET request_count = request_count + 1, last_used_at = NOW() WHERE id = ?", [$row['id']]);
    return $row;
}
function apiJson($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 라우트 로드 =====
require_once NB_ROOT . '/routes/web.php';
require_once NB_ROOT . '/routes/member.php';
require_once NB_ROOT . '/routes/board.php';
require_once NB_ROOT . '/routes/api.php';
require_once NB_ROOT . '/routes/oauth.php';

// 라우트 실행
Router::dispatch();

// [성능] 세션 명시적 close — 응답 보내기 전에 세션 락 해제
// (dispatch 이후로 이동: dispatch 중 세션 쓰기/CSRF 토큰 생성이 정상 저장되도록)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// [성능] 응답 먼저 내보내고 후처리 (클라이언트는 기다리지 않음)
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
}
// 지연 큐 실행 (Cache::deferDeletePattern 등)
Cache::runDeferred();

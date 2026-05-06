<?php
/**
 * NuriBoard 관리자 - 공통 초기화
 * 모든 관리자 페이지에서 require
 */

define('NB_ROOT', dirname(__DIR__));
require_once NB_ROOT . '/config/version.php';

if (!file_exists(NB_ROOT . '/config/config.php')) {
    header('Location: ../install.php');
    exit;
}

require_once NB_ROOT . '/core/DB.php';
require_once NB_ROOT . '/core/Updater.php';
require_once NB_ROOT . '/core/Auth.php';
require_once NB_ROOT . '/core/Member.php';
require_once NB_ROOT . '/core/Board.php';
require_once NB_ROOT . '/core/Post.php';
require_once NB_ROOT . '/core/Comment.php';
require_once NB_ROOT . '/core/SEO.php';
require_once NB_ROOT . '/core/Upload.php';
require_once NB_ROOT . '/core/Point.php';
require_once NB_ROOT . '/core/Vote.php';
require_once NB_ROOT . '/core/Menu.php';
require_once NB_ROOT . '/core/Banner.php';
require_once NB_ROOT . '/core/MobileMenu.php';
require_once NB_ROOT . '/core/AdminLog.php';
require_once NB_ROOT . '/core/Message.php';
require_once NB_ROOT . '/core/Social.php';
require_once NB_ROOT . '/core/Level.php';
require_once NB_ROOT . '/core/Plugin.php';

Auth::init();

// 사이트 설정 로드 (플러그인보다 먼저)
$prefix = DB::getPrefix();
$siteSettings = [];
$rows = DB::fetchAll("SELECT setting_key, setting_value FROM {$prefix}settings");
foreach ($rows as $row) {
    $siteSettings[$row['setting_key']] = $row['setting_value'];
}
if (!function_exists('nb_setting')) {
    define('NB_SETTINGS', $siteSettings);
    function nb_setting(string $key, string $default = ''): string { return NB_SETTINGS[$key] ?? $default; }
    function nb_e(string $str): string { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('nb_url')) {
    function nb_url(string $path = ''): string {
        $base = rtrim(nb_setting('site_url', ''), '/');
        if (!$base) $base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

// 플러그인 로드 (nb_setting 정의 후에 실행)
Plugin::loadAll();

// 자동 DB 업그레이드
if (($siteSettings['nuri_version'] ?? '1.0.0') !== NB_VERSION) {
    $pdo = DB::getInstance();
    try { $pdo->exec("ALTER TABLE {$prefix}boards ADD COLUMN categories VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN category VARCHAR(50) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}comments ADD COLUMN parent_id INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN is_secret TINYINT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN link1 VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN link2 VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN tags VARCHAR(500) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN title_color VARCHAR(10) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN title_bg VARCHAR(10) DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN vote_up INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}posts ADD COLUMN vote_down INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE {$prefix}market_plugins ADD COLUMN category VARCHAR(50) DEFAULT ''"); } catch (Exception $e) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}attachments (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, orig_name VARCHAR(255) NOT NULL, file_size INT DEFAULT 0, file_type VARCHAR(50) DEFAULT '', is_image TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_post (post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}points (id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, point INT NOT NULL, reason VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_member (member_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}votes (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, member_id INT NOT NULL, vote_type TINYINT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_vote (post_id, member_id), INDEX idx_post (post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}menus (id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT DEFAULT 0, title VARCHAR(100) NOT NULL, link VARCHAR(500) DEFAULT '', board_id VARCHAR(50) DEFAULT '', target VARCHAR(10) DEFAULT '', sort_order INT DEFAULT 0, is_active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}banners (id INT AUTO_INCREMENT PRIMARY KEY, position VARCHAR(20) NOT NULL, title VARCHAR(100) DEFAULT '', image VARCHAR(500) NOT NULL, link VARCHAR(500) DEFAULT '', target VARCHAR(10) DEFAULT '_blank', sort_order INT DEFAULT 0, is_active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}widgets (id INT AUTO_INCREMENT PRIMARY KEY, widget_type VARCHAR(30) NOT NULL, position VARCHAR(20) NOT NULL, title VARCHAR(100) DEFAULT '', config TEXT, sort_order INT DEFAULT 0, is_active TINYINT DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_position (position, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $newSettings = ['point_write'=>'10','point_comment'=>'5','point_login'=>'3','upload_max_size'=>'10','upload_extensions'=>'jpg,jpeg,png,gif,webp,pdf,zip,hwp,doc,docx,xls,xlsx,ppt,pptx,txt','site_logo'=>'','site_favicon'=>''];
    foreach ($newSettings as $k => $v) {
        $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$k]);
        if (!$exists) DB::insert("{$prefix}settings", ['setting_key' => $k, 'setting_value' => $v]);
    }
    DB::update("{$prefix}settings", ['setting_value' => NB_VERSION], "setting_key = ?", ['nuri_version']);
}

// 관리자 권한 체크 함수
function adminRequireAuth(): void
{
    if (!Auth::check() || !Auth::isAdmin()) {
        header('Location: ?page=login');
        exit;
    }
}

// ===== 알림 AJAX 핸들러 =====
if (isset($_GET['nb_notif']) && Auth::check() && Auth::isAdmin()) {
    header('Content-Type: application/json');
    $action = $_GET['nb_notif'];
    if ($action === 'read') {
        $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = 'admin_notif_checked_at'");
        if ($exists) {
            DB::update("{$prefix}settings", ['setting_value' => date('Y-m-d H:i:s')], "setting_key = ?", ['admin_notif_checked_at']);
        } else {
            DB::insert("{$prefix}settings", ['setting_key' => 'admin_notif_checked_at', 'setting_value' => date('Y-m-d H:i:s')]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'delete') {
        // 게시글 삭제 X — 알림 목록에서만 숨김 (dismissed ID 목록을 settings에 저장)
        $rawIds = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : explode(',', $_POST['ids'] ?? '');
        $ids = array_values(array_filter(array_map('intval', $rawIds)));
        if ($ids) {
            $existRow = DB::fetch("SELECT setting_value FROM {$prefix}settings WHERE setting_key = 'admin_notif_dismissed'");
            $dismissed = $existRow ? array_filter(array_map('intval', explode(',', $existRow['setting_value']))) : [];
            $dismissed = array_values(array_unique(array_merge($dismissed, $ids)));
            if (count($dismissed) > 1000) $dismissed = array_slice($dismissed, -1000);
            $val = implode(',', $dismissed);
            if ($existRow) {
                DB::update("{$prefix}settings", ['setting_value' => $val], "setting_key = ?", ['admin_notif_dismissed']);
            } else {
                DB::insert("{$prefix}settings", ['setting_key' => 'admin_notif_dismissed', 'setting_value' => $val]);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false]);
    exit;
}

// 관리자 레이아웃 시작
function adminHeader(string $currentPage = 'dashboard'): void
{
    // 업데이트 배지 (1시간 캐시 사용, 오류 시 무시)
    $updHasNew = false;
    $updLatest = '';
    try {
        $updInfo   = Updater::fetchLatest(false);
        $updLatest = (string)($updInfo['version'] ?? '');
        if ($updLatest && version_compare($updLatest, NB_VERSION, '>')) $updHasNew = true;
    } catch (Throwable $e) {}

    // 알림 데이터 로드
    $pfx = DB::getPrefix();
    $notifRow = DB::fetch("SELECT setting_value FROM {$pfx}settings WHERE setting_key = 'admin_notif_checked_at'");
    $checkedAt = $notifRow['setting_value'] ?? '2000-01-01 00:00:00';
    $dismissedRow = DB::fetch("SELECT setting_value FROM {$pfx}settings WHERE setting_key = 'admin_notif_dismissed'");
    $dismissedIds = ($dismissedRow && $dismissedRow['setting_value'])
        ? array_values(array_filter(array_map('intval', explode(',', $dismissedRow['setting_value']))))
        : [];
    $dismissedSql = $dismissedIds ? ('AND p.id NOT IN (' . implode(',', $dismissedIds) . ')') : '';
    $notifPosts = DB::fetchAll(
        "SELECT p.id, p.title, p.created_at, b.title as board_title
         FROM {$pfx}posts p
         LEFT JOIN {$pfx}boards b ON p.board_id = b.board_id
         WHERE p.created_at > ? {$dismissedSql}
         ORDER BY p.created_at DESC LIMIT 50",
        [$checkedAt]
    );
    $notifCount = count($notifPosts);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 - NuriBoard</title>
    <style>
    *{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Noto Sans KR',sans-serif;background:#f1f5f9;color:#1e293b;font-size:14px}.login-page{display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#1e293b,#334155)}.login-box{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 8px 32px rgba(0,0,0,.12)}.login-box h1{text-align:center;font-size:24px;margin-bottom:24px;color:#2563eb}.admin-wrap{display:flex;min-height:100vh}.sidebar{width:240px;background:#1e293b;color:#fff;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100}.sidebar-header{padding:24px 20px;border-bottom:1px solid #334155;display:flex;align-items:center;gap:8px}.sidebar-header h2{font-size:18px;font-weight:800}.sidebar-header .version{font-size:11px;color:#64748b}.sidebar-nav{flex:1;padding:12px 0}.sidebar-nav a{display:flex;align-items:center;gap:10px;padding:12px 20px;color:#94a3b8;text-decoration:none;font-size:14px;transition:all .2s}.sidebar-nav a:hover{color:#fff;background:#334155}.sidebar-nav a.active{color:#fff;background:#2563eb}.sidebar-nav .icon{width:18px;text-align:center}.sidebar-footer{padding:16px 20px;border-top:1px solid #334155;display:flex;gap:16px}.sidebar-footer a{color:#64748b;text-decoration:none;font-size:12px}.sidebar-footer a:hover{color:#fff}.admin-main{flex:1;margin-left:240px;padding:24px}.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}.page-header h1{font-size:22px;font-weight:700}.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:24px}.stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}.stat-card.accent{background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff}.stat-number{font-size:28px;font-weight:800}.stat-label{font-size:13px;color:#64748b;margin-top:4px}.stat-card.accent .stat-label{color:rgba(255,255,255,.8)}.card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden}.card-header{padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between}.card-header h2{font-size:16px;font-weight:600}.card-body{padding:20px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:12px 16px;text-align:left;border-bottom:1px solid #f1f5f9}.table th{font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;background:#f8fafc}.table tr:hover{background:#f8fafc}.text-center{text-align:center}.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}.badge-green{background:#ecfdf5;color:#059669}.badge-red{background:#fef2f2;color:#dc2626}.form-group{margin-bottom:16px}.form-group label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}.form-group input[type="text"],.form-group input[type="email"],.form-group input[type="password"],.form-group input[type="number"],.form-group input[type="url"],.form-group select,.form-group textarea{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;outline:none;transition:border-color .2s}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}.form-group small{display:block;margin-top:4px;color:#94a3b8;font-size:12px}.form-row{display:flex;gap:16px}.form-row .form-group{flex:1}.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#374151;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s}.btn:hover{background:#f9fafb}.btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}.btn-primary:hover{background:#1d4ed8}.btn-danger{color:#dc2626;border-color:#fecaca}.btn-danger:hover{background:#fef2f2}.btn-sm{padding:4px 10px;font-size:12px}.btn-lg{padding:12px 24px;font-size:15px}.btn-full{width:100%;justify-content:center}.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}.alert.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}.alert.success{background:#ecfdf5;color:#059669;border:1px solid #a7f3d0}.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}.modal.open{display:flex}.modal-content{background:#fff;border-radius:16px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.15)}.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #e2e8f0}.modal-header h3{font-size:17px}.modal-close{background:none;border:none;font-size:24px;cursor:pointer;color:#94a3b8}.modal-content form{padding:24px}.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9}.search-form{display:flex;gap:8px}.search-form input{padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;outline:none}.pagination{display:flex;gap:4px;padding:16px;justify-content:center}.pagination a{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;text-decoration:none;color:#475569;font-size:13px}.pagination a:hover{background:#f1f5f9}.pagination a.active{background:#2563eb;color:#fff}code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;color:#7c3aed}.nb-palette{display:flex;flex-wrap:wrap;gap:4px;max-width:320px;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:8px}.nb-swatch{width:28px;height:28px;border-radius:4px;cursor:pointer;border:2px solid transparent;transition:transform .1s,border-color .1s}.nb-swatch:hover{transform:scale(1.2);z-index:1}.nb-swatch.selected{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.3)}.nb-color-preview{display:inline-block;width:36px;height:36px;border-radius:8px;border:2px solid #e2e8f0;vertical-align:middle;margin-right:8px}@media(max-width:768px){.admin-wrap{flex-direction:column}.sidebar{width:100%;position:relative;flex-direction:column}.sidebar-header{padding:12px 16px}.sidebar-header h2{font-size:16px}.sidebar-header .version{font-size:10px}.sidebar-nav{display:flex;flex-wrap:wrap;gap:2px;padding:8px 12px;overflow-x:auto}.sidebar-nav a{padding:6px 12px;font-size:12px;border-radius:6px;white-space:nowrap}.sidebar-footer{padding:8px 12px;gap:12px;border-top:1px solid #334155;display:flex}.sidebar-footer a{font-size:11px}.admin-main{margin-left:0;padding:16px}.stat-grid{grid-template-columns:repeat(2,1fr)}.form-row{flex-direction:column;gap:0}.table{font-size:12px}.table th,.table td{padding:8px}}
    /* ===== 알림 패널 ===== */
    .notif-btn{margin-left:auto;position:relative;background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px;display:flex;align-items:center;border-radius:6px;transition:color .2s}
    .notif-btn:hover{color:#fff;background:#334155}
    .notif-btn svg{width:20px;height:20px}
    .notif-count{position:absolute;top:-3px;right:-5px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 3px;line-height:1;pointer-events:none}
    .notif-overlay{display:none;position:fixed;inset:0;z-index:150}
    .notif-overlay.open{display:block}
    .notif-panel{display:none;position:fixed;top:0;right:0;width:380px;height:100vh;background:#1e293b;border-left:1px solid #334155;z-index:200;flex-direction:column;box-shadow:-6px 0 24px rgba(0,0,0,.4)}
    .notif-panel.open{display:flex}
    .notif-ph{padding:14px 16px;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0}
    .notif-ph-title{color:#fff;font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;white-space:nowrap}
    .notif-ph-title svg{width:16px;height:16px;color:#94a3b8;flex-shrink:0}
    .notif-ph-btns{display:flex;gap:6px;flex-shrink:0}
    .notif-ph-btns button{padding:5px 12px;font-size:12px;border-radius:6px;border:1px solid #475569;background:none;color:#94a3b8;cursor:pointer;transition:all .15s;white-space:nowrap}
    .notif-ph-btns button:hover{background:#334155;color:#fff}
    .notif-ph-btns .btn-ndel{border-color:#7f1d1d;color:#f87171}
    .notif-ph-btns .btn-ndel:hover{background:#7f1d1d;color:#fff}
    .notif-ph-btns .btn-nclose{font-size:15px;padding:4px 10px}
    .notif-list{flex:1;overflow-y:auto}
    .notif-list::-webkit-scrollbar{width:4px}.notif-list::-webkit-scrollbar-thumb{background:#334155;border-radius:2px}
    .notif-item{display:flex;align-items:flex-start;gap:10px;padding:11px 16px;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:background .15s}
    .notif-item:hover{background:#334155}
    .notif-item input[type=checkbox]{margin-top:3px;flex-shrink:0;accent-color:#2563eb;width:14px;height:14px;cursor:pointer}
    .notif-item-body{flex:1;min-width:0}
    .notif-ititle{font-size:13px;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
    .notif-imeta{font-size:11px;color:#64748b}
    .notif-empty{padding:40px 16px;text-align:center;color:#475569;font-size:13px}
    @media(max-width:768px){.notif-panel{width:100%;left:0}}
    </style>
</head>
<body>
    <div class="admin-wrap">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>NuriBoard</h2>
                <span class="version">v<?= NB_VERSION ?></span>
                <button class="notif-btn" id="notifBell" onclick="toggleNotif()" title="새 게시글 알림">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($notifCount > 0): ?><span class="notif-count" id="notifBadge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span><?php endif; ?>
                </button>
            </div>
            <nav class="sidebar-nav">
                <a href="?page=dashboard" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    대시보드
                </a>
                <a href="?page=main-design" class="<?= $currentPage === 'main-design' ? 'active' : '' ?>">
                    메인 페이지
                </a>
                <a href="?page=boards" class="<?= $currentPage === 'boards' ? 'active' : '' ?>">
                    게시판 관리
                </a>
                <a href="?page=members" class="<?= $currentPage === 'members' ? 'active' : '' ?>">
                    회원 관리
                </a>
                <a href="?page=menus" class="<?= $currentPage === 'menus' ? 'active' : '' ?>">
                    메뉴 관리
                </a>
                <a href="?page=levels" class="<?= $currentPage === 'levels' ? 'active' : '' ?>">
                    회원 등급
                </a>
                <a href="?page=logs" class="<?= $currentPage === 'logs' ? 'active' : '' ?>">
                    활동 로그
                </a>
                <a href="?page=banners" class="<?= $currentPage === 'banners' ? 'active' : '' ?>">
                    배너 관리
                </a>
                <a href="?page=mobile-menu" class="<?= $currentPage === 'mobile-menu' ? 'active' : '' ?>">
                    햄버거 메뉴
                </a>
                <a href="?page=ticker" class="<?= $currentPage === 'ticker' ? 'active' : '' ?>">
                    띠공지
                </a>
                <a href="?page=plugins" class="<?= $currentPage === 'plugins' ? 'active' : '' ?>">
                    플러그인
                </a>
                <a href="?page=market-purchases" class="<?= $currentPage === 'market-purchases' ? 'active' : '' ?>">
                    구매한 플러그인
                </a>
                <a href="?page=settings" class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
                    사이트 설정
                </a>
                <a href="?page=ai-monitor" class="<?= $currentPage === 'ai-monitor' ? 'active' : '' ?>">
                    🤖 AI 모니터
                </a>
                <a href="?page=update" class="<?= $currentPage === 'update' ? 'active' : '' ?>" style="<?= $updHasNew ? 'color:#fff' : '' ?>">
                    누리보드 업데이트
                    <?php if ($updHasNew): ?>
                    <span style="margin-left:auto;background:#22c55e;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;flex-shrink:0">NEW</span>
                    <?php endif; ?>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="../">사이트 바로가기</a>
                <a href="../logout">로그아웃</a>
            </div>
        </aside>

        <!-- 알림 오버레이 & 패널 -->
        <div class="notif-overlay" id="notifOverlay" onclick="closeNotif()"></div>
        <div class="notif-panel" id="notifPanel">
            <div class="notif-ph">
                <div class="notif-ph-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    새 게시글 <span id="notifCountLabel"><?= $notifCount ?></span>개
                </div>
                <div class="notif-ph-btns">
                    <button onclick="selectAllNotif()">전체선택</button>
                    <button class="btn-ndel" onclick="deleteSelectedNotif()">삭제</button>
                    <button class="btn-nclose" onclick="closeNotif()">✕</button>
                </div>
            </div>
            <div class="notif-list" id="notifList">
                <?php if (empty($notifPosts)): ?>
                <div class="notif-empty">새 게시글이 없습니다</div>
                <?php else: foreach ($notifPosts as $np): ?>
                <label class="notif-item">
                    <input type="checkbox" name="notif_post" value="<?= (int)$np['id'] ?>">
                    <div class="notif-item-body">
                        <div class="notif-ititle"><?= htmlspecialchars($np['title'] ?? '(제목없음)', ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="notif-imeta"><?= htmlspecialchars($np['board_title'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= substr($np['created_at'], 0, 16) ?></div>
                    </div>
                </label>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <main class="admin-main">
        <?php Plugin::doHook('admin_header'); ?>
<?php
}

function adminFooter(): void
{
?>
        <?php Plugin::doHook('admin_footer'); ?>
        </main>
    </div>
    <script>
    function openModal(id){document.getElementById(id).classList.add('open')}
    function closeModal(id){document.getElementById(id).classList.remove('open')}
    function ajaxPost(data){data.append('ajax','1');return fetch(location.pathname+location.search,{method:'POST',body:data}).then(function(r){return r.json()})}

    /* ===== 알림 패널 ===== */
    var _notifMarked = false;
    function toggleNotif(){
        var panel=document.getElementById('notifPanel');
        var overlay=document.getElementById('notifOverlay');
        if(panel.classList.contains('open')){closeNotif();return;}
        panel.classList.add('open');
        overlay.classList.add('open');
        if(!_notifMarked){
            _notifMarked=true;
            fetch(location.pathname+'?nb_notif=read').then(function(){
                var badge=document.getElementById('notifBadge');
                if(badge)badge.remove();
            });
        }
    }
    function closeNotif(){
        document.getElementById('notifPanel').classList.remove('open');
        document.getElementById('notifOverlay').classList.remove('open');
    }
    function selectAllNotif(){
        var chks=document.querySelectorAll('#notifList input[type=checkbox]');
        var all=Array.from(chks).every(function(c){return c.checked});
        chks.forEach(function(c){c.checked=!all});
    }
    function deleteSelectedNotif(){
        var chks=document.querySelectorAll('#notifList input[type=checkbox]:checked');
        var ids=Array.from(chks).map(function(c){return c.value});
        if(!ids.length){alert('삭제할 게시글을 선택하세요.');return;}
        if(!confirm(ids.length+'개 게시글을 삭제할까요?')){return;}
        var fd=new FormData();
        ids.forEach(function(id){fd.append('ids[]',id);});
        fetch(location.pathname+'?nb_notif=delete',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(res){
                if(!res.ok)return;
                ids.forEach(function(id){
                    var el=document.querySelector('#notifList input[value="'+id+'"]');
                    if(el)el.closest('label.notif-item').remove();
                });
                var remaining=document.querySelectorAll('#notifList input[type=checkbox]').length;
                document.getElementById('notifCountLabel').textContent=remaining;
                if(!remaining){
                    document.getElementById('notifList').innerHTML='<div class="notif-empty">새 게시글이 없습니다</div>';
                }
            });
    }

    /* 컬러 팔레트 컴포넌트 */
    (function(){
        var PALETTE=[
            '#000000','#333333','#555555','#777777','#999999','#bbbbbb','#dddddd','#ffffff',
            '#1e293b','#334155','#475569','#64748b','#94a3b8','#cbd5e1','#e2e8f0','#f1f5f9',
            '#7f1d1d','#991b1b','#dc2626','#ef4444','#f87171','#fca5a5','#fecaca','#fef2f2',
            '#78350f','#92400e','#d97706','#f59e0b','#fbbf24','#fcd34d','#fde68a','#fef3c7',
            '#14532d','#166534','#16a34a','#22c55e','#4ade80','#86efac','#bbf7d0','#ecfdf5',
            '#0c4a6e','#075985','#0284c7','#0ea5e9','#38bdf8','#7dd3fc','#bae6fd','#e0f2fe',
            '#312e81','#3730a3','#4f46e5','#6366f1','#818cf8','#a5b4fc','#c7d2fe','#e0e7ff',
            '#581c87','#6b21a8','#9333ea','#a855f7','#c084fc','#d8b4fe','#e9d5ff','#f3e8ff',
            '#831843','#9d174d','#db2777','#ec4899','#f472b6','#f9a8d4','#fbcfe8','#fce7f3'
        ];
        window.nbColorPalette = function(inputId, previewId, onChange){
            var wrap = document.getElementById(inputId + '_palette');
            if(!wrap) return;
            var html = '<div class="nb-palette">';
            PALETTE.forEach(function(c){
                html += '<span class="nb-swatch" data-color="'+c+'" style="background:'+c+'" title="'+c+'"></span>';
            });
            html += '</div>';
            wrap.innerHTML = html;
            wrap.querySelectorAll('.nb-swatch').forEach(function(sw){
                sw.addEventListener('click', function(){
                    var color = this.dataset.color;
                    document.getElementById(inputId).value = color;
                    var pv = document.getElementById(previewId);
                    if(pv) pv.style.background = color;
                    wrap.querySelectorAll('.nb-swatch').forEach(function(s){s.classList.remove('selected')});
                    this.classList.add('selected');
                    if(onChange) onChange(color);
                });
            });
            // 현재값 선택 표시
            var cur = document.getElementById(inputId).value.toLowerCase();
            wrap.querySelectorAll('.nb-swatch').forEach(function(sw){
                if(sw.dataset.color === cur) sw.classList.add('selected');
            });
        };
    })();
    </script>
</body>
</html>
<?php
}

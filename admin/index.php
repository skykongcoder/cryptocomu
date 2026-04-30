<?php
/**
 * NuriBoard 관리자 - 메인 라우터
 * 각 페이지는 별도 파일로 분리됨
 */

require_once __DIR__ . '/common.php';

$page = $_GET['page'] ?? 'dashboard';

// 로그인 안 된 상태
if (!Auth::check()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'login') {
        $result = Auth::login($_POST['user_id'] ?? '', $_POST['password'] ?? '');
        if ($result['success'] && Auth::isAdmin()) {
            header('Location: ./');
            exit;
        }
        $loginError = $result['success'] ? '관리자 권한이 없습니다.' : $result['message'];
        Auth::logout();
    }
    require __DIR__ . '/login.php';
    exit;
}

if (!Auth::isAdmin()) {
    Auth::logout();
    require __DIR__ . '/login.php';
    exit;
}

// 페이지 라우팅
switch ($page) {
    case 'boards':
        require __DIR__ . '/boards.php';
        break;
    case 'members':
        require __DIR__ . '/members.php';
        break;
    case 'menus':
        require __DIR__ . '/menus.php';
        break;
    case 'levels':
        require __DIR__ . '/levels.php';
        break;
    case 'logs':
        require __DIR__ . '/logs.php';
        break;
    case 'banners':
        require __DIR__ . '/banners.php';
        break;
    case 'ticker':
        require __DIR__ . '/ticker.php';
        break;
    case 'main-design':
        require __DIR__ . '/main-design.php';
        break;
    case 'mobile-menu':
        require __DIR__ . '/mobile-menu.php';
        break;
    case 'plugins':
        require __DIR__ . '/plugins.php';
        break;
    case 'market-purchases':
        require __DIR__ . '/market-purchases.php';
        break;
    case 'settings':
        require __DIR__ . '/settings.php';
        break;
    case 'update':
        require __DIR__ . '/update.php';
        break;
    case 'dashboard':
    default:
        require __DIR__ . '/dashboard.php';
        break;
}

<?php
/**
 * NuriBoard 기본 테마 - 헤더
 */
function nb_menu_badge($menu) {
    $b = $menu['badge'] ?? '';
    if (!$b) return '';
    if (strpos($b, 'dot-') === 0) {
        $colors = ['dot-green'=>'#22c55e','dot-red'=>'#dc2626','dot-blue'=>'#2563eb','dot-orange'=>'#f59e0b'];
        $c = $colors[$b] ?? '#22c55e';
        return ' <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'.$c.';margin-left:4px;vertical-align:middle"></span>';
    }
    if ($b === 'new') return ' <span class="icon-new">NEW</span>';
    if ($b === 'hot') return ' <span class="icon-new" style="background:#f59e0b">HOT</span>';
    return '';
}
$menuTree = Menu::getTree();
$useCustomMenu = !empty($menuTree);
if (!$useCustomMenu) $boards = Board::listAll(true);
MobileMenu::ensureTables();
$_mbBanners = MobileMenu::listActiveBanners();
$_bottomBar = MobileMenu::listActiveBottom();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?= SEO::render() ?>
    <?php
    $__fav = nb_setting('site_favicon');
    if ($__fav):
        $__favUrl  = nb_url($__fav);
        $__favExt  = strtolower(pathinfo($__fav, PATHINFO_EXTENSION));
        $__favType = ['ico'=>'image/x-icon','png'=>'image/png','svg'=>'image/svg+xml',
                      'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'][$__favExt] ?? 'image/x-icon';
    ?>
    <link rel="shortcut icon" type="<?= $__favType ?>" href="<?= $__favUrl ?>">
    <link rel="icon" type="<?= $__favType ?>" href="<?= $__favUrl ?>">
    <link rel="apple-touch-icon" href="<?= $__favUrl ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= nb_asset('style.css') ?>">
    <style>
    .header-msg-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:8px;color:var(--text-light);text-decoration:none;transition:background .15s;font-size:18px}
    .header-msg-btn:hover{background:#f1f5f9;text-decoration:none;color:var(--text)}
    .bell-icon{line-height:1}
    .msg-badge{position:absolute;top:2px;right:2px;background:#dc2626;color:#fff;border-radius:10px;min-width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;border:2px solid #fff;animation:badgePop .3s ease}
    @keyframes badgePop{0%{transform:scale(0)}60%{transform:scale(1.2)}100%{transform:scale(1)}}
    </style>
    <?= Plugin::renderHeaderAssets() ?>
</head>
<body<?= !empty($_bottomBar) ? ' class="has-bottombar"' : '' ?>>
    <!-- 유틸바 -->
    <div class="util-bar">
        <div class="container">
            <div class="util-left"><?= date('Y.m.d') ?> <?= ['일','월','화','수','목','금','토'][date('w')] ?>요일</div>
            <div class="util-right">
                <?php if (Auth::check()): ?>
                    <span><?= nb_e(Auth::user()['nickname']) ?>님</span>
                    <a href="<?= nb_url('profile') ?>">내정보</a>
                    <?php if (Auth::isAdmin()): ?><a href="<?= nb_url('admin/') ?>">관리자</a><?php endif; ?>
                    <a href="<?= nb_url('logout') ?>">로그아웃</a>
                <?php else: ?>
                    <a href="<?= nb_url('login') ?>">로그인</a>
                    <a href="<?= nb_url('register') ?>">회원가입</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 헤더 -->
    <header class="site-header"<?php if (nb_setting('header_bg_color')): ?> style="background:<?= nb_e(nb_setting('header_bg_color')) ?>"<?php endif; ?>>
        <div class="container">
            <div class="header-inner">
                <div class="header-brand">
                    <a href="<?= nb_url('/') ?>" class="logo"<?php if (nb_setting('site_title_color')): ?> style="color:<?= nb_e(nb_setting('site_title_color')) ?>"<?php endif; ?>>
                        <?php if (nb_setting('site_logo')): ?>
                            <img src="<?= nb_url(nb_setting('site_logo')) ?>" alt="<?= nb_e(nb_setting('site_title')) ?>" class="logo-img">
                        <?php else: ?>
                            <?= nb_e(nb_setting('site_title', 'NuriBoard')) ?>
                        <?php endif; ?>
                    </a>
                    <?php if (nb_setting('site_description')): ?>
                        <span class="header-desc"><?= nb_e(nb_setting('site_description')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="header-search">
                    <form method="get" action="<?= nb_url("search") ?>">
                        <input type="text" name="q" placeholder="통합검색" class="search-input" value="<?= nb_e($_GET['q'] ?? '') ?>">
                        <button type="submit" class="search-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
                    </form>
                </div>
                <div class="header-right">
                    <button type="button" class="mobile-search-link" aria-label="검색" onclick="toggleMobileSearch()"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
                    <button class="mobile-toggle" id="navToggle" aria-label="메뉴 열기" onclick="toggleNav()">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- 모바일 검색 팝업 -->
    <div class="mobile-search-popup" id="mobileSearchPopup">
        <form method="get" action="<?= nb_url('search') ?>">
            <input type="text" name="q" placeholder="검색어를 입력하세요" class="mobile-search-input" id="mobileSearchInput" value="<?= nb_e($_GET['q'] ?? '') ?>">
            <button type="submit" class="mobile-search-submit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
        </form>
    </div>

    <!-- 네비게이션 바 -->
    <nav class="site-nav" id="siteNav" style="<?= nb_setting('nav_bg_color') ? '--nav-bg:'.nb_e(nb_setting('nav_bg_color')).';background:'.nb_e(nb_setting('nav_bg_color')).';' : '' ?><?= nb_setting('nav_text_color') ? '--nav-color:'.nb_e(nb_setting('nav_text_color')).';' : '' ?>">
        <div class="container">
            <div class="nav-links">
                <?php if ($useCustomMenu): ?>
                    <?php foreach ($menuTree as $menu): ?>
                        <?php $mColor = !empty($menu['color']) ? ' style="color:'.nb_e($menu['color']).'"' : ''; ?>
                        <?php if (!empty($menu['children'])): ?>
                        <div class="nav-dropdown">
                            <a href="<?= Menu::getUrl($menu) ?>" class="nav-link"<?= $mColor ?>>
                                <?= nb_e($menu['title']) ?> <span class="arrow">▾</span>
                            </a>
                            <div class="dropdown-menu">
                                <?php foreach ($menu['children'] as $child): ?>
                                    <?php $cColor = !empty($child['color']) ? ' style="color:'.nb_e($child['color']).'"' : ''; ?>
                                    <a href="<?= Menu::getUrl($child) ?>"<?= $cColor ?>><?= nb_e($child['title']) ?><?= nb_menu_badge($child) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <a href="<?= Menu::getUrl($menu) ?>" class="nav-link"<?= $mColor ?>><?= nb_e($menu['title']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($boards as $b): ?>
                    <a href="<?= nb_url('board/' . $b['board_id']) ?>" class="nav-link"><?= nb_e($b['title']) ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <!-- 모바일 전체메뉴 오버레이 -->
    <div class="mobile-fullmenu-overlay" id="mobileMyinfo">
        <div class="mobile-fullmenu">
            <!-- 헤더 -->
            <div class="mfm-header">
                <strong class="mfm-title">전체 메뉴</strong>
                <button class="mfm-close" onclick="toggleNav()">&times;</button>
            </div>

            <div class="mfm-body">
                <!-- 퀵 버튼 (고정) -->
                <div class="mfm-quick">
                    <a href="<?= nb_url('attendance') ?>" class="mfm-quick-btn"><span class="mfm-quick-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg></span><span>출석체크</span></a>
                    <a href="<?= nb_url('messages') ?>" class="mfm-quick-btn">
                        <span class="mfm-quick-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                        <span>쪽지<?php if (Auth::check()): ?><?php $_mu = Message::unreadCount(Auth::id()); if ($_mu > 0): ?><span class="nav-badge"><?= $_mu ?></span><?php endif; ?><?php endif; ?></span>
                    </a>
                    <a href="<?= nb_url('profile') ?>" class="mfm-quick-btn"><span class="mfm-quick-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span>내정보</span></a>
                </div>

                <!-- 배너 영역 -->
                <?php if (!empty($_mbBanners)): ?>
                <div class="mfm-banners">
                    <?php foreach ($_mbBanners as $_mbb): ?>
                    <a href="<?= nb_e($_mbb['link']) ?>" target="<?= nb_e($_mbb['target']) ?>" class="mfm-banner">
                        <img src="<?= nb_url($_mbb['image']) ?>" alt="">
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- 메뉴 섹션 -->
                <?php if ($useCustomMenu): ?>
                    <?php foreach ($menuTree as $menu): ?>
                    <div class="mfm-section">
                        <div class="mfm-section-title"><?= nb_e($menu['title']) ?></div>
                        <div class="mfm-section-links">
                            <?php if (!empty($menu['children'])): ?>
                                <?php foreach ($menu['children'] as $child): ?>
                                <a href="<?= Menu::getUrl($child) ?>"><?= nb_e($child['title']) ?><?= nb_menu_badge($child) ?></a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <a href="<?= Menu::getUrl($menu) ?>"><?= nb_e($menu['title']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mfm-section">
                        <div class="mfm-section-title">게시판</div>
                        <div class="mfm-section-links">
                            <?php foreach ($boards as $b): ?>
                            <a href="<?= nb_url('board/' . $b['board_id']) ?>"><?= nb_e($b['title']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 회원정보 -->
                <div class="mfm-member">
                    <?php if (Auth::check()): ?>
                        <div class="mfm-member-info">
                            <div class="mfm-avatar"><?php if (!empty(Auth::user()['profile_image'])): ?><img src="<?= nb_url(Auth::user()['profile_image']) ?>"><?php else: ?><?= strtoupper(mb_substr(Auth::user()['nickname'], 0, 1)) ?><?php endif; ?></div>
                            <div>
                                <div class="mfm-nick"><?= nb_level_icon(Auth::level()) ?> <strong><?= nb_e(Auth::user()['nickname']) ?></strong>님</div>
                                <div class="mfm-stats">Lv.<?= Auth::level() ?> · <?= number_format(Auth::user()['point'] ?? 0) ?>P</div>
                            </div>
                        </div>
                        <div class="mfm-member-links">
                            <?php if (Auth::isAdmin()): ?><a href="<?= nb_url('admin/') ?>">관리자</a><?php endif; ?>
                            <a href="<?= nb_url('logout') ?>">로그아웃</a>
                        </div>
                    <?php else: ?>
                        <div class="mfm-member-links">
                            <a href="<?= nb_url('login') ?>">로그인</a>
                            <a href="<?= nb_url('register') ?>">회원가입</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 띠공지 -->
    <?php if (nb_setting('ticker_enabled') === '1' && nb_setting('ticker_text')): ?>
    <div class="ticker-bar" style="background:<?= nb_e(nb_setting('ticker_bg_color', '#ec4899')) ?>;color:<?= nb_e(nb_setting('ticker_text_color', '#ffffff')) ?>">
        <div class="container">
            <span class="ticker-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 11-5.8-1.6"/></svg></span>
            <div class="ticker-content ticker-<?= nb_e(nb_setting('ticker_effect', 'scroll-left')) ?>">
                <?php if (nb_setting('ticker_effect') === 'wave'): ?>
                    <span><?php foreach (mb_str_split(nb_setting('ticker_text')) as $i => $ch): ?><span class="wave-char" style="animation-delay:<?= ($i * 0.08) % 1 ?>s"><?= $ch === ' ' ? '&nbsp;' : nb_e($ch) ?></span><?php endforeach; ?></span>
                <?php else: ?>
                    <?php $tt = nb_e(nb_setting('ticker_text')); $gap = str_repeat('&nbsp;', 150); ?>
                    <span><?= $tt . $gap . $tt . $gap . $tt . $gap . $tt . $gap . $tt ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php $leftBanners = Banner::listByPosition('left'); $rightSideBanners = Banner::listByPosition('right-wing'); ?>
    <?php if (!empty($leftBanners) || !empty($rightSideBanners)): ?>
    <div class="layout-3col">
        <div class="side-left <?= nb_setting('wing_left_sticky', '1') === '1' ? 'wing-sticky' : '' ?>">
            <?php foreach ($leftBanners as $bn): ?>
                <a href="<?= nb_e($bn['link']) ?>" target="<?= nb_e($bn['target']) ?>" class="wing-banner"><img src="<?= nb_url($bn['image']) ?>" alt="<?= nb_e($bn['title']) ?>"></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (Auth::check() && Auth::isAdmin()): ?>
    <!-- 관리자 프론트 패널 -->
    <button class="admin-float-btn" id="adminFloatBtn" onclick="document.getElementById('adminPanel').classList.toggle('open')" title="관리자 설정">⚙</button>
    <div class="admin-panel" id="adminPanel">
        <div class="ap-header">
            <strong>관리자 패널</strong>
            <button onclick="document.getElementById('adminPanel').classList.remove('open')" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8">&times;</button>
        </div>

        <!-- 메뉴 추가 -->
        <div class="ap-section">
            <div class="ap-title">메뉴 추가</div>
            <div class="ap-form">
                <input type="text" id="apMenuTitle" placeholder="메뉴 이름" class="ap-input">
                <select id="apMenuParent" class="ap-input">
                    <option value="0">최상위 메뉴</option>
                </select>
                <select id="apMenuBoard" class="ap-input" onchange="if(this.value)document.getElementById('apMenuLink').value=''">
                    <option value="">게시판 연결 안함</option>
                    <?php foreach (Board::listAll(true) as $b): ?>
                        <option value="<?= nb_e($b['board_id']) ?>"><?= nb_e($b['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="apMenuLink" placeholder="또는 직접 링크 입력" class="ap-input" onfocus="document.getElementById('apMenuBoard').value=''">
                <button class="btn btn-primary btn-full" onclick="apAddMenu()" style="font-size:13px">메뉴 추가</button>
            </div>
        </div>

        <!-- 현재 메뉴 목록 -->
        <div class="ap-section">
            <div class="ap-title">현재 메뉴</div>
            <div id="apMenuList" class="ap-menu-list">불러오는 중...</div>
        </div>
    </div>
    <?php endif; ?>

    <?php Plugin::doHook('after_header'); ?>

    <main class="site-main">
    <?php Plugin::doHook('before_content'); ?>

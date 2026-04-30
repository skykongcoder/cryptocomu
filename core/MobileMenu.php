<?php
/**
 * NuriBoard - 모바일 메뉴 관리
 * 햄버거 메뉴 배너 + 하단 고정바
 */

class MobileMenu
{
    private static function bannerTable(): string
    {
        return DB::getPrefix() . 'mobile_banners';
    }

    private static function bottomTable(): string
    {
        return DB::getPrefix() . 'mobile_bottombar';
    }

    // ===== 배너 =====
    public static function findBanner(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::bannerTable() . " WHERE id = ?", [$id]);
    }

    public static function createBanner(array $data): int
    {
        return DB::insert(self::bannerTable(), [
            'image' => $data['image'],
            'link' => $data['link'] ?? '',
            'target' => $data['target'] ?? '_blank',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    public static function updateBanner(int $id, array $data): bool
    {
        return DB::update(self::bannerTable(), $data, 'id = ?', [$id]) > 0;
    }

    public static function deleteBanner(int $id): bool
    {
        $banner = self::findBanner($id);
        if ($banner && !empty($banner['image'])) {
            $path = NB_ROOT . '/' . $banner['image'];
            if (file_exists($path)) unlink($path);
        }
        return DB::delete(self::bannerTable(), 'id = ?', [$id]) > 0;
    }

    public static function listBanners(): array
    {
        return DB::fetchAll("SELECT * FROM " . self::bannerTable() . " ORDER BY sort_order ASC, id ASC");
    }

    public static function listActiveBanners(): array
    {
        return DB::fetchAll("SELECT * FROM " . self::bannerTable() . " WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    }

    // ===== 하단 고정바 =====
    public static function findBottom(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::bottomTable() . " WHERE id = ?", [$id]);
    }

    public static function createBottom(array $data): int
    {
        return DB::insert(self::bottomTable(), [
            'title' => $data['title'],
            'icon' => $data['icon'] ?? '',
            'link' => $data['link'] ?? '',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    public static function updateBottom(int $id, array $data): bool
    {
        return DB::update(self::bottomTable(), $data, 'id = ?', [$id]) > 0;
    }

    public static function deleteBottom(int $id): bool
    {
        return DB::delete(self::bottomTable(), 'id = ?', [$id]) > 0;
    }

    public static function listBottom(): array
    {
        return DB::fetchAll("SELECT * FROM " . self::bottomTable() . " ORDER BY sort_order ASC, id ASC");
    }

    public static function listActiveBottom(): array
    {
        return DB::fetchAll("SELECT * FROM " . self::bottomTable() . " WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    }

    // ===== 테이블 자동 생성 (업데이트용) =====
    public static function ensureTables(): void
    {
        $p = DB::getPrefix();
        $pdo = DB::getInstance();

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
    }

    public static function renderIcon(string $key, int $size = 20): string
    {
        if (strpos($key, 'img:') === 0) {
            $path = substr($key, 4);
            $base = class_exists('Router') ? Router::url($path) : '../' . $path;
            return '<img src="' . $base . '" style="width:'.$size.'px;height:'.$size.'px;object-fit:contain;border-radius:4px">';
        }
        $icons = [
            'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'calendar-check' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/>',
            'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
            'user' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'edit' => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
            'bell' => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'heart' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>',
            'gift' => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/>',
            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        ];
        $inner = $icons[$key] ?? $icons['home'];
        return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$inner.'</svg>';
    }
}

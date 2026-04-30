<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * SEO.php - SEO 메타태그/사이트맵/OG태그/구조화데이터
 */

class SEO
{
    private static string $title = '';
    private static string $description = '';
    private static string $keywords = '';
    private static string $canonical = '';
    private static string $ogImage = '';
    private static string $type = 'website';
    private static array $jsonLd = [];

    public static function setTitle(string $title): void
    {
        self::$title = $title;
    }

    public static function setDescription(string $desc): void
    {
        self::$description = mb_strimwidth(strip_tags($desc), 0, 160, '...');
    }

    public static function setKeywords(string $keywords): void
    {
        self::$keywords = $keywords;
    }

    public static function setCanonical(string $url): void
    {
        self::$canonical = $url;
    }

    public static function setOgImage(string $url): void
    {
        self::$ogImage = $url;
    }

    public static function setArticle(array $post, string $authorName): void
    {
        self::$type = 'article';
        self::$jsonLd[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post['title'],
            'datePublished' => $post['created_at'],
            'dateModified' => $post['updated_at'] ?? $post['created_at'],
            'author' => [
                '@type' => 'Person',
                'name' => $authorName,
            ],
        ];
    }

    public static function setBreadcrumb(array $items): void
    {
        $list = [];
        foreach ($items as $i => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'] ?? '',
            ];
        }
        self::$jsonLd[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    public static function render(): string
    {
        $siteTitle = nb_setting('site_title', 'NuriBoard');
        $siteUrl = nb_setting('site_url', '');
        $title = self::$title ? self::$title . ' - ' . $siteTitle : $siteTitle;
        $desc = self::$description ?: nb_setting('site_description', '');
        $keywords = self::$keywords ?: nb_setting('site_keywords', '');

        $html = '';
        $html .= '<title>' . nb_e($title) . '</title>' . "\n";
        $html .= '<meta name="description" content="' . nb_e($desc) . '">' . "\n";
        if ($keywords) {
            $html .= '<meta name="keywords" content="' . nb_e($keywords) . '">' . "\n";
        }

        // Open Graph
        $html .= '<meta property="og:type" content="' . self::$type . '">' . "\n";
        $html .= '<meta property="og:title" content="' . nb_e($title) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . nb_e($desc) . '">' . "\n";
        if ($siteUrl) {
            $html .= '<meta property="og:url" content="' . nb_e(self::$canonical ?: $siteUrl . $_SERVER['REQUEST_URI']) . '">' . "\n";
        }
        if (self::$ogImage) {
            $html .= '<meta property="og:image" content="' . nb_e(self::$ogImage) . '">' . "\n";
        }
        $html .= '<meta property="og:site_name" content="' . nb_e($siteTitle) . '">' . "\n";

        // Twitter Card
        $html .= '<meta name="twitter:card" content="summary">' . "\n";
        $html .= '<meta name="twitter:title" content="' . nb_e($title) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . nb_e($desc) . '">' . "\n";

        // Canonical
        if (self::$canonical) {
            $html .= '<link rel="canonical" href="' . nb_e(self::$canonical) . '">' . "\n";
        }

        // JSON-LD 구조화 데이터
        foreach (self::$jsonLd as $data) {
            $html .= '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }

        return $html;
    }

    public static function generateSitemap(): string
    {
        $siteUrl = nb_setting('site_url', '');
        if (!$siteUrl) return '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // 메인 페이지
        $xml .= '<url><loc>' . $siteUrl . '/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>' . "\n";

        // 게시판 목록
        $boards = Board::listAll(true);
        foreach ($boards as $board) {
            $xml .= '<url><loc>' . $siteUrl . '/board/' . $board['board_id'] . '</loc><changefreq>daily</changefreq><priority>0.8</priority></url>' . "\n";
        }

        // 최근 게시글 (최대 1000개)
        $prefix = DB::getPrefix();
        $posts = DB::fetchAll("SELECT id, board_id, slug, updated_at, created_at FROM {$prefix}posts ORDER BY id DESC LIMIT 1000");
        foreach ($posts as $post) {
            $date = $post['updated_at'] ?? $post['created_at'];
            $xml .= '<url><loc>' . $siteUrl . '/board/' . $post['board_id'] . '/' . $post['id'] . '</loc>';
            $xml .= '<lastmod>' . date('Y-m-d', strtotime($date)) . '</lastmod>';
            $xml .= '<changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    public static function reset(): void
    {
        self::$title = '';
        self::$description = '';
        self::$keywords = '';
        self::$canonical = '';
        self::$ogImage = '';
        self::$type = 'website';
        self::$jsonLd = [];
    }
}

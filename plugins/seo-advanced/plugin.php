<?php
/**
 * SEO 자동 최적화 플러그인 v1.0
 *
 * [1순위] 메타태그 + OG태그 자동생성
 * [2순위] JSON-LD 구조화 데이터 (Article, Breadcrumb, Comment, WebSite)
 * [3순위] Canonical URL 자동 삽입
 * [4순위] 이미지 alt 자동 삽입 + lazy loading
 * [5순위] 관련글 자동 추천 (내부링크)
 * [6순위] noindex 자동 처리
 * [7순위] 검색엔진 인증 메타태그
 */

$_seoFile = __DIR__ . '/config.json';
$_seoCfg = file_exists($_seoFile)
    ? json_decode(file_get_contents($_seoFile), true)
    : [];

// 기본값 병합
$_seoCfg = array_merge([
    'enabled' => '1',
    'title_suffix' => '1',
    'auto_description' => '1',
    'description_length' => '150',
    'default_description' => '',
    'default_keywords' => '',
    'auto_og' => '1',
    'auto_og_image' => '1',
    'og_image' => '',
    'twitter_card' => '1',
    'auto_canonical' => '1',
    'schema_article' => '1',
    'schema_breadcrumb' => '1',
    'schema_comment' => '1',
    'schema_website' => '1',
    'auto_sitemap' => '1',
    'sitemap_ping_google' => '0',
    'robots_txt' => '',
    'auto_image_alt' => '1',
    'lazy_loading' => '1',
    'related_posts' => '1',
    'related_posts_count' => '5',
    'noindex_login' => '1',
    'noindex_register' => '1',
    'noindex_search' => '1',
    'noindex_mypage' => '1',
    'noindex_boards' => '',
    'google_verification' => '',
    'naver_verification' => '',
], $_seoCfg);

if ($_seoCfg['enabled'] !== '1') return;

// ================================================================
// [1] 게시글 메타태그 자동 생성 (before_post_content 훅)
// ================================================================
Plugin::addHook('before_post_content', function ($post) use ($_seoCfg) {

    // 자동 description: 본문 앞 N자 추출
    if ($_seoCfg['auto_description'] === '1') {
        $content = strip_tags($post['content'] ?? '');
        $content = preg_replace('/\s+/', ' ', trim($content));
        $len = (int)$_seoCfg['description_length'] ?: 150;
        if ($content) {
            SEO::setDescription(mb_strimwidth($content, 0, $len, '...'));
        }
    }

    // 자동 키워드: 제목에서 2자 이상 단어 추출 + 기본 키워드 병합
    $titleClean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $post['title'] ?? '');
    $words = array_filter(explode(' ', $titleClean), fn($w) => mb_strlen(trim($w)) >= 2);
    $words = array_unique(array_map('trim', $words));
    $autoKw = implode(', ', array_slice($words, 0, 6));
    if ($_seoCfg['default_keywords']) {
        $autoKw = $_seoCfg['default_keywords'] . ($autoKw ? ', ' . $autoKw : '');
    }
    if ($autoKw) SEO::setKeywords($autoKw);

}, 1);


// ================================================================
// [2] Canonical URL 자동 삽입
// ================================================================
if ($_seoCfg['auto_canonical'] === '1') {
    Plugin::addHook('after_header', function () {
        $siteUrl = nb_setting('site_url', '');
        if (!$siteUrl) return;

        // 쿼리스트링 제거한 깨끗한 URL
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $cleanUrl = $siteUrl . $path;

        // 이미 canonical이 설정되어 있으면 스킵
        echo '<script>(function(){if(!document.head.querySelector(\'link[rel="canonical"]\')){var l=document.createElement("link");l.rel="canonical";l.href="' . htmlspecialchars($cleanUrl, ENT_QUOTES) . '";document.head.appendChild(l);}})();</script>' . "\n";
    }, 1);
}


// ================================================================
// [3] OG태그 / Twitter Card 자동 보강
// ================================================================
Plugin::addHook('after_header', function () use ($_seoCfg) {
    if ($_seoCfg['auto_og'] !== '1') return;

    $siteUrl = nb_setting('site_url', '');
    $js = [];

    // 기본 OG 이미지 fallback (게시글 이미지가 없을 때)
    if ($_seoCfg['auto_og_image'] === '1' && $_seoCfg['og_image']) {
        $ogImg = $_seoCfg['og_image'];
        if (!str_starts_with($ogImg, 'http')) $ogImg = $siteUrl . '/' . ltrim($ogImg, '/');
        $safeImg = htmlspecialchars($ogImg, ENT_QUOTES);
        $js[] = 'if(!h.querySelector(\'meta[property="og:image"]\')){a("property","og:image","' . $safeImg . '");}';
    }

    // og:locale
    $js[] = 'if(!h.querySelector(\'meta[property="og:locale"]\')){a("property","og:locale","ko_KR");}';

    // og:image 크기 (이미지가 있을 때)
    $js[] = 'if(h.querySelector(\'meta[property="og:image"]\')&&!h.querySelector(\'meta[property="og:image:width"]\')){a("property","og:image:width","1200");a("property","og:image:height","630");}';

    // Twitter Card 보강
    if ($_seoCfg['twitter_card'] === '1') {
        $js[] = 'var tc=h.querySelector(\'meta[name="twitter:card"]\');if(tc&&h.querySelector(\'meta[property="og:image"]\')){tc.content="summary_large_image";}';
        // twitter:image fallback (og:image 복사)
        $js[] = 'var oi=h.querySelector(\'meta[property="og:image"]\');if(oi&&!h.querySelector(\'meta[name="twitter:image"]\')){b("twitter:image",oi.content);}';
    }

    if (!empty($js)) {
        echo '<script>(function(){var h=document.head;function a(p,n,c){var m=document.createElement("meta");m.setAttribute(p,n);m.content=c;h.appendChild(m);}function b(n,c){var m=document.createElement("meta");m.name=n;m.content=c;h.appendChild(m);}' . implode('', $js) . '})();</script>' . "\n";
    }
}, 2);


// ================================================================
// [4] noindex 자동 처리
// ================================================================
Plugin::addHook('after_header', function () use ($_seoCfg) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $noindex = false;

    // 시스템 페이지 noindex
    $pages = [];
    if ($_seoCfg['noindex_login'] === '1') array_push($pages, '/login', '/forgot', '/reset');
    if ($_seoCfg['noindex_register'] === '1') $pages[] = '/register';
    if ($_seoCfg['noindex_search'] === '1') $pages[] = '/search';
    if ($_seoCfg['noindex_mypage'] === '1') array_push($pages, '/mypage', '/profile');

    foreach ($pages as $p) {
        if (str_starts_with($uri, $p)) { $noindex = true; break; }
    }

    // 특정 게시판 noindex
    if (!$noindex && $_seoCfg['noindex_boards']) {
        $boards = array_map('trim', explode(',', $_seoCfg['noindex_boards']));
        foreach ($boards as $bid) {
            if ($bid && str_starts_with($uri, '/board/' . $bid)) { $noindex = true; break; }
        }
    }

    if ($noindex) {
        echo '<script>document.querySelector(\'meta[name="robots"]\')?.setAttribute("content","noindex, nofollow");</script>' . "\n";
    }
}, 3);


// ================================================================
// [5] 검색엔진 인증 메타태그
// ================================================================
Plugin::addHook('after_header', function () use ($_seoCfg) {
    $tags = [];
    if ($_seoCfg['google_verification']) $tags['google-site-verification'] = $_seoCfg['google_verification'];
    if ($_seoCfg['naver_verification']) $tags['naver-site-verification'] = $_seoCfg['naver_verification'];

    if (empty($tags)) return;

    $js = '';
    foreach ($tags as $name => $content) {
        $safe = htmlspecialchars($content, ENT_QUOTES);
        $js .= 'if(!h.querySelector(\'meta[name="' . $name . '"]\')){'
             . 'var m=document.createElement("meta");m.name="' . $name . '";m.content="' . $safe . '";h.appendChild(m);}';
    }
    echo '<script>(function(){var h=document.head;' . $js . '})();</script>' . "\n";
}, 4);


// ================================================================
// [6] 기본 키워드/설명 fallback (게시글 아닌 일반 페이지)
// ================================================================
Plugin::addHook('body_end', function () use ($_seoCfg) {
    $js = '';

    if ($_seoCfg['default_keywords']) {
        $kw = htmlspecialchars($_seoCfg['default_keywords'], ENT_QUOTES);
        $js .= 'var k=h.querySelector(\'meta[name="keywords"]\');if(!k){var m=document.createElement("meta");m.name="keywords";m.content="' . $kw . '";h.appendChild(m);}else if(!k.content){k.content="' . $kw . '";}';
    }

    if ($_seoCfg['default_description']) {
        $dd = htmlspecialchars($_seoCfg['default_description'], ENT_QUOTES);
        $js .= 'var d=h.querySelector(\'meta[name="description"]\');if(d&&!d.content){d.content="' . $dd . '";}';
    }

    if ($js) echo '<script>(function(){var h=document.head;' . $js . '})();</script>' . "\n";
}, 5);


// ================================================================
// [7] 이미지 alt 자동 삽입 + lazy loading
// ================================================================
Plugin::addFilter('post_content', function ($content) use ($_seoCfg) {

    // 이미지 alt 자동 삽입: alt 없는 img에 게시글 제목 삽입
    if ($_seoCfg['auto_image_alt'] === '1') {
        // alt="" (빈값) 또는 alt 자체가 없는 이미지 처리
        $content = preg_replace_callback('/<img\b([^>]*)>/i', function ($m) {
            $attrs = $m[1];
            // 이미 의미있는 alt가 있으면 스킵
            if (preg_match('/alt\s*=\s*"([^"]+)"/i', $attrs)) return $m[0];
            if (preg_match('/alt\s*=\s*\'([^\']+)\'/i', $attrs)) return $m[0];

            // 페이지 제목을 alt로 사용
            $title = strip_tags(ob_get_contents() ?: '');
            $pageTitle = nb_setting('site_title', 'NuriBoard');
            $altText = htmlspecialchars($pageTitle, ENT_QUOTES);

            // alt가 아예 없으면 추가, 빈 alt면 교체
            if (preg_match('/alt\s*=\s*["\']["\']/', $attrs)) {
                $attrs = preg_replace('/alt\s*=\s*["\']["\']/', 'alt="' . $altText . '"', $attrs);
            } else {
                $attrs .= ' alt="' . $altText . '"';
            }
            return '<img' . $attrs . '>';
        }, $content);
    }

    // lazy loading 자동 적용
    if ($_seoCfg['lazy_loading'] === '1') {
        $content = preg_replace_callback('/<img\b([^>]*)>/i', function ($m) {
            $attrs = $m[1];
            if (stripos($attrs, 'loading=') !== false) return $m[0];
            return '<img loading="lazy"' . $attrs . '>';
        }, $content);
    }

    return $content;
}, 5);


// ================================================================
// [8] 관련글 자동 추천 (내부링크 강화)
// ================================================================
if ($_seoCfg['related_posts'] === '1') {
    Plugin::addHook('after_post_content', function ($post) use ($_seoCfg) {
        if (empty($post['board_id']) || empty($post['id'])) return;

        $prefix = DB::getPrefix();
        $limit = (int)($_seoCfg['related_posts_count'] ?: 5);

        // 같은 게시판에서 최근 글 중 현재 글 제외
        try {
            $related = DB::fetchAll(
                "SELECT id, board_id, title, created_at FROM {$prefix}posts WHERE board_id = ? AND id != ? ORDER BY id DESC LIMIT {$limit}",
                [$post['board_id'], $post['id']]
            );
        } catch (Exception $e) {
            $related = [];
        }

        if (empty($related)) return;

        echo '<div class="seo-related">';
        echo '<h4 class="seo-related-title">관련 글</h4>';
        echo '<ul class="seo-related-list">';
        foreach ($related as $r) {
            $url = nb_url('board/' . $r['board_id'] . '/' . $r['id']);
            $date = date('m.d', strtotime($r['created_at']));
            echo '<li><a href="' . $url . '">' . nb_e($r['title']) . '</a><span>' . $date . '</span></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '<style>'
            . '.seo-related{margin:24px 0;padding:16px 20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px}'
            . '.seo-related-title{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:10px}'
            . '.seo-related-list{list-style:none;padding:0;margin:0}'
            . '.seo-related-list li{padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}'
            . '.seo-related-list li:last-child{border-bottom:none}'
            . '.seo-related-list a{font-size:14px;color:#334155;text-decoration:none;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            . '.seo-related-list a:hover{color:#2563eb;text-decoration:underline}'
            . '.seo-related-list span{font-size:12px;color:#94a3b8;flex-shrink:0;margin-left:12px}'
            . '</style>';
    }, 10);
}


// ================================================================
// [9] JSON-LD 구조화 데이터: Comment 스키마 (게시글 댓글)
// ================================================================
if ($_seoCfg['schema_comment'] === '1') {
    Plugin::addHook('after_post_content', function ($post) {
        if (empty($post['id'])) return;

        $prefix = DB::getPrefix();
        try {
            $comments = DB::fetchAll(
                "SELECT c.content, c.created_at, m.nickname FROM {$prefix}comments c LEFT JOIN {$prefix}members m ON c.member_id = m.id WHERE c.post_id = ? ORDER BY c.id ASC LIMIT 10",
                [$post['id']]
            );
        } catch (Exception $e) {
            $comments = [];
        }

        if (empty($comments)) return;

        $siteUrl = nb_setting('site_url', '');
        $commentSchema = [];
        foreach ($comments as $cm) {
            $commentSchema[] = [
                '@type' => 'Comment',
                'text' => mb_strimwidth(strip_tags($cm['content']), 0, 200, '...'),
                'dateCreated' => $cm['created_at'],
                'author' => [
                    '@type' => 'Person',
                    'name' => $cm['nickname'] ?? '익명',
                ],
            ];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => $post['title'],
            'datePublished' => $post['created_at'],
            'url' => $siteUrl . '/board/' . ($post['board_id'] ?? '') . '/' . $post['id'],
            'comment' => $commentSchema,
            'commentCount' => count($commentSchema),
        ];

        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }, 11);
}

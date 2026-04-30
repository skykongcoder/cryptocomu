<?php
/**
 * SEO 메타태그 자동 생성 플러그인
 *
 * 각 게시글의 메타 태그를 자동으로 생성합니다.
 */

// ===== 설정 로드 =====
$_seo_config_file = __DIR__ . '/config.json';
$_seo_config_raw = file_exists($_seo_config_file)
    ? json_decode(file_get_contents($_seo_config_file), true)
    : [];
if (!is_array($_seo_config_raw)) $_seo_config_raw = [];

$_seo_config = array_merge([
    'enabled' => '1',
    'title_suffix' => '1',
    'desc_length' => '150',
    'include_keywords' => '1',
    'og_enabled' => '1',
    'json_ld_enabled' => '1',
    'twitter_card' => '1',
    'canonical' => '1',
], $_seo_config_raw);

// 플러그인이 비활성화되면 중단
if ($_seo_config['enabled'] !== '1') return;

// ===== 메타 태그 생성 함수 =====
function _seo_generate_meta($post) {
    global $_seo_config;

    if (!$post || empty($post['title'])) return; // 게시글 없으면 리턴

    $title = htmlspecialchars($post['title'] ?? '');
    $content = htmlspecialchars(strip_tags($post['content'] ?? ''));
    $author = htmlspecialchars($post['nickname'] ?? 'NuriBoard');
    $url = htmlspecialchars(nb_setting('site_url') . '/post/' . ($post['id'] ?? ''));
    $created = htmlspecialchars($post['created_at'] ?? date('Y-m-d'));

    // description: 본문에서 HTML 태그 제거 후 지정 길이 추출
    $desc = strip_tags($post['content'] ?? '');
    $desc = mb_strimwidth($desc, 0, (int)$_seo_config['desc_length'], '...');
    $desc = htmlspecialchars($desc);

    // keywords: 카테고리 + 태그
    $keywords = [];
    if ($_seo_config['include_keywords'] === '1') {
        if (!empty($post['category'])) $keywords[] = htmlspecialchars($post['category']);
        if (!empty($post['tags'])) {
            $tags = explode(',', $post['tags']);
            foreach ($tags as $tag) {
                $keywords[] = htmlspecialchars(trim($tag));
            }
        }
    }
    $keywords_str = htmlspecialchars(implode(', ', $keywords));

    // 썸네일 (첫 번째 이미지 또는 기본값)
    $thumbnail = htmlspecialchars(nb_setting('site_url')) . '/images/thumbnail.png';
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post['content'] ?? '', $matches)) {
        $thumbnail = htmlspecialchars($matches[1]);
    }

    // ===== 기본 메타 태그 =====
    echo "\n<!-- SEO Meta Tags by seo-meta-generator -->\n";

    // title (이미 존재하므로 수정하지 않음)
    // og:title은 여기서 처리

    echo '<meta name="description" content="' . $desc . '">' . "\n";
    if (!empty($keywords_str)) {
        echo '<meta name="keywords" content="' . $keywords_str . '">' . "\n";
    }
    echo '<meta name="author" content="' . $author . '">' . "\n";

    // ===== Open Graph =====
    if ($_seo_config['og_enabled'] === '1') {
        echo '<meta property="og:title" content="' . $title . '">' . "\n";
        echo '<meta property="og:description" content="' . $desc . '">' . "\n";
        echo '<meta property="og:image" content="' . $thumbnail . '">' . "\n";
        echo '<meta property="og:url" content="' . $url . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
    }

    // ===== Twitter Card =====
    if ($_seo_config['twitter_card'] === '1') {
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . $title . '">' . "\n";
        echo '<meta name="twitter:description" content="' . $desc . '">' . "\n";
        echo '<meta name="twitter:image" content="' . $thumbnail . '">' . "\n";
    }

    // ===== Canonical URL =====
    if ($_seo_config['canonical'] === '1') {
        echo '<link rel="canonical" href="' . $url . '">' . "\n";
    }

    // ===== JSON-LD 스키마 마크업 =====
    if ($_seo_config['json_ld_enabled'] === '1') {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $desc,
            'image' => $thumbnail,
            'datePublished' => $created,
            'author' => [
                '@type' => 'Person',
                'name' => $author
            ]
        ];
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
    }

    echo "<!-- End SEO Meta Tags -->\n\n";
}

// ===== Hook 등록 =====
Plugin::addHook('head', function() {
    global $_seo_config;

    // 전역 변수에서 현재 게시글 정보 가져오기
    $post = $GLOBALS['post'] ?? $GLOBALS['current_post'] ?? null;

    // 게시글 정보가 있으면 메타 태그 생성
    if (!empty($post)) {
        _seo_generate_meta($post);
    }
});

<?php
/**
 * 페이지 속도 최적화 플러그인
 * HTML 압축, lazy loading, DNS prefetch, 브라우저 캐싱, JS defer
 */

$_soConfigFile = __DIR__ . '/config.json';
$_soConfig = file_exists($_soConfigFile) ? json_decode(file_get_contents($_soConfigFile), true) : [];
$_soConfig = array_merge([
    'html_minify' => true,
    'lazy_loading' => true,
    'lazy_iframe' => true,
    'dns_prefetch' => true,
    'js_defer' => true,
    'gzip' => true,
    'browser_cache' => true,
    'remove_query_strings' => false,
    'preload_fonts' => false,
    'custom_prefetch' => '',
], $_soConfig);

// 관리자 페이지는 최적화 안 함
$_soIsAdmin = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false);
if ($_soIsAdmin) return;

// ===== Gzip 압축 =====
if ($_soConfig['gzip'] && !ob_get_level()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        ob_start('ob_gzhandler');
    }
}

// ===== 브라우저 캐싱 헤더 =====
if ($_soConfig['browser_cache']) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // 정적 파일 요청인 경우 (실제로는 웹서버에서 처리하지만 PHP로 서빙되는 경우 대비)
    if (preg_match('/\.(css|js|jpg|jpeg|png|gif|webp|svg|woff2?|ttf|eot|ico)(\?|$)/i', $uri)) {
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    }
}

// ===== DNS Prefetch / Preconnect =====
if ($_soConfig['dns_prefetch']) {
    Plugin::addHook('after_header', function() use ($_soConfig) {
        $domains = [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://cdn.jsdelivr.net',
            'https://www.google-analytics.com',
            'https://www.googletagmanager.com',
        ];
        // 사용자 커스텀 도메인
        if (!empty($_soConfig['custom_prefetch'])) {
            $custom = array_map('trim', explode("\n", $_soConfig['custom_prefetch']));
            $domains = array_merge($domains, array_filter($custom));
        }
        echo "\n<!-- Speed Optimizer: DNS Prefetch -->\n";
        foreach ($domains as $d) {
            $d = rtrim(trim($d), '/');
            if (!$d) continue;
            echo '<link rel="dns-prefetch" href="' . htmlspecialchars($d) . '">' . "\n";
            echo '<link rel="preconnect" href="' . htmlspecialchars($d) . '" crossorigin>' . "\n";
        }
    });
}

// ===== HTML 출력 필터 (lazy loading + HTML 압축 + JS defer) =====
if ($_soConfig['html_minify'] || $_soConfig['lazy_loading'] || $_soConfig['js_defer']) {
    ob_start(function($html) use ($_soConfig) {
        if (empty($html) || strlen($html) < 100) return $html;
        // HTML인지 확인
        if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) return $html;

        // 1. 이미지 lazy loading
        if ($_soConfig['lazy_loading']) {
            $html = _so_addLazyLoading($html);
        }

        // 2. iframe lazy loading
        if ($_soConfig['lazy_iframe']) {
            $html = preg_replace_callback(
                '/<iframe(?![^>]*loading=)([^>]*)>/i',
                function($m) {
                    return '<iframe loading="lazy"' . $m[1] . '>';
                },
                $html
            );
        }

        // 3. JS defer
        if ($_soConfig['js_defer']) {
            $html = _so_addJsDefer($html);
        }

        // 4. 쿼리스트링 제거 (정적 파일)
        if ($_soConfig['remove_query_strings']) {
            $html = preg_replace(
                '/(<(?:link|script)[^>]*(?:href|src)=["\'][^"\']*)\?[^"\']*(["\'])/i',
                '$1$2',
                $html
            );
        }

        // 5. HTML 압축 (마지막에)
        if ($_soConfig['html_minify']) {
            $html = _so_minifyHtml($html);
        }

        return $html;
    });
}

// ===== 이미지 lazy loading 추가 =====
function _so_addLazyLoading(string $html): string
{
    // 이미 loading 속성이 있는 img는 건너뜀
    // 첫 번째 이미지(LCP)는 lazy 적용 안 함
    $count = 0;
    $html = preg_replace_callback(
        '/<img(?![^>]*loading=)([^>]*?)(\s*\/?>)/i',
        function($m) use (&$count) {
            $count++;
            // 상위 3개 이미지는 LCP 후보이므로 lazy 안 함 (로고, 히어로 등)
            if ($count <= 3) return $m[0];
            return '<img loading="lazy"' . $m[1] . $m[2];
        },
        $html
    );
    return $html;
}

// ===== JS defer 추가 =====
function _so_addJsDefer(string $html): string
{
    // 인라인 스크립트와 이미 async/defer가 있는 것은 제외
    $html = preg_replace_callback(
        '/<script([^>]*)\ssrc=(["\'])([^"\']+)\2([^>]*)>/i',
        function($m) {
            $attrs = $m[1] . $m[4];
            // 이미 defer/async가 있으면 패스
            if (preg_match('/\b(defer|async)\b/i', $attrs)) return $m[0];
            // jQuery, 인라인 필수 스크립트는 제외
            $src = $m[3];
            if (stripos($src, 'jquery') !== false) return $m[0];
            if (stripos($src, 'summernote') !== false) return $m[0];
            return '<script' . $m[1] . ' src=' . $m[2] . $m[3] . $m[2] . $m[4] . ' defer>';
        },
        $html
    );
    return $html;
}

// ===== HTML 압축 =====
function _so_minifyHtml(string $html): string
{
    // pre, script, style, textarea 내부는 보존
    $protected = [];
    $idx = 0;

    // 보존할 태그 추출
    $html = preg_replace_callback(
        '/<(pre|script|style|textarea)(\s[^>]*)?>.*?<\/\1>/is',
        function($m) use (&$protected, &$idx) {
            $key = '<!--SO_PROTECT_' . $idx . '-->';
            $protected[$key] = $m[0];
            $idx++;
            return $key;
        },
        $html
    );

    // 공백/줄바꿈 압축
    $html = preg_replace('/\s+/', ' ', $html);
    // 태그 사이 공백 제거
    $html = preg_replace('/>\s+</', '><', $html);
    // HTML 주석 제거 (조건부 주석 제외, 보호 마커 제외)
    $html = preg_replace('/<!--(?!\[|SO_PROTECT_).*?-->/s', '', $html);

    // 보존 태그 복원
    foreach ($protected as $key => $val) {
        $html = str_replace($key, $val, $html);
    }

    return trim($html);
}

// ===== .htaccess 캐싱 규칙 생성 (활성화 시 1회) =====
if ($_soConfig['browser_cache']) {
    $htaccessFile = NB_ROOT . '/.htaccess';
    if (file_exists($htaccessFile)) {
        $htContent = file_get_contents($htaccessFile);
        if (strpos($htContent, 'SpeedOptimizer') === false) {
            $cacheRules = "\n\n# SpeedOptimizer - Browser Cache\n<IfModule mod_expires.c>\nExpiresActive On\nExpiresByType image/webp \"access plus 1 year\"\nExpiresByType image/jpeg \"access plus 1 year\"\nExpiresByType image/png \"access plus 1 year\"\nExpiresByType image/gif \"access plus 1 year\"\nExpiresByType image/svg+xml \"access plus 1 year\"\nExpiresByType text/css \"access plus 1 month\"\nExpiresByType application/javascript \"access plus 1 month\"\nExpiresByType application/x-font-woff \"access plus 1 year\"\nExpiresByType font/woff2 \"access plus 1 year\"\n</IfModule>\n<IfModule mod_deflate.c>\nAddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml\n</IfModule>\n# /SpeedOptimizer\n";
            file_put_contents($htaccessFile, $htContent . $cacheRules);
        }
    }
}

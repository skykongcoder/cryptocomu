<?php
/**
 * 누리코리아 SEO 플러그인
 *
 * 구글/네이버 사이트 인증 + GA4/GTM/페이스북/카카오 픽셀 + 사용자 정의 head HTML
 * 모든 트래킹 스크립트를 안전하게 <head> 영역에 자동 삽입.
 *
 * 설정 키:
 *   google_verification   (코어 SEO.php 에서 자동 메타태그 출력, 공유)
 *   naver_verification    (코어 SEO.php 에서 자동 메타태그 출력, 공유)
 *   nks_ga4_id            (G-XXXXXXXXXX)
 *   nks_gtm_id            (GTM-XXXXXX)
 *   nks_fb_pixel          (페이스북 픽셀 ID, 숫자)
 *   nks_kakao_pixel       (카카오 픽셀 ID, 숫자)
 *   nks_custom_head       (자유 HTML, 고급)
 */

// ==================== 설정 로드 ====================
// 주의: 이 plugin.php 는 Plugin::loadAll() 안에서 실행되고,
// index.php 가 NB_SETTINGS 상수를 정의하기 "이전"에 실행됨.
// 따라서 nb_setting() 을 쓰면 Fatal Error → 사이트 다운.
// 해결: DB 에서 직접 SELECT 하거나 NB_SETTINGS 존재 여부 확인 후 사용.

$__nks_get = function (string $key, string $default = '') {
    // 1차: 이미 NB_SETTINGS 가 정의돼 있으면 사용 (렌더 단계 재진입 등)
    if (defined('NB_SETTINGS')) {
        $s = NB_SETTINGS;
        return isset($s[$key]) ? (string)$s[$key] : $default;
    }
    // 2차: DB 직접 조회 (플러그인 로드 시점)
    if (!class_exists('DB')) return $default;
    try {
        $prefix = DB::getPrefix();
        $row = DB::fetch("SELECT setting_value FROM {$prefix}settings WHERE setting_key = ?", [$key]);
        return $row ? (string)$row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
};

$__nks_gsc      = trim((string) $__nks_get('google_verification', ''));
$__nks_nsc      = trim((string) $__nks_get('naver_verification', ''));
$__nks_ga4      = trim((string) $__nks_get('nks_ga4_id', ''));
$__nks_gtm      = trim((string) $__nks_get('nks_gtm_id', ''));
$__nks_fb       = trim((string) $__nks_get('nks_fb_pixel', ''));
$__nks_kakao    = trim((string) $__nks_get('nks_kakao_pixel', ''));
$__nks_custom   = (string) $__nks_get('nks_custom_head', '');

$__nks_html = '';

// --- 구글 서치콘솔 사이트 인증 ---
if ($__nks_gsc !== '') {
    $v = htmlspecialchars($__nks_gsc, ENT_QUOTES, 'UTF-8');
    $__nks_html .= "<meta name=\"google-site-verification\" content=\"{$v}\">\n";
}

// --- 네이버 서치어드바이저 사이트 인증 ---
if ($__nks_nsc !== '') {
    $v = htmlspecialchars($__nks_nsc, ENT_QUOTES, 'UTF-8');
    $__nks_html .= "<meta name=\"naver-site-verification\" content=\"{$v}\">\n";
}

// --- Google Analytics 4 (gtag.js) ---
if (preg_match('/^G-[A-Z0-9]{4,}$/', $__nks_ga4)) {
    $id = htmlspecialchars($__nks_ga4, ENT_QUOTES, 'UTF-8');
    $__nks_html .= "<!-- Google Analytics 4 (nurikorea-seo) -->\n";
    $__nks_html .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n";
    $__nks_html .= "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>\n";
}

// --- Google Tag Manager ---
if (preg_match('/^GTM-[A-Z0-9]{4,}$/', $__nks_gtm)) {
    $id = htmlspecialchars($__nks_gtm, ENT_QUOTES, 'UTF-8');
    $__nks_html .= "<!-- Google Tag Manager (nurikorea-seo) -->\n";
    $__nks_html .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>\n";
}

// --- 페이스북(Meta) 픽셀 ---
if (preg_match('/^\d{10,20}$/', $__nks_fb)) {
    $id = htmlspecialchars($__nks_fb, ENT_QUOTES, 'UTF-8');
    $__nks_html .= "<!-- Meta Pixel (nurikorea-seo) -->\n";
    $__nks_html .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id}');fbq('track','PageView');</script>\n";
    $__nks_html .= "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1\" alt=\"\"/></noscript>\n";
}

// --- 카카오 픽셀 ---
if (preg_match('/^\d{6,20}$/', $__nks_kakao)) {
    $id = htmlspecialchars($__nks_kakao, ENT_QUOTES, 'UTF-8');
    $__nks_html .= "<!-- Kakao Pixel (nurikorea-seo) -->\n";
    $__nks_html .= "<script src=\"https://t1.daumcdn.net/kas/static/kp.js\"></script>\n";
    $__nks_html .= "<script>kakaoPixel('{$id}').pageView();</script>\n";
}

// --- 사용자 정의 head HTML ---
// 고급 사용자용, 검증 최소화 (script 태그 허용, 단 위험 속성 일부 차단)
if (trim($__nks_custom) !== '') {
    // on* 이벤트 핸들러 + javascript: 프로토콜 차단 (기본 XSS 방어)
    $safe = preg_replace('/\s(on\w+)\s*=\s*(["\']).*?\2/i', '', $__nks_custom);
    $safe = preg_replace('/javascript\s*:/i', '', $safe);
    $__nks_html .= "<!-- Custom Head HTML (nurikorea-seo) -->\n";
    $__nks_html .= $safe . "\n";
}

if ($__nks_html !== '' && class_exists('Plugin') && method_exists('Plugin', 'queueHeaderAsset')) {
    Plugin::queueHeaderAsset($__nks_html);
}

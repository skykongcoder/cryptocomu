<?php
/**
 * 크립토 퓨처리스틱 테마 플러그인
 * 사이버펑크 + 글래스모피즘 + 홀로그램 그라디언트
 */

// Google Fonts (Rajdhani, Orbitron, JetBrains Mono) + 메인 CSS + dark mode 강제
// 관리자 페이지에서는 자체 스킨이 있으므로 미적용
if (function_exists('nb_url') && !defined('NB_ADMIN')) {
    Plugin::queueHeaderAsset(
        '<link rel="preconnect" href="https://fonts.googleapis.com">' .
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
        '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Nanum+Pen+Script&display=swap">' .
        '<link rel="stylesheet" href="' . nb_url('plugins/crypto-theme/assets/crypto.css') . '?v=' . filemtime(__DIR__ . '/assets/crypto.css') . '">' .
        '<script>document.documentElement.setAttribute("data-theme","dark");</script>'
    );
}

// 헤더 직후 - 애니메이션 격자 + 입자
Plugin::addHook('after_header', function () {
    ?>
    <div class="ct-bg" aria-hidden="true">
        <div class="ct-grid"></div>
        <div class="ct-scanline"></div>
        <div class="ct-glow ct-glow-1"></div>
        <div class="ct-glow ct-glow-2"></div>
        <div class="ct-glow ct-glow-3"></div>
    </div>
    <?php
}, 99);

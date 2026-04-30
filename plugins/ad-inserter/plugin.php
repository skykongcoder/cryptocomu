<?php
/**
 * 광고 삽입 플러그인
 * 게시글 상단/하단에 광고 코드 자동 삽입
 */

$_adConfigFile = __DIR__ . '/config.json';
$_adConfigRaw = file_exists($_adConfigFile) ? json_decode(file_get_contents($_adConfigFile), true) : [];
if (!is_array($_adConfigRaw)) $_adConfigRaw = [];
$_adConfig = array_merge([
    'ad_top' => '',
    'ad_bottom' => '',
    'ad_sidebar' => '',
], $_adConfigRaw);

// 게시글 상단 광고
if ($_adConfig['ad_top']) {
    Plugin::addHook('before_post_content', function() use ($_adConfig) {
        echo '<div class="ad-area ad-top" style="margin-bottom:16px;text-align:center">' . $_adConfig['ad_top'] . '</div>';
    });
}

// 게시글 하단 광고
if ($_adConfig['ad_bottom']) {
    Plugin::addHook('after_post_content', function() use ($_adConfig) {
        echo '<div class="ad-area ad-bottom" style="margin-top:16px;text-align:center">' . $_adConfig['ad_bottom'] . '</div>';
    });
}

// 사이드바 광고
if ($_adConfig['ad_sidebar']) {
    Plugin::addHook('sidebar_widget', function() use ($_adConfig) {
        echo '<div class="ad-area ad-sidebar" style="margin-bottom:16px;text-align:center">' . $_adConfig['ad_sidebar'] . '</div>';
    });
}

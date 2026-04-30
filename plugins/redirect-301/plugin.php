<?php
/**
 * 301 리다이렉트 관리
 *
 * 404 페이지가 감지되면 자동으로 메인 페이지로 301 리다이렉트
 */

$_config_file = __DIR__ . '/config.json';
$_config_raw = file_exists($_config_file) ? json_decode(file_get_contents($_config_file), true) : [];
if (!is_array($_config_raw)) $_config_raw = [];

$_config = array_merge([
    'enabled' => '1',
], $_config_raw);

// init 시점에 출력 버퍼링 시작 + 종료 시 404 체크
Plugin::addHook('init', function() use ($_config) {
    if ($_config['enabled'] !== '1') return;

    // 관리자 페이지는 제외
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/admin') !== false) return;

    ob_start();

    register_shutdown_function(function() {
        if (http_response_code() === 404) {
            ob_end_clean();
            header('Location: /', true, 301);
            exit;
        }
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    });
});

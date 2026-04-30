<?php
/**
 * OG 기본 이미지 설정
 * NuriBoard CMS Plugin v1.0
 *
 * 동작 원리:
 * - plugin.php 로드 시점(view.php보다 먼저)에 SEO::setOgImage()로 기본 이미지 세팅
 * - 게시글에 첨부 이미지가 있으면 view.php가 덮어씌움 → 게시글 이미지 우선
 * - 게시글 이미지 없으면 기본 이미지 유지 → 카카오톡 썸네일 표시
 */

function _og_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/og-image';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _og_load_config(): array {
    $default = [
        'enabled'   => false,
        'image_url' => '',
    ];
    $file = _og_data_dir() . '/config.json';
    if (!file_exists($file)) return $default;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge($default, $data) : $default;
}

function _og_save_config(array $config): void {
    file_put_contents(
        _og_data_dir() . '/config.json',
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

// ============================================================
// 핵심 로직 — plugin.php 로드 시점에 바로 실행
// view.php의 SEO::setOgImage() 보다 먼저 세팅 → 기본값으로 작동
// ============================================================
$_og_cfg = _og_load_config();
if (!empty($_og_cfg['enabled']) && !empty($_og_cfg['image_url'])) {
    SEO::setOgImage(trim($_og_cfg['image_url']));
}

<?php
/**
 * SEO 분석기 v1.0
 * 구글 서치콘솔 CSV + AI 분석으로 SEO 개선 전략 제시
 */

// 설정 파일 경로 (data 폴더에 저장 → 플러그인 삭제해도 유지)
function _seo_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/seo-analyzer';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _seo_load_config(): array {
    $file = _seo_data_dir() . '/config.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($data)) $data = [];
    return array_merge([
        'openai_api_key' => '',
        'openai_model'   => 'openai/gpt-4o-mini',
        'site_domain'    => '',
    ], $data);
}

function _seo_save_config(array $config): void {
    file_put_contents(
        _seo_data_dir() . '/config.json',
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

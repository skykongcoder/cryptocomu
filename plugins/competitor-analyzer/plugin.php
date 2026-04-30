<?php
/**
 * 경쟁사 분석 & 콘텐츠 자동 생성 플러그인 v1.0
 * 설정 및 실행은 settings.php에서 처리합니다.
 */

// 데이터 폴더 초기화 (플러그인 삭제 후 재설치해도 설정 유지)
if (!function_exists('_ca_data_dir')) {
    function _ca_data_dir(): string {
        $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
        $dir  = $base . '/data/competitor-analyzer';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

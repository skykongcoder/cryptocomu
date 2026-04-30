<?php
/**
 * Teletify — 텔레그램 관리 시스템 소개
 * NuriBoard CMS Plugin v1.0
 *
 * ============================================================
 * 다음 플러그인 만들 때 복사 후 아래 두 줄만 바꾸면 됨:
 * ============================================================
 */

// ★ 원격 JSON 주소 — 이 파일만 서버에서 수정하면 전체 반영
define('PROMO_JSON_URL', 'https://nuribd.com/promo/teletify.json');

// 캐시 유효 시간 (초) — 12시간
define('PROMO_CACHE_TTL', 43200);

// ============================================================
// 헬퍼
// ============================================================

function _promo_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/promo-teletify';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _promo_get_cache(): ?array {
    $file = _promo_data_dir() . '/promo.json';
    if (!file_exists($file)) return null;
    if (filemtime($file) < time() - PROMO_CACHE_TTL) { @unlink($file); return null; }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function _promo_set_cache(array $data): void {
    file_put_contents(
        _promo_data_dir() . '/promo.json',
        json_encode($data, JSON_UNESCAPED_UNICODE)
    );
}

function _promo_fetch(): ?array {
    // 캐시 확인
    $cached = _promo_get_cache();
    if ($cached !== null) return $cached;

    // 원격 JSON fetch
    $ch = curl_init(PROMO_JSON_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'NuriBoard-Promo/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!is_array($data)) return null;

    _promo_set_cache($data);
    return $data;
}

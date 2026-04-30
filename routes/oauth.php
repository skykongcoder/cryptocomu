<?php
/**
 * NuriBoard - OAuth/소셜 로그인 라우트
 * 카카오, 네이버, 구글 소셜 로그인 및 사이트맵 관련 라우트
 */

// ===== 소셜 로그인 =====

// 카카오 로그인 시작
Router::get('/oauth/kakao', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    if (!nb_setting('kakao_client_id')) Router::redirect(nb_url('login'));
    Router::redirect(Social::kakaoAuthUrl());
});

// 카카오 콜백
Router::get('/oauth/kakao/callback', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    $code  = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    if (!$code) Router::redirect(nb_url('login'));
    $result = Social::kakaoCallback($code, $state);
    if ($result['success']) {
        Point::onLogin(Auth::id());
        Level::checkAndUpgrade(Auth::id());
        Router::redirect(nb_url('/'));
    } else {
        Router::loadTheme('member/login', ['error' => $result['message']]);
    }
});

// 네이버 로그인 시작
Router::get('/oauth/naver', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    if (!nb_setting('naver_client_id')) Router::redirect(nb_url('login'));
    Router::redirect(Social::naverAuthUrl());
});

// 네이버 콜백
Router::get('/oauth/naver/callback', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    $code  = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    if (!$code) Router::redirect(nb_url('login'));
    $result = Social::naverCallback($code, $state);
    if ($result['success']) {
        Point::onLogin(Auth::id());
        Level::checkAndUpgrade(Auth::id());
        Router::redirect(nb_url('/'));
    } else {
        Router::loadTheme('member/login', ['error' => $result['message']]);
    }
});

// 구글 로그인 시작
Router::get('/oauth/google', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    if (!nb_setting('google_client_id')) Router::redirect(nb_url('login'));
    Router::redirect(Social::googleAuthUrl());
});

// 구글 콜백
Router::get('/oauth/google/callback', function () {
    if (Auth::check()) Router::redirect(nb_url('/'));
    $code  = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    if (!$code) Router::redirect(nb_url('login'));
    $result = Social::googleCallback($code, $state);
    if ($result['success']) {
        Point::onLogin(Auth::id());
        Level::checkAndUpgrade(Auth::id());
        Router::redirect(nb_url('/'));
    } else {
        Router::loadTheme('member/login', ['error' => $result['message']]);
    }
});

// 사이트맵
Router::get('/sitemap.xml', function () {
    header('Content-Type: application/xml; charset=utf-8');
    echo SEO::generateSitemap();
    exit;
});

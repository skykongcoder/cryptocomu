<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * Auth.php - 인증/세션 관리
 */

class Auth
{
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // 세션 유지 30일
            ini_set('session.gc_maxlifetime', 30 * 86400);
            session_set_cookie_params(30 * 86400, '/', '', false, true);
            session_start();
        }
    }

    public static function login(string $userId, string $password): array
    {
        // 브루트포스 방지: IP당 15분 내 5회 실패 시 차단
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $lockFile = NB_ROOT . '/data/cache/login_' . md5($ip) . '.json';
        if (file_exists($lockFile)) {
            $lockData = json_decode(file_get_contents($lockFile), true) ?: [];
            $lockData = array_filter($lockData, function($t) { return $t > time() - 900; }); // 15분 내
            if (count($lockData) >= 5) {
                return ['success' => false, 'message' => '로그인 시도가 너무 많습니다. 15분 후 다시 시도하세요.'];
            }
        }

        $prefix = DB::getPrefix();
        $member = DB::fetch("SELECT * FROM {$prefix}members WHERE user_id = ?", [$userId]);

        if (!$member) {
            // 실패 기록
            $lockData = file_exists($lockFile) ? (json_decode(file_get_contents($lockFile), true) ?: []) : [];
            $lockData = array_filter($lockData, function($t) { return $t > time() - 900; });
            $lockData[] = time();
            @file_put_contents($lockFile, json_encode($lockData));
            return ['success' => false, 'message' => '아이디 또는 비밀번호가 일치하지 않습니다.'];
        }

        if (!password_verify($password, $member['password'])) {
            $lockData = file_exists($lockFile) ? (json_decode(file_get_contents($lockFile), true) ?: []) : [];
            $lockData = array_filter($lockData, function($t) { return $t > time() - 900; });
            $lockData[] = time();
            @file_put_contents($lockFile, json_encode($lockData));
            return ['success' => false, 'message' => '아이디 또는 비밀번호가 일치하지 않습니다.'];
        }
        // 로그인 성공 시 실패 기록 삭제
        if (file_exists($lockFile)) @unlink($lockFile);

        // 정지 여부 확인
        if (!empty($member['ban_until']) && strtotime($member['ban_until']) > time()) {
            $until = date('Y년 m월 d일 H:i', strtotime($member['ban_until']));
            return ['success' => false, 'message' => "이용이 정지된 계정입니다. (정지 해제: {$until})"];
        }

        // 세션에 사용자 정보 저장
        $_SESSION['member'] = [
            'id' => $member['id'],
            'user_id' => $member['user_id'],
            'nickname' => $member['nickname'],
            'email' => $member['email'],
            'level' => $member['level'],
            'is_admin' => $member['is_admin'],
            'profile_image' => $member['profile_image'] ?? '',
        ];

        // 마지막 로그인 시간 업데이트
        DB::update("{$prefix}members", ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$member['id']]);

        return ['success' => true, 'message' => '로그인되었습니다.'];
    }

    public static function logout(): void
    {
        // 자동로그인 토큰 삭제
        if (!empty($_COOKIE['nb_remember'])) {
            $prefix = DB::getPrefix();
            DB::delete("{$prefix}remember_tokens", "token = ?", [hash('sha256', $_COOKIE['nb_remember'])]);
            setcookie('nb_remember', '', time() - 86400, '/', '', false, true);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * 자동로그인 토큰 생성 및 쿠키 저장 (30일)
     */
    public static function setRememberToken(int $memberId): void
    {
        $prefix = DB::getPrefix();
        $raw = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() + 30 * 86400);
        // 기존 토큰 정리 (같은 회원)
        DB::delete("{$prefix}remember_tokens", "member_id = ?", [$memberId]);
        DB::insert("{$prefix}remember_tokens", [
            'member_id' => $memberId,
            'token' => $hashed,
            'expires_at' => $expires,
        ]);
        setcookie('nb_remember', $raw, time() + 30 * 86400, '/', '', false, true);
    }

    /**
     * 쿠키로 자동로그인 시도
     */
    public static function tryRememberLogin(): bool
    {
        if (self::check()) return true;
        if (empty($_COOKIE['nb_remember'])) return false;

        $prefix = DB::getPrefix();
        $hashed = hash('sha256', $_COOKIE['nb_remember']);
        $row = DB::fetch("SELECT * FROM {$prefix}remember_tokens WHERE token = ? AND expires_at > NOW()", [$hashed]);
        if (!$row) {
            setcookie('nb_remember', '', time() - 86400, '/', '', false, true);
            return false;
        }

        $member = DB::fetch("SELECT * FROM {$prefix}members WHERE id = ?", [$row['member_id']]);
        if (!$member) {
            DB::delete("{$prefix}remember_tokens", "id = ?", [$row['id']]);
            setcookie('nb_remember', '', time() - 86400, '/', '', false, true);
            return false;
        }

        // 정지 체크
        if (!empty($member['ban_until']) && strtotime($member['ban_until']) > time()) {
            DB::delete("{$prefix}remember_tokens", "member_id = ?", [$member['id']]);
            setcookie('nb_remember', '', time() - 86400, '/', '', false, true);
            return false;
        }

        $_SESSION['member'] = [
            'id' => $member['id'],
            'user_id' => $member['user_id'],
            'nickname' => $member['nickname'],
            'email' => $member['email'],
            'level' => $member['level'],
            'is_admin' => $member['is_admin'],
            'profile_image' => $member['profile_image'] ?? '',
        ];
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['member']);
    }

    public static function user(): ?array
    {
        return $_SESSION['member'] ?? null;
    }

    public static function id(): int
    {
        return (int) ($_SESSION['member']['id'] ?? 0);
    }

    public static function isAdmin(): bool
    {
        return (bool) ($_SESSION['member']['is_admin'] ?? false);
    }

    public static function level(): int
    {
        return (int) ($_SESSION['member']['level'] ?? 0);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Router::redirect(Router::url('login?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::check() || !self::isAdmin()) {
            http_response_code(403);
            echo '<h1>접근 권한이 없습니다.</h1>';
            exit;
        }
    }

    // 소셜 로그인용 — member 배열을 직접 받아 세션 설정
    public static function loginByMember(array $member): void
    {
        // 정지 여부 확인
        if (!empty($member['ban_until']) && strtotime($member['ban_until']) > time()) {
            return; // 정지 계정은 무시 (caller에서 처리)
        }
        $_SESSION['member'] = [
            'id'       => $member['id'],
            'user_id'  => $member['user_id'],
            'nickname' => $member['nickname'],
            'email'    => $member['email'] ?? '',
            'level'    => $member['level'],
            'is_admin' => $member['is_admin'],
        ];
        $prefix = DB::getPrefix();
        DB::update("{$prefix}members", ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$member['id']]);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function verifyCsrfValue(string $token): bool
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . self::csrfToken() . '">';
    }
}

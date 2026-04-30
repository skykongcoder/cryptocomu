<?php
/**
 * NuriBoard - 소셜 로그인 (카카오, 네이버, 구글)
 */
class Social
{
    private static function table(): string
    {
        return DB::getPrefix() . 'social_accounts';
    }

    private static function membersTable(): string
    {
        return DB::getPrefix() . 'members';
    }

    // ─── 카카오 ───────────────────────────────────────────────

    public static function kakaoAuthUrl(): string
    {
        $clientId    = nb_setting('kakao_client_id');
        $redirectUri = self::kakaoRedirectUri();
        $state       = self::generateState('kakao');
        return 'https://kauth.kakao.com/oauth/authorize?'
             . http_build_query([
                 'client_id'     => $clientId,
                 'redirect_uri'  => $redirectUri,
                 'response_type' => 'code',
                 'state'         => $state,
             ]);
    }

    public static function kakaoCallback(string $code, string $state): array
    {
        if (!self::verifyState('kakao', $state)) {
            return ['success' => false, 'message' => '잘못된 요청입니다 (state 불일치).'];
        }
        $token = self::kakaoGetToken($code);
        if (!$token) {
            return ['success' => false, 'message' => '카카오 인증 실패. 다시 시도해 주세요.'];
        }
        $profile = self::kakaoGetProfile($token);
        if (!$profile) {
            return ['success' => false, 'message' => '카카오 프로필을 가져올 수 없습니다.'];
        }
        return self::loginOrCreate('kakao', $profile);
    }

    private static function kakaoRedirectUri(): string
    {
        return rtrim(nb_setting('site_url'), '/') . Router::url('oauth/kakao/callback');
    }

    private static function kakaoGetToken(string $code): ?string
    {
        $params = [
            'grant_type'   => 'authorization_code',
            'client_id'    => nb_setting('kakao_client_id'),
            'redirect_uri' => self::kakaoRedirectUri(),
            'code'         => $code,
        ];
        $resp = self::httpPost('https://kauth.kakao.com/oauth/token', $params);
        return $resp['access_token'] ?? null;
    }

    private static function kakaoGetProfile(string $token): ?array
    {
        $resp = self::httpGet('https://kapi.kakao.com/v2/user/me', [], [
            'Authorization: Bearer ' . $token,
        ]);
        if (empty($resp['id'])) return null;
        $account  = $resp['kakao_account'] ?? [];
        $kakaoProfile = $account['profile'] ?? [];
        return [
            'provider'    => 'kakao',
            'provider_id' => (string)$resp['id'],
            'nickname'    => $kakaoProfile['nickname'] ?? '카카오' . substr($resp['id'], -4),
            'email'       => $account['email'] ?? null,
            'avatar'      => $kakaoProfile['thumbnail_image_url'] ?? null,
        ];
    }

    // ─── 네이버 ───────────────────────────────────────────────

    public static function naverAuthUrl(): string
    {
        $clientId    = nb_setting('naver_client_id');
        $redirectUri = self::naverRedirectUri();
        $state       = self::generateState('naver');
        return 'https://nid.naver.com/oauth2.0/authorize?'
             . http_build_query([
                 'response_type' => 'code',
                 'client_id'     => $clientId,
                 'redirect_uri'  => $redirectUri,
                 'state'         => $state,
             ]);
    }

    public static function naverCallback(string $code, string $state): array
    {
        if (!self::verifyState('naver', $state)) {
            return ['success' => false, 'message' => '잘못된 요청입니다 (state 불일치).'];
        }
        $token = self::naverGetToken($code, $state);
        if (!$token) {
            return ['success' => false, 'message' => '네이버 인증 실패. 다시 시도해 주세요.'];
        }
        $profile = self::naverGetProfile($token);
        if (!$profile) {
            return ['success' => false, 'message' => '네이버 프로필을 가져올 수 없습니다.'];
        }
        return self::loginOrCreate('naver', $profile);
    }

    private static function naverRedirectUri(): string
    {
        return rtrim(nb_setting('site_url'), '/') . Router::url('oauth/naver/callback');
    }

    private static function naverGetToken(string $code, string $state): ?string
    {
        $params = [
            'grant_type'    => 'authorization_code',
            'client_id'     => nb_setting('naver_client_id'),
            'client_secret' => nb_setting('naver_client_secret'),
            'redirect_uri'  => self::naverRedirectUri(),
            'code'          => $code,
            'state'         => $state,
        ];
        $resp = self::httpPost('https://nid.naver.com/oauth2.0/token', $params);
        return $resp['access_token'] ?? null;
    }

    private static function naverGetProfile(string $token): ?array
    {
        $resp = self::httpGet('https://openapi.naver.com/v1/nid/me', [], [
            'Authorization: Bearer ' . $token,
        ]);
        $info = $resp['response'] ?? null;
        if (!$info || empty($info['id'])) return null;
        return [
            'provider'    => 'naver',
            'provider_id' => (string)$info['id'],
            'nickname'    => $info['nickname'] ?? '네이버' . substr($info['id'], -4),
            'email'       => $info['email'] ?? null,
            'avatar'      => $info['profile_image'] ?? null,
        ];
    }

    // ─── 구글 ───────────────────────────────────────────────

    public static function googleAuthUrl(): string
    {
        $clientId    = nb_setting('google_client_id');
        $redirectUri = self::googleRedirectUri();
        $state       = self::generateState('google');
        return 'https://accounts.google.com/o/oauth2/v2/auth?'
             . http_build_query([
                 'client_id'     => $clientId,
                 'redirect_uri'  => $redirectUri,
                 'response_type' => 'code',
                 'scope'         => 'openid email profile',
                 'state'         => $state,
                 'access_type'   => 'online',
                 'prompt'        => 'select_account',
             ]);
    }

    public static function googleCallback(string $code, string $state): array
    {
        if (!self::verifyState('google', $state)) {
            return ['success' => false, 'message' => '잘못된 요청입니다 (state 불일치).'];
        }
        $token = self::googleGetToken($code);
        if (!$token) {
            return ['success' => false, 'message' => '구글 인증 실패. 다시 시도해 주세요.'];
        }
        $profile = self::googleGetProfile($token);
        if (!$profile) {
            return ['success' => false, 'message' => '구글 프로필을 가져올 수 없습니다.'];
        }
        return self::loginOrCreate('google', $profile);
    }

    private static function googleRedirectUri(): string
    {
        return rtrim(nb_setting('site_url'), '/') . Router::url('oauth/google/callback');
    }

    private static function googleGetToken(string $code): ?string
    {
        $params = [
            'grant_type'    => 'authorization_code',
            'client_id'     => nb_setting('google_client_id'),
            'client_secret' => nb_setting('google_client_secret'),
            'redirect_uri'  => self::googleRedirectUri(),
            'code'          => $code,
        ];
        $resp = self::httpPost('https://oauth2.googleapis.com/token', $params);
        return $resp['access_token'] ?? null;
    }

    private static function googleGetProfile(string $token): ?array
    {
        $resp = self::httpGet('https://www.googleapis.com/oauth2/v2/userinfo', [], [
            'Authorization: Bearer ' . $token,
        ]);
        if (empty($resp['id'])) return null;
        return [
            'provider'    => 'google',
            'provider_id' => (string)$resp['id'],
            'nickname'    => $resp['name'] ?? ($resp['given_name'] ?? '구글' . substr($resp['id'], -4)),
            'email'       => $resp['email'] ?? null,
            'avatar'      => $resp['picture'] ?? null,
        ];
    }

    // ─── 공통: 로그인 또는 회원 생성 ─────────────────────────

    private static function loginOrCreate(string $provider, array $profile): array
    {
        $providerId = $profile['provider_id'];

        // 1) 기존 소셜 계정 확인
        $social = DB::fetch(
            "SELECT * FROM " . self::table() . " WHERE provider = ? AND provider_id = ?",
            [$provider, $providerId]
        );
        if ($social) {
            $member = DB::fetch(
                "SELECT * FROM " . self::membersTable() . " WHERE id = ?",
                [$social['member_id']]
            );
            if ($member) {
                Auth::loginByMember($member);
                return ['success' => true, 'new' => false];
            }
        }

        // 2) 이메일로 기존 회원 확인 후 연동
        if (!empty($profile['email'])) {
            $member = DB::fetch(
                "SELECT * FROM " . self::membersTable() . " WHERE email = ?",
                [$profile['email']]
            );
            if ($member) {
                // 소셜 계정 연동
                self::link($member['id'], $provider, $providerId);
                Auth::loginByMember($member);
                return ['success' => true, 'new' => false, 'linked' => true];
            }
        }

        // 3) 새 회원 생성
        $nickname = self::uniqueNickname($profile['nickname']);
        $userId   = $provider . '_' . $providerId;
        $password = bin2hex(random_bytes(16)); // 랜덤 비밀번호 (소셜 전용)

        $result = Member::register([
            'user_id'  => $userId,
            'password' => $password,
            'nickname' => $nickname,
            'email'    => $profile['email'] ?? '',
        ]);

        if (!$result['success']) {
            // user_id 충돌 시 suffix 추가 후 재시도
            $userId   = $provider . '_' . $providerId . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
            $result = Member::register([
                'user_id'  => $userId,
                'password' => $password,
                'nickname' => $nickname,
                'email'    => $profile['email'] ?? '',
            ]);
        }

        if (!$result['success']) {
            return ['success' => false, 'message' => '회원 생성에 실패했습니다.'];
        }

        $member = Member::find((int)$result['id']);
        if (!$member) {
            return ['success' => false, 'message' => '회원 정보를 불러올 수 없습니다.'];
        }

        self::link($member['id'], $provider, $providerId);
        Auth::loginByMember($member);
        return ['success' => true, 'new' => true];
    }

    public static function link(int $memberId, string $provider, string $providerId): void
    {
        $exists = DB::fetch(
            "SELECT id FROM " . self::table() . " WHERE provider = ? AND provider_id = ?",
            [$provider, $providerId]
        );
        if (!$exists) {
            DB::insert(self::table(), [
                'member_id'   => $memberId,
                'provider'    => $provider,
                'provider_id' => $providerId,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // 연동된 소셜 계정 목록
    public static function linkedAccounts(int $memberId): array
    {
        return DB::fetchAll(
            "SELECT * FROM " . self::table() . " WHERE member_id = ?",
            [$memberId]
        );
    }

    // 소셜 계정 연동 해제
    public static function unlink(int $memberId, string $provider): bool
    {
        $pdo = DB::getInstance();
        $stmt = $pdo->prepare(
            "DELETE FROM " . self::table() . " WHERE member_id = ? AND provider = ?"
        );
        return $stmt->execute([$memberId, $provider]);
    }

    // ─── 내부 헬퍼 ───────────────────────────────────────────

    private static function generateState(string $prefix): string
    {
        $state = $prefix . '_' . bin2hex(random_bytes(16));
        $_SESSION['oauth_state_' . $prefix] = $state;
        return $state;
    }

    private static function verifyState(string $prefix, string $state): bool
    {
        $key    = 'oauth_state_' . $prefix;
        $stored = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);
        return $stored !== '' && hash_equals($stored, $state);
    }

    private static function uniqueNickname(string $base): string
    {
        $nick = mb_substr($base, 0, 15);
        $try  = $nick;
        $i    = 2;
        $table = DB::getPrefix() . 'members';
        while (DB::fetch("SELECT id FROM {$table} WHERE nickname = ?", [$try])) {
            $try = $nick . $i++;
        }
        return $try;
    }

    private static function httpPost(string $url, array $params): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body ? json_decode($body, true) : null;
    }

    private static function httpGet(string $url, array $params = [], array $headers = []): ?array
    {
        if ($params) $url .= '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body ? json_decode($body, true) : null;
    }
}

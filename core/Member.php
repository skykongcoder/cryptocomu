<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * Member.php - 회원 관리
 */

class Member
{
    private static function table(): string
    {
        return DB::getPrefix() . 'members';
    }

    public static function register(array $data): array
    {
        $table = self::table();

        // 중복 체크
        if (DB::fetch("SELECT id FROM {$table} WHERE user_id = ?", [$data['user_id']])) {
            return ['success' => false, 'message' => '이미 사용 중인 아이디입니다.'];
        }
        if (DB::fetch("SELECT id FROM {$table} WHERE nickname = ?", [$data['nickname']])) {
            return ['success' => false, 'message' => '이미 사용 중인 닉네임입니다.'];
        }

        $id = DB::insert($table, [
            'user_id' => $data['user_id'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'nickname' => $data['nickname'],
            'email' => $data['email'] ?? '',
            'level' => 2,
            'point' => 0,
            'is_admin' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => '회원가입이 완료되었습니다.', 'id' => $id];
    }

    public static function find(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    public static function findByUserId(string $userId): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE user_id = ?", [$userId]);
    }

    public static function update(int $id, array $data): bool
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        return DB::update(self::table(), $data, 'id = ?', [$id]) > 0;
    }

    public static function delete(int $id): bool
    {
        return DB::delete(self::table(), 'id = ?', [$id]) > 0;
    }

    public static function list(int $page = 1, int $perPage = 20, string $search = '', string $filter = ''): array
    {
        $table = self::table();
        $offset = ($page - 1) * $perPage;

        $where = '1';
        $params = [];
        if ($search) {
            $where = "(user_id LIKE ? OR nickname LIKE ? OR email LIKE ?)";
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }
        if ($filter === 'banned') {
            $where .= " AND ban_until > NOW()";
        }

        $total = DB::count($table, $where, $params);
        $members = DB::fetchAll(
            "SELECT id, user_id, nickname, email, level, point, is_admin, warnings, ban_until, created_at, last_login FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'members' => $members,
            'total' => $total,
            'page' => $page,
            'total_pages' => max(1, ceil($total / $perPage)),
        ];
    }

    public static function count(): int
    {
        return DB::count(self::table());
    }

    public static function findByEmail(string $email): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE email = ?", [$email]);
    }

    /** 비밀번호 재설정 토큰 생성 (DB 저장) */
    public static function createResetToken(int $memberId): string
    {
        $table = DB::getPrefix() . 'password_resets';
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1시간
        // 기존 토큰 삭제 후 새로 생성
        DB::delete($table, 'member_id = ?', [$memberId]);
        DB::insert($table, [
            'member_id' => $memberId,
            'token' => $token,
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /** 토큰으로 회원 조회 (유효기간 체크) */
    public static function findByResetToken(string $token): ?array
    {
        $table = DB::getPrefix() . 'password_resets';
        $row = DB::fetch(
            "SELECT r.*, m.id as member_id, m.user_id, m.nickname, m.email
             FROM {$table} r
             JOIN " . DB::getPrefix() . "members m ON r.member_id = m.id
             WHERE r.token = ? AND r.expires_at > NOW()",
            [$token]
        );
        return $row ?: null;
    }

    /** 토큰 삭제 (사용 후) */
    public static function deleteResetToken(string $token): void
    {
        DB::delete(DB::getPrefix() . 'password_resets', 'token = ?', [$token]);
    }

    /** 경고 추가 (누적 횟수 반환) */
    public static function addWarning(int $memberId, string $reason = ''): int
    {
        $table = self::table();
        DB::query("UPDATE {$table} SET warnings = warnings + 1 WHERE id = ?", [$memberId]);
        $member = self::find($memberId);
        $count  = (int)($member['warnings'] ?? 0);

        // DB에 경고 내역 기록
        DB::insert(DB::getPrefix() . 'member_warnings', [
            'member_id'  => $memberId,
            'admin_id'   => 0, // 호출 전에 AdminLog::write로 기록
            'reason'     => $reason,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $count;
    }

    /** 정지 처리 */
    public static function ban(int $memberId, int $days = 7): void
    {
        $until = date('Y-m-d H:i:s', time() + $days * 86400);
        DB::update(self::table(), ['ban_until' => $until], 'id = ?', [$memberId]);
    }

    /** 정지 해제 */
    public static function unban(int $memberId): void
    {
        DB::update(self::table(), ['ban_until' => null, 'warnings' => 0], 'id = ?', [$memberId]);
    }

    /** 정지 여부 확인 */
    public static function isBanned(int $memberId): bool
    {
        $member = self::find($memberId);
        if (!$member || empty($member['ban_until'])) return false;
        return strtotime($member['ban_until']) > time();
    }

    /** 정지 만료 시각 */
    public static function banUntil(int $memberId): ?string
    {
        $member = self::find($memberId);
        if (!$member || empty($member['ban_until'])) return null;
        if (strtotime($member['ban_until']) <= time()) return null;
        return $member['ban_until'];
    }

    /** 경고 내역 조회 */
    public static function warnings(int $memberId): array
    {
        $table  = DB::getPrefix() . 'member_warnings';
        $prefix = DB::getPrefix();
        return DB::fetchAll(
            "SELECT w.*, m.nickname as admin_name
             FROM {$table} w
             LEFT JOIN {$prefix}members m ON w.admin_id = m.id
             WHERE w.member_id = ?
             ORDER BY w.id DESC",
            [$memberId]
        );
    }
}

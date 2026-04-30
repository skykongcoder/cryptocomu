<?php
/**
 * NuriBoard - 팔로우 시스템
 */

class Follow
{
    private static function table(): string
    {
        return DB::getPrefix() . 'follows';
    }

    public static function ensureTable(): void
    {
        $t = self::table();
        DB::getInstance()->exec("CREATE TABLE IF NOT EXISTS {$t} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT NOT NULL,
            target_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_follow (follower_id, target_id),
            INDEX idx_target (target_id),
            INDEX idx_follower (follower_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function follow(int $followerId, int $targetId): bool
    {
        if ($followerId === $targetId || $followerId <= 0 || $targetId <= 0) return false;
        if (self::isFollowing($followerId, $targetId)) return false;

        DB::insert(self::table(), [
            'follower_id' => $followerId,
            'target_id' => $targetId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 알림 전송
        $follower = Member::find($followerId);
        if ($follower) {
            DB::insert(DB::getPrefix() . 'notifications', [
                'member_id' => $targetId,
                'type' => 'follow',
                'message' => $follower['nickname'] . '님이 회원님을 팔로우했습니다.',
                'link' => '/member/' . $followerId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return true;
    }

    public static function unfollow(int $followerId, int $targetId): bool
    {
        return DB::delete(self::table(), 'follower_id = ? AND target_id = ?', [$followerId, $targetId]) > 0;
    }

    public static function isFollowing(int $followerId, int $targetId): bool
    {
        return (bool) DB::fetch(
            "SELECT id FROM " . self::table() . " WHERE follower_id = ? AND target_id = ?",
            [$followerId, $targetId]
        );
    }

    public static function followerCount(int $targetId): int
    {
        return DB::count(self::table(), 'target_id = ?', [$targetId]);
    }

    public static function followingCount(int $memberId): int
    {
        return DB::count(self::table(), 'follower_id = ?', [$memberId]);
    }

    public static function listFollowers(int $targetId, int $page = 1, int $perPage = 30): array
    {
        $prefix = DB::getPrefix();
        $offset = max(0, ($page - 1) * $perPage);
        $total = self::followerCount($targetId);
        $rows = DB::fetchAll(
            "SELECT m.id, m.nickname, m.level, m.profile_image, m.created_at, f.created_at as followed_at
             FROM " . self::table() . " f
             INNER JOIN {$prefix}members m ON f.follower_id = m.id
             WHERE f.target_id = ?
             ORDER BY f.id DESC LIMIT {$perPage} OFFSET {$offset}",
            [$targetId]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => max(1, (int)ceil($total / $perPage))];
    }

    public static function listFollowing(int $memberId, int $page = 1, int $perPage = 30): array
    {
        $prefix = DB::getPrefix();
        $offset = max(0, ($page - 1) * $perPage);
        $total = self::followingCount($memberId);
        $rows = DB::fetchAll(
            "SELECT m.id, m.nickname, m.level, m.profile_image, m.created_at, f.created_at as followed_at
             FROM " . self::table() . " f
             INNER JOIN {$prefix}members m ON f.target_id = m.id
             WHERE f.follower_id = ?
             ORDER BY f.id DESC LIMIT {$perPage} OFFSET {$offset}",
            [$memberId]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => max(1, (int)ceil($total / $perPage))];
    }
}

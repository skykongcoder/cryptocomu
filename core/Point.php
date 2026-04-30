<?php
/**
 * NuriBoard - 포인트 시스템
 */

class Point
{
    private static function table(): string
    {
        return DB::getPrefix() . 'points';
    }

    public static function give(int $memberId, int $point, string $reason): void
    {
        if ($point === 0) return;

        DB::insert(self::table(), [
            'member_id' => $memberId,
            'point' => $point,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 회원 포인트 업데이트
        $prefix = DB::getPrefix();
        DB::query("UPDATE {$prefix}members SET point = point + ? WHERE id = ?", [$point, $memberId]);

        // 세션 포인트도 갱신
        if (isset($_SESSION['member']) && $_SESSION['member']['id'] === $memberId) {
            $_SESSION['member']['point'] = ($_SESSION['member']['point'] ?? 0) + $point;
        }
    }

    public static function onWrite(int $memberId): void
    {
        $pt = (int) nb_setting('point_write', '10');
        if ($pt > 0) self::give($memberId, $pt, '글 작성');
    }

    public static function onDeletePost(int $memberId): void
    {
        $pt = (int) nb_setting('point_write', '10');
        if ($pt > 0) self::give($memberId, -$pt, '글 삭제');
    }

    public static function onComment(int $memberId): void
    {
        $pt = (int) nb_setting('point_comment', '5');
        if ($pt > 0) self::give($memberId, $pt, '댓글 작성');
    }

    public static function onDeleteComment(int $memberId): void
    {
        $pt = (int) nb_setting('point_comment', '5');
        if ($pt > 0) self::give($memberId, -$pt, '댓글 삭제');
    }

    public static function onLogin(int $memberId): void
    {
        // 하루에 한 번만 지급
        $today = DB::fetch(
            "SELECT id FROM " . self::table() . " WHERE member_id = ? AND reason = '로그인' AND DATE(created_at) = CURDATE()",
            [$memberId]
        );
        if ($today) return;

        $pt = (int) nb_setting('point_login', '3');
        if ($pt > 0) self::give($memberId, $pt, '로그인');
    }

    public static function history(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $table = self::table();
        $offset = ($page - 1) * $perPage;
        $total = DB::count($table, 'member_id = ?', [$memberId]);
        $rows = DB::fetchAll(
            "SELECT * FROM {$table} WHERE member_id = ? ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            [$memberId]
        );
        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'total_pages' => max(1, ceil($total / $perPage)),
        ];
    }

    public static function todayTotal(): int
    {
        $today = date('Y-m-d');
        $row = DB::fetch("SELECT COALESCE(SUM(point), 0) as total FROM " . self::table() . " WHERE point > 0 AND DATE(created_at) = ?", [$today]);
        return (int) ($row['total'] ?? 0);
    }
}

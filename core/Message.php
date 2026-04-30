<?php
/**
 * NuriBoard - 쪽지 시스템
 */

class Message
{
    private static function table(): string
    {
        return DB::getPrefix() . 'messages';
    }

    /** 쪽지 보내기 */
    public static function send(int $senderId, int $receiverId, string $title, string $content): int
    {
        return DB::insert(self::table(), [
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'title'       => $title,
            'content'     => $content,
            'is_read'     => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** 받은 쪽지함 */
    public static function inbox(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $table  = self::table();
        $prefix = DB::getPrefix();
        $offset = ($page - 1) * $perPage;
        $total  = DB::count($table, 'receiver_id = ? AND receiver_deleted = 0', [$memberId]);
        $rows   = DB::fetchAll(
            "SELECT m.*, s.nickname as sender_name, s.level as sender_level
             FROM {$table} m
             LEFT JOIN {$prefix}members s ON m.sender_id = s.id
             WHERE m.receiver_id = ? AND m.receiver_deleted = 0
             ORDER BY m.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$memberId]
        );
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'total_pages' => max(1, ceil($total / $perPage))];
    }

    /** 보낸 쪽지함 */
    public static function outbox(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $table  = self::table();
        $prefix = DB::getPrefix();
        $offset = ($page - 1) * $perPage;
        $total  = DB::count($table, 'sender_id = ? AND sender_deleted = 0', [$memberId]);
        $rows   = DB::fetchAll(
            "SELECT m.*, r.nickname as receiver_name, r.level as receiver_level
             FROM {$table} m
             LEFT JOIN {$prefix}members r ON m.receiver_id = r.id
             WHERE m.sender_id = ? AND m.sender_deleted = 0
             ORDER BY m.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$memberId]
        );
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'total_pages' => max(1, ceil($total / $perPage))];
    }

    /** 쪽지 단건 조회 */
    public static function find(int $id): ?array
    {
        $table  = self::table();
        $prefix = DB::getPrefix();
        return DB::fetch(
            "SELECT m.*, s.nickname as sender_name, s.level as sender_level,
                    r.nickname as receiver_name, r.level as receiver_level
             FROM {$table} m
             LEFT JOIN {$prefix}members s ON m.sender_id = s.id
             LEFT JOIN {$prefix}members r ON m.receiver_id = r.id
             WHERE m.id = ?",
            [$id]
        );
    }

    /** 읽음 처리 */
    public static function markRead(int $id): void
    {
        DB::update(self::table(), ['is_read' => 1], 'id = ?', [$id]);
    }

    /** 삭제 (soft delete — 양쪽 모두 삭제해야 실제 삭제) */
    public static function deleteForReceiver(int $id): void
    {
        DB::update(self::table(), ['receiver_deleted' => 1], 'id = ?', [$id]);
        self::cleanIfBothDeleted($id);
    }

    public static function deleteForSender(int $id): void
    {
        DB::update(self::table(), ['sender_deleted' => 1], 'id = ?', [$id]);
        self::cleanIfBothDeleted($id);
    }

    private static function cleanIfBothDeleted(int $id): void
    {
        $msg = DB::fetch("SELECT sender_deleted, receiver_deleted FROM " . self::table() . " WHERE id = ?", [$id]);
        if ($msg && $msg['sender_deleted'] && $msg['receiver_deleted']) {
            DB::delete(self::table(), 'id = ?', [$id]);
        }
    }

    /** 읽지 않은 쪽지 수 */
    public static function unreadCount(int $memberId): int
    {
        return DB::count(self::table(), 'receiver_id = ? AND is_read = 0 AND receiver_deleted = 0', [$memberId]);
    }

    /** 닉네임으로 회원 찾기 (쪽지 수신자 검색) */
    public static function findMemberByNickname(string $nickname): ?array
    {
        return DB::fetch(
            "SELECT id, nickname, level FROM " . DB::getPrefix() . "members WHERE nickname = ?",
            [$nickname]
        );
    }
}

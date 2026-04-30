<?php
/**
 * NuriBoard - 신고 관리
 */

class Report
{
    private static function table(): string
    {
        return DB::getPrefix() . 'reports';
    }

    /** 신고 접수 */
    public static function create(array $data): int|false
    {
        $table = self::table();

        // 동일 유저가 같은 대상을 중복 신고하면 막기
        $exists = DB::fetch(
            "SELECT id FROM {$table} WHERE type = ? AND target_id = ? AND reporter_id = ?",
            [$data['type'], $data['target_id'], $data['reporter_id']]
        );
        if ($exists) return false;

        return DB::insert($table, [
            'type'        => $data['type'],       // 'post' | 'comment'
            'target_id'   => $data['target_id'],
            'reporter_id' => $data['reporter_id'],
            'reason'      => $data['reason'],
            'status'      => 'pending',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** 목록 (관리자) */
    public static function list(int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $table  = self::table();
        $prefix = DB::getPrefix();
        $offset = ($page - 1) * $perPage;

        $where  = $status ? "r.status = '{$status}'" : '1';
        $total  = DB::count("{$table} r", $where);

        $rows = DB::fetchAll(
            "SELECT r.*,
                    m.nickname AS reporter_name,
                    am.nickname AS admin_name
             FROM {$table} r
             LEFT JOIN {$prefix}members m  ON r.reporter_id = m.id
             LEFT JOIN {$prefix}members am ON r.resolved_by = am.id
             WHERE {$where}
             ORDER BY r.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            []
        );

        return [
            'items'       => $rows,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => max(1, ceil($total / $perPage)),
        ];
    }

    public static function find(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    /** 처리 (승인 → 콘텐츠 숨김 / 기각) */
    public static function resolve(int $id, string $status, int $adminId): void
    {
        DB::update(self::table(), [
            'status'      => $status,   // 'approved' | 'rejected'
            'resolved_by' => $adminId,
            'resolved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    /** 미처리 신고 수 */
    public static function pendingCount(): int
    {
        return DB::count(self::table(), 'status = ?', ['pending']);
    }
}

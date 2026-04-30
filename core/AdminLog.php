<?php
/**
 * NuriBoard - 관리자 활동 로그
 */

class AdminLog
{
    private static function table(): string
    {
        return DB::getPrefix() . 'admin_logs';
    }

    /** 로그 기록 */
    public static function write(string $action, string $targetType = '', int $targetId = 0, string $detail = ''): void
    {
        if (!Auth::check()) return;
        DB::insert(self::table(), [
            'admin_id'    => Auth::id(),
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'detail'      => $detail,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** 목록 조회 */
    public static function list(int $page = 1, int $perPage = 30): array
    {
        $table  = self::table();
        $prefix = DB::getPrefix();
        $offset = ($page - 1) * $perPage;
        $total  = DB::count($table);
        $rows   = DB::fetchAll(
            "SELECT l.*, m.nickname as admin_name
             FROM {$table} l
             LEFT JOIN {$prefix}members m ON l.admin_id = m.id
             ORDER BY l.id DESC
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

    // 자주 쓰는 액션 상수
    const ACTION_LABELS = [
        'member_warn'    => '경고 부여',
        'member_ban'     => '회원 정지',
        'member_unban'   => '정지 해제',
        'member_update'  => '회원 정보 수정',
        'member_delete'  => '회원 삭제',
        'post_delete'    => '게시글 삭제',
        'post_hide'      => '게시글 숨김',
        'report_approve' => '신고 승인',
        'report_reject'  => '신고 기각',
        'board_create'   => '게시판 생성',
        'board_update'   => '게시판 수정',
        'board_delete'   => '게시판 삭제',
        'settings_save'  => '사이트 설정 변경',
        'banner_create'  => '배너 추가',
        'banner_delete'  => '배너 삭제',
    ];
}

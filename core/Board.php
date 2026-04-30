<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * Board.php - 게시판 관리
 */

class Board
{
    private static function table(): string
    {
        return DB::getPrefix() . 'boards';
    }

    public static function findById(string $boardId): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE board_id = ?", [$boardId]);
    }

    public static function find(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return DB::insert(self::table(), [
            'board_id' => $data['board_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'board_type' => $data['board_type'] ?? 'normal',
            'categories' => $data['categories'] ?? '',
            'list_count' => $data['list_count'] ?? 20,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'write_level' => $data['write_level'] ?? 2,
            'comment_level' => $data['comment_level'] ?? 2,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        return DB::update(self::table(), $data, 'id = ?', [$id]) > 0;
    }

    public static function delete(int $id): bool
    {
        $board = self::find($id);
        if (!$board) return false;

        // 게시판의 댓글 삭제
        $prefix = DB::getPrefix();
        DB::query("DELETE c FROM {$prefix}comments c INNER JOIN {$prefix}posts p ON c.post_id = p.id WHERE p.board_id = ?", [$board['board_id']]);
        // 게시판의 글 삭제
        DB::delete("{$prefix}posts", "board_id = ?", [$board['board_id']]);
        // 게시판 삭제
        return DB::delete(self::table(), 'id = ?', [$id]) > 0;
    }

    public static function listAll(bool $activeOnly = false): array
    {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        return DB::fetchAll("SELECT * FROM " . self::table() . " {$where} ORDER BY sort_order ASC, id ASC");
    }

    public static function count(): int
    {
        return DB::count(self::table());
    }

    public static function postCount(string $boardId): int
    {
        return DB::count(DB::getPrefix() . 'posts', 'board_id = ?', [$boardId]);
    }
}

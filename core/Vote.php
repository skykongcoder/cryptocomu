<?php
/**
 * NuriBoard - 추천/비추천 시스템
 */

class Vote
{
    private static function table(): string
    {
        return DB::getPrefix() . 'votes';
    }

    /**
     * 추천 또는 비추천
     * @param int $postId
     * @param int $memberId
     * @param int $type 1=추천, -1=비추천
     * @return array ['success'=>bool, 'message'=>string, 'vote_up'=>int, 'vote_down'=>int]
     */
    public static function vote(int $postId, int $memberId, int $type): array
    {
        $table = self::table();
        $postTable = DB::getPrefix() . 'posts';
        $post = Post::find($postId);

        if (!$post) {
            return ['success' => false, 'message' => '게시글을 찾을 수 없습니다.'];
        }

        // 본인 글 추천 불가
        if ($post['member_id'] === $memberId) {
            return ['success' => false, 'message' => '본인 글은 추천할 수 없습니다.'];
        }

        // 이미 투표했는지 확인
        $existing = DB::fetch("SELECT * FROM {$table} WHERE post_id = ? AND member_id = ?", [$postId, $memberId]);

        if ($existing) {
            if ($existing['vote_type'] == $type) {
                return ['success' => false, 'message' => '이미 ' . ($type === 1 ? '추천' : '비추천') . '하셨습니다.'];
            }
            // 변경: 추천→비추천 또는 비추천→추천
            DB::update($table, ['vote_type' => $type], 'id = ?', [$existing['id']]);
        } else {
            DB::insert($table, [
                'post_id' => $postId,
                'member_id' => $memberId,
                'vote_type' => $type,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 집계 업데이트 (votes 테이블 기준으로 정확히 계산)
        $upRow = DB::fetch("SELECT COUNT(*) as cnt FROM {$table} WHERE post_id = ? AND vote_type = 1", [$postId]);
        $downRow = DB::fetch("SELECT COUNT(*) as cnt FROM {$table} WHERE post_id = ? AND vote_type = -1", [$postId]);
        $up = (int)($upRow['cnt'] ?? 0);
        $down = (int)($downRow['cnt'] ?? 0);
        DB::update($postTable, ['vote_up' => $up, 'vote_down' => $down], 'id = ?', [$postId]);

        return [
            'success' => true,
            'message' => ($type === 1 ? '추천' : '비추천') . '했습니다.',
            'vote_up' => $up,
            'vote_down' => $down,
        ];
    }

    /**
     * 사용자가 해당 글에 투표했는지 확인
     * @return int 0=안함, 1=추천, -1=비추천
     */
    public static function getUserVote(int $postId, int $memberId): int
    {
        $row = DB::fetch("SELECT vote_type FROM " . self::table() . " WHERE post_id = ? AND member_id = ?", [$postId, $memberId]);
        return $row ? (int)$row['vote_type'] : 0;
    }
}

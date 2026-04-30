<?php
/**
 * NuriBoard - 댓글 CRUD (대댓글 지원)
 */

class Comment
{
    private static function table(): string
    {
        return DB::getPrefix() . 'comments';
    }

    /** 채택 시스템 마이그레이션 (요청당 1회) */
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $p = DB::getPrefix();
            $pdo = DB::getInstance();
            // posts 컬럼
            $cols = $pdo->query("SHOW COLUMNS FROM {$p}posts LIKE 'adopted_comment_id'")->fetch();
            if (!$cols) {
                $pdo->exec("ALTER TABLE {$p}posts ADD COLUMN adopted_comment_id INT DEFAULT NULL, ADD COLUMN adopted_at DATETIME DEFAULT NULL");
            }
            // comments 컬럼
            $cols = $pdo->query("SHOW COLUMNS FROM {$p}comments LIKE 'is_adopted'")->fetch();
            if (!$cols) {
                $pdo->exec("ALTER TABLE {$p}comments ADD COLUMN is_adopted TINYINT DEFAULT 0, ADD COLUMN adopted_at DATETIME DEFAULT NULL");
            }
        } catch (\Exception $e) { /* ignore */ }
    }

    public static function find(int $id): ?array
    {
        self::ensureSchema();
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        $id = DB::insert(self::table(), [
            'post_id' => $data['post_id'],
            'member_id' => $data['member_id'],
            'parent_id' => $data['parent_id'] ?? 0,
            'content' => $data['content'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 게시글 댓글 수 업데이트
        $count = DB::count(self::table(), 'post_id = ?', [$data['post_id']]);
        DB::update(DB::getPrefix() . 'posts', ['comment_count' => $count], 'id = ?', [$data['post_id']]);

        return $id;
    }

    public static function delete(int $id): bool
    {
        $comment = self::find($id);
        if (!$comment) return false;

        // 대댓글도 삭제
        DB::delete(self::table(), 'parent_id = ?', [$id]);
        $result = DB::delete(self::table(), 'id = ?', [$id]) > 0;

        if ($result) {
            $count = DB::count(self::table(), 'post_id = ?', [$comment['post_id']]);
            DB::update(DB::getPrefix() . 'posts', ['comment_count' => $count], 'id = ?', [$comment['post_id']]);
        }

        return $result;
    }

    public static function listByPost(int $postId): array
    {
        self::ensureSchema();
        $table = self::table();
        $memberTable = DB::getPrefix() . 'members';
        $all = DB::fetchAll(
            "SELECT c.*, m.nickname as writer_name, m.level as writer_level
             FROM {$table} c
             LEFT JOIN {$memberTable} m ON c.member_id = m.id
             WHERE c.post_id = ?
             ORDER BY c.id ASC",
            [$postId]
        );

        // 부모-자식 구조로 정렬: 부모댓글 → 대댓글 순서 (채택 댓글은 최상단)
        $parents = [];
        $children = [];
        $adoptedParent = null;
        foreach ($all as $c) {
            if (empty($c['parent_id']) || $c['parent_id'] == 0) {
                if (!empty($c['is_adopted'])) {
                    $adoptedParent = $c;
                } else {
                    $parents[] = $c;
                }
            } else {
                $children[$c['parent_id']][] = $c;
            }
        }
        // 채택 댓글을 맨 앞에 고정
        if ($adoptedParent !== null) {
            array_unshift($parents, $adoptedParent);
        }

        $result = [];
        foreach ($parents as $p) {
            $p['is_reply'] = false;
            $result[] = $p;
            if (isset($children[$p['id']])) {
                foreach ($children[$p['id']] as $child) {
                    $child['is_reply'] = true;
                    $result[] = $child;
                }
            }
        }

        return $result;
    }

    /**
     * 댓글 채택 처리
     * @return array ['ok'=>bool, 'msg'=>string, 'points'=>int]
     */
    public static function adopt(int $commentId, int $postAuthorId): array
    {
        self::ensureSchema();
        $pdo = DB::getInstance();
        $prefix = DB::getPrefix();

        $cmt = self::find($commentId);
        if (!$cmt) return ['ok' => false, 'msg' => '댓글을 찾을 수 없습니다.', 'points' => 0];
        if (!empty($cmt['parent_id'])) return ['ok' => false, 'msg' => '대댓글은 채택할 수 없습니다.', 'points' => 0];
        if (!empty($cmt['is_hidden'])) return ['ok' => false, 'msg' => '숨겨진 댓글은 채택할 수 없습니다.', 'points' => 0];
        if (!empty($cmt['is_adopted'])) return ['ok' => false, 'msg' => '이미 채택된 댓글입니다.', 'points' => 0];
        if (empty($cmt['member_id'])) return ['ok' => false, 'msg' => '비회원 댓글은 채택할 수 없습니다.', 'points' => 0];
        if ((int)$cmt['member_id'] === $postAuthorId) return ['ok' => false, 'msg' => '본인의 댓글은 채택할 수 없습니다.', 'points' => 0];

        $post = DB::fetch("SELECT * FROM {$prefix}posts WHERE id = ?", [$cmt['post_id']]);
        if (!$post) return ['ok' => false, 'msg' => '게시글을 찾을 수 없습니다.', 'points' => 0];
        if ((int)$post['member_id'] !== $postAuthorId) return ['ok' => false, 'msg' => '글 작성자만 채택할 수 있습니다.', 'points' => 0];
        if (!empty($post['adopted_comment_id'])) return ['ok' => false, 'msg' => '이 글은 이미 채택된 댓글이 있습니다.', 'points' => 0];

        $pts = (int) nb_setting('point_adoption', '50');
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            DB::update($prefix . 'posts', [
                'adopted_comment_id' => $commentId,
                'adopted_at' => $now,
            ], 'id = ?', [$post['id']]);

            DB::update($prefix . 'comments', [
                'is_adopted' => 1,
                'adopted_at' => $now,
            ], 'id = ?', [$commentId]);

            if ($pts > 0) {
                Point::give((int)$cmt['member_id'], $pts, '댓글 채택');
            }

            // 채택 알림 쪽지 (sender=0 시스템)
            $postTitle = mb_strimwidth($post['title'], 0, 30, '..');
            $msgBody  = "회원님의 댓글이 채택되었습니다! 🎉\n\n";
            $msgBody .= "게시글: {$postTitle}\n";
            if ($pts > 0) $msgBody .= "지급 포인트: +{$pts}P\n";
            $msgBody .= "\n좋은 댓글 감사합니다!";
            Message::send(0, (int)$cmt['member_id'], '[알림] 댓글이 채택되었습니다', $msgBody);

            $pdo->commit();
            return ['ok' => true, 'msg' => '댓글을 채택했습니다.', 'points' => $pts];
        } catch (\Exception $e) {
            $pdo->rollBack();
            return ['ok' => false, 'msg' => '채택 처리 중 오류: ' . $e->getMessage(), 'points' => 0];
        }
    }

    public static function totalCount(): int
    {
        return DB::count(self::table());
    }

    public static function todayCount(): int
    {
        $today = date('Y-m-d');
        $prefix = DB::getPrefix();
        $row = DB::fetch("SELECT COUNT(*) as cnt FROM {$prefix}comments WHERE DATE(created_at) = ?", [$today]);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * 게시글의 댓글을 다른 게시글로 복사 (대댓글 parent_id 매핑)
     */
    public static function copyByPost(int $sourcePostId, int $targetPostId): void
    {
        $comments = DB::fetchAll(
            "SELECT * FROM " . self::table() . " WHERE post_id = ? ORDER BY id ASC",
            [$sourcePostId]
        );

        if (empty($comments)) return;

        // 원본 ID → 새 ID 매핑
        $idMap = [];

        // 부모 댓글 먼저 복사
        foreach ($comments as $c) {
            if (empty($c['parent_id']) || $c['parent_id'] == 0) {
                $newId = DB::insert(self::table(), [
                    'post_id' => $targetPostId,
                    'member_id' => $c['member_id'],
                    'parent_id' => 0,
                    'content' => $c['content'],
                    'is_hidden' => $c['is_hidden'],
                    'created_at' => $c['created_at'],
                ]);
                $idMap[$c['id']] = $newId;
            }
        }

        // 대댓글 복사 (부모 ID 매핑)
        foreach ($comments as $c) {
            if (!empty($c['parent_id']) && $c['parent_id'] > 0) {
                $newParentId = $idMap[$c['parent_id']] ?? 0;
                $newId = DB::insert(self::table(), [
                    'post_id' => $targetPostId,
                    'member_id' => $c['member_id'],
                    'parent_id' => $newParentId,
                    'content' => $c['content'],
                    'is_hidden' => $c['is_hidden'],
                    'created_at' => $c['created_at'],
                ]);
            }
        }

        // 새 게시글의 댓글 수 업데이트
        $count = DB::count(self::table(), 'post_id = ?', [$targetPostId]);
        DB::update(DB::getPrefix() . 'posts', ['comment_count' => $count], 'id = ?', [$targetPostId]);
    }

    /** 신고 승인 시 댓글 숨김 */
    public static function hide(int $id): void
    {
        DB::update(self::table(), ['is_hidden' => 1], 'id = ?', [$id]);
    }
}

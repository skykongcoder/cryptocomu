<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * Post.php - 게시글 CRUD
 */

class Post
{
    private static function table(): string
    {
        return DB::getPrefix() . 'posts';
    }

    public static function find(int $id): ?array
    {
        return DB::fetch("SELECT * FROM " . self::table() . " WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        $slug = self::generateSlug($data['title']);
        $id = DB::insert(self::table(), [
            'board_id' => $data['board_id'],
            'member_id' => $data['member_id'],
            'category' => $data['category'] ?? '',
            'title' => $data['title'],
            'content' => $data['content'],
            'slug' => $slug,
            'is_notice' => $data['is_notice'] ?? 0,
            'title_color' => $data['title_color'] ?? '',
            'title_bg' => $data['title_bg'] ?? '',
            'is_secret' => $data['is_secret'] ?? 0,
            'is_hidden' => $data['is_hidden'] ?? 0,
            'link1' => $data['link1'] ?? '',
            'link2' => $data['link2'] ?? '',
            'tags' => $data['tags'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($id) Plugin::doHook('post_created', $id, $data);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        if (isset($data['title'])) {
            $data['slug'] = self::generateSlug($data['title']);
        }
        $result = DB::update(self::table(), $data, 'id = ?', [$id]) > 0;
        if ($result) Plugin::doHook('post_updated', $id, $data);
        return $result;
    }

    public static function delete(int $id): bool
    {
        // 첨부파일 삭제
        Upload::deleteByPost($id);
        // 댓글 삭제
        DB::delete(DB::getPrefix() . 'comments', 'post_id = ?', [$id]);
        return DB::delete(self::table(), 'id = ?', [$id]) > 0;
    }

    public static function list(string $boardId, int $page = 1, int $perPage = 20, string $search = '', string $category = ''): array
    {
        $table = self::table();
        $memberTable = DB::getPrefix() . 'members';
        $offset = ($page - 1) * $perPage;

        $where = "p.board_id = ? AND p.is_hidden = 0";
        $params = [$boardId];

        if ($search) {
            $search = Plugin::applyFilter('post.search_query', $search, $boardId);
            $where .= " AND (p.title LIKE ? OR p.content LIKE ? OR m.nickname LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($category) {
            $where .= " AND p.category = ?";
            $params[] = $category;
        }

        // 플러그인이 where 절 추가 가능 (예: 특정 회원 숨김, 플래그 필터)
        $filtered = Plugin::applyFilter('post.list_query', ['where' => $where, 'params' => $params], $boardId, $search, $category);
        $where = $filtered['where'];
        $params = $filtered['params'];

        $totalRow = DB::fetch("SELECT COUNT(*) as cnt FROM {$table} p LEFT JOIN {$memberTable} m ON p.member_id = m.id WHERE {$where}", $params);
        $total = (int)($totalRow['cnt'] ?? 0);

        // 공지글 먼저, 그다음 최신순
        $posts = DB::fetchAll(
            "SELECT p.*, m.nickname as writer_name, m.level as writer_level, m.level as writer_level
             FROM {$table} p
             LEFT JOIN {$memberTable} m ON p.member_id = m.id
             WHERE {$where}
             ORDER BY p.is_notice DESC, p.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'total_pages' => max(1, ceil($total / $perPage)),
        ];
    }

    public static function getPrev(int $id, string $boardId): ?array
    {
        return DB::fetch(
            "SELECT id, title FROM " . self::table() . "
             WHERE board_id = ? AND is_hidden = 0 AND is_notice = 0 AND id < ?
             ORDER BY id DESC LIMIT 1",
            [$boardId, $id]
        );
    }

    public static function getNext(int $id, string $boardId): ?array
    {
        return DB::fetch(
            "SELECT id, title FROM " . self::table() . "
             WHERE board_id = ? AND is_hidden = 0 AND is_notice = 0 AND id > ?
             ORDER BY id ASC LIMIT 1",
            [$boardId, $id]
        );
    }

    public static function incrementHit(int $id): void
    {
        // [성능] 응답 전송 후 실행 (페이지 로드 블로킹 X)
        register_shutdown_function(function () use ($id) {
            try {
                DB::query("UPDATE " . self::table() . " SET hit = hit + 1 WHERE id = ?", [$id]);
            } catch (Exception $e) {}
        });
    }

    public static function recentPosts(int $limit = 10, ?string $boardId = null): array
    {
        $table = self::table();
        $memberTable = DB::getPrefix() . 'members';
        $where = '1';
        $params = [];
        if ($boardId) {
            $where = 'p.board_id = ?';
            $params = [$boardId];
        }
        return DB::fetchAll(
            "SELECT p.*, m.nickname as writer_name, m.level as writer_level
             FROM {$table} p
             LEFT JOIN {$memberTable} m ON p.member_id = m.id
             WHERE {$where}
             ORDER BY p.id DESC LIMIT {$limit}",
            $params
        );
    }

    /**
     * 갤러리 게시판 최근 글 + 썸네일
     */
    public static function galleryPosts(int $limit = 9, ?string $boardId = null): array
    {
        $prefix = DB::getPrefix();
        $where = 'p.board_id IN (SELECT board_id FROM ' . $prefix . 'boards WHERE board_type = \'gallery\' AND is_active = 1)';
        $params = [];
        if ($boardId) {
            $where = 'p.board_id = ?';
            $params = [$boardId];
        }
        $posts = DB::fetchAll(
            "SELECT p.id, p.board_id, p.title, p.content, p.link1, p.hit, p.comment_count, p.created_at,
                    m.nickname as writer_name
             FROM {$prefix}posts p
             LEFT JOIN {$prefix}members m ON p.member_id = m.id
             WHERE {$where}
             ORDER BY p.id DESC LIMIT {$limit}",
            $params
        );

        // 각 글의 썸네일 추출
        $attTable = $prefix . 'attachments';
        foreach ($posts as &$p) {
            $p['thumbnail'] = '';
            $p['is_video'] = false;
            $p['youtube_id'] = '';
            // 1. 첨부파일에서 첫 이미지
            $att = DB::fetch("SELECT file_name FROM {$attTable} WHERE post_id = ? AND is_image = 1 ORDER BY id ASC LIMIT 1", [$p['id']]);
            if ($att) {
                $p['thumbnail'] = $att['file_name'];
            } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $p['content'] ?? '', $m)) {
                // 2. 본문에서 첫 이미지 추출
                $p['thumbnail'] = $m[1];
            } else {
                // 3. 본문에서 유튜브 링크 → 썸네일
                $yid = self::extractYoutubeId($p['content'] ?? '');
                if ($yid) {
                    $p['thumbnail'] = "https://img.youtube.com/vi/{$yid}/hqdefault.jpg";
                    $p['is_video'] = true;
                    $p['youtube_id'] = $yid;
                }
            }
        }
        return $posts;
    }

    /**
     * 본문에서 유튜브 영상 ID 추출
     * 지원 형식: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, youtube.com/shorts/ID
     */
    public static function extractYoutubeId(string $content): ?string
    {
        if ($content === '') return null;
        $patterns = [
            '/(?:youtube\.com\/watch\?(?:[^"\'\s&]*&)*v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/|youtube\.com\/v\/)([A-Za-z0-9_-]{11})/i',
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $content, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    /**
     * 본문의 생 유튜브 URL을 iframe 임베드로 자동 변환
     * 이미 iframe/링크 내부에 있는 URL은 건드리지 않음
     */
    public static function autoEmbedYoutube(string $html): string
    {
        if ($html === '') return $html;

        $ytUrlPattern = '#https?://(?:www\.)?(?:youtube\.com/watch\?(?:[^\s"\'<]*&)*v=|youtu\.be/|youtube\.com/shorts/)([A-Za-z0-9_-]{11})[^\s"\'<]*#i';
        $placeholders = [];

        // 이미 embed된 iframe은 보호
        $html = preg_replace_callback('/<iframe[\s\S]*?<\/iframe>/i', function ($m) use (&$placeholders) {
            $key = '___NB_PH_' . count($placeholders) . '___';
            $placeholders[$key] = $m[0];
            return $key;
        }, $html);

        // <a href="유튜브URL">...</a> → embed 변환 (Summernote가 자동 링크로 감싼 경우)
        $html = preg_replace_callback('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>[\s\S]*?<\/a>/i', function ($m) use ($ytUrlPattern, &$placeholders) {
            $href = $m[1];
            if (preg_match($ytUrlPattern, $href, $ym)) {
                $vid = $ym[1];
                return '<div class="video-embed"><iframe src="https://www.youtube.com/embed/' . $vid . '" frameborder="0" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen></iframe></div>';
            }
            $key = '___NB_PH_' . count($placeholders) . '___';
            $placeholders[$key] = $m[0];
            return $key;
        }, $html);

        // 생 유튜브 URL → embed 변환
        $html = preg_replace_callback(
            '#(^|[\s>])(https?://(?:www\.)?(?:youtube\.com/watch\?(?:[^\s"\'<]*&)*v=|youtu\.be/|youtube\.com/shorts/)([A-Za-z0-9_-]{11})[^\s"\'<]*)#i',
            function ($m) {
                $vid = $m[3];
                return $m[1] . '<div class="video-embed"><iframe src="https://www.youtube.com/embed/' . $vid . '" frameborder="0" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen></iframe></div>';
            },
            $html
        );

        foreach ($placeholders as $k => $v) {
            $html = str_replace($k, $v, $html);
        }
        return $html;
    }

    /**
     * 본문/첨부에서 썸네일 정보 추출 (이미지 또는 유튜브)
     * 반환: ['thumb' => URL, 'is_video' => bool, 'youtube_id' => string]
     */
    public static function extractThumbInfo(int $postId, string $content): array
    {
        $prefix = DB::getPrefix();
        $result = ['thumb' => '', 'is_video' => false, 'youtube_id' => ''];
        $att = DB::fetch("SELECT file_name FROM {$prefix}attachments WHERE post_id = ? AND is_image = 1 ORDER BY id ASC LIMIT 1", [$postId]);
        if ($att) {
            $result['thumb'] = $att['file_name'];
            return $result;
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            $result['thumb'] = $m[1];
            return $result;
        }
        $yid = self::extractYoutubeId($content);
        if ($yid) {
            $result['thumb'] = "https://img.youtube.com/vi/{$yid}/hqdefault.jpg";
            $result['is_video'] = true;
            $result['youtube_id'] = $yid;
        }
        return $result;
    }

    public static function totalCount(): int
    {
        return DB::count(self::table());
    }

    public static function todayCount(): int
    {
        $today = date('Y-m-d');
        $prefix = DB::getPrefix();
        $row = DB::fetch("SELECT COUNT(*) as cnt FROM {$prefix}posts WHERE DATE(created_at) = ?", [$today]);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * 게시글 복사 (원본 유지, 대상 게시판에 새 글 생성)
     */
    public static function copy(int $id, string $targetBoardId): ?int
    {
        $source = self::find($id);
        if (!$source) return null;

        $newId = self::create([
            'board_id' => $targetBoardId,
            'member_id' => $source['member_id'],
            'category' => $source['category'],
            'title' => $source['title'],
            'content' => $source['content'],
            'is_notice' => 0,
            'is_secret' => $source['is_secret'],
            'title_color' => $source['title_color'],
            'title_bg' => $source['title_bg'],
            'link1' => $source['link1'],
            'link2' => $source['link2'],
            'tags' => $source['tags'],
        ]);

        // 첨부파일 복사
        Upload::copyByPost($id, $newId);
        // 댓글 복사
        Comment::copyByPost($id, $newId);

        return $newId;
    }

    /**
     * 게시글 이동 (원본 게시판에서 삭제, 대상 게시판으로 이전)
     */
    public static function move(int $id, string $targetBoardId): bool
    {
        return self::update($id, ['board_id' => $targetBoardId]);
    }

    private static function generateSlug(string $title): string
    {
        // 한글 + 영문 + 숫자 유지, 나머지는 하이픈
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $title);
        $slug = trim($slug, '-');
        $slug = mb_strimwidth($slug, 0, 200);
        return $slug ?: 'post';
    }
}

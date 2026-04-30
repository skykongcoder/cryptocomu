<?php
/**
 * AI 스마트 내부 링크 빌더
 * NuriBoard CMS Plugin v1.1
 *
 * 변경사항 v1.1:
 * - 페이지 로드 시 AI 호출 완전 제거 → 글 저장 시점에만 AI 호출
 * - content LIKE 풀스캔 제거 → title LIKE 만 사용
 * - after_post_content 는 캐시 읽기 전용 (항상 빠름)
 */

// ============================================================
// 헬퍼 함수
// ============================================================

function _ilb_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/internal-link-builder';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _ilb_load_config_fresh(): array {
    $default = [
        'enabled'        => false,
        'link_count'     => 3,
        'allowed_boards' => '',
        'openai_api_key' => '',
        'openai_model'   => 'openai/gpt-4o-mini',
    ];
    $file = _ilb_data_dir() . '/config.json';
    if (!file_exists($file)) return $default;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge($default, $data) : $default;
}

function _ilb_save_config(array $config): void {
    file_put_contents(
        _ilb_data_dir() . '/config.json',
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function _ilb_log(string $msg): void {
    $file = _ilb_data_dir() . '/debug.log';
    if (file_exists($file) && filesize($file) > 1024 * 1024) {
        file_put_contents($file, '');
    }
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ============================================================
// 게시판 허용 여부 확인
// ============================================================

function _ilb_board_allowed(string $board_id, array $cfg): bool {
    $allowed_str = trim($cfg['allowed_boards'] ?? '');
    if ($allowed_str === '') return true; // 전체 적용
    $allowed = array_filter(array_map('trim', explode(',', $allowed_str)));
    return in_array($board_id, $allowed, true);
}

// ============================================================
// 캐시 헬퍼 (파일 기반)
// ============================================================

function _ilb_cache_path(int $post_id): string {
    $dir = _ilb_data_dir() . '/cache';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/' . $post_id . '.json';
}

function _ilb_cache_get(int $post_id): ?array {
    $path = _ilb_cache_path($post_id);
    if (!file_exists($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function _ilb_cache_set(int $post_id, array $data): void {
    @file_put_contents(
        _ilb_cache_path($post_id),
        json_encode($data, JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function _ilb_cache_delete(int $post_id): void {
    $path = _ilb_cache_path($post_id);
    if (file_exists($path)) @unlink($path);
}

// ============================================================
// 키워드 추출 — 제목 단어 기반 (AI 호출 없음, 빠름)
// ============================================================

function _ilb_extract_keywords_fallback(string $title): array {
    $words  = preg_split('/[\s\p{P}\p{S}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
    $seen   = [];
    $result = [];
    foreach ($words as $w) {
        $w = trim($w);
        if (mb_strlen($w) < 2 || isset($seen[$w])) continue;
        $seen[$w] = true;
        $result[] = $w;
        if (count($result) >= 5) break;
    }
    return $result;
}

// ============================================================
// 관련글 검색 — title LIKE 만 사용 (content 풀스캔 제거)
// ============================================================

function _ilb_find_related(array $post, array $cfg): array {
    $prefix     = DB::getPrefix();
    $link_count = max(1, min(5, (int)($cfg['link_count'] ?? 3)));
    $board_id   = (string)($post['board_id'] ?? '');
    $current_id = (int)($post['id'] ?? $post['post_id'] ?? 0);
    $title      = $post['title'] ?? '';
    $content    = strip_tags($post['content'] ?? '');

    // ★ 제목 키워드만 사용 — AI 호출 없음 (글 저장 속도 영향 없음)
    $keywords = _ilb_extract_keywords_fallback($title);
    if (empty($keywords)) {
        _ilb_log("키워드 없음, 중단 post_id={$current_id}");
        return [];
    }

    // 허용 게시판
    $allowed = [];
    if (!empty($cfg['allowed_boards'])) {
        $allowed = array_values(array_filter(
            array_map('trim', explode(',', $cfg['allowed_boards'])),
            fn($b) => $b !== ''
        ));
    }

    // ★ title LIKE 만 사용 (content 풀스캔 제거)
    $like_parts = [];
    $params     = [$current_id];
    foreach ($keywords as $kw) {
        $like_parts[] = "p.title LIKE ?";
        $params[]     = '%' . $kw . '%';
    }
    $like_sql = implode(' OR ', $like_parts);

    $board_sql = '';
    if (!empty($allowed)) {
        $board_sql = ' AND p.board_id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
        foreach ($allowed as $b) $params[] = $b;
    }

    $params[] = $link_count;

    $sql = "SELECT p.id, p.title, p.board_id
            FROM {$prefix}posts p
            WHERE p.id != ?
              AND ({$like_sql})
              {$board_sql}
            ORDER BY p.id DESC
            LIMIT ?";

    try {
        $rows = DB::fetchAll($sql, $params);
    } catch (Throwable $e) {
        _ilb_log("DB 오류: " . $e->getMessage());
        return [];
    }

    if (empty($rows)) {
        _ilb_log("관련글 없음 키워드=" . implode(',', $keywords));
        return [];
    }

    $related = [];
    foreach ((array)$rows as $row) {
        $bid       = (string)($row['board_id'] ?? '');
        $rid       = (int)($row['id'] ?? 0);
        $related[] = [
            'title'    => $row['title'] ?? '',
            'url'      => nb_url('/board/' . $bid . '/' . $rid),
            'board_id' => $bid,
        ];
    }

    _ilb_log("관련글 " . count($related) . "개 캐시 저장 post_id={$current_id}");
    return $related;
}

// ============================================================
// 박스 렌더링
// ============================================================

function _ilb_render_box(array $related): string {
    $headlines = [
        '이 글과 함께 읽으면 좋아요',
        '더 알아보기',
        '참고하면 좋은 글',
        '이 주제 더 파보기',
        '연관 아티클',
        '놓치면 아쉬운 글',
        '이것도 궁금하지 않으세요?',
        '이어서 읽기',
        '함께 보면 도움되는 글',
    ];
    $headline = $headlines[array_rand($headlines)];

    $cards = '';
    foreach ($related as $r) {
        $url   = htmlspecialchars($r['url'],   ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
        $cards .= '
        <a href="' . $url . '" style="display:block;flex:1;min-width:200px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 18px;text-decoration:none;color:#1e293b;transition:box-shadow .2s,border-color .2s;box-shadow:0 1px 3px rgba(0,0,0,0.06);"
           onmouseover="this.style.borderColor=\'#22c55e\';this.style.boxShadow=\'0 4px 12px rgba(34,197,94,.15)\'"
           onmouseout="this.style.borderColor=\'#e2e8f0\';this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.06)\'">
            <div style="font-size:13px;font-weight:700;line-height:1.5;color:#1e293b;margin-bottom:8px;">' . $title . '</div>
            <div style="font-size:12px;color:#22c55e;font-weight:600;">읽어보기 &rarr;</div>
        </a>';
    }

    return '
<div style="background:#f8fffe;border:1px solid #d1fae5;border-radius:12px;padding:20px 24px;margin:32px 0;">
    <div style="font-size:12px;font-weight:700;color:#15803d;margin-bottom:14px;text-transform:uppercase;letter-spacing:.6px;">'
        . htmlspecialchars($headline) . '</div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;">' . $cards . '</div>
</div>';
}

// ============================================================
// 훅: 글 저장 시 캐시 생성 (AI 호출은 여기서만)
// ============================================================

Plugin::addHook('post_created', function($post_id, $post_data = []) {
    try {
        $cfg = _ilb_load_config_fresh();
        if (empty($cfg['enabled'])) return;

        $post_id = (int)$post_id;

        // 글 정보 로드 (board_id는 DB에서 확실하게 가져옴)
        $prefix = DB::getPrefix();
        $post   = DB::fetch("SELECT id, title, content, board_id FROM {$prefix}posts WHERE id = ?", [$post_id]);
        if (!$post) return;

        // 허용 게시판 체크
        if (!_ilb_board_allowed((string)($post['board_id'] ?? ''), $cfg)) return;

        $related = _ilb_find_related($post, $cfg);
        _ilb_cache_set($post_id, $related);
    } catch (Throwable $e) {
        _ilb_log("post_created 오류: " . $e->getMessage());
    }
});

Plugin::addHook('post_updated', function($post_id, $post_data = []) {
    try {
        $cfg = _ilb_load_config_fresh();
        if (empty($cfg['enabled'])) return;

        $post_id = (int)$post_id;

        // 캐시 삭제 후 재생성 (board_id는 DB에서 확실하게 가져옴)
        _ilb_cache_delete($post_id);

        $prefix = DB::getPrefix();
        $post   = DB::fetch("SELECT id, title, content, board_id FROM {$prefix}posts WHERE id = ?", [$post_id]);
        if (!$post) return;

        // 허용 게시판 체크
        if (!_ilb_board_allowed((string)($post['board_id'] ?? ''), $cfg)) return;

        $related = _ilb_find_related($post, $cfg);
        _ilb_cache_set($post_id, $related);
    } catch (Throwable $e) {
        _ilb_log("post_updated 오류: " . $e->getMessage());
    }
});

// ============================================================
// 훅: 글 보기 — 캐시 읽기 전용 (API 호출 절대 없음)
// ============================================================

Plugin::addHook('after_post_content', function($post = []) {
    try {
        $cfg = _ilb_load_config_fresh();
        if (empty($cfg['enabled'])) return;

        $post_id = (int)($post['id'] ?? $post['post_id'] ?? 0);
        if (!$post_id) return;

        // 게시판 필터 — board_id 없으면 DB에서 직접 조회
        $board_id = (string)($post['board_id'] ?? '');
        if ($board_id === '') {
            $prefix  = DB::getPrefix();
            $pr = DB::fetch("SELECT board_id FROM {$prefix}posts WHERE id = ?", [$post_id]);
            $board_id = (string)($pr['board_id'] ?? '');
        }
        if (!_ilb_board_allowed($board_id, $cfg)) return;

        // ★ 캐시만 읽음 — AI/DB 풀스캔 절대 없음
        $cached = _ilb_cache_get($post_id);

        if ($cached === null) {
            // 캐시 없으면 제목 키워드 LIKE 만으로 빠른 폴백 (AI 없음)
            $keywords = _ilb_extract_keywords_fallback($post['title'] ?? '');
            if (!empty($keywords)) {
                $prefix     = DB::getPrefix();
                $link_count = max(1, min(5, (int)($cfg['link_count'] ?? 3)));
                $allowed    = [];
                if (!empty($cfg['allowed_boards'])) {
                    $allowed = array_values(array_filter(
                        array_map('trim', explode(',', $cfg['allowed_boards'])),
                        fn($b) => $b !== ''
                    ));
                }
                $like_parts = [];
                $params     = [$post_id];
                foreach ($keywords as $kw) {
                    $like_parts[] = "p.title LIKE ?";
                    $params[]     = '%' . $kw . '%';
                }
                $board_sql = '';
                if (!empty($allowed)) {
                    $board_sql = ' AND p.board_id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
                    foreach ($allowed as $b) $params[] = $b;
                }
                $params[] = $link_count;
                $sql = "SELECT p.id, p.title, p.board_id FROM {$prefix}posts p
                        WHERE p.id != ? AND (" . implode(' OR ', $like_parts) . ") {$board_sql}
                        ORDER BY p.id DESC LIMIT ?";
                try {
                    $rows = DB::fetchAll($sql, $params);
                    $cached = [];
                    foreach ((array)$rows as $row) {
                        $bid     = (string)($row['board_id'] ?? '');
                        $rid     = (int)($row['id'] ?? 0);
                        $cached[] = [
                            'title'    => $row['title'] ?? '',
                            'url'      => nb_url('/board/' . $bid . '/' . $rid),
                            'board_id' => $bid,
                        ];
                    }
                    // 폴백 결과도 캐시 저장 (다음 방문 시 DB 조회 생략)
                    _ilb_cache_set($post_id, $cached);
                } catch (Throwable $e) {
                    $cached = [];
                }
            } else {
                $cached = [];
            }
        }

        if (!empty($cached)) {
            echo _ilb_render_box($cached);
        }

    } catch (Throwable $e) {
        _ilb_log("after_post_content 오류: " . $e->getMessage());
    }
});

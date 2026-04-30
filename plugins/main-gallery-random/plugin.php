<?php
/**
 * 메인 그리드 랜덤화
 *
 * 메인 페이지의 그리드 배너에 이미지 게시판 글을 랜덤으로 표시.
 * 일정 시간마다 자동으로 다른 이미지로 교체되어 매번 새로운 모습.
 *
 * 작동 원리:
 * 1. 이미지 게시판 글을 모두 가져와 캐시
 * 2. 갱신 주기마다 새로 셔플
 * 3. ob_start로 메인 페이지의 .gallery-grid HTML을 캐시 데이터로 교체
 */

// ── 설정 ────────────────────────────────────────────────────

function _mgr_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/main-gallery-random';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _mgr_load_cfg(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $default = [
        'enabled'   => true,
        'count'     => 6,         // 표시 개수
        'cache_ttl' => 3600,      // 갱신 주기 (초)
        'pool_size' => 100,       // 랜덤 풀 크기 (이 글들 중에서 셔플)
        'board_id'  => '',        // 특정 게시판만 (비워두면 모든 갤러리 게시판)
    ];
    $file = _mgr_data_dir() . '/config.json';
    if (!file_exists($file)) { $cache = $default; return $cache; }
    $data = json_decode(file_get_contents($file), true);
    $cache = is_array($data) ? array_merge($default, $data) : $default;
    return $cache;
}

function _mgr_save_cfg(array $cfg): void {
    file_put_contents(
        _mgr_data_dir() . '/config.json',
        json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

// ── 캐시 (랜덤 선택된 글 목록) ──────────────────────────────

function _mgr_cache_file(): string {
    return _mgr_data_dir() . '/cache.json';
}

function _mgr_get_random_posts(): array {
    $cfg = _mgr_load_cfg();
    $cache_file = _mgr_cache_file();

    // 캐시 유효 → 그대로 사용
    if (file_exists($cache_file)) {
        $age = time() - filemtime($cache_file);
        if ($age < (int)$cfg['cache_ttl']) {
            $cached = json_decode(file_get_contents($cache_file), true);
            if (is_array($cached) && !empty($cached)) return $cached;
        }
    }

    // 캐시 만료 → 새로 가져와서 셔플
    if (!class_exists('Post')) return [];

    $pool_size = max((int)$cfg['count'], (int)$cfg['pool_size']);
    $board_id  = !empty($cfg['board_id']) ? $cfg['board_id'] : null;

    $all = Post::galleryPosts($pool_size, $board_id);
    if (empty($all)) {
        // 갤러리 게시판이 비었으면 빈 캐시 (다음 호출 때 다시 시도하지 않게 1분만)
        @file_put_contents($cache_file, '[]');
        return [];
    }

    // 셔플
    shuffle($all);

    // count만큼 자르기
    $selected = array_slice($all, 0, (int)$cfg['count']);

    @file_put_contents($cache_file, json_encode($selected, JSON_UNESCAPED_UNICODE));
    return $selected;
}

function _mgr_clear_cache(): void {
    $f = _mgr_cache_file();
    if (file_exists($f)) @unlink($f);
}

// ── 새 그리드 HTML 생성 ──────────────────────────────────────

function _mgr_render_grid(array $posts): string {
    if (empty($posts)) return '';

    $html = '<div class="gallery-grid">';
    foreach ($posts as $p) {
        $link    = !empty($p['link1'])
                   ? $p['link1']
                   : (function_exists('nb_url') ? nb_url("board/{$p['board_id']}/{$p['id']}") : "/board/{$p['board_id']}/{$p['id']}");
        $target  = !empty($p['link1']) ? ' target="_blank"' : '';
        $is_video= !empty($p['is_video']);
        $thumb   = !empty($p['thumbnail'])
                   ? (function_exists('nb_url') ? nb_url($p['thumbnail']) : $p['thumbnail'])
                   : '';

        $cls = 'gallery-item' . ($is_video ? ' is-video' : '');
        $html .= '<a href="' . htmlspecialchars($link) . '"' . $target . ' class="' . $cls . '">';

        if ($thumb) {
            // 동영상이면 썸네일이 외부 URL일 가능성 (youtube)
            if ($is_video && stripos($p['thumbnail'], 'http') === 0) {
                $thumb = htmlspecialchars($p['thumbnail']);
            }
            $html .= '<img src="' . htmlspecialchars($thumb) . '" alt="" loading="lazy">';
            if ($is_video) {
                $html .= '<span class="gallery-play" aria-hidden="true">'
                       . '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                       . '<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/></svg>'
                       . '</span>';
            }
        } else {
            $html .= '<div class="gallery-noimg">'
                   . '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
                   . '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'
                   . '</div>';
        }
        $html .= '</a>';
    }
    $html .= '</div>';
    return $html;
}

// ── HTML 가로채기 ────────────────────────────────────────────

function _mgr_process_html(string $html): string {
    $cfg = _mgr_load_cfg();
    if (empty($cfg['enabled'])) return $html;
    if (strlen($html) < 100) return $html;
    if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) return $html;

    // 메인 페이지인지 확인
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $uri = trim($uri, '/');
    if ($uri !== '' && $uri !== 'index.php' && $uri !== 'index') {
        return $html;
    }

    // .gallery-grid 영역 찾기 (가장 처음 등장하는 것 = 메인 그리드)
    if (!preg_match('/<div\s+class="gallery-grid"\s*>(.*?)<\/div>\s*(<\/div>)/s', $html, $m, PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    // 새 그리드 생성
    $posts = _mgr_get_random_posts();
    if (empty($posts)) return $html;

    $new_grid = _mgr_render_grid($posts);
    if (!$new_grid) return $html;

    // 교체 (첫 번째 .gallery-grid만)
    $orig = $m[0][0];
    $offset = $m[0][1];
    $new_html = $new_grid . $m[2][0]; // </div> 닫는 부분 유지

    return substr($html, 0, $offset) . $new_html . substr($html, $offset + strlen($orig));
}

// ── 출력 버퍼 등록 ───────────────────────────────────────────

$_mgr_cfg_boot = _mgr_load_cfg();
if (!empty($_mgr_cfg_boot['enabled'])) {
    $is_cli  = php_sapi_name() === 'cli';
    $is_ajax = defined('NB_AJAX') && NB_AJAX;
    $is_api  = defined('NB_API')  && NB_API;
    if (!$is_cli && !$is_ajax && !$is_api) {
        ob_start('_mgr_process_html');
    }
}

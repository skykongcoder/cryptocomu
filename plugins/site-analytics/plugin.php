<?php
/**
 * 사이트 분석 플러그인
 * 방문자 추적, 유입 경로, 검색 키워드, 실시간 접속자, 기기/브라우저 분석
 */

// ===== DB 테이블 생성 (플러그인 로드 시 자동 체크) =====
$prefix = DB::getPrefix();
try {
    DB::fetch("SELECT 1 FROM {$prefix}analytics_visits LIMIT 1");
} catch (Exception $e) {

    // 방문 기록 테이블
    DB::query("CREATE TABLE IF NOT EXISTS {$prefix}analytics_visits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL DEFAULT '',
        member_id INT UNSIGNED DEFAULT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        page_url VARCHAR(500) NOT NULL DEFAULT '',
        page_title VARCHAR(200) NOT NULL DEFAULT '',
        referer VARCHAR(500) NOT NULL DEFAULT '',
        referer_domain VARCHAR(200) NOT NULL DEFAULT '',
        referer_type ENUM('direct','search','social','link','internal') NOT NULL DEFAULT 'direct',
        search_keyword VARCHAR(200) NOT NULL DEFAULT '',
        search_engine VARCHAR(50) NOT NULL DEFAULT '',
        device ENUM('pc','mobile','tablet') NOT NULL DEFAULT 'pc',
        browser VARCHAR(50) NOT NULL DEFAULT '',
        os VARCHAR(50) NOT NULL DEFAULT '',
        country VARCHAR(10) NOT NULL DEFAULT '',
        is_new_visitor TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_session (session_id),
        INDEX idx_referer_type (referer_type),
        INDEX idx_page (page_url(191)),
        INDEX idx_ip (ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 일별 집계 테이블
    DB::query("CREATE TABLE IF NOT EXISTS {$prefix}analytics_daily (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stat_date DATE NOT NULL,
        total_visits INT UNSIGNED NOT NULL DEFAULT 0,
        unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
        new_visitors INT UNSIGNED NOT NULL DEFAULT 0,
        returning_visitors INT UNSIGNED NOT NULL DEFAULT 0,
        page_views INT UNSIGNED NOT NULL DEFAULT 0,
        avg_pages DECIMAL(5,2) NOT NULL DEFAULT 0,
        direct_count INT UNSIGNED NOT NULL DEFAULT 0,
        search_count INT UNSIGNED NOT NULL DEFAULT 0,
        social_count INT UNSIGNED NOT NULL DEFAULT 0,
        link_count INT UNSIGNED NOT NULL DEFAULT 0,
        pc_count INT UNSIGNED NOT NULL DEFAULT 0,
        mobile_count INT UNSIGNED NOT NULL DEFAULT 0,
        tablet_count INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uk_date (stat_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ===== 방문자 추적 (모든 페이지 로드 시) =====
Plugin::addHook('before_content', function() {
    // 관리자 페이지는 추적 안함
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/admin') === 0) return;

    // AJAX/API 요청 무시
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) return;
    if (strpos($uri, '/api/') === 0) return;

    // UA 기반 봇 차단
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($ua)) return;
    if (preg_match('/bot|crawl|spider|slurp|mediapartners|python|curl|wget|scrapy|httpclient|libwww|okhttp/i', $ua)) return;

    // URL 패턴 기반 스캔봇 차단
    // (wp-includes, xmlrpc 등은 누리보드에 존재하지 않는 경로 — 해킹 스캐너만 접근)
    $scanPatterns = ['/wp-', '/xmlrpc', '/.env', '/.git', '/phpmyadmin', '/wp-login', '/wp-admin', '/config.php', '/setup.php', '/install.php', '/admin.php', '/shell.php', '/eval.php'];
    foreach ($scanPatterns as $pat) {
        if (stripos($uri, $pat) !== false) return;
    }

    $prefix = DB::getPrefix();
    $sessionId = session_id() ?: md5($_SERVER['REMOTE_ADDR'] . $ua);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $pageUrl = strtok($uri, '?');
    $memberId = function_exists('Auth') || class_exists('Auth') ? (Auth::check() ? Auth::id() : null) : null;

    // 같은 세션에서 같은 페이지 5분 이내 재방문은 무시
    $recent = DB::fetch(
        "SELECT id FROM {$prefix}analytics_visits WHERE session_id = ? AND page_url = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        [$sessionId, $pageUrl]
    );
    if ($recent) return;

    // 리퍼러 분석
    $refererInfo = sa_analyzeReferer($referer);

    // 기기/브라우저 분석
    $deviceInfo = sa_analyzeUserAgent($ua);

    // 신규 방문자 판별 (오늘 첫 방문인지) — 범위 쿼리로 인덱스 활용
    $todayVisit = DB::fetch(
        "SELECT id FROM {$prefix}analytics_visits WHERE ip = ? AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY LIMIT 1",
        [$ip]
    );
    $isNew = $todayVisit ? 0 : 1;

    // 기록 저장
    DB::query(
        "INSERT INTO {$prefix}analytics_visits (session_id, member_id, ip, page_url, referer, referer_domain, referer_type, search_keyword, search_engine, device, browser, os, is_new_visitor, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $sessionId,
            $memberId,
            $ip,
            $pageUrl,
            mb_substr($referer, 0, 500),
            $refererInfo['domain'],
            $refererInfo['type'],
            $refererInfo['keyword'],
            $refererInfo['engine'],
            $deviceInfo['device'],
            $deviceInfo['browser'],
            $deviceInfo['os'],
            $isNew
        ]
    );
});

// ===== 일별 집계 (관리자 방문 시에만 실행 — 일반 방문자 부하 없음) =====
Plugin::addHook('after_footer', function() {
    // 관리자가 아니면 스킵 → 일반 방문자/구글봇 응답속도 보호
    if (!class_exists('Auth') || !Auth::check() || !Auth::isAdmin()) return;

    $prefix = DB::getPrefix();

    // 어제 집계가 아직 안 됐으면 실행
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $exists = DB::fetch("SELECT id FROM {$prefix}analytics_daily WHERE stat_date = ?", [$yesterday]);
    if ($exists) return;

    // 어제 데이터가 있는지 확인
    $hasData = DB::fetch("SELECT id FROM {$prefix}analytics_visits WHERE DATE(created_at) = ? LIMIT 1", [$yesterday]);
    if (!$hasData) return;

    $stats = DB::fetch("SELECT
        COUNT(*) as total_visits,
        COUNT(DISTINCT ip) as unique_visitors,
        SUM(is_new_visitor) as new_visitors,
        GREATEST(0, COUNT(DISTINCT ip) - SUM(CASE WHEN is_new_visitor = 1 THEN 1 ELSE 0 END)) as returning_visitors,
        COUNT(*) as page_views,
        ROUND(COUNT(*) / GREATEST(COUNT(DISTINCT session_id), 1), 2) as avg_pages,
        SUM(referer_type = 'direct') as direct_count,
        SUM(referer_type = 'search') as search_count,
        SUM(referer_type = 'social') as social_count,
        SUM(referer_type = 'link') as link_count,
        SUM(device = 'pc') as pc_count,
        SUM(device = 'mobile') as mobile_count,
        SUM(device = 'tablet') as tablet_count
    FROM {$prefix}analytics_visits WHERE DATE(created_at) = ?", [$yesterday]);

    if ($stats && $stats['total_visits'] > 0) {
        DB::query("INSERT INTO {$prefix}analytics_daily (stat_date, total_visits, unique_visitors, new_visitors, returning_visitors, page_views, avg_pages, direct_count, search_count, social_count, link_count, pc_count, mobile_count, tablet_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE total_visits=VALUES(total_visits), unique_visitors=VALUES(unique_visitors), new_visitors=VALUES(new_visitors), returning_visitors=VALUES(returning_visitors), page_views=VALUES(page_views), avg_pages=VALUES(avg_pages), direct_count=VALUES(direct_count), search_count=VALUES(search_count), social_count=VALUES(social_count), link_count=VALUES(link_count), pc_count=VALUES(pc_count), mobile_count=VALUES(mobile_count), tablet_count=VALUES(tablet_count)",
            [$yesterday, $stats['total_visits'], $stats['unique_visitors'], $stats['new_visitors'], $stats['returning_visitors'], $stats['page_views'], $stats['avg_pages'], $stats['direct_count'], $stats['search_count'], $stats['social_count'], $stats['link_count'], $stats['pc_count'], $stats['mobile_count'], $stats['tablet_count']]
        );
    }
});

// ===== 리퍼러 분석 함수 =====
function sa_analyzeReferer(string $referer): array {
    $result = ['type' => 'direct', 'domain' => '', 'keyword' => '', 'engine' => ''];
    if (empty($referer)) return $result;

    $parsed = parse_url($referer);
    $host = $parsed['host'] ?? '';
    $result['domain'] = $host;

    // 내부 링크인지 확인
    $myHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === $myHost) {
        $result['type'] = 'internal';
        return $result;
    }

    // 검색엔진 판별
    $searchEngines = [
        'google'    => ['google.com', 'google.co.kr', 'google.co.jp'],
        'naver'     => ['search.naver.com', 'naver.com', 'm.search.naver.com'],
        'daum'      => ['search.daum.net', 'daum.net', 'm.search.daum.net'],
        'bing'      => ['bing.com', 'www.bing.com'],
        'yahoo'     => ['search.yahoo.com', 'yahoo.co.jp'],
        'zum'       => ['search.zum.com', 'zum.com'],
        'nate'      => ['search.nate.com', 'nate.com'],
        'duckduckgo'=> ['duckduckgo.com'],
    ];

    foreach ($searchEngines as $engine => $domains) {
        foreach ($domains as $domain) {
            if (stripos($host, $domain) !== false) {
                $result['type'] = 'search';
                $result['engine'] = $engine;

                // 키워드 추출
                $query = $parsed['query'] ?? '';
                parse_str($query, $params);
                $keywordKeys = ['q', 'query', 'search_query', 'p', 'wd', 'text'];
                foreach ($keywordKeys as $key) {
                    if (!empty($params[$key])) {
                        $result['keyword'] = mb_substr($params[$key], 0, 200);
                        break;
                    }
                }
                if (empty($result['keyword'])) {
                    $result['keyword'] = '(키워드 비공개)';
                }
                return $result;
            }
        }
    }

    // SNS 판별
    $socialDomains = [
        'facebook.com', 'fb.com', 'instagram.com', 't.co', 'twitter.com', 'x.com',
        'youtube.com', 'youtu.be', 'tiktok.com', 'linkedin.com', 'pinterest.com',
        'reddit.com', 'blog.naver.com', 'cafe.naver.com', 'tistory.com', 'brunch.co.kr',
        'band.us', 'kakaostory.com', 'threads.net'
    ];
    foreach ($socialDomains as $social) {
        if (stripos($host, $social) !== false) {
            $result['type'] = 'social';
            return $result;
        }
    }

    // 나머지는 외부 링크
    $result['type'] = 'link';
    return $result;
}

// ===== UA 분석 함수 =====
function sa_analyzeUserAgent(string $ua): array {
    $result = ['device' => 'pc', 'browser' => 'other', 'os' => 'other'];

    // 기기 판별
    if (preg_match('/iPad|tablet|PlayBook|Silk/i', $ua)) {
        $result['device'] = 'tablet';
    } elseif (preg_match('/Mobile|Android.*Chrome|iPhone|iPod|BlackBerry|Opera Mini|IEMobile|webOS/i', $ua)) {
        $result['device'] = 'mobile';
    }

    // 브라우저 판별
    if (preg_match('/SamsungBrowser/i', $ua)) $result['browser'] = 'samsung';
    elseif (preg_match('/Whale/i', $ua)) $result['browser'] = 'whale';
    elseif (preg_match('/Edg/i', $ua)) $result['browser'] = 'edge';
    elseif (preg_match('/OPR|Opera/i', $ua)) $result['browser'] = 'opera';
    elseif (preg_match('/Chrome/i', $ua) && !preg_match('/Edg|OPR/i', $ua)) $result['browser'] = 'chrome';
    elseif (preg_match('/Firefox/i', $ua)) $result['browser'] = 'firefox';
    elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) $result['browser'] = 'safari';
    elseif (preg_match('/MSIE|Trident/i', $ua)) $result['browser'] = 'ie';

    // OS 판별
    if (preg_match('/Windows/i', $ua)) $result['os'] = 'windows';
    elseif (preg_match('/Macintosh|Mac OS/i', $ua)) $result['os'] = 'mac';
    elseif (preg_match('/Android/i', $ua)) $result['os'] = 'android';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $result['os'] = 'ios';
    elseif (preg_match('/Linux/i', $ua)) $result['os'] = 'linux';

    return $result;
}

// ===== API 엔드포인트 (settings.php AJAX용) =====
if (class_exists('Router')) {
Router::post('/admin/plugin/site-analytics/api', function() {
        if (!Auth::check() || !Auth::isAdmin()) {
            echo json_encode(['success' => false, 'message' => '권한 없음']);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? '';
        $prefix = DB::getPrefix();

        switch ($action) {
            // 실시간 접속자
            case 'realtime':
                $online = DB::fetchAll("SELECT DISTINCT ip, page_url, device, browser, referer_type, created_at
                    FROM {$prefix}analytics_visits
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    ORDER BY created_at DESC");
                echo json_encode(['success' => true, 'count' => count($online), 'visitors' => $online]);
                break;

            // 오늘 요약
            case 'today':
                $today = DB::fetch("SELECT
                    COUNT(*) as page_views,
                    COUNT(DISTINCT ip) as unique_visitors,
                    SUM(is_new_visitor) as new_visitors,
                    SUM(referer_type='search') as from_search,
                    SUM(referer_type='social') as from_social,
                    SUM(referer_type='direct') as from_direct,
                    SUM(referer_type='link') as from_link,
                    SUM(device='pc') as pc,
                    SUM(device='mobile') as mobile,
                    SUM(device='tablet') as tablet
                FROM {$prefix}analytics_visits WHERE DATE(created_at) = CURDATE()");

                // 이탈률: 페이지 1개만 본 세션 / 전체 세션
                $bounce = DB::fetch("SELECT
                    ROUND(
                        SUM(CASE WHEN cnt = 1 THEN 1 ELSE 0 END) * 100.0 / GREATEST(COUNT(*), 1)
                    , 1) as bounce_rate
                    FROM (
                        SELECT session_id, COUNT(*) as cnt
                        FROM {$prefix}analytics_visits
                        WHERE DATE(created_at) = CURDATE()
                        GROUP BY session_id
                    ) t");

                // 평균 체류시간: 2페이지 이상 본 세션의 첫~마지막 페이지뷰 시간 차이(초)
                $duration = DB::fetch("SELECT
                    ROUND(AVG(dur)) as avg_duration
                    FROM (
                        SELECT session_id,
                            TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as dur
                        FROM {$prefix}analytics_visits
                        WHERE DATE(created_at) = CURDATE()
                        GROUP BY session_id
                        HAVING COUNT(*) > 1
                    ) t");

                $today['bounce_rate']   = $bounce['bounce_rate'] ?? 0;
                $today['avg_duration']  = (int)($duration['avg_duration'] ?? 0);
                echo json_encode(['success' => true, 'data' => $today]);
                break;

            // 시간대별 (오늘)
            case 'hourly':
                $rows = DB::fetchAll("SELECT HOUR(created_at) as h, COUNT(*) as cnt, COUNT(DISTINCT ip) as uv
                    FROM {$prefix}analytics_visits
                    WHERE DATE(created_at) = CURDATE()
                    GROUP BY HOUR(created_at) ORDER BY h");
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // 일별 추이 (최근 30일)
            case 'daily_trend':
                $days = (int)($input['days'] ?? 30);
                $rows = DB::fetchAll("SELECT stat_date, total_visits, unique_visitors, new_visitors, page_views, search_count, direct_count, social_count
                    FROM {$prefix}analytics_daily
                    WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                    ORDER BY stat_date ASC");
                // 오늘 데이터도 추가
                $todayData = DB::fetch("SELECT
                    CURDATE() as stat_date,
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT ip) as unique_visitors,
                    SUM(is_new_visitor) as new_visitors,
                    COUNT(*) as page_views,
                    SUM(referer_type='search') as search_count,
                    SUM(referer_type='direct') as direct_count,
                    SUM(referer_type='social') as social_count
                FROM {$prefix}analytics_visits WHERE DATE(created_at) = CURDATE()");
                if ($todayData && $todayData['total_visits'] > 0) {
                    $rows[] = $todayData;
                }
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // 유입 경로 TOP
            case 'referers':
                $rows = DB::fetchAll("SELECT referer_domain, referer_type, COUNT(*) as cnt
                    FROM {$prefix}analytics_visits
                    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND referer_type != 'internal' AND referer_domain != ''
                    GROUP BY referer_domain, referer_type
                    ORDER BY cnt DESC LIMIT 20");
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // 검색 키워드 TOP
            case 'keywords':
                $rows = DB::fetchAll("SELECT search_keyword, search_engine, COUNT(*) as cnt
                    FROM {$prefix}analytics_visits
                    WHERE search_keyword != '' AND search_keyword != '(키워드 비공개)'
                      AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY search_keyword, search_engine
                    ORDER BY cnt DESC LIMIT 30");
                $hiddenCount = DB::fetch("SELECT COUNT(*) as cnt FROM {$prefix}analytics_visits
                    WHERE search_keyword = '(키워드 비공개)'
                      AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                echo json_encode(['success' => true, 'data' => $rows, 'hidden' => $hiddenCount['cnt'] ?? 0]);
                break;

            // 인기 페이지 TOP
            case 'pages':
                $rows = DB::fetchAll("SELECT page_url, COUNT(*) as views, COUNT(DISTINCT ip) as visitors
                    FROM {$prefix}analytics_visits
                    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY page_url
                    ORDER BY views DESC LIMIT 20");
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // 브라우저 통계
            case 'browsers':
                $rows = DB::fetchAll("SELECT browser, COUNT(*) as cnt
                    FROM {$prefix}analytics_visits
                    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY browser ORDER BY cnt DESC");
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // OS 통계
            case 'os_stats':
                $rows = DB::fetchAll("SELECT os, COUNT(*) as cnt
                    FROM {$prefix}analytics_visits
                    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY os ORDER BY cnt DESC");
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // 검색엔진별
            case 'engines':
                $rows = DB::fetchAll("SELECT search_engine, COUNT(*) as cnt
                    FROM {$prefix}analytics_visits
                    WHERE referer_type = 'search'
                      AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY search_engine ORDER BY cnt DESC");
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            // 데이터 정리 (90일 이상 된 raw 데이터 삭제)
            case 'cleanup':
                $deleted = DB::query("DELETE FROM {$prefix}analytics_visits WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                echo json_encode(['success' => true, 'message' => '90일 이상 된 상세 데이터가 정리되었습니다.']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => '알 수 없는 요청']);
        }
        exit;
});
} // end class_exists Router

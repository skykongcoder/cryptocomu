<?php
/**
 * AEO 부스터 (Answer Engine Optimization)
 *
 * AI 검색엔진(ChatGPT, Perplexity, Google AI Overview)에 콘텐츠가 채택되도록 최적화.
 *
 * 코어 SEO.php 와 중복 방지:
 *   Article / BreadcrumbList / WebSite / Organization → core 담당 (건드리지 않음)
 *   FAQPage (core 에 메서드만 있고 아무도 호출 안 함 → 이 플러그인이 자동 주입)
 *   HowTo / Speakable (core 에 없음 → 이 플러그인이 자동 주입)
 *
 * 추가 기능:
 *   /llms.txt 자동 생성 (AI 검색엔진 친화 사이트맵)
 *   AI 크롤러(GPTBot, PerplexityBot 등) robots.txt 규칙 관리
 */

// ==================== 설정 헬퍼 ====================
if (!function_exists('aeo_get_setting')) {
    function aeo_get_setting(string $key, string $default = ''): string
    {
        if (defined('NB_SETTINGS')) {
            $s = NB_SETTINGS;
            return isset($s[$key]) ? (string)$s[$key] : $default;
        }
        if (!class_exists('DB')) return $default;
        try {
            $prefix = DB::getPrefix();
            $row = DB::fetch("SELECT setting_value FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            return $row ? (string)$row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('aeo_set_setting')) {
    function aeo_set_setting(string $key, string $value): void
    {
        if (!class_exists('DB')) return;
        try {
            $prefix = DB::getPrefix();
            $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                DB::update("{$prefix}settings", ['setting_value' => $value], 'setting_key = ?', [$key]);
            } else {
                DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $value]);
            }
        } catch (Exception $e) {}
    }
}

// ==================== FAQ 감지 ====================
if (!function_exists('aeo_detect_faq')) {
    function aeo_detect_faq(string $content): array
    {
        $plain = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain === '') return [];

        $faqs = [];

        // 패턴 1: "Q: ... A: ..." / "Q. ... A. ..."
        if (preg_match_all('~(?:^|\n)\s*Q\s*[.:)]+\s*(.+?)(?:\n|$).*?(?:^|\n)\s*A\s*[.:)]+\s*(.+?)(?=(?:\n\s*Q\s*[.:)])|$)~imsu', "\n" . $plain, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $q = trim(preg_replace('~\s+~', ' ', $pair[1]));
                $a = trim(preg_replace('~\s+~', ' ', $pair[2]));
                if ($q && $a && mb_strlen($q) >= 4 && mb_strlen($a) >= 4) {
                    $faqs[] = ['q' => $q, 'a' => $a];
                }
            }
        }

        // 패턴 2: "질문: ... 답변: ..."
        if (empty($faqs) && preg_match_all('~(?:^|\n)\s*질문\s*[.:)]+\s*(.+?)(?:\n|$).*?(?:^|\n)\s*답변\s*[.:)]+\s*(.+?)(?=(?:\n\s*질문)|$)~msu', "\n" . $plain, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $q = trim(preg_replace('~\s+~', ' ', $pair[1]));
                $a = trim(preg_replace('~\s+~', ' ', $pair[2]));
                if ($q && $a && mb_strlen($q) >= 4 && mb_strlen($a) >= 4) {
                    $faqs[] = ['q' => $q, 'a' => $a];
                }
            }
        }

        // 최소 2쌍 있어야 유효
        return count($faqs) >= 2 ? $faqs : [];
    }
}

// ==================== HowTo 감지 ====================
if (!function_exists('aeo_detect_howto')) {
    function aeo_detect_howto(string $content, string $title): array
    {
        $plain = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain === '') return [];

        $titleHints = ['방법', '따라하기', '따라 하기', '튜토리얼', '가이드', '하는 법', '하는법', 'How to', 'Tutorial', 'Guide'];
        $hasTitleHint = false;
        foreach ($titleHints as $h) {
            if (mb_stripos($title, $h) !== false) { $hasTitleHint = true; break; }
        }

        $steps = [];
        // 1. / 1) / 1: / 1- 로 시작하는 줄 감지 (최소 6자 이상 내용)
        if (preg_match_all('~(?:^|\n)\s*\d+\s*[.)\\-:]\s*([^\n]{6,200})~u', "\n" . $plain, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $text = trim(preg_replace('~\s+~', ' ', $row[1]));
                if ($text !== '') $steps[] = $text;
            }
        }

        $minSteps = $hasTitleHint ? 2 : 3;
        if (count($steps) < $minSteps) return [];

        return array_slice($steps, 0, 10);
    }
}

// ==================== 현재 페이지의 게시글 조회 ====================
if (!function_exists('aeo_current_post')) {
    function aeo_current_post(): ?array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = []; // 실패 시 재조회 방지

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // 쿼리스트링/프래그먼트 제거
        $path = strtok($uri, '?');
        $path = strtok($path, '#');
        // /board/{board_id}/{post_id_or_slug}
        if (!preg_match('~^/board/([^/]+)/([^/]+)~', (string)$path, $m)) return null;
        if (!class_exists('DB')) return null;

        try {
            $prefix = DB::getPrefix();
            $boardId = $m[1];
            $key     = $m[2];
            // id 숫자면 id 로, 아니면 slug 로 조회
            if (ctype_digit($key)) {
                $post = DB::fetch("SELECT id, board_id, title, content, slug FROM {$prefix}posts WHERE id = ? AND board_id = ?", [(int)$key, $boardId]);
            } else {
                $post = DB::fetch("SELECT id, board_id, title, content, slug FROM {$prefix}posts WHERE slug = ? AND board_id = ?", [$key, $boardId]);
            }
            if (!$post) return null;

            // URL 재구성
            $site = rtrim(aeo_get_setting('site_url', ''), '/');
            if ($site === '') {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $site  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? '');
            }
            $post['_url'] = $site . '/board/' . $post['board_id'] . '/' . ($post['slug'] ?: $post['id']);
            $cache = $post;
            return $post;
        } catch (Exception $e) {
            return null;
        }
    }
}

// ==================== 스키마 JSON-LD 출력 ====================
if (!function_exists('aeo_build_schemas')) {
    function aeo_build_schemas(): string
    {
        $post = aeo_current_post();
        if (!$post) return '';

        $title   = (string)($post['title'] ?? '');
        $content = (string)($post['content'] ?? '');
        $url     = (string)($post['_url'] ?? '');
        $html    = '';

        // FAQPage
        if (aeo_get_setting('aeo_enable_faq', '1') === '1') {
            $faqs = aeo_detect_faq($content);
            if (!empty($faqs)) {
                $mainEntity = [];
                foreach ($faqs as $f) {
                    $mainEntity[] = [
                        '@type' => 'Question',
                        'name'  => $f['q'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => $f['a'],
                        ],
                    ];
                }
                $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $mainEntity];
                $html .= "\n<script type=\"application/ld+json\">" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
            }
        }

        // HowTo
        if (aeo_get_setting('aeo_enable_howto', '1') === '1') {
            $steps = aeo_detect_howto($content, $title);
            if (!empty($steps)) {
                $stepList = [];
                foreach ($steps as $i => $s) {
                    $stepList[] = [
                        '@type'    => 'HowToStep',
                        'position' => $i + 1,
                        'name'     => mb_strimwidth($s, 0, 80, '...'),
                        'text'     => $s,
                    ];
                }
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type'    => 'HowTo',
                    'name'     => $title,
                    'step'     => $stepList,
                ];
                if ($url) $schema['url'] = $url;
                $html .= "\n<script type=\"application/ld+json\">" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
            }
        }

        // Speakable (AI 음성 검색용)
        if (aeo_get_setting('aeo_enable_speakable', '1') === '1' && $title) {
            $schema = [
                '@context'  => 'https://schema.org',
                '@type'     => 'WebPage',
                'name'      => $title,
                'speakable' => [
                    '@type'       => 'SpeakableSpecification',
                    'cssSelector' => ['.post-title', '.post-content', 'article h1', 'article p'],
                ],
            ];
            if ($url) $schema['url'] = $url;
            $html .= "\n<script type=\"application/ld+json\">" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
        }

        return $html;
    }
}

// ==================== head 에 스키마 주입 ====================
$__aeo_html = aeo_build_schemas();
if ($__aeo_html !== '' && class_exists('Plugin') && method_exists('Plugin', 'queueHeaderAsset')) {
    Plugin::queueHeaderAsset($__aeo_html);
}

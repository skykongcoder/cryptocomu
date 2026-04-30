<?php
/**
 * 이미지 ALT 자동 삽입 플러그인
 * 게시글 본문의 이미지에 alt 속성이 비어있으면 글 제목으로 자동 채워줍니다.
 */

Plugin::addFilter('post_content', function ($content) {
    // 현재 요청에서 게시글 정보 가져오기
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!preg_match('#/board/([^/]+)/(\d+)#', $uri, $m)) return $content;

    $boardId = $m[1];

    // 설정 로드
    $configFile = __DIR__ . '/config.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    $allowedBoards = $config['boards'] ?? [];
    $keyword = trim($config['keyword'] ?? '');

    // 특정 게시판만 적용 (비어있으면 전체 적용)
    if (!empty($allowedBoards) && !in_array($boardId, $allowedBoards)) return $content;

    $post = Post::find((int)$m[2]);
    if (!$post || empty($post['title'])) return $content;

    // alt 텍스트 생성: 제목 + 키워드
    $altText = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8');
    if ($keyword !== '') {
        $altText .= ' - ' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
    }

    // alt="" 또는 alt 속성이 없는 img 태그에 삽입
    $content = preg_replace_callback('/<img\b([^>]*)>/i', function ($match) use ($altText) {
        $attrs = $match[1];

        // alt 속성이 있고 값이 비어있지 않으면 그대로 유지
        if (preg_match('/\balt\s*=\s*"([^"]+)"/i', $attrs)) return $match[0];
        if (preg_match("/\balt\s*=\s*'([^']+)'/i", $attrs)) return $match[0];

        // alt="" 인 경우 교체
        if (preg_match('/\balt\s*=\s*""/i', $attrs)) {
            $attrs = preg_replace('/\balt\s*=\s*""/i', 'alt="' . $altText . '"', $attrs);
            return '<img' . $attrs . '>';
        }

        // alt 속성이 아예 없는 경우 추가
        if (!preg_match('/\balt\s*=/i', $attrs)) {
            return '<img alt="' . $altText . '"' . $attrs . '>';
        }

        return $match[0];
    }, $content);

    return $content;
}, 5);

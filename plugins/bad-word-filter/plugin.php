<?php
/**
 * 금칙어 필터 플러그인
 * 게시글/댓글에 금칙어 포함 시 자동 *** 치환
 */

$_bwWordsFile = __DIR__ . '/words.txt';
$_bwWordsRaw = file_exists($_bwWordsFile) ? file($_bwWordsFile) : [];
$GLOBALS['_bwWords'] = is_array($_bwWordsRaw) ? array_filter(array_map('trim', $_bwWordsRaw)) : [];

// 금칙어 치환 함수
function _bwFilter($text) {
    $words = $GLOBALS['_bwWords'] ?? [];
    if (empty($words)) return $text;
    foreach ($words as $word) {
        if ($word === '') continue;
        $replacement = str_repeat('*', mb_strlen($word));
        $text = str_ireplace($word, $replacement, $text);
    }
    return $text;
}

// 게시글 제목 필터
Plugin::addFilter('post_title', '_bwFilter');

// 게시글 내용 필터
Plugin::addFilter('post_content', '_bwFilter');

// 댓글 내용 필터
Plugin::addFilter('comment_content', '_bwFilter');

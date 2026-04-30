<?php
/**
 * 텔레그램 알림 v1.0
 * 게시글/댓글/쪽지 발생 시 관리자에게 텔레그램 알림을 보냅니다.
 * 설정은 /data/telegram-notify/config.json 에 저장 (플러그인 삭제 후 재설치해도 유지)
 */

function _tgn_data_dir(): string {
    $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
    $dir  = $base . '/data/telegram-notify';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _tgn_load_config(): array {
    $file = _tgn_data_dir() . '/config.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($data)) $data = [];
    return array_merge([
        'bot_token'       => '',
        'chat_id'         => '',
        'notify_post'     => '1',
        'notify_comment'  => '1',
        'notify_memo'     => '1',
        'last_comment_id' => 0,
        'last_memo_id'    => 0,
        'last_poll'       => '',
    ], $data);
}

function _tgn_save_config(array $config): void {
    file_put_contents(
        _tgn_data_dir() . '/config.json',
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function _tgn_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    return $scheme . '://' . $host;
}

function _tgn_truncate(string $text, int $len = 100): string {
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', trim($text));
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len) . '...';
}

function _tgn_send(string $token, string $chatId, string $message): void {
    if (empty($token) || empty($chatId)) return;
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ===== 게시글 생성 훅 (즉시 알림) =====
Plugin::addHook('post_created', function($id, $data) {
    $config = _tgn_load_config();
    if (empty($config['bot_token']) || empty($config['chat_id'])) return;
    if (($config['notify_post'] ?? '1') !== '1') return;

    $title   = _tgn_truncate($data['title']   ?? '(제목없음)', 60);
    $content = _tgn_truncate($data['content'] ?? '', 100);
    $board   = $data['board_id'] ?? '';
    $author  = $data['nickname'] ?? ($data['name'] ?? '익명');
    $url     = _tgn_base_url() . '/board/' . $board . '/' . $id;

    $msg = "📝 <b>새 게시글</b>\n"
         . "게시판: {$board}\n"
         . "작성자: {$author}\n"
         . "제목: {$title}\n"
         . "내용: {$content}\n"
         . "<a href=\"{$url}\">👉 바로가기</a>";

    _tgn_send($config['bot_token'], $config['chat_id'], $msg);
});

// ===== 댓글/쪽지 폴링 (60초 간격) =====
Plugin::addHook('after_header', function() {
    $config = _tgn_load_config();
    if (empty($config['bot_token']) || empty($config['chat_id'])) return;

    // 60초 이내 재실행 방지
    $lastPoll = $config['last_poll'] ?? '';
    if ($lastPoll && (time() - strtotime($lastPoll)) < 60) return;

    $config['last_poll'] = date('Y-m-d H:i:s');
    $prefix = DB::getPrefix();

    // 새 댓글 확인
    if (($config['notify_comment'] ?? '1') === '1') {
        $lastId = (int)($config['last_comment_id'] ?? 0);
        $rows   = DB::fetchAll(
            "SELECT c.id, c.post_id, c.content, m.nickname, p.title AS post_title, p.board_id
             FROM {$prefix}comments c
             LEFT JOIN {$prefix}members m ON c.member_id = m.id
             LEFT JOIN {$prefix}posts   p ON c.post_id   = p.id
             WHERE c.id > ? ORDER BY c.id ASC LIMIT 5",
            [$lastId]
        );
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $author  = _tgn_truncate($row['nickname']   ?? '익명', 20);
                $post    = _tgn_truncate($row['post_title'] ?? '', 40);
                $content = _tgn_truncate($row['content']   ?? '', 100);
                $url     = _tgn_base_url() . '/board/' . ($row['board_id'] ?? '') . '/' . ($row['post_id'] ?? '');
                $msg = "💬 <b>새 댓글</b>\n"
                     . "작성자: {$author}\n"
                     . "글: {$post}\n"
                     . "내용: {$content}\n"
                     . "<a href=\"{$url}\">👉 바로가기</a>";
                _tgn_send($config['bot_token'], $config['chat_id'], $msg);
                $config['last_comment_id'] = (int)$row['id'];
            }
        }
    }

    // 새 쪽지 확인
    if (($config['notify_memo'] ?? '1') === '1') {
        $lastId = (int)($config['last_memo_id'] ?? 0);
        $rows   = DB::fetchAll(
            "SELECT m.id, m.content, s.nickname AS sender
             FROM {$prefix}messages m
             LEFT JOIN {$prefix}members s ON m.sender_id = s.id
             WHERE m.id > ? ORDER BY m.id ASC LIMIT 5",
            [$lastId]
        );
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $sender  = _tgn_truncate($row['sender']  ?? '익명', 20);
                $content = _tgn_truncate($row['content'] ?? '', 100);
                $url     = _tgn_base_url() . '/messages';
                $msg = "✉️ <b>새 쪽지</b>\n"
                     . "보낸이: {$sender}\n"
                     . "내용: {$content}\n"
                     . "<a href=\"{$url}\">👉 쪽지함 보기</a>";
                _tgn_send($config['bot_token'], $config['chat_id'], $msg);
                $config['last_memo_id'] = (int)$row['id'];
            }
        }
    }

    _tgn_save_config($config);
});

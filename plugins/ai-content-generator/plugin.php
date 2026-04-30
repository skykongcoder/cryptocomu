<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * AI 콘텐츠 자동 생성기 플러그인
 * OpenAI API로 키워드 기반 글/댓글 자동 생성
 */

// 설정 파일
$_acConfigFile = __DIR__ . '/config.json';
$_acConfig = file_exists($_acConfigFile) ? json_decode(file_get_contents($_acConfigFile), true) : [];
$_acConfig = array_merge([
    'api_key' => '',
    'keywords' => '',
    'site_desc' => '',
    'tone' => 'friendly',
    'posts_per_board' => 10,
    'comments_per_post' => 4,
    'auto_boards' => '1',
], $_acConfig);

// AI 요청 함수
function _acAskGPT($prompt, $apiKey) {
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'openai/gpt-oss-120b:free',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.9,
        ]),
        CURLOPT_TIMEOUT => 90,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? '';
}

// 관리자 설정 페이지
Plugin::addHook('plugin.settings.ai-content-generator', function() use ($_acConfigFile, &$_acConfig) {

    // 설정 저장
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ac_save'])) {
        $_acConfig['api_key'] = trim($_POST['ac_api_key'] ?? '');
        $_acConfig['keywords'] = trim($_POST['ac_keywords'] ?? '');
        $_acConfig['site_desc'] = trim($_POST['ac_site_desc'] ?? '');
        $_acConfig['tone'] = $_POST['ac_tone'] ?? 'friendly';
        $_acConfig['posts_per_board'] = max(1, min(30, (int)($_POST['ac_posts_per_board'] ?? 10)));
        $_acConfig['comments_per_post'] = max(0, min(10, (int)($_POST['ac_comments_per_post'] ?? 4)));
        $_acConfig['auto_boards'] = isset($_POST['ac_auto_boards']) ? '1' : '0';
        // 선택된 게시판 외 나머지를 exclude
        $selectedBoards = $_POST['ac_boards'] ?? [];
        $allBoardIds = array_column(DB::fetchAll("SELECT board_id FROM " . DB::getPrefix() . "boards WHERE is_active = 1"), 'board_id');
        $_acConfig['exclude_boards'] = implode(',', array_diff($allBoardIds, $selectedBoards));
        file_put_contents($_acConfigFile, json_encode($_acConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo '<div class="alert success">설정이 저장되었습니다.</div>';
    }

    // 콘텐츠 생성 실행
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ac_generate'])) {
        if (empty($_acConfig['api_key'])) {
            echo '<div class="alert error">OpenRouter API 키를 먼저 설정하세요.</div>';
        } else {
            set_time_limit(600);
            echo '<div class="card"><div class="card-body"><h3>생성 중...</h3><pre style="max-height:400px;overflow-y:auto;background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;font-size:12px">';
            ob_flush(); flush();

            $prefix = DB::getPrefix();

            // 기본 게시판 자동 생성
            if ($_acConfig['auto_boards'] === '1') {
                $defaultBoards = [
                    ['board_id' => 'free', 'title' => '자유게시판', 'description' => '자유롭게 글을 쓸 수 있는 공간입니다.'],
                    ['board_id' => 'qna', 'title' => '질문답변', 'description' => '궁금한 것을 질문하고 답변을 받아보세요.'],
                    ['board_id' => 'notice', 'title' => '공지사항', 'description' => '사이트 공지사항을 확인하세요.'],
                    ['board_id' => 'info', 'title' => '정보공유', 'description' => '유용한 정보를 공유하는 게시판입니다.'],
                ];
                foreach ($defaultBoards as $db) {
                    $exists = DB::fetch("SELECT id FROM {$prefix}boards WHERE board_id = ?", [$db['board_id']]);
                    if (!$exists) {
                        Board::create([
                            'board_id' => $db['board_id'], 'title' => $db['title'],
                            'description' => $db['description'], 'board_type' => 'normal',
                            'list_count' => 20, 'sort_order' => 0,
                            'write_level' => 2, 'comment_level' => 2,
                        ]);
                        echo "게시판 생성: {$db['title']} ({$db['board_id']})\n";
                    } else {
                        echo "게시판 '{$db['title']}' 이미 존재\n";
                    }
                }
                // 메뉴 자동 등록 (없으면)
                $menuCount = DB::count("{$prefix}menus");
                if ($menuCount < 1) {
                    foreach ($defaultBoards as $si => $db) {
                        Menu::create(['parent_id' => 0, 'title' => $db['title'], 'link' => '', 'board_id' => $db['board_id'], 'sort_order' => $si]);
                    }
                    echo "메뉴 자동 등록 완료\n";
                }
                echo "\n"; ob_flush(); flush();
            }

            $boards = DB::fetchAll("SELECT board_id, title, board_type FROM {$prefix}boards WHERE is_active = 1");
            $keywords = array_filter(array_map('trim', explode(',', $_acConfig['keywords'])));
            $siteDesc = $_acConfig['site_desc'];
            $tone = $_acConfig['tone'];
            $toneMap = ['friendly' => '친근하고 캐주얼한', 'formal' => '정중하고 격식있는', 'fun' => '재미있고 유머러스한', 'info' => '정보성 있고 전문적인'];
            $toneText = $toneMap[$tone] ?? '친근한';
            $postsPerBoard = $_acConfig['posts_per_board'];
            $commentsPerPost = $_acConfig['comments_per_post'];

            // 가상 회원 생성
            $nicknames = ['봄바람','하늘빛','커피한잔','별빛소녀','초록이','달빛산책','파도소리','구름위','햇살가득','민트초코','벚꽃엔딩','바다향기','노을빛','산들바람','꿈꾸는양','고양이발','라면좋아','음악듣는중','독서왕','코딩마스터'];
            $memberIds = [];
            foreach ($nicknames as $i => $nick) {
                $userId = 'ai_user_' . ($i + 1);
                $exists = DB::fetch("SELECT id FROM {$prefix}members WHERE user_id = ?", [$userId]);
                if ($exists) { $memberIds[] = $exists['id']; continue; }
                $id = DB::insert("{$prefix}members", [
                    'user_id' => $userId, 'password' => password_hash('aiuser1234!', PASSWORD_BCRYPT),
                    'nickname' => $nick, 'email' => '', 'level' => rand(2, 6),
                    'point' => rand(30, 300), 'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days')),
                ]);
                $memberIds[] = $id;
                echo "회원 생성: {$nick}\n"; ob_flush(); flush();
            }

            // 게시판별 글 생성
            $excludeBoards = array_filter(array_map('trim', explode(',', $_acConfig['exclude_boards'] ?? '')));
            foreach ($boards as $board) {
                if ($board['board_type'] === 'gallery') {
                    echo "\n이미지 게시판 '{$board['title']}' 건너뜀\n"; continue;
                }
                if (in_array($board['board_id'], $excludeBoards)) {
                    echo "\n'{$board['title']}' 제외됨\n"; continue;
                }

                echo "\n===== {$board['title']} 게시글 생성 중 =====\n"; ob_flush(); flush();

                $kwList = implode(', ', $keywords);
                $prompt = "한국 커뮤니티 사이트의 '{$board['title']}' 게시판에 올릴 게시글 {$postsPerBoard}개를 만들어줘.

사이트 설명: {$siteDesc}
관련 키워드: {$kwList}
분위기: {$toneText} 톤으로 작성

각 게시글은 키워드를 자연스럽게 포함하는 롱테일 키워드 기반으로 작성해.
제목은 커뮤니티 글 느낌으로 자연스럽게.
내용은 3~6문장.
댓글은 {$commentsPerPost}개씩, 다양한 반응으로.

JSON 배열만 출력:
[{\"title\":\"제목\",\"content\":\"본문\",\"comments\":[\"댓글1\",\"댓글2\"]}]";

                $response = _acAskGPT($prompt, $_acConfig['api_key']);
                preg_match('/\[.*\]/s', $response, $matches);
                if (empty($matches[0])) { echo "AI 응답 파싱 실패\n"; continue; }
                $posts = json_decode($matches[0], true);
                if (!$posts) { echo "JSON 디코딩 실패\n"; continue; }

                foreach ($posts as $p) {
                    $memberId = $memberIds[array_rand($memberIds)];
                    $createdAt = date('Y-m-d H:i:s', strtotime('-' . rand(0, 14) . ' days -' . rand(0, 23) . ' hours -' . rand(0, 59) . ' minutes'));
                    $postId = DB::insert("{$prefix}posts", [
                        'board_id' => $board['board_id'], 'member_id' => $memberId,
                        'title' => mb_strimwidth($p['title'] ?? '', 0, 200),
                        'content' => '<p>' . nl2br(htmlspecialchars($p['content'] ?? '')) . '</p>',
                        'slug' => '', 'hit' => rand(10, 300), 'comment_count' => 0,
                        'vote_up' => 0, 'vote_down' => 0,
                        'created_at' => $createdAt, 'updated_at' => $createdAt,
                    ]);
                    echo "  [{$postId}] {$p['title']}\n"; ob_flush(); flush();

                    $cc = 0;
                    foreach ($p['comments'] ?? [] as $cText) {
                        $cMid = $memberIds[array_rand($memberIds)];
                        DB::insert("{$prefix}comments", [
                            'post_id' => $postId, 'member_id' => $cMid, 'parent_id' => 0,
                            'content' => htmlspecialchars($cText),
                            'created_at' => date('Y-m-d H:i:s', strtotime($createdAt . ' +' . rand(1, 72) . ' hours')),
                        ]);
                        $cc++;
                    }
                    DB::update("{$prefix}posts", ['comment_count' => $cc], 'id = ?', [$postId]);
                }
                echo "  → " . count($posts) . "개 생성 완료\n"; ob_flush(); flush();
            }

            // 캐시 삭제
            if (class_exists('Cache')) Cache::flush();
            echo "\n===== 모든 콘텐츠 생성 완료! =====\n";
            echo '</pre></div></div>';
            return;
        }
    }

    ?>
    <form method="post">
        <input type="hidden" name="ac_save" value="1">
        <div class="form-group">
            <label>OpenRouter API 키</label>
            <input type="text" name="ac_api_key" value="<?= htmlspecialchars($_acConfig['api_key']) ?>" placeholder="sk-or-v1-..." style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-family:monospace">
            <small>OpenAI에서 발급받은 API 키 (<a href="https://openrouter.ai/keys" target="_blank">발급받기</a>)</small>
        </div>
        <div class="form-group">
            <label>키워드 (쉼표로 구분)</label>
            <input type="text" name="ac_keywords" value="<?= htmlspecialchars($_acConfig['keywords']) ?>" placeholder="누리보드, 커뮤니티, CMS, 그누보드" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px">
            <small>AI가 이 키워드 기반으로 롱테일 키워드를 만들고 글을 작성합니다</small>
        </div>
        <div class="form-group">
            <label>사이트 설명</label>
            <textarea name="ac_site_desc" rows="3" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;resize:vertical" placeholder="그누보드보다 쉬운 한국형 커뮤니티 CMS"><?= htmlspecialchars($_acConfig['site_desc']) ?></textarea>
            <small>사이트가 어떤 곳인지 설명하면 AI가 맞춤 글을 작성합니다</small>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>글 분위기</label>
                <select name="ac_tone" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px">
                    <option value="friendly" <?= $_acConfig['tone'] === 'friendly' ? 'selected' : '' ?>>친근한 커뮤니티</option>
                    <option value="formal" <?= $_acConfig['tone'] === 'formal' ? 'selected' : '' ?>>정중한 격식체</option>
                    <option value="fun" <?= $_acConfig['tone'] === 'fun' ? 'selected' : '' ?>>유머러스한</option>
                    <option value="info" <?= $_acConfig['tone'] === 'info' ? 'selected' : '' ?>>정보성/전문적</option>
                </select>
            </div>
            <div class="form-group">
                <label>게시판당 글 수</label>
                <input type="number" name="ac_posts_per_board" value="<?= $_acConfig['posts_per_board'] ?>" min="1" max="30" style="width:80px;padding:8px;border:1px solid #d1d5db;border-radius:8px">
            </div>
            <div class="form-group">
                <label>글당 댓글 수</label>
                <input type="number" name="ac_comments_per_post" value="<?= $_acConfig['comments_per_post'] ?>" min="0" max="10" style="width:80px;padding:8px;border:1px solid #d1d5db;border-radius:8px">
            </div>
        </div>
        <div class="form-group" style="margin-top:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="checkbox" name="ac_auto_boards" value="1" <?= $_acConfig['auto_boards'] === '1' ? 'checked' : '' ?>>
                기본 게시판 자동 생성 (자유게시판, 질문답변, 공지사항, 정보공유 + 메뉴 등록)
            </label>
            <small style="margin-left:26px">체크하면 게시판이 없어도 자동으로 4개 생성 후 글을 채웁니다</small>
        </div>
        <div class="form-group" style="margin-top:12px">
            <label style="font-size:14px;font-weight:600;margin-bottom:8px;display:block">글을 생성할 게시판 선택</label>
            <?php
            $allBoards = DB::fetchAll("SELECT board_id, title, board_type FROM " . DB::getPrefix() . "boards WHERE is_active = 1");
            $excludeBoards = array_filter(array_map('trim', explode(',', $_acConfig['exclude_boards'] ?? '')));
            foreach ($allBoards as $_b):
                $checked = !in_array($_b['board_id'], $excludeBoards) ? 'checked' : '';
            ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;padding:4px 0">
                <input type="checkbox" name="ac_boards[]" value="<?= htmlspecialchars($_b['board_id']) ?>" <?= $checked ?>>
                <?= htmlspecialchars($_b['title']) ?>
                <?= $_b['board_type'] === 'gallery' ? '<span style="color:#94a3b8;font-size:11px">(이미지)</span>' : '' ?>
            </label>
            <?php endforeach; ?>
            <small>체크 해제한 게시판에는 글이 생성되지 않습니다</small>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="submit" class="btn btn-primary">설정 저장</button>
        </div>
    </form>

    <div style="margin-top:24px;padding-top:20px;border-top:2px solid #e2e8f0">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:12px">콘텐츠 생성</h3>
        <p style="font-size:13px;color:#64748b;margin-bottom:12px">
            위 설정을 저장한 후, 아래 버튼을 클릭하면 모든 게시판에 AI가 자동으로 글과 댓글을 생성합니다.<br>
            게시판이 많을수록 시간이 오래 걸립니다 (게시판당 약 30초~1분).
        </p>
        <form method="post" onsubmit="return confirm('모든 활성 게시판에 콘텐츠를 생성합니다. 진행할까요?')">
            <input type="hidden" name="ac_generate" value="1">
            <button type="submit" class="btn btn-lg" style="background:#059669;color:#fff;border-color:#059669;font-size:15px;padding:12px 32px">
                AI 콘텐츠 생성 시작
            </button>
        </form>
    </div>
    <?php
});

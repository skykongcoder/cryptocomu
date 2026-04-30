<?php
/**
 * 자동 사이트 빌더 (AI)
 *
 * 키워드·문구를 입력하면 AI가 업종/주제를 추론해
 * 메뉴 5~10개와 게시판·게시글·댓글을 원클릭 자동 생성.
 */
require_once __DIR__ . '/../_openrouter_models.php';

// ---------- 설정 로드 ----------
$_asbConfigFile = __DIR__ . '/config.json';
$_asbConfigRaw  = file_exists($_asbConfigFile) ? json_decode(file_get_contents($_asbConfigFile), true) : [];
if (!is_array($_asbConfigRaw)) $_asbConfigRaw = [];
$_asbConfig = array_merge([
    'api_key'            => '',
    'model'              => 'openai/gpt-4o-mini',
    'keywords'           => '',
    'posts_per_board'    => 8,
    'comments_per_post'  => 6,
    'virtual_members'    => 30,
    'reply_ratio'        => 40,
    'auto_create_boards' => '1',
    'auto_create_menus'  => '1',
], $_asbConfigRaw);

// ---------- OpenAI 호출 헬퍼 ----------
if (!function_exists('_asbCallGPT')) {
    function _asbCallGPT($prompt, $apiKey, $model, $jsonMode = true) {
        $payload = [
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.9,
        ];
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return ['error' => 'curl: ' . $err];
        $data = json_decode($res, true);
        if (isset($data['error'])) {
            return ['error' => 'api: ' . ($data['error']['message'] ?? 'unknown')];
        }
        $content = $data['choices'][0]['message']['content'] ?? '';
        if ($jsonMode) {
            $parsed = json_decode($content, true);
            if (!is_array($parsed)) return ['error' => 'json_parse_failed', 'raw' => $content];
            return ['ok' => true, 'data' => $parsed];
        }
        return ['ok' => true, 'text' => $content];
    }
}

// ---------- 관리자 member_id ----------
if (!function_exists('_asbGetAdminMemberId')) {
    function _asbGetAdminMemberId() {
        if (class_exists('Auth') && Auth::check()) {
            $mid = (int)Auth::id();
            if ($mid > 0) return $mid;
        }
        $prefix = DB::getPrefix();
        $row = DB::fetch("SELECT id FROM {$prefix}members WHERE is_admin = 1 ORDER BY id ASC LIMIT 1");
        if ($row && !empty($row['id'])) return (int)$row['id'];
        $row2 = DB::fetch("SELECT id FROM {$prefix}members ORDER BY id ASC LIMIT 1");
        return $row2 ? (int)$row2['id'] : 1;
    }
}

// ---------- 가상 회원 풀 생성 ----------
if (!function_exists('_asbEnsureVirtualMembers')) {
    function _asbEnsureVirtualMembers($count = 30) {
        $prefix = DB::getPrefix();
        $nicknames = [
            '봄바람','하늘빛','커피한잔','별빛소녀','초록이','달빛산책','파도소리','구름위','햇살가득','민트초코',
            '벚꽃엔딩','바다향기','노을빛','산들바람','꿈꾸는양','고양이발','라면좋아','음악듣는중','독서왕','코딩마스터',
            '야근요정','주말만세','카페인중독','새벽세시','퇴근길','출근러','월급루팡','프론트왕','백엔드빌런','디자이너K',
            '그누탈출','누리덕후','플러그인장인','스킨러버','마켓구경','초보관리자','서버터짐','DB요정','404찾아라','UX고민중',
            '커뮤니티매니아','피드백주는사람','버그사냥꾼','오픈소스러버','PHP7사수대','MySQL친구','리팩토링중','깃허브탐험가','무한스크롤','다크모드광',
        ];
        $created = [];
        $existingIds = [];
        for ($i = 0; $i < $count; $i++) {
            $userId = 'asb_bot_' . ($i + 1);
            $exists = DB::fetch("SELECT id FROM {$prefix}members WHERE user_id = ?", [$userId]);
            if ($exists) {
                $existingIds[] = (int)$exists['id'];
                continue;
            }
            $nick = $nicknames[$i % count($nicknames)];
            if ($i >= count($nicknames)) $nick .= ($i - count($nicknames) + 2);
            $id = DB::insert("{$prefix}members", [
                'user_id'    => $userId,
                'password'   => password_hash('asb_' . bin2hex(random_bytes(8)), PASSWORD_BCRYPT),
                'nickname'   => $nick,
                'email'      => '',
                'level'      => rand(2, 6),
                'point'      => rand(30, 500),
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(5, 120) . ' days')),
            ]);
            $created[] = $id;
        }
        $all = array_merge($existingIds, $created);
        return [
            'ids'         => $all,
            'created_cnt' => count($created),
            'existed_cnt' => count($existingIds),
        ];
    }
}

// ---------- 설정 페이지 Hook ----------
Plugin::addHook('plugin.settings.' . basename(__DIR__), function () use ($_asbConfigFile, &$_asbConfig) {

    if (class_exists('Auth') && !Auth::isAdmin()) {
        echo '<div class="alert error">관리자 권한이 필요합니다.</div>';
        return;
    }

    $prefix = DB::getPrefix();

    // ========== (A) 설정 저장 ==========
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asb_save'])) {
        $_asbConfig['api_key']            = trim($_POST['asb_api_key'] ?? '');
        $_asbConfig['model']              = $_POST['asb_model'] ?? 'openai/gpt-4o-mini';
        $_asbConfig['keywords']           = trim($_POST['asb_keywords'] ?? '');
        $_asbConfig['posts_per_board']    = max(1, min(30, (int)($_POST['asb_posts_per_board'] ?? 8)));
        $_asbConfig['comments_per_post']  = max(0, min(20, (int)($_POST['asb_comments_per_post'] ?? 6)));
        $_asbConfig['virtual_members']    = max(5, min(100, (int)($_POST['asb_virtual_members'] ?? 30)));
        $_asbConfig['reply_ratio']        = max(0, min(100, (int)($_POST['asb_reply_ratio'] ?? 40)));
        $_asbConfig['auto_create_boards'] = isset($_POST['asb_auto_create_boards']) ? '1' : '0';
        $_asbConfig['auto_create_menus']  = isset($_POST['asb_auto_create_menus']) ? '1' : '0';
        file_put_contents($_asbConfigFile, json_encode($_asbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo '<div class="alert success">설정이 저장되었습니다.</div>';
    }

    // ========== (B) 사이트 분석 ==========
    $analysis = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asb_analyze'])) {
        if (empty($_asbConfig['api_key'])) {
            echo '<div class="alert error">OpenRouter API 키를 먼저 설정하세요.</div>';
        } elseif (empty(trim($_asbConfig['keywords']))) {
            echo '<div class="alert error">키워드를 먼저 입력하고 저장하세요.</div>';
        } else {
            set_time_limit(180);

            $boards = DB::fetchAll("SELECT board_id, title FROM {$prefix}boards WHERE is_active = 1 ORDER BY sort_order");
            $menus  = DB::fetchAll("SELECT title FROM {$prefix}menus WHERE is_active = 1 ORDER BY parent_id, sort_order");

            $existingBoardIds = implode(', ', array_column($boards, 'board_id'));
            $existingMenuTitles = implode(', ', array_column($menus, 'title'));
            if ($existingBoardIds === '') $existingBoardIds = '없음';
            if ($existingMenuTitles === '') $existingMenuTitles = '없음';

            $keywords = trim($_asbConfig['keywords']);

            $prompt = <<<PROMPT
너는 한국어 웹사이트 기획 전문가다.

운영자가 아래 키워드와 문구를 입력했다. 이것을 분석해 어떤 업종·주제의 사이트인지 파악하고, 그에 맞는 게시판과 다단 메뉴를 제안하라.

[입력된 키워드/문구]
{$keywords}

[현재 존재하는 게시판 ID] (중복 금지)
{$existingBoardIds}

[현재 존재하는 메뉴 제목] (중복 금지)
{$existingMenuTitles}

규칙:
- 키워드를 보고 업종·주제를 추론해 detected_topic 한 문장으로 작성
- suggested_boards: 5~10개, 주제와 딱 맞는 구체적인 이름 (범용 자유게시판보다 주제 밀착형)
- board_id는 영문 소문자/숫자/하이픈만 (예: dental-review, health-tips)
- categories는 쉼표 구분 4~6개
- suggested_menus: 상위 카테고리 2~4개(parent_idx=-1) 아래 각 2~3개 하위 메뉴, 총 5~10개
- 상위 메뉴는 board_id 없이 카테고리 역할, 하위 메뉴가 실제 게시판 연결
- 기존 board_id·메뉴 제목과 절대 중복 금지

반드시 아래 JSON 스키마만 출력 (코드블록/설명 금지):
{
  "detected_topic": "한 문장으로 사이트 주제 추론",
  "tone": "커뮤니티 분위기",
  "suggested_boards": [
    {
      "board_id": "dental-review",
      "title": "치료 후기",
      "description": "한 줄 설명",
      "categories": "A,B,C,D",
      "board_type": "normal"
    }
  ],
  "suggested_menus": [
    {"title": "병원 정보", "board_id": "", "parent_idx": -1, "sort_order": 10},
    {"title": "치료 후기", "board_id": "dental-review", "parent_idx": 0, "sort_order": 11}
  ]
}

suggested_menus 설명:
- parent_idx = -1 이면 최상위 메뉴 (카테고리용)
- parent_idx = N 이면 suggested_menus 배열의 N번째 항목이 부모 (배열 내 상대 인덱스)
- 상위 카테고리가 먼저 오고 그 아래 하위 메뉴들이 따라오도록 배열 순서 구성
PROMPT;

            $result = _asbCallGPT($prompt, $_asbConfig['api_key'], $_asbConfig['model'], true);
            if (isset($result['error'])) {
                echo '<div class="alert error">AI 호출 실패: ' . htmlspecialchars($result['error']) . '</div>';
            } else {
                $analysis = $result['data'];
                echo '<div class="alert success">분석 완료! 아래 제안을 확인하고 "전체 자동 구성"을 누르세요.</div>';
            }
        }
    }

    // ========== (C) 실제 생성 ==========
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asb_generate'])) {
        if (empty($_asbConfig['api_key'])) {
            echo '<div class="alert error">OpenRouter API 키를 먼저 설정하세요.</div>';
        } else {
            $analysisJson = $_POST['asb_analysis_json'] ?? '';
            $analysis = json_decode($analysisJson, true);
            if (!is_array($analysis)) {
                echo '<div class="alert error">분석 데이터가 없습니다. 먼저 "사이트 분석"을 실행하세요.</div>';
            } else {
                set_time_limit(1800);
                @ini_set('output_buffering', 'off');
                @ini_set('zlib.output_compression', false);

                echo '<div class="card" style="margin-top:16px"><div class="card-body"><h3 style="margin:0 0 12px">자동 구성 진행 중...</h3>';
                echo '<pre style="max-height:600px;overflow-y:auto;background:#0f172a;color:#e2e8f0;padding:16px;border-radius:8px;font-size:12px;line-height:1.6;white-space:pre-wrap">';
                @ob_flush(); @flush();

                $topic = $analysis['detected_topic'] ?? '커뮤니티 사이트';
                $tone  = $analysis['tone'] ?? '친근한 커뮤니티';
                echo "주제: {$topic}\n톤: {$tone}\n\n";
                @ob_flush(); @flush();

                // 1) 게시판 생성
                if ($_asbConfig['auto_create_boards'] === '1' && !empty($analysis['suggested_boards'])) {
                    echo "===== 게시판 생성 =====\n";
                    foreach ($analysis['suggested_boards'] as $sb) {
                        $bid = preg_replace('/[^a-z0-9\-]/', '', strtolower($sb['board_id'] ?? ''));
                        if ($bid === '') continue;
                        $exists = DB::fetch("SELECT id FROM {$prefix}boards WHERE board_id = ?", [$bid]);
                        if ($exists) {
                            echo "  SKIP  {$bid} ({$sb['title']}) - 이미 존재\n";
                            continue;
                        }
                        Board::create([
                            'board_id'      => $bid,
                            'title'         => mb_substr($sb['title'] ?? $bid, 0, 100),
                            'description'   => mb_substr($sb['description'] ?? '', 0, 1000),
                            'board_type'    => ($sb['board_type'] ?? 'normal') === 'gallery' ? 'gallery' : 'normal',
                            'categories'    => mb_substr($sb['categories'] ?? '', 0, 500),
                            'list_count'    => 20,
                            'sort_order'    => 100,
                            'is_active'     => 1,
                            'write_level'   => 2,
                            'comment_level' => 2,
                        ]);
                        echo "  NEW   {$bid} ({$sb['title']})\n";
                        @ob_flush(); @flush();
                    }
                    echo "\n";
                }

                // 2) 메뉴 생성 (다단 구조)
                if ($_asbConfig['auto_create_menus'] === '1' && !empty($analysis['suggested_menus'])) {
                    echo "===== 메뉴 생성 =====\n";
                    $existingMenuBoards = array_column(
                        DB::fetchAll("SELECT board_id FROM {$prefix}menus WHERE board_id != '' AND is_active = 1"),
                        'board_id'
                    );
                    $existingMenuTitles = array_column(
                        DB::fetchAll("SELECT title FROM {$prefix}menus WHERE is_active = 1"),
                        'title'
                    );
                    $idxToMenuId = [];
                    foreach ($analysis['suggested_menus'] as $idx => $sm) {
                        $title = trim($sm['title'] ?? '');
                        if ($title === '') continue;
                        $mbid = preg_replace('/[^a-z0-9\-]/', '', strtolower($sm['board_id'] ?? ''));

                        if (in_array($title, $existingMenuTitles, true)) {
                            echo "  SKIP  메뉴({$title}) - 같은 제목 존재\n";
                            continue;
                        }
                        if ($mbid !== '' && in_array($mbid, $existingMenuBoards, true)) {
                            echo "  SKIP  메뉴({$mbid}) - 이미 연결됨\n";
                            continue;
                        }
                        if ($mbid !== '') {
                            $boardExists = DB::fetch("SELECT id FROM {$prefix}boards WHERE board_id = ?", [$mbid]);
                            if (!$boardExists) {
                                echo "  SKIP  메뉴({$mbid}) - 대상 게시판 없음\n";
                                continue;
                            }
                        }
                        $parentId = 0;
                        $parentIdx = (int)($sm['parent_idx'] ?? -1);
                        if ($parentIdx >= 0 && isset($idxToMenuId[$parentIdx])) {
                            $parentId = $idxToMenuId[$parentIdx];
                        }

                        $newId = Menu::create([
                            'parent_id'  => $parentId,
                            'title'      => mb_substr($title, 0, 100),
                            'link'       => '',
                            'board_id'   => $mbid,
                            'target'     => '',
                            'sort_order' => (int)($sm['sort_order'] ?? 100),
                            'is_active'  => 1,
                        ]);
                        $idxToMenuId[$idx] = (int)$newId;
                        if ($mbid !== '') $existingMenuBoards[] = $mbid;
                        $existingMenuTitles[] = $title;
                        $indent = $parentId > 0 ? '   └ ' : '';
                        echo "  NEW   {$indent}{$title}" . ($mbid ? " → {$mbid}" : '') . "\n";
                        @ob_flush(); @flush();
                    }
                    echo "\n";
                }

                // 3) 가상 회원 생성
                echo "===== 가상 회원 생성 =====\n";
                $vmResult = _asbEnsureVirtualMembers((int)$_asbConfig['virtual_members']);
                $memberIds = $vmResult['ids'];
                echo "  신규 {$vmResult['created_cnt']}명, 기존 {$vmResult['existed_cnt']}명, 총 " . count($memberIds) . "명\n\n";
                if (empty($memberIds)) {
                    $memberIds = [_asbGetAdminMemberId()];
                }
                @ob_flush(); @flush();

                // 4) 게시글 + 댓글 생성
                echo "===== 게시글 및 댓글 생성 =====\n";
                $targetBoardIds  = array_filter(array_map('trim', (array)($_POST['asb_target_boards'] ?? [])));
                $allBoards       = DB::fetchAll("SELECT board_id, title, description, categories, board_type FROM {$prefix}boards WHERE is_active = 1");
                if (!empty($targetBoardIds)) {
                    $allBoards = array_values(array_filter($allBoards, fn($b) => in_array($b['board_id'], $targetBoardIds, true)));
                }
                $postsPerBoard   = (int)$_asbConfig['posts_per_board'];
                $commentsPerPost = (int)$_asbConfig['comments_per_post'];
                $replyRatio      = (int)$_asbConfig['reply_ratio'];

                foreach ($allBoards as $board) {
                    if ($board['board_type'] === 'gallery') {
                        echo "\n  SKIP  {$board['title']} (이미지 게시판)\n";
                        continue;
                    }
                    echo "\n  ---- {$board['title']} ({$board['board_id']}) ----\n";
                    @ob_flush(); @flush();

                    $catLine = $board['categories'] !== '' ? "카테고리: {$board['categories']}" : '';
                    $postPrompt = <<<PROMPT
너는 한국어 SEO 전문 콘텐츠 작가다. 실제 사람이 직접 경험하고 쓴 것처럼 자연스럽고 생생하면서, 검색엔진 최적화(SEO) 기준을 완벽히 충족하는 글을 써야 한다.

사이트 주제: {$topic}
톤: {$tone}
게시판: {$board['title']}
게시판 설명: {$board['description']}
{$catLine}

이 게시판에 올릴 게시글 {$postsPerBoard}개를 작성하라. 각 글마다 댓글 수는 1~{$commentsPerPost}개 사이에서 자연스럽게 랜덤으로 배분하라 (모든 글에 똑같은 댓글 수 금지, 인기글은 많고 평범한 글은 적게).

【SEO 제목 규칙】
- 제목에 핵심 키워드를 앞쪽에 배치 (검색 의도 반영)
- 숫자·연도·구체적 표현 활용 (예: "2024년 실제 후기", "3가지 방법", "비용 총정리")
- 질문형·정보형·후기형 제목을 골고루 섞기
- 20~35자 내외로 작성

【SEO 본문 작성 규칙 - 반드시 지킬 것】
- 본문은 반드시 3~4개의 단락(paragraph)으로 나눠서 작성
- 각 단락은 3~5문장으로 구성, 단락 사이는 빈 줄(\n\n)로 구분
- 1단락: 핵심 키워드 자연스럽게 포함한 도입부 (검색 의도에 바로 응답)
- 2단락: 본론 - 구체적 수치·날짜·장소·가격 등 팩트 포함, 핵심 키워드 1~2회 자연스럽게 반복
- 3단락: 실용 팁·주의사항·비교 정보 등 추가 가치 제공 (롱테일 키워드 자연 포함)
- 4단락: 마무리·요약·질문 유도 (독자 체류시간 늘리는 문장으로 마무리)
- HTML 태그 없이 순수 텍스트만 사용
- 키워드 억지 반복 금지 - 자연스러운 문맥 안에서만 사용
- 절대 짧게 쓰지 말 것. 검색에서 살아남을 만큼 충분히 풍성하게

【댓글 작성 규칙】
- 댓글 1개당 2~4문장으로 충분히 반응 (단순 "좋아요" 한 마디 금지)
- 댓글에도 관련 키워드가 자연스럽게 등장하도록 (검색 노출 가중치 상승 효과)
- 공감·질문·본인 경험 추가·반박·팁 보충 등 다양하게
- 댓글 중 일부는 다른 댓글에 대한 답글(reply)로 작성. reply_to_idx에 댓글 배열 내 대상 인덱스(0부터) 지정, 최상위 댓글이면 -1
- 답글은 전체 댓글의 약 {$replyRatio}% 정도
- 각 글마다 tags는 실제 사람들이 검색할 법한 롱테일 키워드로 (쉼표구분 3~5개)

반드시 아래 JSON 만 출력 (content 안에서 단락 구분은 \n\n 사용):
{
  "posts": [
    {
      "title": "...",
      "content": "1단락 내용...\n\n2단락 내용...\n\n3단락 내용...\n\n4단락 내용...",
      "tags": "tag1,tag2,tag3",
      "category": "",
      "comments": [
        {"text": "댓글 내용 2~4문장으로 충분히", "reply_to_idx": -1},
        {"text": "저도 비슷한 경험이 있는데 정말 공감돼요. 저는 이렇게 해결했습니다.", "reply_to_idx": 0}
      ]
    }
  ]
}
PROMPT;

                    $res = _asbCallGPT($postPrompt, $_asbConfig['api_key'], $_asbConfig['model'], true);
                    if (isset($res['error'])) {
                        echo "    ERROR  " . $res['error'] . "\n";
                        @ob_flush(); @flush();
                        continue;
                    }
                    $posts = $res['data']['posts'] ?? [];
                    if (!is_array($posts) || empty($posts)) {
                        echo "    ERROR  posts 배열 비어있음\n";
                        @ob_flush(); @flush();
                        continue;
                    }

                    $boardCats   = array_filter(array_map('trim', explode(',', $board['categories'] ?? '')));
                    $postCount   = 0;
                    $commentTotal = 0;

                    foreach ($posts as $p) {
                        $title = trim($p['title'] ?? '');
                        $body  = trim($p['content'] ?? '');
                        if ($title === '' || $body === '') continue;

                        $cat = trim($p['category'] ?? '');
                        if ($cat === '' && !empty($boardCats)) {
                            $cat = $boardCats[array_rand($boardCats)];
                        }
                        $tags = trim($p['tags'] ?? '');

                        $postAuthorMid = $memberIds[array_rand($memberIds)];
                        $createdAt = date('Y-m-d H:i:s', strtotime('-' . rand(0, 20) . ' days -' . rand(0, 23) . ' hours -' . rand(0, 59) . ' minutes'));

                        $postId = DB::insert("{$prefix}posts", [
                            'board_id'      => $board['board_id'],
                            'member_id'     => $postAuthorMid,
                            'category'      => mb_substr($cat, 0, 50),
                            'title'         => mb_substr($title, 0, 250),
                            'content'       => implode('', array_map(function($para) {
                                                $para = trim($para);
                                                return $para !== '' ? '<p>' . nl2br(htmlspecialchars($para)) . '</p>' : '';
                                            }, preg_split('/\n{2,}/', $body))),
                            'slug'          => '',
                            'hit'           => rand(15, 400),
                            'comment_count' => 0,
                            'is_notice'     => 0,
                            'is_secret'     => 0,
                            'is_hidden'     => 0,
                            'tags'          => mb_substr($tags, 0, 500),
                            'vote_up'       => rand(0, 25),
                            'vote_down'     => rand(0, 3),
                            'created_at'    => $createdAt,
                            'updated_at'    => $createdAt,
                        ]);

                        $commentIds = [];
                        $cCount = 0;
                        $prevTime = strtotime($createdAt);
                        foreach (($p['comments'] ?? []) as $idx => $c) {
                            $ctext = trim($c['text'] ?? '');
                            if ($ctext === '') continue;
                            $replyIdx = isset($c['reply_to_idx']) ? (int)$c['reply_to_idx'] : -1;
                            $parentId = 0;
                            if ($replyIdx >= 0 && isset($commentIds[$replyIdx])) {
                                $parentId = $commentIds[$replyIdx];
                            }
                            $prevTime += rand(300, 7200);
                            $cTime = date('Y-m-d H:i:s', $prevTime);

                            $authorMid = $memberIds[array_rand($memberIds)];
                            if ($parentId > 0 && count($memberIds) > 1) {
                                for ($try = 0; $try < 3; $try++) {
                                    if ($authorMid !== $postAuthorMid) break;
                                    $authorMid = $memberIds[array_rand($memberIds)];
                                }
                            }

                            $commentDbId = DB::insert("{$prefix}comments", [
                                'post_id'    => $postId,
                                'member_id'  => $authorMid,
                                'parent_id'  => $parentId,
                                'content'    => htmlspecialchars($ctext),
                                'created_at' => $cTime,
                            ]);
                            $commentIds[$idx] = (int)$commentDbId;
                            $cCount++;
                        }
                        if ($cCount > 0) {
                            DB::update("{$prefix}posts", ['comment_count' => $cCount], 'id = ?', [$postId]);
                        }
                        $commentTotal += $cCount;

                        echo "    [{$postId}] {$title}  (댓글 {$cCount})\n";
                        @ob_flush(); @flush();
                        $postCount++;
                    }
                    echo "    → 글 {$postCount}개 / 댓글 {$commentTotal}개\n";
                    @ob_flush(); @flush();
                }

                if (class_exists('Cache')) { Cache::flush(); }
                echo "\n===== 모든 작업 완료! 사이트가 북적이기 시작합니다 =====\n";
                echo '</pre></div></div>';
                return;
            }
        }
    }

    // ========== 현재 저장된 키워드 목록 ==========
    $savedKeywords = array_filter(array_map('trim', explode(',', $_asbConfig['keywords'] ?? '')));

    // ---------- 폼 UI ----------
    ?>
    <style>
    #asb-keyword-box {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #fff;
        min-height: 48px;
        cursor: text;
    }
    #asb-keyword-box:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px #e0e7ff; }
    .asb-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #ede9fe;
        color: #4c1d95;
        border-radius: 20px;
        padding: 4px 10px 4px 12px;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
    }
    .asb-tag-del {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #7c3aed;
        color: #fff;
        font-size: 10px;
        cursor: pointer;
        border: none;
        padding: 0;
        line-height: 1;
    }
    .asb-tag-del:hover { background: #5b21b6; }
    #asb-kw-input {
        border: none;
        outline: none;
        font-size: 14px;
        min-width: 140px;
        flex: 1;
        padding: 2px 4px;
    }
    .asb-kw-hint { font-size: 12px; color: #94a3b8; margin-top: 5px; }
    </style>

    <div style="margin-bottom:20px;padding:14px 16px;background:#eff6ff;border-left:4px solid #3b82f6;border-radius:6px">
        <strong style="color:#1e40af">자동 사이트 빌더 (AI)</strong>
        <p style="margin:6px 0 0;font-size:13px;color:#334155;line-height:1.6">
            1단계: 내 사이트 키워드 입력 &amp; 저장 → 2단계: AI 분석 → 3단계: 전체 자동 구성<br>
            기존 게시판/메뉴는 절대 건드리지 않고 <b>새 것만 추가</b>합니다. 가상 회원들이 댓글·대댓글까지 달아 사이트를 북적이게 만들어 줍니다.
        </p>
    </div>

    <form method="post" id="asb-form-save">
        <input type="hidden" name="asb_save" value="1">
        <input type="hidden" name="asb_keywords" id="asb-keywords-hidden" value="<?= htmlspecialchars($_asbConfig['keywords']) ?>">

        <div class="form-group">
            <label style="font-weight:600">OpenRouter API 키</label>
            <input type="text" name="asb_api_key" value="<?= htmlspecialchars($_asbConfig['api_key']) ?>"
                   placeholder="sk-or-v1-..." style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-family:monospace">
            <small><a href="https://openrouter.ai/keys" target="_blank">OpenAI에서 발급받기</a></small>
        </div>

        <!-- 키워드 입력 -->
        <div class="form-group" style="margin-top:18px">
            <label style="font-weight:600;font-size:15px">
                내 사이트 키워드를 입력하세요
            </label>
            <p style="font-size:13px;color:#64748b;margin:4px 0 10px;line-height:1.7">
                <b>🚀 한 번에 여러 개 추가:</b> <code>누리보드,누리보드코리아,SEO,CMS</code> 처럼
                <b>콤마(,)</b>로 구분해 입력창에 붙여넣으면 <b>낱개로 자동 분리</b>돼 한꺼번에 등록됩니다 (100개·200개도 OK).<br>
                <b>✏️ 낱개 추가:</b> 단어 하나만 입력 후 <b>Enter</b> 또는 <b>추가</b> 버튼.<br>
                예) <code>치과</code> <code>임플란트</code> <code>진료 후기</code> <code>교정 비용</code>
            </p>
            <div id="asb-keyword-box">
                <?php foreach ($savedKeywords as $kw): ?>
                    <span class="asb-tag" data-kw="<?= htmlspecialchars($kw) ?>">
                        <?= htmlspecialchars($kw) ?>
                        <button type="button" class="asb-tag-del" onclick="asbRemoveTag(this)">✕</button>
                    </span>
                <?php endforeach; ?>
                <input type="text" id="asb-kw-input" placeholder="키워드 입력 후 Enter">
            </div>
            <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
                <button type="button" onclick="asbAddTag()" style="padding:6px 16px;border:1px solid #6366f1;border-radius:6px;background:#6366f1;color:#fff;font-size:13px;cursor:pointer">
                    + 추가
                </button>
                <button type="button" onclick="asbClearAll()" style="padding:6px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#64748b;font-size:13px;cursor:pointer">
                    전체 삭제
                </button>
                <span class="asb-kw-hint" id="asb-kw-count">현재 <?= count($savedKeywords) ?>개 저장됨</span>
            </div>
        </div>

        <div class="form-row" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px">
            <div class="form-group" style="flex:1;min-width:200px">
                <label>모델</label>
                <select name="asb_model" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px">
                    <?= nb_openrouter_options($_asbConfig['model'] ?? '') ?>
                </select>
            </div>
            <div class="form-group" style="width:140px">
                <label>게시판당 글수</label>
                <input type="number" name="asb_posts_per_board" value="<?= (int)$_asbConfig['posts_per_board'] ?>"
                       min="1" max="30" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px">
            </div>
            <div class="form-group" style="width:140px">
                <label>글당 댓글수</label>
                <input type="number" name="asb_comments_per_post" value="<?= (int)$_asbConfig['comments_per_post'] ?>"
                       min="0" max="20" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px">
            </div>
        </div>

        <div class="form-row" style="display:flex;gap:12px;flex-wrap:wrap">
            <div class="form-group" style="width:160px">
                <label>가상 회원 수</label>
                <input type="number" name="asb_virtual_members" value="<?= (int)$_asbConfig['virtual_members'] ?>"
                       min="5" max="100" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px">
                <small>글/댓글 작성자 풀</small>
            </div>
            <div class="form-group" style="width:160px">
                <label>답글 비율 (%)</label>
                <input type="number" name="asb_reply_ratio" value="<?= (int)$_asbConfig['reply_ratio'] ?>"
                       min="0" max="100" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px">
                <small>댓글 중 대댓글 비율</small>
            </div>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="checkbox" name="asb_auto_create_boards" value="1" <?= $_asbConfig['auto_create_boards'] === '1' ? 'checked' : '' ?>>
                AI 제안 게시판 자동 생성 (5~10개)
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;margin-top:6px">
                <input type="checkbox" name="asb_auto_create_menus" value="1" <?= $_asbConfig['auto_create_menus'] === '1' ? 'checked' : '' ?>>
                AI 제안 다단 메뉴 자동 등록 (상위/하위 구조, 기존 메뉴 유지)
            </label>
        </div>

        <div style="margin-top:16px">
            <button type="submit" class="btn btn-primary">설정 저장</button>
        </div>
    </form>

    <div style="margin-top:28px;padding-top:20px;border-top:2px solid #e2e8f0">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">2단계: AI 사이트 분석</h3>
        <p style="font-size:13px;color:#64748b;margin-bottom:12px">
            입력한 키워드를 AI에게 보내 어떤 사이트인지 파악하고, 꼭 맞는 게시판·메뉴 구조를 제안받습니다.
        </p>
        <?php if (empty(trim($_asbConfig['keywords']))): ?>
            <div style="padding:12px 16px;background:#fef9c3;border:1px solid #fde047;border-radius:6px;font-size:13px;color:#713f12">
                먼저 키워드를 1개 이상 입력하고 저장하세요.
            </div>
        <?php else: ?>
            <div style="margin-bottom:10px;padding:10px 14px;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;font-size:13px;color:#14532d">
                저장된 키워드:
                <?php foreach ($savedKeywords as $kw): ?>
                    <span style="display:inline-block;background:#dcfce7;color:#166534;padding:2px 10px;border-radius:20px;margin:2px 3px;font-size:12px"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
            </div>
            <form method="post">
                <input type="hidden" name="asb_analyze" value="1">
                <button type="submit" class="btn" style="background:#6366f1;color:#fff;border-color:#6366f1;padding:10px 24px">
                    AI 분석 시작
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (is_array($analysis)): ?>
        <div class="card" style="margin-top:20px;border:1px solid #c7d2fe;background:#eef2ff">
            <div class="card-body" style="padding:18px">
                <h3 style="margin:0 0 10px;font-size:16px">AI 분석 결과</h3>
                <p style="margin:0 0 4px;font-size:14px">
                    <b>추론된 주제:</b>
                    <span style="background:#6366f1;color:#fff;padding:2px 12px;border-radius:20px;margin-left:6px;font-size:13px">
                        <?= htmlspecialchars($analysis['detected_topic'] ?? '-') ?>
                    </span>
                </p>
                <p style="margin:4px 0 14px;font-size:13px;color:#475569"><b>톤:</b> <?= htmlspecialchars($analysis['tone'] ?? '-') ?></p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div>
                        <b style="font-size:13px">제안 게시판 (<?= count($analysis['suggested_boards'] ?? []) ?>개)</b>
                        <ul style="margin:6px 0;padding-left:18px;font-size:13px;line-height:1.8">
                            <?php foreach (($analysis['suggested_boards'] ?? []) as $sb): ?>
                                <li>
                                    <b><?= htmlspecialchars($sb['title'] ?? '') ?></b>
                                    <span style="color:#64748b">(<?= htmlspecialchars($sb['board_id'] ?? '') ?>)</span><br>
                                    <small style="color:#475569"><?= htmlspecialchars($sb['description'] ?? '') ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div>
                        <b style="font-size:13px">제안 메뉴 (<?= count($analysis['suggested_menus'] ?? []) ?>개)</b>
                        <ul style="margin:6px 0;padding-left:18px;font-size:13px;line-height:1.8">
                            <?php foreach (($analysis['suggested_menus'] ?? []) as $sm):
                                $isChild = isset($sm['parent_idx']) && (int)$sm['parent_idx'] >= 0;
                            ?>
                                <li style="<?= $isChild ? 'margin-left:14px;color:#475569' : 'font-weight:600' ?>">
                                    <?= $isChild ? '└ ' : '' ?><?= htmlspecialchars($sm['title'] ?? '') ?>
                                    <?php if (!empty($sm['board_id'])): ?>
                                        → <code><?= htmlspecialchars($sm['board_id']) ?></code>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:20px;padding-top:16px;border-top:2px solid #e2e8f0">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">3단계: 전체 자동 구성</h3>
            <p style="font-size:13px;color:#64748b;margin-bottom:12px">
                게시판·다단 메뉴·가상 회원·게시글·댓글(대댓글 포함)까지 한 번에 자동으로 만들어 드립니다.<br>
                게시판 수 × 글수 × 댓글수에 따라 몇 분 걸릴 수 있습니다. 창을 닫지 마세요.
            </p>

            <?php
            // 새로 생성될 게시판 ID 목록
            $newBoardIds = array_map(fn($sb) => preg_replace('/[^a-z0-9\-]/', '', strtolower($sb['board_id'] ?? '')), $analysis['suggested_boards'] ?? []);
            $newBoardIds = array_filter($newBoardIds);
            // 기존 게시판 목록
            $existingBoards = DB::fetchAll("SELECT board_id, title FROM {$prefix}boards WHERE is_active = 1 ORDER BY sort_order");
            ?>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px">
                <p style="font-size:13px;font-weight:600;margin:0 0 10px;color:#1e293b">
                    글을 생성할 게시판을 선택하세요
                    <span style="font-weight:400;color:#64748b;margin-left:6px">— AI가 새로 만드는 게시판은 기본 체크, 기존 게시판은 기본 해제</span>
                </p>
                <div style="display:flex;gap:8px;margin-bottom:10px">
                    <button type="button" onclick="asbCheckAll(true)" style="font-size:12px;padding:4px 10px;border:1px solid #94a3b8;border-radius:4px;background:#fff;cursor:pointer;color:#475569">전체 선택</button>
                    <button type="button" onclick="asbCheckAll(false)" style="font-size:12px;padding:4px 10px;border:1px solid #94a3b8;border-radius:4px;background:#fff;cursor:pointer;color:#475569">전체 해제</button>
                </div>
                <?php if (!empty($existingBoards)): ?>
                <p style="font-size:12px;color:#94a3b8;margin:0 0 6px">기존 게시판</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px" id="asb-existing-boards">
                    <?php foreach ($existingBoards as $eb): ?>
                    <label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:5px 10px">
                        <input type="checkbox" name="asb_target_boards[]" value="<?= htmlspecialchars($eb['board_id']) ?>" class="asb-board-chk">
                        <?= htmlspecialchars($eb['title']) ?>
                        <span style="color:#94a3b8;font-size:11px">(<?= htmlspecialchars($eb['board_id']) ?>)</span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($newBoardIds)): ?>
                <p style="font-size:12px;color:#059669;margin:0 0 6px">✓ AI가 새로 생성할 게시판 (기본 선택됨)</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                    <?php foreach (($analysis['suggested_boards'] ?? []) as $sb):
                        $bid = preg_replace('/[^a-z0-9\-]/', '', strtolower($sb['board_id'] ?? ''));
                        if ($bid === '') continue;
                    ?>
                    <label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:5px 10px">
                        <input type="checkbox" name="asb_target_boards[]" value="<?= htmlspecialchars($bid) ?>" class="asb-board-chk" checked>
                        <?= htmlspecialchars($sb['title'] ?? $bid) ?>
                        <span style="color:#16a34a;font-size:11px">(<?= htmlspecialchars($bid) ?>)</span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <form method="post" onsubmit="return asbConfirmGenerate()">
                <input type="hidden" name="asb_generate" value="1">
                <input type="hidden" name="asb_analysis_json" value='<?= htmlspecialchars(json_encode($analysis, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>
                <!-- 체크박스 값을 폼으로 전달 -->
                <div id="asb-board-hidden-container"></div>
                <button type="submit" class="btn btn-lg" style="background:#059669;color:#fff;border-color:#059669;font-size:15px;padding:12px 32px">
                    전체 자동 구성 실행
                </button>
            </form>
            <script>
            function asbCheckAll(checked) {
                document.querySelectorAll('.asb-board-chk').forEach(function(c){ c.checked = checked; });
            }
            function asbConfirmGenerate() {
                var checked = document.querySelectorAll('.asb-board-chk:checked');
                if (checked.length === 0) { alert('글을 생성할 게시판을 하나 이상 선택하세요.'); return false; }
                // 선택된 값을 hidden input으로 폼에 주입
                var container = document.getElementById('asb-board-hidden-container');
                container.innerHTML = '';
                checked.forEach(function(c) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'asb_target_boards[]'; inp.value = c.value;
                    container.appendChild(inp);
                });
                return confirm('선택한 ' + checked.length + '개 게시판에 글을 생성합니다. 완료까지 창을 닫지 마세요. 계속할까요?');
            }
            </script>
        </div>
    <?php endif; ?>

    <script>
    (function () {
        var input  = document.getElementById('asb-kw-input');
        var box    = document.getElementById('asb-keyword-box');
        var hidden = document.getElementById('asb-keywords-hidden');
        var count  = document.getElementById('asb-kw-count');

        function syncHidden() {
            var tags = box.querySelectorAll('.asb-tag');
            var vals = [];
            tags.forEach(function (t) { vals.push(t.getAttribute('data-kw')); });
            hidden.value = vals.join(',');
            count.textContent = '현재 ' + vals.length + '개 저장됨';
        }

        function addTag(val) {
            val = val.trim();
            if (!val) return;
            // 콤마/줄바꿈/탭 구분된 경우 낱개로 자동 분리
            if (/[,\n\t]/.test(val)) {
                val.split(/[,\n\t]+/).forEach(function (v) { addTag(v); });
                return;
            }
            var existing = box.querySelectorAll('.asb-tag');
            for (var i = 0; i < existing.length; i++) {
                if (existing[i].getAttribute('data-kw') === val) return; // 중복 방지
            }
            var span = document.createElement('span');
            span.className = 'asb-tag';
            span.setAttribute('data-kw', val);
            span.innerHTML = val + ' <button type="button" class="asb-tag-del" onclick="asbRemoveTag(this)">✕</button>';
            box.insertBefore(span, input);
            syncHidden();
        }

        window.asbAddTag = function () {
            addTag(input.value);
            input.value = '';
            input.focus();
        };

        window.asbRemoveTag = function (btn) {
            btn.parentElement.remove();
            syncHidden();
        };

        window.asbClearAll = function () {
            box.querySelectorAll('.asb-tag').forEach(function (t) { t.remove(); });
            syncHidden();
        };

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                window.asbAddTag();
            }
            if (e.key === 'Backspace' && input.value === '') {
                var tags = box.querySelectorAll('.asb-tag');
                if (tags.length > 0) tags[tags.length - 1].remove();
                syncHidden();
            }
        });

        // 붙여넣기: 콤마나 줄바꿈 포함 텍스트면 즉시 낱개 분리
        input.addEventListener('paste', function (e) {
            var pasted = (e.clipboardData || window.clipboardData).getData('text');
            if (!pasted) return;
            if (/[,\n\t]/.test(pasted)) {
                e.preventDefault();
                var before = box.querySelectorAll('.asb-tag').length;
                addTag(pasted);
                input.value = '';
                var added = box.querySelectorAll('.asb-tag').length - before;
                if (added > 0) {
                    count.textContent = '✓ ' + added + '개 추가됨 · 현재 ' + box.querySelectorAll('.asb-tag').length + '개 저장됨';
                    count.style.color = '#16a34a';
                    setTimeout(function () {
                        count.style.color = '';
                        syncHidden();
                    }, 2000);
                }
            }
        });

        box.addEventListener('click', function () { input.focus(); });

        syncHidden();
    })();
    </script>
    <?php
});

<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * AI FAQ 자동 생성기 v1.1
 * 글 본문을 분석해 FAQ를 자동 생성하고 글 하단에 추가
 * FAQPage 스키마 마크업 자동 출력 → 구글 리치 리절트 노출
 */

// ===== 설정 헬퍼 =====
if (!function_exists('_faq_data_dir')) {
    function _faq_data_dir(): string {
        $base = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2);
        $dir  = $base . '/data/ai-faq-generator';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

if (!function_exists('_faq_load_config')) {
    function _faq_load_config(): array {
        // ★ static 캐싱 — 같은 요청 내 config.json 중복 읽기 방지
        static $cached = null;
        if ($cached !== null) return $cached;

        $default = [
            'enabled'        => false,
            'openai_api_key' => '',
            'openai_model'   => 'openai/gpt-4o-mini',
            'allowed_boards' => '',
            'faq_count'      => 4,
            'auto_generate'  => true,
        ];
        $file = _faq_data_dir() . '/config.json';
        if (!file_exists($file)) { $cached = $default; return $cached; }
        $data = json_decode(file_get_contents($file), true);
        $cached = is_array($data) ? array_merge($default, $data) : $default;
        return $cached;
    }
}

if (!function_exists('_faq_reload_config')) {
    // 저장 직후 캐시 무효화용
    function _faq_reload_config(): array {
        $default = [
            'enabled'        => false,
            'openai_api_key' => '',
            'openai_model'   => 'openai/gpt-4o-mini',
            'allowed_boards' => '',
            'faq_count'      => 4,
            'auto_generate'  => true,
        ];
        $file = _faq_data_dir() . '/config.json';
        if (!file_exists($file)) return $default;
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? array_merge($default, $data) : $default;
    }
}

if (!function_exists('_faq_save_config')) {
    function _faq_save_config(array $config): void {
        file_put_contents(
            _faq_data_dir() . '/config.json',
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}

// ===== DB 테이블 — 파일 플래그로 매 요청 DB 체크 방지 =====
$_faq_flag = _faq_data_dir() . '/.table_created';
if (!file_exists($_faq_flag)) {
    $prefix = DB::getPrefix();
    try {
        DB::fetch("SELECT 1 FROM {$prefix}faq_items LIMIT 1");
    } catch (Exception $e) {
        DB::query("CREATE TABLE IF NOT EXISTS {$prefix}faq_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED NOT NULL,
            faq_json TEXT NOT NULL,
            generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_post (post_id),
            INDEX idx_post (post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    @file_put_contents($_faq_flag, date('Y-m-d H:i:s'));
}
unset($_faq_flag);

// ===== 게시판 허용 체크 =====
if (!function_exists('_faq_board_allowed')) {
    function _faq_board_allowed(string $board_id, array $config = []): bool {
        if (empty($config)) $config = _faq_load_config();
        if (empty(trim($config['allowed_boards']))) return true;
        $allowed = array_filter(array_map('trim', explode(',', $config['allowed_boards'])));
        return in_array($board_id, $allowed, true);
    }
}

// ===== OpenAI 호출 =====
if (!function_exists('_faq_generate')) {
    function _faq_generate(int $post_id): bool {
        $config = _faq_load_config();
        if (empty($config['openai_api_key'])) return false;

        $prefix = DB::getPrefix();
        $post   = DB::fetch("SELECT title, content FROM {$prefix}posts WHERE id = ?", [$post_id]);
        if (!$post) return false;

        $content = mb_substr(strip_tags($post['content']), 0, 3000);
        $title   = $post['title'];
        $count   = (int)($config['faq_count'] ?? 4);

        $prompt = "다음 글을 읽고, 독자가 궁금해할 만한 질문 {$count}개와 답변을 생성하세요.
제목: {$title}
본문: {$content}

반드시 아래 JSON 형식으로만 응답하세요. 다른 텍스트는 절대 포함하지 마세요:
[{\"q\":\"질문1\",\"a\":\"답변1\"},{\"q\":\"질문2\",\"a\":\"답변2\"}]

규칙:
- 질문은 실제 독자가 검색할 법한 자연스러운 표현
- 답변은 2~3문장으로 명확하게
- 본문 내용 기반으로 작성";

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $config['openai_api_key'],
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                'model'       => $config['openai_model'] ?? 'openai/gpt-4o-mini',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'max_tokens'  => 1000,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        $text = trim($data['choices'][0]['message']['content'] ?? '');

        if (preg_match('/\[.*\]/s', $text, $m)) $text = $m[0];
        $faqs = json_decode($text, true);
        if (!is_array($faqs) || empty($faqs)) return false;

        $faqJson = json_encode($faqs, JSON_UNESCAPED_UNICODE);
        $exists  = DB::fetch("SELECT id FROM {$prefix}faq_items WHERE post_id = ?", [$post_id]);
        if ($exists) {
            DB::query("UPDATE {$prefix}faq_items SET faq_json = ?, generated_at = NOW() WHERE post_id = ?", [$faqJson, $post_id]);
        } else {
            DB::query("INSERT INTO {$prefix}faq_items (post_id, faq_json) VALUES (?, ?)", [$post_id, $faqJson]);
        }
        return true;
    }
}

// ===== 훅: 글 작성 시 자동 생성 =====
Plugin::addHook('post_created', function($post_id, $post_data = []) {
    $config = _faq_load_config();
    if (!$config['enabled'] || !$config['auto_generate']) return;

    $board_id = (string)($post_data['board_id'] ?? '');
    if ($board_id === '') {
        $prefix = DB::getPrefix();
        $row = DB::fetch("SELECT board_id FROM {$prefix}posts WHERE id = ?", [(int)$post_id]);
        $board_id = (string)($row['board_id'] ?? '');
    }
    if (!_faq_board_allowed($board_id, $config)) return;

    _faq_generate((int)$post_id);
});

// ===== 훅: 글 수정 시 자동 생성 (이미 FAQ 있으면 skip) =====
Plugin::addHook('post_updated', function($post_id, $post_data = []) {
    $config = _faq_load_config();
    if (!$config['enabled'] || !$config['auto_generate']) return;

    $prefix = DB::getPrefix();
    // ★ 이미 생성된 FAQ 있으면 API 재호출 금지
    if (DB::fetch("SELECT id FROM {$prefix}faq_items WHERE post_id = ?", [(int)$post_id])) return;

    $board_id = (string)($post_data['board_id'] ?? '');
    if ($board_id === '') {
        $row = DB::fetch("SELECT board_id FROM {$prefix}posts WHERE id = ?", [(int)$post_id]);
        $board_id = (string)($row['board_id'] ?? '');
    }
    if (!_faq_board_allowed($board_id, $config)) return;

    _faq_generate((int)$post_id);
});

// ===== 훅: 글 하단 FAQ 출력 =====
Plugin::addHook('after_post_content', function($post_data = []) {
    $config = _faq_load_config();
    if (!$config['enabled']) return;

    if (empty($post_data['id'])) {
        global $post;
        $post_data = $post ?? [];
    }
    if (empty($post_data['id'])) return;

    $prefix = DB::getPrefix();

    // 게시판 필터 — board_id 없으면 DB에서 직접 조회
    $board_id = (string)($post_data['board_id'] ?? '');
    if ($board_id === '') {
        $pr = DB::fetch("SELECT board_id FROM {$prefix}posts WHERE id = ?", [(int)$post_data['id']]);
        $board_id = (string)($pr['board_id'] ?? '');
    }
    if (!_faq_board_allowed($board_id, $config)) return;

    $row = DB::fetch("SELECT faq_json FROM {$prefix}faq_items WHERE post_id = ?", [(int)$post_data['id']]);
    if (!$row) return;

    $faqs = json_decode($row['faq_json'], true);
    if (!is_array($faqs) || empty($faqs)) return;

    // 관리자 재생성 버튼
    $regenBtn = '';
    if (class_exists('Auth') && Auth::check() && Auth::isAdmin()) {
        $regenBtn = '<button onclick="faqRegen(' . (int)$post_data['id'] . ', event)" class="faq-regen-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            FAQ 재생성
        </button>';
    }

    echo '<style>
.faq-section{margin-top:40px;border-top:2px solid #22c55e;padding-top:24px;font-family:-apple-system,"Segoe UI",sans-serif}
.faq-header{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.faq-header h3{font-size:17px;font-weight:700;color:#111827;flex:1;margin:0}
.faq-regen-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;color:#6b7280;cursor:pointer;transition:background .15s}
.faq-regen-btn:hover{background:#f3f4f6}
.faq-item{border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;overflow:hidden}
.faq-q{width:100%;display:flex;align-items:center;gap:10px;padding:14px 16px;background:#fff;border:none;cursor:pointer;text-align:left;font-size:14px;font-weight:600;color:#111827;transition:background .15s}
.faq-q:hover{background:#f9fafb}
.faq-q-badge{flex-shrink:0;width:22px;height:22px;background:#22c55e;color:#fff;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800}
.faq-q-text{flex:1}
.faq-arrow{flex-shrink:0;color:#9ca3af;transition:transform .2s}
.faq-q[aria-expanded="true"] .faq-arrow{transform:rotate(180deg)}
.faq-a{display:none;gap:10px;padding:14px 16px;background:#f9fafb;border-top:1px solid #e5e7eb}
.faq-a.open{display:flex}
.faq-a-badge{flex-shrink:0;width:22px;height:22px;background:#dcfce7;color:#16a34a;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800}
.faq-a p{flex:1;font-size:14px;color:#374151;line-height:1.7;margin:0}
</style>';

    echo '<div class="faq-section">';
    echo '<div class="faq-header">';
    echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    echo '<h3>자주 묻는 질문</h3>';
    echo $regenBtn;
    echo '</div>';

    foreach ($faqs as $faq) {
        if (empty($faq['q']) || empty($faq['a'])) continue;
        echo '<div class="faq-item">';
        echo '<button class="faq-q" onclick="faqToggle(this)" aria-expanded="false">';
        echo '<span class="faq-q-badge">Q</span>';
        echo '<span class="faq-q-text">' . htmlspecialchars($faq['q']) . '</span>';
        echo '<svg class="faq-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
        echo '</button>';
        echo '<div class="faq-a">';
        echo '<span class="faq-a-badge">A</span>';
        echo '<p>' . nl2br(htmlspecialchars($faq['a'])) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>';

    $schemaItems = array_map(fn($f) => [
        '@type'          => 'Question',
        'name'           => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], array_filter($faqs, fn($f) => !empty($f['q']) && !empty($f['a'])));

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_values($schemaItems),
    ];
    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

    echo '<script>
function faqToggle(btn){
    var a=btn.nextElementSibling;
    var open=btn.getAttribute("aria-expanded")==="true";
    btn.setAttribute("aria-expanded",String(!open));
    open?a.classList.remove("open"):a.classList.add("open");
}
function faqRegen(postId,e){
    if(!confirm("FAQ를 다시 생성할까요? 기존 내용이 교체됩니다."))return;
    var btn=e.target.closest("button");
    btn.disabled=true;btn.textContent="생성 중...";
    fetch("/admin/plugin/ai-faq-generator/regen",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({post_id:postId})
    }).then(r=>r.json()).then(d=>{
        if(d.success)location.reload();
        else{alert("실패: "+(d.message||"오류"));btn.disabled=false;btn.textContent="FAQ 재생성";}
    }).catch(()=>{btn.disabled=false;btn.textContent="FAQ 재생성";});
}
</script>';
});

// ===== API 라우트 =====
if (class_exists('Router')) {

    // 단일 재생성
    Router::post('/admin/plugin/ai-faq-generator/regen', function() {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || !Auth::isAdmin()) {
            echo json_encode(['success' => false, 'message' => '권한 없음']); exit;
        }
        $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $postId = (int)($input['post_id'] ?? 0);
        if (!$postId) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청']); exit;
        }
        $ok = _faq_generate($postId);
        echo json_encode(['success' => $ok, 'message' => $ok ? '재생성 완료' : 'API 키를 확인하세요']);
        exit;
    });

    // 일괄 생성용 글 목록
    Router::get('/admin/plugin/ai-faq-generator/posts', function() {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || !Auth::isAdmin()) {
            echo json_encode(['success' => false, 'message' => '권한 없음']); exit;
        }
        $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $config = _faq_load_config();
        $prefix = DB::getPrefix();
        $where  = '';
        $params = [];
        if (!empty(trim($config['allowed_boards']))) {
            $boards = array_filter(array_map('trim', explode(',', $config['allowed_boards'])));
            $in     = implode(',', array_fill(0, count($boards), '?'));
            $where  = "WHERE board_id IN ($in)";
            $params = array_values($boards);
        }
        $params[] = $limit;
        $rows = DB::fetchAll("SELECT id, title FROM {$prefix}posts {$where} ORDER BY id DESC LIMIT ?", $params);
        echo json_encode(['success' => true, 'posts' => $rows]);
        exit;
    });
}

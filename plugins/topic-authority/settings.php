<?php
/**
 * 토픽 어소리티 빌더 - 관리자 설정 페이지
 */
require_once __DIR__ . '/../_openrouter_models.php';

$_configFile = _ta_data_dir() . '/config.json';
$_cfg_raw    = file_exists($_configFile) ? json_decode(file_get_contents($_configFile), true) : [];
if (!is_array($_cfg_raw)) $_cfg_raw = [];

// 플러그인 폴더 기본값 병합
$_plugin_cfg = file_exists(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
if (!is_array($_plugin_cfg)) $_plugin_cfg = [];

$cfg = array_merge([
    'openai_api_key'     => '',
    'openai_model'       => 'openai/gpt-4o-mini',
    'unsplash_api_key'   => '',
    'image_enabled'      => '1',
    'images_per_post'    => '2',
    'interval_minutes'   => 30,
    'promo_links'        => [],
    'last_run'           => '',
    'total_generated'    => 0,
    'draft_keyword'      => '',
    'draft_cluster_count'=> 10,
    'draft_board_id'     => '',
], $_plugin_cfg, $_cfg_raw);

$msg = '';

// ===== GET 방식 임시저장 (어드민 POST 간섭 우회) =====
if (isset($_GET['ta_save']) && $_GET['ta_save'] === '1') {
    $cfg['draft_keyword']       = trim($_GET['kw'] ?? '');
    $cfg['draft_cluster_count'] = max(5, min(20, (int)($_GET['cc'] ?? 10)));
    $cfg['draft_board_id']      = trim($_GET['bid'] ?? '');
    file_put_contents($_configFile, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = 'success:✅ 저장 완료! 게시판 ID=' . $cfg['draft_board_id'] . ', 키워드=' . mb_substr($cfg['draft_keyword'], 0, 20) . '...';
}

// ===== 저장 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 프로젝트 임시저장
    if (isset($_POST['save_draft'])) {
        $cfg['draft_keyword']       = trim($_POST['target_keyword'] ?? '');
        $cfg['draft_cluster_count'] = max(5, min(20, (int)($_POST['ta_cluster_count'] ?? 10)));
        $cfg['draft_board_id']      = trim($_POST['ta_board_id'] ?? '');
        file_put_contents($_configFile, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $msg = 'success:저장 완료!';
    }

    // API 키 저장
    if (isset($_POST['save_api'])) {
        $cfg['openai_api_key']   = trim($_POST['openai_api_key'] ?? '');
        $cfg['openai_model']     = trim($_POST['openai_model'] ?? 'openai/gpt-4o-mini');
        $cfg['unsplash_api_key'] = trim($_POST['unsplash_api_key'] ?? '');
        $cfg['image_enabled']    = isset($_POST['image_enabled']) ? '1' : '0';
        $imgVal = trim($_POST['images_per_post'] ?? '2');
        $cfg['images_per_post'] = preg_match('/^\d+-\d+$/', $imgVal) ? $imgVal : (string)max(1, min(5, (int)$imgVal));
        $cfg['interval_minutes'] = max(5, (int)($_POST['interval_minutes'] ?? 30));
        file_put_contents($_configFile, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $msg = 'success:API 설정이 저장되었습니다.';
    }

    // 홍보 링크 저장
    if (isset($_POST['save_links'])) {
        $anchors = $_POST['link_anchor'] ?? [];
        $urls    = $_POST['link_url'] ?? [];
        $links   = [];
        foreach ($anchors as $i => $anchor) {
            $anchor = trim($anchor);
            $url    = trim($urls[$i] ?? '');
            if ($anchor && $url) $links[] = ['anchor' => $anchor, 'url' => $url];
        }
        $cfg['promo_links'] = $links;
        file_put_contents($_configFile, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $msg = 'success:홍보 링크가 저장되었습니다.';
    }

    // 새 프로젝트 시작 (즉시 큐 등록, AI 설계는 백그라운드에서)
    if (isset($_POST['start_project'])) {
        $keyword      = trim($_POST['target_keyword'] ?? '');
        $clusterCount = max(5, min(20, (int)($_POST['ta_cluster_count'] ?? 10)));
        $boardId      = trim($_POST['ta_board_id'] ?? '');

        if (empty($keyword)) {
            $msg = 'error:타겟 키워드를 입력하세요.';
        } elseif (empty($boardId)) {
            $msg = 'error:게시판을 선택하세요.';
        } elseif (empty($cfg['openai_api_key'])) {
            $msg = 'error:OpenRouter API 키를 먼저 설정하세요.';
        } else {
            $project = [
                'id'            => uniqid('ta_'),
                'keyword'       => $keyword,
                'cluster_count' => $clusterCount,
                'board_id'      => $boardId,
                'status'        => 'designing',
                'created_at'    => date('Y-m-d H:i:s'),
                'pillar_post_id'=> 0,
                'pillar_title'  => '',
                'items'         => [],
            ];
            $queueData = _ta_read_queue();
            $queueData['projects'][] = $project;
            _ta_write_queue($queueData);
            // 임시저장 초기화
            $cfg['draft_keyword'] = '';
            $cfg['draft_cluster_count'] = 10;
            $cfg['draft_board_id'] = '';
            file_put_contents($_configFile, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $msg = "success:프로젝트 등록 완료! 다음 접속 시 AI가 콘텐츠 구조를 설계하고 글 발행을 시작합니다.";
        }
    }

    // 즉시 실행
    if (isset($_POST['force_run'])) {
        $result = _ta_force_run();
        $msg = ($result['success'] ? 'success:' : 'error:') . ($result['message'] ?? $result['error']);
    }

    // 로그 지우기
    if (isset($_POST['clear_log'])) {
        @unlink(_ta_data_dir() . '/debug.log');
        $msg = 'success:로그를 지웠습니다.';
    }

    // 프로젝트 삭제
    if (isset($_POST['delete_project'])) {
        $delId     = trim($_POST['delete_project']);
        $queueData = _ta_read_queue();
        $queueData['projects'] = array_values(array_filter($queueData['projects'], fn($p) => ($p['id'] ?? '') !== $delId));
        _ta_write_queue($queueData);
        $msg = 'success:프로젝트가 삭제되었습니다.';
    }
}

$queueData = _ta_read_queue();
$projects  = $queueData['projects'] ?? [];

// 게시판 목록
$prefix = DB::getPrefix();
$boards = DB::fetchAll("SELECT board_id, title FROM {$prefix}boards WHERE is_active = 1 ORDER BY board_id") ?: [];

// 메시지 처리
$msgType = '';
$msgText = '';
if ($msg) {
    [$msgType, $msgText] = explode(':', $msg, 2);
}
?>

<style>
.ta-wrap { max-width: 900px; font-family: -apple-system, sans-serif; }
.ta-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
.ta-card h2 { font-size: 17px; font-weight: 700; color: #1e293b; margin: 0 0 20px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; gap: 8px; }
.ta-form-row { margin-bottom: 16px; }
.ta-form-row label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.ta-input { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
.ta-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.ta-btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.ta-btn-primary { background: #3b82f6; color: #fff; }
.ta-btn-primary:hover { background: #2563eb; }
.ta-btn-success { background: #22c55e; color: #fff; }
.ta-btn-success:hover { background: #16a34a; }
.ta-btn-danger  { background: #ef4444; color: #fff; font-size: 12px; padding: 6px 12px; }
.ta-btn-test    { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; margin-left: 8px; padding: 10px 16px; }
.ta-btn-test:hover { background: #e2e8f0; }
.ta-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
.ta-msg.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.ta-msg.error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.ta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.ta-stat { background: #f8fafc; border-radius: 8px; padding: 16px; text-align: center; }
.ta-stat .num { font-size: 28px; font-weight: 800; color: #3b82f6; }
.ta-stat .label { font-size: 12px; color: #64748b; margin-top: 4px; }
.ta-project { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
.ta-project-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.ta-keyword { font-size: 16px; font-weight: 700; color: #1e293b; }
.ta-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.ta-badge.active    { background: #dbeafe; color: #1d4ed8; }
.ta-badge.designing { background: #fef9c3; color: #854d0e; }
.ta-badge.completed { background: #dcfce7; color: #15803d; }
.ta-progress-bar { background: #f1f5f9; border-radius: 6px; height: 8px; margin: 8px 0; }
.ta-progress-fill { background: linear-gradient(90deg, #3b82f6, #22c55e); border-radius: 6px; height: 100%; transition: width .3s; }
.ta-items { margin-top: 10px; max-height: 200px; overflow-y: auto; }
.ta-item { padding: 6px 10px; margin: 4px 0; border-radius: 6px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.ta-item.done    { background: #f0fdf4; }
.ta-item.pending { background: #fefce8; }
.ta-item.failed  { background: #fef2f2; }
.ta-item .dot    { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ta-item.done .dot    { background: #22c55e; }
.ta-item.pending .dot { background: #f59e0b; }
.ta-item.failed .dot  { background: #ef4444; }
.ta-item .type-badge  { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; }
.ta-item .type-badge.pillar  { background: #dbeafe; color: #1d4ed8; }
.ta-item .type-badge.cluster { background: #e0e7ff; color: #4338ca; }
.ta-link-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
.ta-link-row .ta-input { flex: 1; }
.ta-remove-link { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px; padding: 0 4px; }
select.ta-input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }
</style>

<div class="ta-wrap">

<?php if ($msgText): ?>
<div class="ta-msg <?= $msgType ?>"><?= htmlspecialchars($msgText) ?></div>
<?php endif; ?>


<!-- 현황 통계 -->
<div class="ta-card">
    <h2>📊 토픽 어소리티 현황</h2>
    <?php
    $totalProjects   = count($projects);
    $activeProjects  = count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'active'));
    $doneCount       = (int)($cfg['total_generated'] ?? 0);
    $pendingCount    = 0;
    foreach ($projects as $p) {
        foreach ($p['items'] ?? [] as $it) {
            if (($it['status'] ?? '') === 'pending') $pendingCount++;
        }
    }
    ?>
    <div class="ta-grid">
        <div class="ta-stat"><div class="num"><?= $totalProjects ?></div><div class="label">전체 프로젝트</div></div>
        <div class="ta-stat"><div class="num"><?= $activeProjects ?></div><div class="label">진행 중</div></div>
        <div class="ta-stat"><div class="num"><?= $doneCount ?></div><div class="label">발행된 글</div></div>
        <div class="ta-stat"><div class="num"><?= $pendingCount ?></div><div class="label">대기 중인 글</div></div>
    </div>
    <?php if ($cfg['last_run']): ?>
    <p style="font-size:12px;color:#94a3b8;margin:16px 0 0;text-align:center">마지막 발행: <?= htmlspecialchars($cfg['last_run']) ?></p>
    <?php endif; ?>
</div>

<!-- API 설정 -->
<div class="ta-card">
    <h2>🔑 API 키 설정</h2>
    <form method="post">
        <input type="hidden" name="save_api" value="1">

        <div class="ta-form-row">
            <label>OpenRouter API 키 <span style="color:#ef4444">*필수</span></label>
            <div style="display:flex;align-items:center">
                <input type="password" name="openai_api_key" class="ta-input" value="<?= htmlspecialchars($cfg['openai_api_key']) ?>" placeholder="sk-or-v1-..." id="openai_key_input">
                <button type="button" class="ta-btn ta-btn-test" onclick="testOpenAI()">테스트</button>
            </div>
            <div id="openai_test_result" style="font-size:13px;margin-top:6px"></div>
        </div>

        <div class="ta-form-row">
            <label>OpenAI 모델</label>
            <select name="openai_model" class="ta-input" style="width:340px">
                <?= nb_openrouter_options($cfg['openai_model'] ?? '') ?>
            </select>
        </div>

        <div class="ta-form-row">
            <label>Unsplash API 키 <span style="color:#64748b;font-weight:400">(이미지 자동 삽입, 선택)</span></label>
            <div style="display:flex;align-items:center">
                <input type="password" name="unsplash_api_key" class="ta-input" value="<?= htmlspecialchars($cfg['unsplash_api_key']) ?>" placeholder="unsplash access key..." id="unsplash_key_input">
                <button type="button" class="ta-btn ta-btn-test" onclick="testUnsplash()">테스트</button>
            </div>
            <div id="unsplash_test_result" style="font-size:13px;margin-top:6px"></div>
        </div>

        <div class="ta-form-row">
            <label>
                <input type="checkbox" name="image_enabled" <?= $cfg['image_enabled'] === '1' ? 'checked' : '' ?>>
                &nbsp;글에 이미지 자동 삽입
            </label>
        </div>

        <div class="ta-form-row" style="display:flex;gap:24px;align-items:flex-start">
            <div style="flex:1">
                <label>글당 이미지 수</label>
                <select name="images_per_post" class="ta-input">
                    <?php
                    $imgOptions = ['1'=>'1장 고정', '1-2'=>'1~2장 랜덤', '1-3'=>'1~3장 랜덤', '2-4'=>'2~4장 랜덤', '1-5'=>'1~5장 랜덤'];
                    foreach ($imgOptions as $v => $lbl): ?>
                    <option value="<?= $v ?>" <?= $cfg['images_per_post'] === $v ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1">
                <label>글 발행 간격 (분)</label>
                <select name="interval_minutes" class="ta-input">
                    <?php foreach ([10=>10,20=>20,30=>30,60=>60,120=>120,180=>180] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= (int)$cfg['interval_minutes'] === $v ? 'selected' : '' ?>><?= $l ?>분</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="ta-btn ta-btn-primary">💾 API 설정 저장</button>
    </form>
</div>

<!-- 홍보 링크 -->
<div class="ta-card">
    <h2>🔗 내 사이트 홍보 링크 <span style="font-size:13px;font-weight:400;color:#64748b">(글 본문에 자동 삽입)</span></h2>
    <div style="background:#fefce8;border:1px solid #fde047;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#854d0e">
        ⚠️ <strong>앵커텍스트는 매번 랜덤으로 선택</strong>됩니다 — 구글은 동일 앵커 반복을 싫어하니 콤마로 여러 개 입력하세요.<br>
        삽입 수량도 적당량을 감자합니다 🥔 (글마다 자동으로 랜덤 조절)
    </div>
    <form method="post">
        <input type="hidden" name="save_links" value="1">
        <div style="display:flex;gap:8px;font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;padding:0 4px">
            <span style="flex:1.5">앵커 텍스트 (여러 개면 콤마로 구분)</span>
            <span style="flex:1">URL</span>
            <span style="width:28px"></span>
        </div>
        <div id="link-rows">
            <?php
            $promoLinks = $cfg['promo_links'];
            if (empty($promoLinks)) $promoLinks = [['anchor'=>'','url'=>'']];
            foreach ($promoLinks as $i => $link): ?>
            <div class="ta-link-row" id="link-row-<?= $i ?>">
                <input type="text"  name="link_anchor[]" class="ta-input" style="flex:1.5" value="<?= htmlspecialchars($link['anchor']) ?>" placeholder="구글상위노출,SEO전문가,워프스타 (콤마로 구분)">
                <input type="text"  name="link_url[]"    class="ta-input" style="flex:1"   value="<?= htmlspecialchars($link['url']) ?>"    placeholder="https://btg1.net">
                <button type="button" class="ta-remove-link" onclick="removeLink(this)">×</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="addLink()" style="background:none;border:1px dashed #cbd5e1;color:#64748b;padding:8px 16px;border-radius:8px;cursor:pointer;margin-bottom:16px;font-size:13px">+ 링크 추가</button>
        <br>
        <button type="submit" class="ta-btn ta-btn-primary">💾 링크 저장</button>
    </form>
</div>

<!-- 새 프로젝트 시작 -->
<div class="ta-card" style="border:2px solid #3b82f6;">
    <h2>🚀 새 토픽 어소리티 프로젝트 시작</h2>
    <form method="post" id="ta_project_form" autocomplete="off">
    <input type="hidden" name="save_draft"       value="1">
    <!-- 숨은 필드: JS가 select 값을 여기에 복사해서 제출 -->
    <input type="hidden" name="target_keyword"   id="h_kw"  value="">
    <input type="hidden" name="ta_cluster_count" id="h_cc"  value="">
    <input type="hidden" name="ta_board_id"      id="h_bid" value="">

    <?php
    $savedKw  = (string)($cfg['draft_keyword'] ?? '');
    $savedCc  = (int)($cfg['draft_cluster_count'] ?? 10);
    $savedBid = (string)($cfg['draft_board_id'] ?? '');
    $savedBoardTitle = '';
    foreach ($boards as $b) {
        if ((string)$b['board_id'] === $savedBid && $savedBid !== '') { $savedBoardTitle = $b['title']; break; }
    }
    ?>

    <?php if ($savedBid || $savedKw): ?>
    <div id="ta_saved_info" style="background:#f0fdf4;border:1px solid #86efac;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;color:#166534">
        ✅ <strong>저장된 설정:</strong>
        키워드 <b><?= htmlspecialchars($savedKw ?: '(없음)') ?></b> /
        세부글 <b><?= $savedCc ?>개</b> /
        게시판 <b><?= htmlspecialchars($savedBoardTitle ?: '(미선택)') ?></b>
    </div>
    <?php else: ?>
    <div id="ta_saved_info" style="display:none;background:#f0fdf4;border:1px solid #86efac;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;color:#166534"></div>
    <?php endif; ?>

    <div class="ta-form-row">
        <label>🎯 타겟 키워드 <span style="color:#ef4444">*필수</span></label>
        <input type="text" id="ta_kw" class="ta-input"
               value="<?= htmlspecialchars($savedKw) ?>"
               placeholder="예: 구글 상위노출, 홈페이지 제작 비용, SEO 최적화 방법..."
               style="font-size:16px;padding:14px 16px;">
        <div style="font-size:12px;color:#94a3b8;margin-top:6px">이 키워드를 중심으로 핵심글 1개 + 세부글 N개가 자동 생성됩니다.</div>
    </div>

    <div style="display:flex;gap:16px">
        <div class="ta-form-row" style="flex:1">
            <label>세부글 수 <span style="color:#94a3b8;font-weight:400">(핵심글 1개 + 세부글 N개)</span></label>
            <select id="ta_cc" class="ta-input">
                <?php foreach ([5,8,10,12,15,20] as $n): ?>
                <option value="<?= $n ?>" <?= $n === $savedCc ? 'selected' : '' ?>><?= $n ?>개</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ta-form-row" style="flex:1">
            <label>발행 게시판</label>
            <select id="ta_bid" class="ta-input" autocomplete="off">
                <option value="">-- 게시판 선택 --</option>
                <?php foreach ($boards as $b): ?>
                <option value="<?= htmlspecialchars($b['board_id']) ?>" <?= (string)$b['board_id'] === $savedBid ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div style="background:#eff6ff;padding:16px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#1e40af">
        <strong>💡 작동 방식</strong><br>
        1. AI가 키워드 분석 → 핵심글 + 세부글 구조 자동 설계<br>
        2. 방문자가 사이트 접속할 때마다 설정한 간격으로 글 1개씩 자동 발행<br>
        3. 핵심글-세부글 내부링크 자동 연결 → 토픽 어소리티 구축 완료
    </div>
    <div style="background:#f0fdf4;border:1px solid #86efac;padding:14px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#166534">
        🟢 <strong>이 플러그인은 관리자 페이지를 닫아도 계속 동작합니다!</strong><br>
        누구든 사이트에 방문할 때마다 자동으로 글을 발행합니다. 별도 크론(cron) 설정 불필요.
    </div>

    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <button type="submit" onclick="return taPrepareSubmit('save')" class="ta-btn" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-size:14px;padding:12px 22px">
            💾 저장
        </button>
        <button type="submit" name="start_project" value="1" onclick="return taPrepareSubmit('start')" class="ta-btn ta-btn-success" style="font-size:16px;padding:14px 28px">
            ✨ 토픽 어소리티 구축 시작!
        </button>
    </div>
    </form>
</div>

<script>
// submit 직전에 select/input의 현재 값을 hidden input에 복사
// (select에 name 붙이면 브라우저 autofill/어드민 간섭 가능성 있어서 hidden input으로 우회)
function taPrepareSubmit(mode) {
    var kw  = document.getElementById('ta_kw');
    var cc  = document.getElementById('ta_cc');
    var bid = document.getElementById('ta_bid');
    var hKw = document.getElementById('h_kw');
    var hCc = document.getElementById('h_cc');
    var hBid = document.getElementById('h_bid');

    if (mode === 'start') {
        if (!kw || !kw.value.trim()) { alert('타겟 키워드를 입력하세요.'); kw && kw.focus(); return false; }
        if (!bid || !bid.value)      { alert('게시판을 선택하세요.');       bid && bid.focus(); return false; }
    }

    hKw.value  = kw ? kw.value : '';
    hCc.value  = cc ? cc.value : '10';
    hBid.value = bid ? bid.value : '';

    // 콘솔에 디버그 출력
    console.log('[TA Submit]', {mode: mode, kw: hKw.value, cc: hCc.value, bid: hBid.value});
    return true;
}
</script>

<!-- 즉시 실행 -->
<?php if (!empty($projects)): ?>
<div style="text-align:center;margin-bottom:24px">
    <form method="post" style="display:inline">
        <input type="hidden" name="force_run" value="1">
        <button type="submit" class="ta-btn ta-btn-primary" style="padding:12px 28px">
            ⚡ 지금 즉시 글 1개 발행
        </button>
    </form>
    <div style="font-size:12px;color:#94a3b8;margin-top:8px">간격 설정 무시하고 즉시 다음 글을 발행합니다.</div>
</div>
<?php endif; ?>

<!-- 디버그 로그 -->
<?php
$_logFile = _ta_data_dir() . '/debug.log';
if (file_exists($_logFile)):
    $_logContent = @file_get_contents($_logFile);
    $_logLines = array_slice(array_filter(explode("\n", $_logContent)), -30);
?>
<div class="ta-card">
    <h2>🐛 디버그 로그 <span style="font-size:12px;font-weight:400;color:#64748b">(최근 30줄)</span>
        <form method="post" style="display:inline;margin-left:auto">
            <input type="hidden" name="clear_log" value="1">
            <button type="submit" class="ta-btn ta-btn-danger" style="font-size:11px">로그 지우기</button>
        </form>
    </h2>
    <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:8px;font-size:12px;max-height:300px;overflow:auto;margin:0"><?= htmlspecialchars(implode("\n", $_logLines)) ?></pre>
</div>
<?php endif; ?>

<!-- 큐 현황 -->
<?php if (!empty($projects)): ?>
<div class="ta-card">
    <h2>📋 프로젝트 현황</h2>
    <?php foreach (array_reverse($projects) as $project):
        $total    = count($project['items'] ?? []);
        $done     = count(array_filter($project['items'] ?? [], fn($it) => $it['status'] === 'done'));
        $pct      = $total > 0 ? round($done / $total * 100) : 0;
        $status   = $project['status'] ?? 'active';
        $isDesigning = ($status === 'designing');
    ?>
    <div class="ta-project">
        <div class="ta-project-header">
            <div>
                <div class="ta-keyword">🎯 <?= htmlspecialchars($project['keyword']) ?></div>
                <div style="font-size:12px;color:#94a3b8;margin-top:4px"><?= htmlspecialchars($project['created_at'] ?? '') ?> | 게시판: <?= htmlspecialchars($project['board_id'] ?? '') ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <span class="ta-badge <?= $status ?>"><?= $status === 'active' ? '진행중' : ($status === 'designing' ? '설계중' : '완료') ?></span>
                <form method="post" style="display:inline" onsubmit="return confirm('이 프로젝트를 삭제하시겠습니까?')">
                    <input type="hidden" name="delete_project" value="<?= htmlspecialchars($project['id'] ?? '') ?>">
                    <button type="submit" class="ta-btn ta-btn-danger">삭제</button>
                </form>
            </div>
        </div>
        <?php if ($isDesigning): ?>
        <div style="font-size:13px;color:#854d0e;margin-bottom:8px">🤖 AI가 콘텐츠 구조 설계 중... 다음 방문 시 자동으로 시작됩니다.</div>
        <div class="ta-progress-bar"><div class="ta-progress-fill" style="width:5%;background:linear-gradient(90deg,#fbbf24,#f59e0b)"></div></div>
        <?php else: ?>
        <div style="font-size:13px;color:#475569;margin-bottom:8px"><?= $done ?>/<?= $total ?>개 발행 (<?= $pct ?>%)</div>
        <div class="ta-progress-bar">
            <div class="ta-progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
        <?php endif; ?>
        <div class="ta-items">
            <?php foreach ($project['items'] ?? [] as $item):
                $st = $item['status'] ?? 'pending';
            ?>
            <div class="ta-item <?= $st ?>">
                <div class="dot"></div>
                <span class="type-badge <?= $item['type'] ?>"><?= $item['type'] === 'pillar' ? '핵심글' : '세부글' ?></span>
                <span style="flex:1"><?= htmlspecialchars($item['title']) ?></span>
                <?php if ($st === 'done' && !empty($item['post_id'])): ?>
                <a href="/board/<?= htmlspecialchars($project['board_id'] ?? '') ?>/<?= (int)$item['post_id'] ?>" target="_blank" style="font-size:11px;color:#3b82f6">보기</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div>

<script>
// 테스트 성공 시 API 설정 폼을 자동 submit해서 서버에 저장 (페이지 리로드)
function autoSaveApiForm() {
    var form = document.querySelector('input[name="save_api"]').form;
    if (form) form.submit();
}

function testOpenAI() {
    var key = document.getElementById('openai_key_input').value;
    var result = document.getElementById('openai_test_result');
    if (!key) { result.innerHTML = '<span style="color:#ef4444">API 키를 입력하세요.</span>'; return; }
    result.innerHTML = '<span style="color:#94a3b8">테스트 중...</span>';
    fetch('https://openrouter.ai/api/v1/models', {
        headers: { 'Authorization': 'Bearer ' + key }
    }).then(function(r) {
        if (r.ok) {
            result.innerHTML = '<span style="color:#22c55e">✅ 연결 성공! 💾 자동 저장 중...</span>';
            setTimeout(autoSaveApiForm, 600);
        } else {
            result.innerHTML = '<span style="color:#ef4444">❌ API 키가 올바르지 않습니다. (HTTP ' + r.status + ')</span>';
        }
    }).catch(function() {
        result.innerHTML = '<span style="color:#ef4444">❌ 연결 실패 (네트워크 오류)</span>';
    });
}

function testUnsplash() {
    var key = document.getElementById('unsplash_key_input').value;
    var result = document.getElementById('unsplash_test_result');
    if (!key) { result.innerHTML = '<span style="color:#ef4444">API 키를 입력하세요.</span>'; return; }
    result.innerHTML = '<span style="color:#94a3b8">테스트 중...</span>';
    fetch('https://api.unsplash.com/photos/random?client_id=' + encodeURIComponent(key), {
        headers: { 'Accept-Version': 'v1' }
    }).then(function(r) {
        if (r.ok) {
            result.innerHTML = '<span style="color:#22c55e">✅ 연결 성공! 💾 자동 저장 중...</span>';
            setTimeout(autoSaveApiForm, 600);
        } else {
            result.innerHTML = '<span style="color:#ef4444">❌ API 키가 올바르지 않습니다. (HTTP ' + r.status + ')</span>';
        }
    }).catch(function() {
        result.innerHTML = '<span style="color:#ef4444">❌ 연결 실패 (네트워크 오류)</span>';
    });
}

var linkCount = <?= count($cfg['promo_links'] ?: [['','']]) ?>;
function addLink() {
    var row = document.createElement('div');
    row.className = 'ta-link-row';
    row.id = 'link-row-' + linkCount;
    row.innerHTML = '<input type="text" name="link_anchor[]" class="ta-input" style="flex:1.5" placeholder="구글상위노출,SEO전문가,워프스타 (콤마로 구분)">'
                  + '<input type="text" name="link_url[]"    class="ta-input" style="flex:1"   placeholder="https://btg1.net">'
                  + '<button type="button" class="ta-remove-link" onclick="removeLink(this)">×</button>';
    document.getElementById('link-rows').appendChild(row);
    linkCount++;
}
function removeLink(btn) {
    btn.closest('.ta-link-row').remove();
}
</script>

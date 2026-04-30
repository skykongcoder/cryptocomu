<?php
/**
 * SEO 분석기 - 설정 페이지
 */
require_once __DIR__ . '/../_openrouter_models.php';

// ===== JSON 응답 헬퍼 (출력 버퍼 비우고 순수 JSON만 전송) =====
function _seo_json_exit(array $data): void {
    while (ob_get_level()) ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 서버 연결 테스트 (AJAX) =====
if (isset($_GET['seo_ping'])) {
    $cfg    = _seo_load_config();
    $apiKey = trim($_GET['key'] ?? $cfg['openai_api_key']);
    $ch = curl_init('https://openrouter.ai/api/v1/models');
    if (function_exists('nb_ca_bundle') && ($_nb_ca = nb_ca_bundle())) curl_setopt($ch, CURLOPT_CAINFO, $_nb_ca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $err  = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errMsg = curl_error($ch);
    curl_close($ch);
    _seo_json_exit([
        'ok'      => ($code === 200 && !$err),
        'code'    => $code,
        'curl_err'=> $err,
        'msg'     => $errMsg,
    ]);
}

$_configFile = _seo_data_dir() . '/config.json';
$cfg = _seo_load_config();

// ===== 설정 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $cfg['openai_api_key'] = trim($_POST['openai_api_key'] ?? '');
    $cfg['openai_model']   = trim($_POST['openai_model']   ?? 'openai/gpt-4o-mini');
    $cfg['site_domain']    = rtrim(trim($_POST['site_domain'] ?? ''), '/');
    _seo_save_config($cfg);
    _seo_json_exit(['success' => true, 'msg' => '설정이 저장되었습니다.']);
}

// ===== 분석 실행 (AJAX JSON 응답) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_analysis'])) {
    @set_time_limit(180);

    $domain = rtrim(trim($_POST['site_domain'] ?? $cfg['site_domain']), '/');
    $apiKey = trim($_POST['openai_api_key'] ?? $cfg['openai_api_key']);
    $model  = trim($_POST['openai_model']   ?? $cfg['openai_model']);

    if (empty($apiKey))  { _seo_json_exit(['success'=>false,'error'=>'OpenRouter API 키를 먼저 입력하세요.']); }
    if (empty($domain))  { _seo_json_exit(['success'=>false,'error'=>'사이트 도메인을 입력하세요.']);       }

    $csvData = [];
    if (!empty($_FILES['gsc_csv']['tmp_name'])) {
        $csvData = _seo_parse_csv($_FILES['gsc_csv']['tmp_name']);
    }

    $crawled = _seo_crawl_site($domain);
    $result  = _seo_analyze($csvData, $crawled, $domain, $apiKey, $model);

    if ($result['success']) {
        $ts = date('Y-m-d H:i:s');
        file_put_contents(_seo_data_dir() . '/last_report.txt',      $result['content']);
        file_put_contents(_seo_data_dir() . '/last_report_time.txt', $ts);
        _seo_json_exit(['success'=>true, 'content'=>$result['content'], 'time'=>$ts]);
    } else {
        _seo_json_exit(['success'=>false, 'error'=>$result['error']]);
    }
}

// 이전 리포트 불러오기
$lastReport     = '';
$lastReportTime = '';
$reportFile     = _seo_data_dir() . '/last_report.txt';
$timeFile       = _seo_data_dir() . '/last_report_time.txt';
if (file_exists($reportFile)) {
    $lastReport     = file_get_contents($reportFile);
    $lastReportTime = file_exists($timeFile) ? file_get_contents($timeFile) : '';
}

// ===== CSV 파싱 =====
function _seo_parse_csv(string $tmpFile): array {
    $rows = [];
    if (($fh = fopen($tmpFile, 'r')) === false) return [];
    $header = null;
    while (($line = fgetcsv($fh)) !== false) {
        if (!$header) { $header = array_map('trim', $line); continue; }
        if (count($line) < 2) continue;
        $row = array_combine(array_slice($header, 0, count($line)), $line);
        $keyword  = $row['쿼리']        ?? $row['Top queries'] ?? $row['Query'] ?? '';
        $clicks   = $row['클릭수']      ?? $row['Clicks']      ?? 0;
        $impr     = $row['노출수']      ?? $row['Impressions'] ?? 0;
        $position = $row['평균 게재순위'] ?? $row['Position']   ?? $row['Average position'] ?? 0;
        $url      = $row['페이지']      ?? $row['Landing page'] ?? $row['Page'] ?? '';
        if (empty($keyword)) continue;
        $rows[] = [
            'keyword'  => trim($keyword),
            'clicks'   => (int)$clicks,
            'impr'     => (int)$impr,
            'position' => round((float)str_replace(',', '.', $position), 1),
            'url'      => trim($url),
        ];
    }
    fclose($fh);
    usort($rows, fn($a, $b) => $b['clicks'] - $a['clicks']);
    return array_slice($rows, 0, 50);
}

// ===== 사이트 크롤링 =====
function _seo_crawl_site(string $domain): array {
    $result = ['title' => '', 'description' => '', 'h1' => [], 'h2' => []];
    $ch = curl_init($domain);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SEO-Analyzer/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    if (!$html) return $result;

    if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m))
        $result['title'] = trim(strip_tags($m[1]));
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/si', $html, $m))
        $result['description'] = trim($m[1]);
    preg_match_all('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m);
    $result['h1'] = array_slice(array_map(fn($t) => trim(strip_tags($t)), $m[1]), 0, 5);
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/si', $html, $m);
    $result['h2'] = array_slice(array_map(fn($t) => trim(strip_tags($t)), $m[1]), 0, 10);
    return $result;
}

// ===== AI 분석 =====
function _seo_analyze(array $csvData, array $crawled, string $domain, string $apiKey, string $model): array {
    $kwLines = '';
    foreach ($csvData as $r) {
        $kwLines .= "- 키워드: {$r['keyword']} | 순위: {$r['position']} | 클릭: {$r['clicks']} | 노출: {$r['impr']}\n";
    }
    $rank1 = count(array_filter($csvData, fn($r) => $r['position'] <= 10));
    $rank2 = count(array_filter($csvData, fn($r) => $r['position'] > 10 && $r['position'] <= 20));
    $rank3 = count(array_filter($csvData, fn($r) => $r['position'] > 20 && $r['position'] <= 50));
    $rank4 = count(array_filter($csvData, fn($r) => $r['position'] > 50));

    $siteInfo = "사이트 제목: {$crawled['title']}\n"
              . "메타 설명: {$crawled['description']}\n"
              . "H1 태그: " . implode(' / ', $crawled['h1']) . "\n"
              . "H2 태그(일부): " . implode(' / ', array_slice($crawled['h2'], 0, 5));

    if (!empty($csvData)) {
        $prompt = "당신은 구글 SEO 전문가입니다. 아래 데이터를 기반으로 SEO 분석 리포트를 작성하세요.\n\n"
                . "=== 분석 대상 사이트 ===\n도메인: {$domain}\n{$siteInfo}\n\n"
                . "=== 구글 서치콘솔 데이터 ===\n"
                . "총 키워드 수: " . count($csvData) . "개\n"
                . "순위 1~10위: {$rank1}개 | 11~20위: {$rank2}개 | 21~50위: {$rank3}개 | 50위+: {$rank4}개\n\n"
                . "상위 키워드 목록:\n{$kwLines}\n"
                . "=== 분석 지시 ===\n이모티콘 없이 한국어로 작성하세요. 데이터 기반으로만 분석하세요.\n\n"
                . "## 현재 SEO 상태\n- 핵심 키워드 (클릭 상위 5개):\n- 평균 순위:\n- 순위 구간 분포:\n\n"
                . "## 기회 키워드 (빠른 효과 가능)\n순위 11~20위 키워드 중 노출은 많은데 클릭이 적은 키워드를 찾아 개선 방법 제시\n\n"
                . "## 문제점\n사이트 title, meta, h1 구조와 키워드 데이터를 비교해서 문제점을 구체적으로 리스트\n\n"
                . "## 개선 전략 (실행 가능한 액션)\n단순 설명 금지. 구체적으로 '이 키워드를 제목에 포함하라' 형태로\n\n"
                . "## 우선순위 Top 5\n가장 빠르게 효과 볼 수 있는 것부터 번호로 정리";
    } else {
        $prompt = "당신은 구글 SEO 전문가입니다. 아래 사이트 크롤링 데이터를 기반으로 SEO 기초 진단 리포트를 작성하세요.\n\n"
                . "=== 분석 대상 사이트 ===\n도메인: {$domain}\n{$siteInfo}\n\n"
                . "=== 상황 ===\n아직 구글 서치콘솔 데이터가 없는 초기 단계입니다.\n\n"
                . "=== 분석 지시 ===\n이모티콘 없이 한국어로 작성하세요.\n\n"
                . "## 사이트 현황 진단\n- title 태그 평가\n- meta description 평가\n- H1/H2 구조 평가\n\n"
                . "## 지금 당장 고쳐야 할 것 (기초 SEO)\n발견된 문제점을 구체적으로 리스트\n\n"
                . "## 콘텐츠 전략 제안\n구글 상위노출을 위해 지금 당장 써야 할 콘텐츠 유형과 키워드 방향 제시\n\n"
                . "## 지금 바로 실행할 것 Top 5\n실행 가능한 액션을 번호로. '~하라' 형태로 구체적으로";
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
        CURLOPT_POSTFIELDS => json_encode([
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.4,
            'max_tokens'  => 2000,
        ]),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp     = curl_exec($ch);
    $curlErr  = curl_errno($ch);
    $curlMsg  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) return ['success' => false, 'error' => "서버에서 OpenAI 연결 실패 (curl #{$curlErr}: {$curlMsg}) — 호스팅 방화벽 문제일 수 있습니다."];
    if ($httpCode !== 200) return ['success' => false, 'error' => "OpenAI 응답 오류 (HTTP {$httpCode})"];
    $parsed  = json_decode($resp, true);
    $content = $parsed['choices'][0]['message']['content'] ?? '';
    if (empty($content)) return ['success' => false, 'error' => 'AI 응답이 비어있습니다.'];
    return ['success' => true, 'content' => $content];
}
?>

<style>
.sa-wrap { max-width: 860px; font-family: -apple-system, sans-serif; }
.sa-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
.sa-card h2 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
.sa-row { margin-bottom: 14px; }
.sa-row label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.sa-input { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
.sa-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.sa-btn { padding: 10px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.sa-btn-primary { background: #3b82f6; color: #fff; }
.sa-btn-primary:hover { background: #2563eb; }
.sa-btn-run { background: linear-gradient(135deg, #6366f1, #3b82f6); color: #fff; font-size: 16px; padding: 14px 32px; }
.sa-btn-run:hover { opacity: .9; }
.sa-btn-run:disabled { opacity: .6; cursor: not-allowed; }
.sa-btn-sm { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; margin-left: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 8px; }
.sa-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; }
.sa-msg.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.sa-msg.error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.sa-info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #1e40af; line-height: 1.8; margin-bottom: 16px; }
.sa-report { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; font-size: 14px; line-height: 1.9; color: #1e293b; white-space: pre-wrap; word-break: break-word; }
.sa-upload-area { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 24px; text-align: center; cursor: pointer; transition: border-color .2s; }
.sa-upload-area:hover { border-color: #3b82f6; background: #f8fafc; }
.sa-upload-area input { display: none; }
.sa-file-name { font-size: 13px; color: #22c55e; font-weight: 600; margin-top: 8px; }
</style>

<div class="sa-wrap">

<div id="sa_page_msg"></div>

<!-- API 설정 -->
<div class="sa-card">
    <h2>설정</h2>
    <div class="sa-row">
        <label>OpenRouter API 키</label>
        <div style="display:flex;align-items:center">
            <input type="password" id="sa_oai_key" class="sa-input"
                   value="<?= htmlspecialchars($cfg['openai_api_key']) ?>" placeholder="sk-or-v1-...">
            <button type="button" class="sa-btn-sm" onclick="saTestKeyBrowser()">브라우저 테스트</button>
            <button type="button" class="sa-btn-sm" onclick="saTestKeyServer()">서버 테스트</button>
        </div>
        <div id="sa_test_result" style="font-size:13px;margin-top:6px"></div>
    </div>
    <div class="sa-row">
        <label>모델</label>
        <select id="sa_model" class="sa-input" style="width:340px">
            <?= nb_openrouter_options($cfg['openai_model'] ?? '') ?>
        </select>
    </div>
    <div class="sa-row">
        <label>내 사이트 도메인</label>
        <input type="text" id="sa_domain" class="sa-input"
               value="<?= htmlspecialchars($cfg['site_domain']) ?>" placeholder="https://example.com">
    </div>
    <button type="button" class="sa-btn sa-btn-primary" onclick="saSaveConfig()">저장</button>
</div>

<!-- 분석 실행 -->
<div class="sa-card" style="border:2px solid #6366f1">
    <h2>SEO 분석 실행</h2>

    <div class="sa-info">
        <strong>CSV 파일 준비 방법 (딱 1분이면 돼요)</strong><br><br>
        1. <a href="https://search.google.com/search-console" target="_blank" style="color:#1d4ed8;font-weight:600">search.google.com/search-console</a> 접속<br>
        2. 왼쪽 메뉴 → <b>실적</b> 클릭<br>
        3. 오른쪽 상단 <b>내보내기 → CSV 다운로드</b><br>
        4. 다운받은 <b>ZIP 파일을 알집으로 풀기</b><br>
        5. 압축 풀면 파일 여러 개 나오는데 <b style="color:#dc2626">「검색어 수.csv」 만 업로드</b><br><br>
        <span style="color:#64748b;font-size:12px">※ 나머지 파일은 업로드 안 해도 됩니다. CSV 없이도 기초 진단 가능.</span>
    </div>

    <div class="sa-row">
        <label>서치콘솔 CSV 파일 <span style="font-weight:400;color:#22c55e">(선택사항 — 없어도 분석 가능)</span></label>
        <div class="sa-upload-area" onclick="document.getElementById('sa_csv').click()">
            <div style="font-size:14px;color:#64748b;margin-top:4px">클릭해서 「검색어 수.csv」 선택</div>
            <div style="font-size:12px;color:#94a3b8;margin-top:4px">없으면 그냥 아래 버튼 눌러도 돼요 → 사이트 구조 기반으로 기초 SEO 진단</div>
            <div class="sa-file-name" id="sa_file_name"></div>
            <input type="file" id="sa_csv" accept=".csv" onchange="saShowFile(this)">
        </div>
    </div>

    <div style="text-align:center;margin-top:20px">
        <button type="button" class="sa-btn sa-btn-run" id="sa_run_btn" onclick="saRun()">
            SEO 분석 시작
        </button>
        <div style="font-size:12px;color:#94a3b8;margin-top:8px">분석에 30~90초 소요됩니다.</div>
    </div>
</div>

<!-- 로딩 오버레이 -->
<div id="sa_loading" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.85);z-index:9999;align-items:center;justify-content:center;flex-direction:column">
    <div style="background:#1e293b;border-radius:20px;padding:40px 50px;text-align:center;min-width:360px;box-shadow:0 25px 60px rgba(0,0,0,0.5)">
        <div id="sa_spinner" style="width:64px;height:64px;border:5px solid #334155;border-top-color:#6366f1;border-radius:50%;animation:saSpin 1s linear infinite;margin:0 auto 24px"></div>

        <div id="sa_step_text" style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:8px">분석 준비 중...</div>
        <div id="sa_step_sub"  style="font-size:13px;color:#94a3b8;margin-bottom:24px">잠시만 기다려 주세요</div>

        <div style="text-align:left;margin-bottom:24px">
            <div id="step1" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;opacity:.4">
                <span id="step1_icon" style="font-size:13px;color:#6366f1;font-weight:700;width:14px">·</span>
                <span style="color:#e2e8f0;font-size:13px">CSV 데이터 읽는 중</span>
            </div>
            <div id="step2" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;opacity:.4">
                <span id="step2_icon" style="font-size:13px;color:#6366f1;font-weight:700;width:14px">·</span>
                <span style="color:#e2e8f0;font-size:13px">사이트 크롤링 중</span>
            </div>
            <div id="step3" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;opacity:.4">
                <span id="step3_icon" style="font-size:13px;color:#6366f1;font-weight:700;width:14px">·</span>
                <span style="color:#e2e8f0;font-size:13px">AI 분석 실행 중</span>
            </div>
            <div id="step4" style="display:flex;align-items:center;gap:10px;opacity:.4">
                <span id="step4_icon" style="font-size:13px;color:#6366f1;font-weight:700;width:14px">·</span>
                <span style="color:#e2e8f0;font-size:13px">리포트 생성 중</span>
            </div>
        </div>

        <div style="font-size:13px;color:#64748b;margin-bottom:16px">경과 시간: <span id="sa_timer" style="color:#6366f1;font-weight:700">0</span>초</div>

        <div id="sa_timeout_msg" style="display:none;background:#7f1d1d;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#fca5a5;line-height:1.6">
            서버에서 OpenAI 연결이 안 되는 것 같습니다.<br>
            위 <b>서버 테스트</b> 버튼으로 연결 상태를 확인해 주세요.
        </div>

        <button onclick="saCancel()" style="background:transparent;border:1px solid #475569;color:#94a3b8;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:13px">
            취소
        </button>
        <div style="font-size:11px;color:#475569;margin-top:8px">취소 시 분석이 중단됩니다</div>
    </div>
</div>

<style>
@keyframes saSpin { to { transform: rotate(360deg); } }
</style>

<!-- 분석 리포트 영역 -->
<div id="sa_report_area">
<?php if ($lastReport): ?>
<div class="sa-card">
    <h2>SEO 분석 리포트
        <?php if ($lastReportTime): ?>
        <span style="font-size:12px;font-weight:400;color:#94a3b8;margin-left:8px">마지막 분석: <?= htmlspecialchars($lastReportTime) ?></span>
        <?php endif; ?>
    </h2>
    <div class="sa-report" id="sa_report"><?= htmlspecialchars($lastReport) ?></div>
    <div style="margin-top:16px;text-align:right">
        <button onclick="saCopyReport()" class="sa-btn" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-size:13px">복사</button>
    </div>
</div>
<?php endif; ?>
</div>

</div>

<script>
var SA_URL  = <?= json_encode($_SERVER['REQUEST_URI']) ?>;
var SA_KEY  = <?= json_encode($cfg['openai_api_key']) ?>;
var SA_MOD  = <?= json_encode($cfg['openai_model']) ?>;
var SA_DOM  = <?= json_encode($cfg['site_domain']) ?>;

var saAbortCtrl = null;
var saTimerInt  = null;
var saStepTimers = [];

function saShowFile(input) {
    var name = input.files[0] ? input.files[0].name : '';
    document.getElementById('sa_file_name').textContent = name;
}

// ─── 분석 실행 (AJAX) ────────────────────────────────────────
function saRun() {
    var key    = document.getElementById('sa_oai_key').value.trim() || SA_KEY;
    var domain = document.getElementById('sa_domain').value.trim()  || SA_DOM;
    var model  = document.getElementById('sa_model').value           || SA_MOD;

    if (!key)    { saPageMsg('error', 'OpenRouter API 키를 입력하세요.'); return; }
    if (!domain) { saPageMsg('error', '사이트 도메인을 입력하세요.'); return; }

    // 오버레이 표시
    var overlay = document.getElementById('sa_loading');
    overlay.style.display = 'flex';
    document.getElementById('sa_timeout_msg').style.display = 'none';
    document.getElementById('sa_spinner').style.borderTopColor = '#6366f1';
    document.getElementById('sa_run_btn').disabled = true;

    // 타이머
    var sec = 0;
    saTimerInt = setInterval(function() {
        document.getElementById('sa_timer').textContent = ++sec;
    }, 1000);

    // 단계 애니메이션
    var steps = [
        { id:'step1', text:'CSV 데이터 읽는 중...',  sub:'키워드와 순위 데이터를 파싱하고 있어요',              delay:0 },
        { id:'step2', text:'사이트 크롤링 중...',     sub:'제목, 메타태그, 헤딩 구조를 분석하고 있어요',         delay:4000 },
        { id:'step3', text:'AI 분석 실행 중...',      sub:'GPT가 SEO 데이터를 분석 중이에요 (가장 오래 걸려요)', delay:8000 },
        { id:'step4', text:'리포트 생성 중...',       sub:'개선 전략과 우선순위를 정리하고 있어요',              delay:35000 },
    ];
    steps.forEach(function(step, idx) {
        saStepTimers.push(setTimeout(function() {
            if (idx > 0) {
                document.getElementById('step' + idx).style.opacity = '1';
                document.getElementById('step' + idx + '_icon').textContent = 'v';
            }
            document.getElementById(step.id).style.opacity = '1';
            document.getElementById(step.id + '_icon').textContent = '>';
            document.getElementById('sa_step_text').textContent = step.text;
            document.getElementById('sa_step_sub').textContent  = step.sub;
        }, step.delay));
    });

    // 100초 타임아웃 (AbortController로 fetch 취소)
    saAbortCtrl = new AbortController();
    var abortTimer = setTimeout(function() {
        saAbortCtrl.abort();
    }, 100000);

    // FormData 구성
    var fd = new FormData();
    fd.append('run_analysis', '1');
    fd.append('openai_api_key', key);
    fd.append('openai_model',   model);
    fd.append('site_domain',    domain);
    var csv = document.getElementById('sa_csv');
    if (csv.files.length) fd.append('gsc_csv', csv.files[0]);

    fetch(SA_URL, { method:'POST', body:fd, signal:saAbortCtrl.signal })
        .then(function(r) {
            clearTimeout(abortTimer);
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(data) {
            saStopLoading();
            if (data.success) {
                saRenderReport(data.content, data.time);
                saPageMsg('success', '분석이 완료되었습니다!');
            } else {
                saOverlayError(data.error || '알 수 없는 오류');
            }
        })
        .catch(function(err) {
            clearTimeout(abortTimer);
            saStopLoading();
            if (err.name === 'AbortError') {
                saOverlayError('100초 초과 — 서버에서 OpenAI 연결이 안 될 수 있습니다.\n위 서버 테스트 버튼으로 확인해 주세요.');
            } else {
                saOverlayError('네트워크 오류: ' + err.message);
            }
        });
}

function saStopLoading() {
    clearInterval(saTimerInt);
    saStepTimers.forEach(clearTimeout);
    saStepTimers = [];
    document.getElementById('sa_run_btn').disabled = false;
}

function saOverlayError(msg) {
    document.getElementById('sa_loading').style.display = 'none';
    saPageMsg('error', msg);
}

function saCancel() {
    if (saAbortCtrl) saAbortCtrl.abort();
    saStopLoading();
    document.getElementById('sa_loading').style.display = 'none';
    document.getElementById('sa_run_btn').disabled = false;
}

// ─── 리포트 렌더링 ────────────────────────────────────────────
function saRenderReport(content, time) {
    var timeStr = time
        ? '<span style="font-size:12px;font-weight:400;color:#94a3b8;margin-left:8px">' + time + '</span>'
        : '';
    var escaped = saEscape(content);
    document.getElementById('sa_report_area').innerHTML =
        '<div class="sa-card">'
        + '<h2>SEO 분석 리포트 ' + timeStr + '</h2>'
        + '<div class="sa-report" id="sa_report">' + escaped + '</div>'
        + '<div style="margin-top:16px;text-align:right">'
        + '<button onclick="saCopyReport()" class="sa-btn" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-size:13px">복사</button>'
        + '</div></div>';
    document.getElementById('sa_report_area').scrollIntoView({behavior:'smooth'});
}

function saEscape(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ─── 설정 저장 ────────────────────────────────────────────────
function saSaveConfig() {
    var fd = new FormData();
    fd.append('save_config',    '1');
    fd.append('openai_api_key', document.getElementById('sa_oai_key').value.trim());
    fd.append('openai_model',   document.getElementById('sa_model').value);
    fd.append('site_domain',    document.getElementById('sa_domain').value.trim());
    fetch(SA_URL, {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){ saPageMsg(d.success ? 'success' : 'error', d.msg || '저장 완료'); })
        .catch(function(){ saPageMsg('error','저장 실패'); });
}

// ─── API 키 테스트 (브라우저→OpenAI) ──────────────────────────
function saTestKeyBrowser() {
    var key = document.getElementById('sa_oai_key').value.trim();
    var el  = document.getElementById('sa_test_result');
    if (!key) { el.innerHTML = '<span style="color:#ef4444">API 키를 입력하세요.</span>'; return; }
    el.innerHTML = '<span style="color:#94a3b8">브라우저에서 테스트 중...</span>';
    fetch('https://openrouter.ai/api/v1/models', { headers:{'Authorization':'Bearer '+key} })
        .then(function(r) {
            el.innerHTML = r.ok
                ? '<span style="color:#22c55e">브라우저 연결 성공 (HTTP '+r.status+')</span>'
                : '<span style="color:#ef4444">API 키 오류 (HTTP '+r.status+')</span>';
        })
        .catch(function(){ el.innerHTML='<span style="color:#ef4444">네트워크 오류</span>'; });
}

// ─── API 키 테스트 (서버→OpenAI) ──────────────────────────────
function saTestKeyServer() {
    var key = document.getElementById('sa_oai_key').value.trim();
    var el  = document.getElementById('sa_test_result');
    if (!key) { el.innerHTML = '<span style="color:#ef4444">API 키를 입력하세요.</span>'; return; }
    el.innerHTML = '<span style="color:#94a3b8">서버에서 테스트 중 (최대 15초)...</span>';
    fetch(SA_URL + '&seo_ping=1&key=' + encodeURIComponent(key))
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (d.ok) {
                el.innerHTML = '<span style="color:#22c55e">서버 연결 성공! 분석이 정상 작동할 것입니다.</span>';
            } else {
                el.innerHTML = '<span style="color:#ef4444">서버→OpenAI 연결 실패 (HTTP '+d.code+', curl #'+d.curl_err+')<br>'
                    + '호스팅 방화벽에서 api.openai.com 이 막혀있을 수 있습니다. 호스팅사에 문의하세요.</span>';
            }
        })
        .catch(function(){ el.innerHTML='<span style="color:#ef4444">서버 테스트 실패</span>'; });
}

// ─── 복사 ─────────────────────────────────────────────────────
function saCopyReport() {
    var el = document.getElementById('sa_report');
    if (!el) return;
    navigator.clipboard.writeText(el.textContent).then(function() {
        alert('리포트가 클립보드에 복사되었습니다.');
    });
}

// ─── 페이지 메시지 ────────────────────────────────────────────
function saPageMsg(type, text) {
    document.getElementById('sa_page_msg').innerHTML =
        '<div class="sa-msg ' + type + '">' + saEscape(text) + '</div>';
}
</script>

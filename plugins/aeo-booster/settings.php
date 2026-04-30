<?php
/**
 * AEO 부스터 - 관리자 설정 페이지
 * 탭 구조: 구조화 데이터 / llms.txt / AI 크롤러 / 미리보기
 */

require_once __DIR__ . '/plugin.php';

$aeo_flash = '';
// 탭은 JS 로 전환 (누리보드 admin 라우팅과 충돌 방지)
// POST 후에는 어느 탭에 있었는지 active_tab 으로 복원
$aeo_tab = $_POST['active_tab'] ?? 'schema';

// ==================== llms.txt 생성 ====================
if (!function_exists('aeo_generate_llms_txt')) {
    function aeo_generate_llms_txt(): string
    {
        $siteName = aeo_get_setting('site_title', aeo_get_setting('site_name', '누리보드 사이트'));
        $siteDesc = aeo_get_setting('site_description', '');
        $siteUrl  = rtrim(aeo_get_setting('site_url', ''), '/');

        $out  = "# " . $siteName . "\n\n";
        if ($siteDesc !== '') $out .= "> " . $siteDesc . "\n\n";

        $out .= "## 사이트 개요\n\n";
        if ($siteUrl) $out .= "- 주소: " . $siteUrl . "\n";
        $out .= "- 생성 시각: " . date('Y-m-d H:i') . "\n\n";

        if (!class_exists('DB')) return $out;

        try {
            $prefix = DB::getPrefix();

            // 게시판 목록
            $boards = DB::fetchAll("SELECT board_id, title, description FROM {$prefix}boards WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
            if (!empty($boards)) {
                $out .= "## 게시판\n\n";
                foreach ($boards as $b) {
                    $url  = $siteUrl . '/board/' . $b['board_id'];
                    $desc = trim((string)($b['description'] ?? ''));
                    $line = "- [" . $b['title'] . "](" . $url . ")";
                    if ($desc !== '') $line .= ": " . $desc;
                    $out .= $line . "\n";
                }
                $out .= "\n";
            }

            // 최근 게시글 (최대 30개)
            $posts = DB::fetchAll("SELECT id, board_id, title, slug, created_at FROM {$prefix}posts ORDER BY id DESC LIMIT 30");
            if (!empty($posts)) {
                $out .= "## 최근 게시글\n\n";
                foreach ($posts as $p) {
                    $tail = !empty($p['slug']) ? $p['slug'] : (string)$p['id'];
                    $url  = $siteUrl . '/board/' . $p['board_id'] . '/' . $tail;
                    $out .= "- [" . $p['title'] . "](" . $url . ")\n";
                }
                $out .= "\n";
            }
        } catch (Exception $e) {
            $out .= "(데이터 조회 오류)\n";
        }

        $out .= "## AI 사용 안내\n\n";
        $out .= "이 사이트의 공개 콘텐츠는 AI 검색엔진에서 인용·요약될 수 있습니다.\n";
        $out .= "원문 링크를 함께 표시해 주세요.\n";

        return $out;
    }
}

// ==================== robots.txt 갱신 ====================
if (!function_exists('aeo_apply_robots')) {
    function aeo_apply_robots(): array
    {
        if (!defined('NB_ROOT')) return ['ok' => false, 'msg' => 'NB_ROOT 미정의'];
        $file = NB_ROOT . '/robots.txt';

        // AI 봇 목록
        $bots = [
            'GPTBot'             => aeo_get_setting('aeo_bot_gptbot', 'allow'),
            'OAI-SearchBot'      => aeo_get_setting('aeo_bot_oai_search', 'allow'),
            'ChatGPT-User'       => aeo_get_setting('aeo_bot_chatgpt_user', 'allow'),
            'Google-Extended'    => aeo_get_setting('aeo_bot_google_ext', 'allow'),
            'PerplexityBot'      => aeo_get_setting('aeo_bot_perplexity', 'allow'),
            'anthropic-ai'       => aeo_get_setting('aeo_bot_anthropic', 'allow'),
            'ClaudeBot'          => aeo_get_setting('aeo_bot_claude', 'allow'),
            'Meta-ExternalAgent' => aeo_get_setting('aeo_bot_meta', 'allow'),
            'CCBot'              => aeo_get_setting('aeo_bot_ccbot', 'block'),
        ];

        // 기존 robots.txt 읽기
        $existing = file_exists($file) ? file_get_contents($file) : "User-agent: *\nAllow: /\n";

        // AEO 블록 제거
        $pattern = '/\n?# === AEO 부스터 관리 블록 시작 ===.*?# === AEO 부스터 관리 블록 끝 ===\n?/s';
        $cleaned = preg_replace($pattern, "\n", $existing);
        $cleaned = rtrim($cleaned, "\n") . "\n";

        // 새 AEO 블록 작성
        $block = "\n# === AEO 부스터 관리 블록 시작 ===\n";
        $block .= "# 이 블록은 AEO 부스터 플러그인이 자동 관리합니다. 수동 편집 금지.\n";
        foreach ($bots as $name => $mode) {
            $rule = ($mode === 'block') ? 'Disallow: /' : 'Allow: /';
            $block .= "User-agent: " . $name . "\n" . $rule . "\n\n";
        }
        $block .= "# === AEO 부스터 관리 블록 끝 ===\n";

        $result = @file_put_contents($file, $cleaned . $block);
        return $result !== false
            ? ['ok' => true,  'msg' => 'robots.txt 갱신 완료']
            : ['ok' => false, 'msg' => 'robots.txt 쓰기 실패 (권한 확인 필요)'];
    }
}

// ==================== llms.txt 파일 저장 ====================
if (!function_exists('aeo_save_llms_file')) {
    function aeo_save_llms_file(): array
    {
        if (!defined('NB_ROOT')) return ['ok' => false, 'msg' => 'NB_ROOT 미정의'];
        $file = NB_ROOT . '/llms.txt';
        $content = aeo_generate_llms_txt();
        $result = @file_put_contents($file, $content);
        return $result !== false
            ? ['ok' => true, 'msg' => 'llms.txt 생성 완료 (' . strlen($content) . ' bytes)']
            : ['ok' => false, 'msg' => 'llms.txt 쓰기 실패 (권한 확인 필요)'];
    }
}

// ==================== POST 처리 ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['aeo_act'] ?? '';

    if ($act === 'save_schema') {
        aeo_set_setting('aeo_enable_faq',       !empty($_POST['aeo_enable_faq'])       ? '1' : '0');
        aeo_set_setting('aeo_enable_howto',     !empty($_POST['aeo_enable_howto'])     ? '1' : '0');
        aeo_set_setting('aeo_enable_speakable', !empty($_POST['aeo_enable_speakable']) ? '1' : '0');
        $aeo_flash = '구조화 데이터 설정 저장됨.';
        $aeo_tab = 'schema';
    }
    elseif ($act === 'save_llms') {
        aeo_set_setting('aeo_llms_enabled', !empty($_POST['aeo_llms_enabled']) ? '1' : '0');
        if (!empty($_POST['aeo_llms_enabled'])) {
            $r = aeo_save_llms_file();
            $aeo_flash = $r['msg'];
        } else {
            // 비활성화 → 파일 삭제
            if (defined('NB_ROOT') && file_exists(NB_ROOT . '/llms.txt')) @unlink(NB_ROOT . '/llms.txt');
            $aeo_flash = 'llms.txt 생성이 비활성화되었습니다. 파일을 삭제했습니다.';
        }
        $aeo_tab = 'llms';
    }
    elseif ($act === 'regen_llms') {
        $r = aeo_save_llms_file();
        $aeo_flash = $r['msg'];
        $aeo_tab = 'llms';
    }
    elseif ($act === 'save_robots') {
        $bots = ['gptbot','oai_search','chatgpt_user','google_ext','perplexity','anthropic','claude','meta','ccbot'];
        foreach ($bots as $b) {
            $mode = ($_POST['aeo_bot_' . $b] ?? 'allow') === 'block' ? 'block' : 'allow';
            aeo_set_setting('aeo_bot_' . $b, $mode);
        }
        $r = aeo_apply_robots();
        $aeo_flash = $r['msg'];
        $aeo_tab = 'robots';
    }
}

// 현재 설정 로드
$v = [
    'faq'        => aeo_get_setting('aeo_enable_faq', '1') === '1',
    'howto'      => aeo_get_setting('aeo_enable_howto', '1') === '1',
    'speakable'  => aeo_get_setting('aeo_enable_speakable', '1') === '1',
    'llms_on'    => aeo_get_setting('aeo_llms_enabled', '0') === '1',
];
$bots = [
    ['key' => 'gptbot',       'name' => 'GPTBot',             'desc' => 'ChatGPT 학습용 크롤러 (OpenAI)'],
    ['key' => 'oai_search',   'name' => 'OAI-SearchBot',      'desc' => 'ChatGPT 검색 기능 크롤러'],
    ['key' => 'chatgpt_user', 'name' => 'ChatGPT-User',       'desc' => 'ChatGPT 사용자가 링크 조회 시'],
    ['key' => 'google_ext',   'name' => 'Google-Extended',    'desc' => 'Google Bard / AI Overview 학습용'],
    ['key' => 'perplexity',   'name' => 'PerplexityBot',      'desc' => 'Perplexity AI 검색엔진'],
    ['key' => 'anthropic',    'name' => 'anthropic-ai',       'desc' => 'Anthropic Claude 학습용 (legacy)'],
    ['key' => 'claude',       'name' => 'ClaudeBot',          'desc' => 'Anthropic Claude 크롤러'],
    ['key' => 'meta',         'name' => 'Meta-ExternalAgent', 'desc' => 'Meta AI (페이스북/인스타)'],
    ['key' => 'ccbot',        'name' => 'CCBot',              'desc' => 'Common Crawl (대규모 데이터셋 수집)'],
];
?>

<style>
.aeo-wrap { max-width: 860px; }
.aeo-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.aeo-head h2 { font-size: 22px; font-weight: 700; color: #111827; margin: 0; }
.aeo-head p { margin: 4px 0 0; font-size: 13px; color: #6b7280; }

.aeo-tabs { display: flex; gap: 2px; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; }
.aeo-tab { padding: 10px 18px; background: transparent; border: 0; font-size: 14px; font-weight: 600; color: #6b7280; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; text-decoration: none; display: inline-block; }
.aeo-tab:hover { color: #111827; }
.aeo-tab.active { color: #16a34a; border-bottom-color: #16a34a; }

.aeo-flash { padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }

.aeo-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
.aeo-section-head { padding: 14px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 15px; font-weight: 700; color: #111827; }
.aeo-section-body { padding: 20px; }

.aeo-toggle { display: flex; align-items: flex-start; gap: 12px; padding: 14px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 10px; cursor: pointer; }
.aeo-toggle:hover { background: #f3f4f6; }
.aeo-toggle input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; accent-color: #16a34a; }
.aeo-toggle-body { flex: 1; }
.aeo-toggle-body b { display: block; font-size: 14px; color: #111827; margin-bottom: 3px; }
.aeo-toggle-body span { font-size: 12px; color: #6b7280; line-height: 1.6; }

.aeo-radio-row { display: flex; align-items: center; padding: 10px 14px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; gap: 14px; }
.aeo-radio-body { flex: 1; }
.aeo-radio-body b { display: block; font-size: 13px; color: #111827; }
.aeo-radio-body span { font-size: 12px; color: #6b7280; }
.aeo-radio-group { display: flex; gap: 10px; }
.aeo-radio-group label { display: flex; align-items: center; gap: 4px; font-size: 13px; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-weight: 600; }
.aeo-radio-group label.allow { background: #dcfce7; color: #16a34a; }
.aeo-radio-group label.block { background: #fee2e2; color: #dc2626; }
.aeo-radio-group input[type="radio"] { accent-color: currentColor; }

.aeo-btn { padding: 10px 20px; background: #16a34a; color: #fff; border: 1px solid #16a34a; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
.aeo-btn:hover { background: #15803d; }
.aeo-btn-ghost { background: #fff; color: #374151; border-color: #d1d5db; }
.aeo-btn-ghost:hover { background: #f3f4f6; }

.aeo-code { background: #0f172a; color: #e2e8f0; padding: 14px 16px; border-radius: 8px; font-family: 'Consolas', monospace; font-size: 12px; line-height: 1.7; overflow-x: auto; white-space: pre-wrap; word-break: break-all; max-height: 380px; overflow-y: auto; }

.aeo-info { padding: 12px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; font-size: 12px; color: #1e40af; line-height: 1.7; margin-top: 12px; }
.aeo-info b { color: #1e3a8a; }
</style>

<div class="aeo-wrap">
    <div class="aeo-head">
        <div>
            <h2>AEO 부스터</h2>
            <p>AI 검색엔진(ChatGPT, Perplexity, Google AI Overview)에 콘텐츠를 최적화합니다.</p>
        </div>
    </div>

    <?php if ($aeo_flash): ?>
    <div class="aeo-flash"><?= htmlspecialchars($aeo_flash) ?></div>
    <?php endif; ?>

    <div class="aeo-tabs">
        <button type="button" class="aeo-tab <?= $aeo_tab==='schema' ?'active':'' ?>" data-tab="schema">구조화 데이터</button>
        <button type="button" class="aeo-tab <?= $aeo_tab==='llms'   ?'active':'' ?>" data-tab="llms">llms.txt</button>
        <button type="button" class="aeo-tab <?= $aeo_tab==='robots' ?'active':'' ?>" data-tab="robots">AI 크롤러</button>
        <button type="button" class="aeo-tab <?= $aeo_tab==='preview'?'active':'' ?>" data-tab="preview">미리보기</button>
    </div>

    <div class="aeo-pane" data-pane="schema" style="<?= $aeo_tab==='schema' ?'':'display:none' ?>">
    <!-- ========== 구조화 데이터 탭 ========== -->
    <form method="post">
        <input type="hidden" name="aeo_act" value="save_schema">
        <input type="hidden" name="active_tab" value="schema">

        <div class="aeo-section">
            <div class="aeo-section-head">구조화 데이터(JSON-LD) 자동 삽입</div>
            <div class="aeo-section-body">
                <label class="aeo-toggle">
                    <input type="checkbox" name="aeo_enable_faq" value="1" <?= $v['faq']?'checked':'' ?>>
                    <div class="aeo-toggle-body">
                        <b>FAQPage 스키마 자동 감지</b>
                        <span>게시글 본문에서 "Q:/A:" 또는 "질문:/답변:" 패턴 발견 시 FAQPage 스키마를 자동 삽입합니다. (최소 2쌍 이상 필요) 구글 AI Overview / Perplexity 채택률 향상.</span>
                    </div>
                </label>

                <label class="aeo-toggle">
                    <input type="checkbox" name="aeo_enable_howto" value="1" <?= $v['howto']?'checked':'' ?>>
                    <div class="aeo-toggle-body">
                        <b>HowTo 스키마 자동 감지</b>
                        <span>게시글 본문에 번호 매긴 단계(1. 2. 3. 또는 ①②③)가 3개 이상 있으면 HowTo 스키마를 자동 삽입합니다. 제목에 "방법/가이드/튜토리얼" 포함 시 2단계부터 인식.</span>
                    </div>
                </label>

                <label class="aeo-toggle">
                    <input type="checkbox" name="aeo_enable_speakable" value="1" <?= $v['speakable']?'checked':'' ?>>
                    <div class="aeo-toggle-body">
                        <b>Speakable 스키마 (음성 검색 최적화)</b>
                        <span>시리/구글 어시스턴트/알렉사 같은 음성 검색엔진이 글을 낭독하기 좋은 영역(제목/본문 첫 문단)을 지정합니다.</span>
                    </div>
                </label>

                <div class="aeo-info">
                    <b>중복 안 됨</b>: 누리보드 코어가 이미 Article, BreadcrumbList, WebSite, Organization 스키마를 생성합니다. 이 플러그인은 <b>그 외에 비어있는 AI 친화 스키마만</b> 추가합니다.
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:16px">
            <button type="submit" class="aeo-btn">저장</button>
        </div>
    </form>
    </div><!-- /schema pane -->

    <div class="aeo-pane" data-pane="llms" style="<?= $aeo_tab==='llms' ?'':'display:none' ?>">
    <!-- ========== llms.txt 탭 ========== -->
    <form method="post">
        <input type="hidden" name="aeo_act" value="save_llms">
        <input type="hidden" name="active_tab" value="llms">

        <div class="aeo-section">
            <div class="aeo-section-head">llms.txt 자동 생성</div>
            <div class="aeo-section-body">
                <label class="aeo-toggle">
                    <input type="checkbox" name="aeo_llms_enabled" value="1" <?= $v['llms_on']?'checked':'' ?>>
                    <div class="aeo-toggle-body">
                        <b>사이트 루트에 /llms.txt 자동 생성</b>
                        <span>AI 검색엔진용 사이트 요약 파일. 게시판 목록 + 최근 게시글 30개 URL을 마크다운 형식으로 제공하여 AI 크롤러가 사이트 구조를 빠르게 파악할 수 있게 합니다.</span>
                    </div>
                </label>

                <div class="aeo-info">
                    <b>llms.txt 란?</b> 2024년부터 확산 중인 AI 친화 사이트맵 표준(llmstxt.org). ChatGPT, Perplexity 등이 사이트 구조를 빠르게 이해하도록 돕습니다. robots.txt가 "가지 마라"를 지시한다면, llms.txt는 "이게 중요하다"를 안내하는 역할.
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;gap:10px;margin-top:16px">
            <?php if ($v['llms_on']): ?>
            <button type="submit" name="aeo_act" value="regen_llms" class="aeo-btn aeo-btn-ghost">지금 다시 생성</button>
            <?php else: ?>
            <span></span>
            <?php endif; ?>
            <button type="submit" class="aeo-btn">저장</button>
        </div>
    </form>

    <?php if ($v['llms_on'] && defined('NB_ROOT') && file_exists(NB_ROOT . '/llms.txt')): ?>
    <div class="aeo-section" style="margin-top:16px">
        <div class="aeo-section-head">현재 /llms.txt 내용</div>
        <div class="aeo-section-body">
            <div class="aeo-code"><?= htmlspecialchars(file_get_contents(NB_ROOT . '/llms.txt')) ?></div>
        </div>
    </div>
    <?php endif; ?>
    </div><!-- /llms pane -->

    <div class="aeo-pane" data-pane="robots" style="<?= $aeo_tab==='robots' ?'':'display:none' ?>">
    <!-- ========== AI 크롤러 탭 ========== -->
    <form method="post">
        <input type="hidden" name="aeo_act" value="save_robots">
        <input type="hidden" name="active_tab" value="robots">

        <div class="aeo-section">
            <div class="aeo-section-head">AI 크롤러 허용 / 차단</div>
            <div class="aeo-section-body">
                <?php foreach ($bots as $bot): ?>
                <?php $key = 'aeo_bot_' . $bot['key']; $cur = aeo_get_setting($key, $bot['key'] === 'ccbot' ? 'block' : 'allow'); ?>
                <div class="aeo-radio-row">
                    <div class="aeo-radio-body">
                        <b><?= htmlspecialchars($bot['name']) ?></b>
                        <span><?= htmlspecialchars($bot['desc']) ?></span>
                    </div>
                    <div class="aeo-radio-group">
                        <label class="allow">
                            <input type="radio" name="<?= $key ?>" value="allow" <?= $cur==='allow'?'checked':'' ?>>
                            허용
                        </label>
                        <label class="block">
                            <input type="radio" name="<?= $key ?>" value="block" <?= $cur==='block'?'checked':'' ?>>
                            차단
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="aeo-info">
                    저장 시 사이트 루트의 robots.txt 파일을 자동 갱신합니다. 기존 규칙은 보존되며 AEO 관리 블록만 교체됩니다.
                    <br><b>추천</b>: 블로그/커뮤니티는 대부분 "허용" 유지. 수익을 위해 콘텐츠 무단 학습을 막으려면 차단. CCBot은 기본 차단 권장(대규모 무단 수집).
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:16px">
            <button type="submit" class="aeo-btn">저장 + robots.txt 적용</button>
        </div>
    </form>

    <?php if (defined('NB_ROOT') && file_exists(NB_ROOT . '/robots.txt')): ?>
    <div class="aeo-section" style="margin-top:16px">
        <div class="aeo-section-head">현재 /robots.txt 내용</div>
        <div class="aeo-section-body">
            <div class="aeo-code"><?= htmlspecialchars(file_get_contents(NB_ROOT . '/robots.txt')) ?></div>
        </div>
    </div>
    <?php endif; ?>
    </div><!-- /robots pane -->

    <div class="aeo-pane" data-pane="preview" style="<?= $aeo_tab==='preview' ?'':'display:none' ?>">
    <!-- ========== 미리보기 탭 ========== -->
    <div class="aeo-section">
        <div class="aeo-section-head">스키마 미리보기</div>
        <div class="aeo-section-body">
            <?php
            $preview = aeo_build_schemas();
            if ($preview === ''):
                $cur = aeo_current_post();
            ?>
                <?php if (!$cur): ?>
                <p style="color:#6b7280;font-size:14px;margin:0">이 설정 페이지에서는 미리보기를 표시할 수 없습니다. <b>게시글 상세 페이지</b>에서 접속하면 해당 글의 감지된 스키마가 표시됩니다.</p>
                <p style="color:#6b7280;font-size:13px;margin:12px 0 0">실시간 검증: Google <a href="https://search.google.com/test/rich-results" target="_blank" style="color:#16a34a">Rich Results Test</a>에서 게시글 URL 입력 후 "URL 테스트" 클릭.</p>
                <?php else: ?>
                <p style="color:#6b7280;font-size:14px;margin:0 0 8px"><b>"<?= htmlspecialchars($cur['title']) ?>"</b> 글에서 감지된 스키마가 없습니다.</p>
                <ul style="font-size:13px;color:#6b7280;line-height:1.8;padding-left:20px">
                    <li>FAQPage: "Q:/A:" 또는 "질문:/답변:" 패턴 최소 2쌍 필요</li>
                    <li>HowTo: 번호 매긴 단계(1. 2. 3.) 3개 이상 필요 (제목에 "방법/가이드" 포함 시 2개부터)</li>
                    <li>Speakable: 항상 생성 (제목만 있으면 OK)</li>
                </ul>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:#16a34a;font-size:14px;margin:0 0 12px;font-weight:600">이 글에서 감지된 스키마:</p>
                <div class="aeo-code"><?= htmlspecialchars($preview) ?></div>
                <p style="color:#6b7280;font-size:12px;margin:12px 0 0">이 JSON-LD는 게시글 상세 페이지 렌더링 시 &lt;head&gt; 영역에 자동 삽입됩니다.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="aeo-section">
        <div class="aeo-section-head">검증 도구</div>
        <div class="aeo-section-body">
            <ul style="font-size:14px;line-height:2;margin:0;padding-left:20px;color:#374151">
                <li><a href="https://search.google.com/test/rich-results" target="_blank" style="color:#16a34a;font-weight:600">Google Rich Results Test</a> — 구조화 데이터 검증</li>
                <li><a href="https://validator.schema.org/" target="_blank" style="color:#16a34a;font-weight:600">Schema.org Validator</a> — JSON-LD 문법 검증</li>
                <li><a href="https://search.google.com/search-console" target="_blank" style="color:#16a34a;font-weight:600">Google Search Console</a> — 실제 색인 상태 확인</li>
            </ul>
        </div>
    </div>
    </div><!-- /preview pane -->

</div>

<script>
(function(){
    var tabs  = document.querySelectorAll('.aeo-wrap .aeo-tab');
    var panes = document.querySelectorAll('.aeo-wrap .aeo-pane');
    tabs.forEach(function(btn){
        btn.addEventListener('click', function(){
            var target = this.getAttribute('data-tab');
            tabs.forEach(function(b){ b.classList.toggle('active', b === btn); });
            panes.forEach(function(p){
                p.style.display = (p.getAttribute('data-pane') === target) ? '' : 'none';
            });
        });
    });
})();
</script>

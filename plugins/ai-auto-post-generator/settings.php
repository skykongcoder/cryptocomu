<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * AI 자동글 작성기 v2.0 - 설정 & 실행 페이지
 */

$_ai_config_file = __DIR__ . '/config.json';
$_ai_config_raw = file_exists($_ai_config_file)
    ? json_decode(file_get_contents($_ai_config_file), true)
    : [];
if (!is_array($_ai_config_raw)) $_ai_config_raw = [];

$_ai_config = array_merge([
    'openai_api_key' => '',
    'unsplash_api_key' => '',
    'boards' => [],
    'auto_enabled' => false,
    'interval_hours' => 6,
    'posts_per_run' => 1,
    'min_length' => 500,
    'max_length' => 1000,
    'tone' => 'informative',
    'custom_prompt' => '',
    'keywords' => [],
    'keyword_index' => 0,
    'must_include' => [],
    'auto_member' => true,
    'add_image' => true,
    'auto_mode' => false,
    'last_run' => '',
], $_ai_config_raw);

// ===== 설정 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_save'])) {
    // 키워드 목록 파싱 (줄바꿈 구분)
    $kwRaw = trim($_POST['keywords'] ?? '');
    $keywords = $kwRaw ? array_values(array_filter(array_map('trim', explode("\n", $kwRaw)))) : [];

    // 필수 포함 문구 파싱
    $miRaw = trim($_POST['must_include'] ?? '');
    $mustInclude = $miRaw ? array_values(array_filter(array_map('trim', explode("\n", $miRaw)))) : [];

    $_ai_config['openai_api_key'] = trim($_POST['openai_api_key'] ?? '');
    $_ai_config['unsplash_api_key'] = trim($_POST['unsplash_api_key'] ?? '');
    $_ai_config['boards'] = $_POST['boards'] ?? [];

    // 게시판별 설정 (boards_config) — 각 게시판마다 keywords/prompt/tone 별도
    $boardsConfigInput = $_POST['boards_config'] ?? [];
    $boardsConfig = [];
    if (is_array($boardsConfigInput)) {
        foreach ($boardsConfigInput as $bid => $bcfg) {
            $kwLines = array_values(array_filter(array_map('trim', explode("\n", $bcfg['keywords'] ?? ''))));
            $bp = trim($bcfg['custom_prompt'] ?? '');
            $bt = trim($bcfg['tone'] ?? '');
            // 비어있는 board는 저장 안 함 (글로벌 설정으로 fallback)
            if (!$kwLines && !$bp && !$bt) continue;
            $boardsConfig[$bid] = [
                'keywords'      => $kwLines,
                'custom_prompt' => $bp,
                'tone'          => $bt,
            ];
        }
    }
    $_ai_config['boards_config'] = $boardsConfig;
    $_ai_config['auto_enabled'] = isset($_POST['auto_enabled']);
    $_ai_config['interval_hours'] = max(1, (int)($_POST['interval_hours'] ?? 6));
    $_ai_config['posts_per_run'] = max(1, min(10, (int)($_POST['posts_per_run'] ?? 1)));
    $_ai_config['min_length'] = max(200, min(2000, (int)($_POST['min_length'] ?? 500)));
    $_ai_config['max_length'] = max(300, min(3000, (int)($_POST['max_length'] ?? 1000)));
    $_ai_config['tone'] = $_POST['tone'] ?? 'informative';
    // 프롬프트 목록 파싱
    $rawPrompts = $_POST['prompts'] ?? [];
    $_ai_config['prompts'] = array_values(array_filter(array_map('trim', $rawPrompts)));
    $linksRaw = trim($_POST['insert_links'] ?? '');
    $_ai_config['insert_links'] = $linksRaw ? array_values(array_filter(array_map('trim', explode("\n", $linksRaw)))) : [];
    $_ai_config['keywords'] = $keywords;
    $_ai_config['must_include'] = $mustInclude;
    $_ai_config['auto_member'] = isset($_POST['auto_member']);
    $_ai_config['add_image'] = isset($_POST['add_image']);

    file_put_contents($_ai_config_file, json_encode($_ai_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다.</div>';
}

// ===== 자동 실행 시작 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_start_auto'])) {
    if (empty($_ai_config['openai_api_key'])) {
        echo '<div class="alert error">OpenRouter API 키를 먼저 설정하세요.</div>';
    } elseif (empty($_ai_config['keywords'])) {
        echo '<div class="alert error">키워드를 먼저 등록하세요.</div>';
    } elseif (empty($_ai_config['boards'])) {
        echo '<div class="alert error">게시판을 선택하세요.</div>';
    } else {
        $_ai_config['auto_mode'] = true;
        $_ai_config['last_run'] = ''; // 첫 실행 간격 무시
        file_put_contents($_ai_config_file, json_encode($_ai_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        set_time_limit(600);
        _ai_auto_run($_ai_config, $_ai_config_file);

        // config 재로드
        $_ai_config = json_decode(file_get_contents($_ai_config_file), true);
        echo '<div class="alert success">▶ 자동 실행 시작. 첫 글이 생성되었습니다.</div>';
    }
}

// ===== 자동 실행 중지 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_stop_auto'])) {
    $_ai_config['auto_mode'] = false;
    file_put_contents($_ai_config_file, json_encode($_ai_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">⏸ 자동 실행이 중지되었습니다.</div>';
}

// ===== 수동 글 생성 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_generate'])) {
    if (empty($_ai_config['openai_api_key'])) {
        echo '<div class="alert error">OpenRouter API 키를 먼저 설정하세요.</div>';
    } elseif (empty($_ai_config['keywords'])) {
        echo '<div class="alert error">키워드를 먼저 등록하세요.</div>';
    } elseif (empty($_ai_config['boards'])) {
        echo '<div class="alert error">게시판을 선택하세요.</div>';
    } else {
        echo '<div class="card"><div class="card-body"><h4>글 생성 중...</h4><pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;max-height:400px;overflow-y:auto;font-size:12px">';
        set_time_limit(600);
        ob_flush(); flush();

        _ai_auto_run($_ai_config, $_ai_config_file);

        // config 다시 읽기 (keyword_index 갱신)
        $_ai_config = json_decode(file_get_contents($_ai_config_file), true);

        echo "생성 완료!\n";
        echo '</pre></div></div>';
    }
}

$_allBoards = Board::listAll(true);

// 기본 프롬프트 텍스트
$_defaultPrompt = "다음 키워드에 대해 SEO 최적화된 {min_length}자 이상 {max_length}자 이하의 고품질 글을 작성하세요.

키워드: {keyword}

톤: {tone}

요구사항:
- 자연스러운 한국어 문장
- 키워드를 자연스럽게 2-3회 포함
- 실용적이고 유용한 정보
- 명확한 단락 구조
- HTML 태그 사용 금지";
?>

<form method="post">
    <input type="hidden" name="ai_save" value="1">

    <!-- API 키 설정 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">API 키 설정</h3>

    <div class="form-group">
        <label>OpenRouter API 키 * (ChatGPT 글 생성에 필요)</label>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="password" name="openai_api_key" id="openai_key" value="<?= htmlspecialchars($_ai_config['openai_api_key']) ?>" placeholder="sk-or-v1-..." style="flex:1">
            <button type="button" class="btn btn-sm" onclick="testApi('openai')" id="btn_test_openai">테스트</button>
            <span id="result_openai" style="font-size:13px;font-weight:600"></span>
        </div>
        <small>
            <a href="https://btg1.net/bbs/board.php?bo_table=tip1&wr_id=229&page=7" target="_blank" style="color:#2563eb;font-weight:600">OpenRouter API 키 발급방법 상세 가이드</a>
            &nbsp;|&nbsp; gpt-4o-mini 모델 사용, 월 $5~50 비용 발생
        </small>
    </div>

    <div class="form-group">
        <label>Unsplash API 키 (글에 자동으로 관련 이미지 추가, 선택사항)</label>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="password" name="unsplash_api_key" id="unsplash_key" value="<?= htmlspecialchars($_ai_config['unsplash_api_key']) ?>" placeholder="비워두면 이미지 미추가" style="flex:1">
            <button type="button" class="btn btn-sm" onclick="testApi('unsplash')" id="btn_test_unsplash">테스트</button>
            <span id="result_unsplash" style="font-size:13px;font-weight:600"></span>
        </div>
        <small>
            <a href="https://btg1.net/bbs/board.php?bo_table=tip1&wr_id=485" target="_blank" style="color:#2563eb;font-weight:600">Unsplash API 키(Access Key) 발급방법 상세 가이드</a>
            &nbsp;|&nbsp; 완전 무료, 시간당 50건
        </small>
    </div>

    <div style="padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:13px;color:#1e40af;margin-bottom:8px">
        <strong>중요:</strong> API 키를 입력한 후 반드시 아래 <strong>"설정 저장"</strong> 버튼을 먼저 누르세요. 저장 후 테스트 버튼으로 연결 상태를 확인할 수 있습니다.
    </div>

    <hr style="margin:20px 0">

    <!-- 자동 실행 설정 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">자동 실행 설정</h3>

    <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="auto_enabled" <?= $_ai_config['auto_enabled'] ? 'checked' : '' ?>>
            <strong>방문자 접속 시 자동 실행</strong>
        </label>
        <small>체크하면 사이트에 누군가 접속할 때 간격이 지났으면 자동 생성. (방문자 없으면 동작 안 함)<br>
        → 방문자 없어도 동작시키려면 아래 <strong>"🚀 자동 실행"</strong> 섹션의 버튼을 사용하세요.</small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>실행 간격</label>
            <select name="interval_hours">
                <option value="1" <?= $_ai_config['interval_hours'] == 1 ? 'selected' : '' ?>>1시간마다</option>
                <option value="3" <?= $_ai_config['interval_hours'] == 3 ? 'selected' : '' ?>>3시간마다</option>
                <option value="6" <?= $_ai_config['interval_hours'] == 6 ? 'selected' : '' ?>>6시간마다</option>
                <option value="12" <?= $_ai_config['interval_hours'] == 12 ? 'selected' : '' ?>>12시간마다</option>
                <option value="24" <?= $_ai_config['interval_hours'] == 24 ? 'selected' : '' ?>>24시간마다</option>
            </select>
        </div>
        <div class="form-group">
            <label>1회 생성할 글 수</label>
            <input type="number" name="posts_per_run" value="<?= (int)$_ai_config['posts_per_run'] ?>" min="1" max="10" style="width:80px">
            <small>최대 10개</small>
        </div>
    </div>

    <?php if ($_ai_config['last_run']): ?>
    <div style="padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#166534;margin-bottom:16px">
        마지막 실행: <strong><?= htmlspecialchars($_ai_config['last_run']) ?></strong>
        | 다음 키워드 순서: <strong><?= (int)$_ai_config['keyword_index'] + 1 ?></strong>번째
    </div>
    <?php endif; ?>

    <hr style="margin:20px 0">

    <!-- 게시판 선택 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">게시판 선택</h3>

    <div class="form-group">
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($_allBoards as $b): ?>
            <label style="display:flex;align-items:center;gap:4px;padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="boards[]" value="<?= htmlspecialchars($b['board_id']) ?>"
                    <?= in_array($b['board_id'], $_ai_config['boards']) ? 'checked' : '' ?>>
                <?= htmlspecialchars($b['title']) ?> (<?= htmlspecialchars($b['board_id']) ?>)
            </label>
            <?php endforeach; ?>
        </div>
        <small>여러 게시판을 선택하면 랜덤으로 배분됩니다.</small>
    </div>

    <hr style="margin:20px 0">

    <!-- 게시판별 맞춤 설정 -->
    <h3 style="margin:0 0 8px;font-size:16px;font-weight:600">🎯 게시판별 맞춤 설정 <small style="font-size:11px;font-weight:400;color:#64748b">(선택사항)</small></h3>
    <p style="font-size:13px;color:#64748b;margin-bottom:14px">
        체크된 게시판마다 별도의 <strong>키워드 / 톤 / 커스텀 프롬프트</strong>를 설정할 수 있습니다.<br>
        비어있는 게시판은 아래 글로벌 설정을 사용합니다. 톤이 다른 게시판(시세토론·기술분석·자유게시판 등)에 권장.
    </p>

    <?php
    $boardsConfig = $_ai_config['boards_config'] ?? [];
    $checked = $_ai_config['boards'] ?? [];
    $boardsToShow = !empty($checked) ? array_filter($_allBoards, fn($b) => in_array($b['board_id'], $checked))
                                      : $_allBoards;
    ?>
    <details style="margin-bottom:16px">
        <summary style="cursor:pointer;padding:10px 14px;background:#f1f5f9;border-radius:8px;font-weight:600;font-size:13px">
            ▸ 게시판 <?= count($boardsToShow) ?>개 펼치기 / 접기
        </summary>
        <div style="margin-top:10px;display:grid;gap:10px">
            <?php foreach ($boardsToShow as $b):
                $bid = $b['board_id'];
                $bcfg = $boardsConfig[$bid] ?? [];
                $kwText = is_array($bcfg['keywords'] ?? null) ? implode("\n", $bcfg['keywords']) : '';
                $promptText = $bcfg['custom_prompt'] ?? '';
                $toneText = $bcfg['tone'] ?? '';
                $hasConfig = $kwText || $promptText || $toneText;
            ?>
            <details style="border:1px solid #e2e8f0;border-radius:8px;background:#fff" <?= $hasConfig ? 'open' : '' ?>>
                <summary style="cursor:pointer;padding:10px 14px;font-size:13px;font-weight:600;background:linear-gradient(90deg,#f8fafc,transparent)">
                    <?= htmlspecialchars($b['title']) ?>
                    <span style="color:#94a3b8;font-size:11px;font-family:monospace;margin-left:6px">[<?= htmlspecialchars($bid) ?>]</span>
                    <?php if ($hasConfig): ?>
                    <span style="color:#059669;font-size:11px;margin-left:8px">● 맞춤 설정됨</span>
                    <?php endif; ?>
                </summary>
                <div style="padding:10px 14px;display:grid;gap:8px">
                    <div>
                        <label style="font-size:12px;color:#475569;font-weight:600">키워드 (한 줄에 하나)</label>
                        <textarea name="boards_config[<?= htmlspecialchars($bid) ?>][keywords]" rows="3"
                                  style="width:100%;resize:vertical;font-family:monospace;font-size:12px;padding:6px"
                                  placeholder="이 게시판에 사용할 키워드. 비워두면 글로벌 키워드 사용."><?= htmlspecialchars($kwText) ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:8px">
                        <div>
                            <label style="font-size:12px;color:#475569;font-weight:600">톤 (자유 텍스트)</label>
                            <input type="text" name="boards_config[<?= htmlspecialchars($bid) ?>][tone]"
                                   value="<?= htmlspecialchars($toneText) ?>"
                                   placeholder="예: 친근 캐주얼 / 전문 분석체"
                                   style="width:100%;padding:6px;font-size:12px">
                        </div>
                        <div>
                            <label style="font-size:12px;color:#475569;font-weight:600">커스텀 프롬프트 (있으면 글로벌보다 우선)</label>
                            <textarea name="boards_config[<?= htmlspecialchars($bid) ?>][custom_prompt]" rows="2"
                                      style="width:100%;resize:vertical;font-family:monospace;font-size:11px;padding:6px"
                                      placeholder="이 게시판 글의 스타일·구조를 정의. 변수: {keyword} {tone} {min_length} {max_length} {board_id}"><?= htmlspecialchars($promptText) ?></textarea>
                        </div>
                    </div>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
    </details>

    <hr style="margin:20px 0">

    <!-- 키워드 관리 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">키워드 목록 <small style="font-size:11px;font-weight:400;color:#64748b">(글로벌 — 게시판별 키워드가 없을 때 사용)</small></h3>

    <div class="form-group">
        <label>키워드 (한 줄에 하나씩)</label>
        <textarea name="keywords" rows="8" style="width:100%;resize:vertical;font-family:monospace;font-size:13px" placeholder="누리보드 설치 방법&#10;PHP 게시판 추천&#10;웹사이트 만들기&#10;SEO 최적화 팁&#10;커뮤니티 운영 노하우"><?= htmlspecialchars(implode("\n", $_ai_config['keywords'])) ?></textarea>
        <small>자동 실행 시 위에서부터 순서대로 사용되며, 마지막까지 가면 처음부터 다시 시작됩니다.</small>
    </div>

    <hr style="margin:20px 0">

    <!-- 필수 포함 문구 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">필수 포함 문구</h3>

    <div class="form-group">
        <label>반드시 포함할 문구 (한 줄에 하나씩)</label>
        <textarea name="must_include" rows="4" style="width:100%;resize:vertical;font-family:monospace;font-size:13px" placeholder="누리보드는 SEO에 최적화된 CMS입니다.&#10;자세한 내용은 nuribd.com에서 확인하세요."><?= htmlspecialchars(implode("\n", $_ai_config['must_include'])) ?></textarea>
        <small>AI가 생성하는 글에 이 문구들을 자연스럽게 포함시킵니다. 홍보 문구, 링크 안내 등에 활용하세요.</small>
    </div>

    <hr style="margin:20px 0">

    <!-- 커스텀 프롬프트 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">커스텀 프롬프트 목록</h3>
    <p style="font-size:13px;color:#64748b;margin-bottom:12px">프롬프트를 여러 개 등록하면 글 생성 시 랜덤으로 하나가 선택됩니다. 비워두면 기본 프롬프트가 사용됩니다.</p>

    <div id="promptList">
        <?php
        $prompts = $_ai_config['prompts'] ?? [];
        if (empty($prompts)) $prompts = [''];
        foreach ($prompts as $pi => $pv):
        ?>
        <div class="prompt-item" style="margin-bottom:12px;position:relative;border:1px solid #e2e8f0;border-radius:8px;padding:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <strong style="font-size:13px;color:#475569">프롬프트 #<span class="prompt-num"><?= $pi + 1 ?></span></strong>
                <button type="button" class="btn btn-sm btn-danger" onclick="removePrompt(this)" style="padding:2px 8px;font-size:11px">삭제</button>
            </div>
            <textarea name="prompts[]" rows="8" style="width:100%;resize:vertical;font-family:monospace;font-size:13px" placeholder="<?= htmlspecialchars($_defaultPrompt) ?>"><?= htmlspecialchars($pv) ?></textarea>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn" onclick="addPrompt()" style="margin-bottom:8px">+ 프롬프트 추가</button>
    <div><small>사용 가능한 변수: <code>{keyword}</code> <code>{min_length}</code> <code>{max_length}</code> <code>{tone}</code></small></div>

    <hr style="margin:20px 0">

    <!-- 삽입 링크 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">삽입 링크 (선택)</h3>

    <div class="form-group">
        <label>본문에 삽입할 링크 (한 줄에 하나씩, URL | 표시텍스트)</label>
        <textarea name="insert_links" rows="4" style="width:100%;resize:vertical;font-family:monospace;font-size:13px" placeholder="https://nuribd.com | 누리보드 공식 사이트&#10;https://nuribd.com/market | 플러그인 마켓"><?= htmlspecialchars(implode("\n", $_ai_config['insert_links'] ?? [])) ?></textarea>
        <small>비워두면 링크 없이 글이 생성됩니다. AI가 본문 중간에 자연스럽게 링크를 삽입합니다.</small>
    </div>

    <hr style="margin:20px 0">

    <!-- 글 작성 옵션 -->
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:600">글 작성 옵션</h3>

    <div class="form-row">
        <div class="form-group">
            <label>글 길이 최소</label>
            <input type="number" name="min_length" value="<?= (int)$_ai_config['min_length'] ?>" min="200" max="2000" style="width:100px"> 자
        </div>
        <div class="form-group">
            <label>글 길이 최대</label>
            <input type="number" name="max_length" value="<?= (int)$_ai_config['max_length'] ?>" min="300" max="3000" style="width:100px"> 자
        </div>
    </div>

    <div class="form-group">
        <label>글의 톤</label>
        <select name="tone">
            <option value="informative" <?= $_ai_config['tone'] === 'informative' ? 'selected' : '' ?>>정보성 (정보공유, 튜토리얼)</option>
            <option value="promotional" <?= $_ai_config['tone'] === 'promotional' ? 'selected' : '' ?>>홍보성 (제품 소개)</option>
            <option value="casual" <?= $_ai_config['tone'] === 'casual' ? 'selected' : '' ?>>캐주얼 (블로그 스타일)</option>
            <option value="formal" <?= $_ai_config['tone'] === 'formal' ? 'selected' : '' ?>>격식있음 (뉴스 스타일)</option>
        </select>
    </div>

    <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="add_image" <?= $_ai_config['add_image'] ? 'checked' : '' ?>>
            Unsplash 이미지 자동 추가
        </label>
    </div>

    <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="auto_member" <?= $_ai_config['auto_member'] ? 'checked' : '' ?>>
            가상 회원으로 작성
        </label>
        <small>해제하면 관리자(ID 1) 이름으로 작성됩니다.</small>
    </div>

    <button type="submit" class="btn btn-primary">설정 저장</button>

<hr style="margin:24px 0">

<!-- 자동 실행 (관리자 탭용) -->
<h3 style="margin:0 0 16px;font-size:16px;font-weight:600">🚀 자동 실행</h3>

<div style="padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:13px;color:#1e40af;margin-bottom:12px">
    💡 아래 실행 버튼들도 위 폼의 키워드·게시판·옵션을 자동으로 함께 저장합니다.
</div>

<?php
$autoMode = !empty($_ai_config['auto_mode']);
$lastRunTs = $_ai_config['last_run'] ? strtotime($_ai_config['last_run']) : 0;
$intervalSec = max(1, (int)($_ai_config['interval_hours'] ?? 6)) * 3600;
?>

<?php if (!$autoMode): ?>
    <div style="background:#fef3c7;border:1px solid #fde047;border-radius:8px;padding:16px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
                <strong style="color:#854d0e;font-size:14px">▶ 자동 실행</strong>
                <div style="font-size:12px;color:#78716c;margin-top:4px">
                    시작하면 첫 글이 바로 생성되고, <?= (int)($_ai_config['interval_hours'] ?? 6) ?>시간마다 자동으로 글이 생성됩니다.<br>
                    ⚠️ 이 탭을 열어둔 상태에서만 작동합니다. (탭 닫으면 중지)
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" name="ai_start_auto" value="1" id="ai-start-btn"
                        class="btn btn-primary" style="background:#16a34a;border-color:#16a34a"
                        onclick="this.innerText='⏳ 첫 글 생성 중... (30~60초)';">▶ 자동 실행 시작</button>
                <button type="submit" name="ai_generate" value="1" id="ai-run-btn" class="btn"
                        onclick="this.innerText='⏳ 생성 중...';">⚡ 1번만 실행</button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div style="background:linear-gradient(135deg,#dcfce7 0%,#bbf7d0 100%);border:2px solid #16a34a;border-radius:10px;padding:16px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:12px;height:12px;background:#16a34a;border-radius:50%;animation:aiPulse 1.5s infinite"></div>
                    <strong style="color:#166534;font-size:15px">🔄 자동 실행 중</strong>
                </div>
                <div style="font-size:13px;color:#166534;margin-top:8px">
                    다음 생성: <strong style="font-size:16px" id="ai-countdown">계산 중...</strong>
                </div>
                <div style="font-size:11px;color:#15803d;margin-top:4px" id="ai-auto-status">
                    마지막 실행: <?= $lastRunTs ? date('H:i:s', $lastRunTs) : '없음' ?> · 간격 <?= (int)($_ai_config['interval_hours'] ?? 6) ?>시간
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" name="ai_stop_auto" value="1" class="btn"
                        style="background:#dc2626;color:white;border-color:#dc2626;white-space:nowrap">⏸ 자동 실행 중지</button>
            </div>
        </div>
    </div>

    <style>
    @keyframes aiPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.3); }
    }
    </style>

    <script>
    (function() {
        var countdownEl = document.getElementById('ai-countdown');
        if (!countdownEl) return;

        var lastRunTs = <?= $lastRunTs ?: 0 ?>;
        var intervalSec = <?= $intervalSec ?>;
        var nextRunTs = lastRunTs ? lastRunTs + intervalSec : Math.floor(Date.now() / 1000);

        function aiUpdateCountdown() {
            var now = Math.floor(Date.now() / 1000);
            var remaining = nextRunTs - now;

            if (remaining <= 0) {
                countdownEl.innerText = '⏳ 지금 생성 중...';
                var statusEl = document.getElementById('ai-auto-status');
                if (statusEl) statusEl.innerText = '글 생성 중입니다. 30~60초 기다려주세요...';
                // 메인 form 에 ai_generate flag 를 동적으로 추가하고 submit
                var saveForm = document.querySelector('form[method="post"] input[name="ai_save"]');
                if (saveForm && saveForm.form) {
                    var trig = document.createElement('input');
                    trig.type = 'hidden';
                    trig.name = 'ai_generate';
                    trig.value = '1';
                    saveForm.form.appendChild(trig);
                    saveForm.form.submit();
                }
                return;
            }

            var hours = Math.floor(remaining / 3600);
            var min = Math.floor((remaining % 3600) / 60);
            var sec = remaining % 60;
            var text = '';
            if (hours > 0) text += hours + '시간 ';
            text += min + '분 ' + String(sec).padStart(2, '0') + '초 후';
            countdownEl.innerText = text;
            setTimeout(aiUpdateCountdown, 1000);
        }

        aiUpdateCountdown();
    })();
    </script>
<?php endif; ?>

<hr style="margin:24px 0">

<!-- 수동 실행 (간단) -->
<h3 style="margin:0 0 16px;font-size:16px;font-weight:600">⚡ 수동 실행</h3>

<p style="font-size:13px;color:#64748b;margin-bottom:12px">설정된 키워드 목록에서 다음 순서의 키워드로 즉시 글을 생성합니다.<br>
키워드 변경사항도 함께 저장된 후 실행됩니다.</p>
<button type="submit" name="ai_generate" value="1" class="btn btn-primary" style="padding:10px 24px"
        onclick="return confirm('키워드 목록에서 다음 순서대로 글을 생성합니다. 실행할까요?')">
    지금 즉시 실행
</button>

</form>
<!-- ↑ main form 종료 (line 135 에서 시작) — 키워드/게시판/모든 옵션을 포함 -->


<div style="margin-top:24px;padding:20px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
    <h4 style="font-size:15px;font-weight:700;margin-bottom:12px">처음 사용하시나요? 설정 순서를 따라하세요</h4>
    <ol style="font-size:13px;color:#334155;line-height:2.2;padding-left:20px;margin:0">
        <li><strong>API 키 발급</strong> - 위 가이드 링크를 보고 OpenRouter API 키를 발급받으세요. (Unsplash는 선택)</li>
        <li><strong>API 키 입력 후 "설정 저장" 클릭</strong> - 저장해야 테스트가 가능합니다.</li>
        <li><strong>테스트 버튼으로 연결 확인</strong> - "성공"이 뜨면 정상입니다.</li>
        <li><strong>게시판 선택</strong> - 글이 올라갈 게시판을 체크하세요.</li>
        <li><strong>키워드 등록</strong> - AI가 글을 쓸 주제를 한 줄에 하나씩 입력하세요.</li>
        <li><strong>프롬프트 작성</strong> - AI에게 어떤 스타일로 글을 쓸지 지시하는 문장입니다. 비워두면 기본 프롬프트가 사용됩니다.</li>
        <li><strong>설정 저장 후 "지금 즉시 실행"으로 테스트</strong> - 글이 잘 생성되는지 확인하세요.</li>
        <li><strong>자동 실행 활성화</strong> - 확인이 끝나면 자동 실행을 켜세요.</li>
    </ol>
</div>

<div style="margin-top:12px;padding:20px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0">
    <h4 style="font-size:15px;font-weight:700;margin-bottom:12px;color:#166534">키워드, 프롬프트 작성이 어려우신가요?</h4>
    <p style="font-size:13px;color:#166534;line-height:1.8;margin:0">
        ChatGPT에게 도움을 요청하세요! 예를 들어:<br>
        <code style="background:#dcfce7;padding:2px 6px;border-radius:4px">"내 사이트는 ○○ 주제인데, SEO에 좋은 키워드 20개만 추천해줘"</code><br>
        <code style="background:#dcfce7;padding:2px 6px;border-radius:4px">"○○ 관련 블로그 글을 자연스럽게 써주는 프롬프트를 만들어줘"</code><br>
        이렇게 물어보면 바로 사용할 수 있는 키워드와 프롬프트를 받을 수 있습니다.
    </p>
</div>

<div style="margin-top:12px;padding:16px;background:#fef3c7;border-radius:8px;border:1px solid #fde68a">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px;color:#92400e">주의사항</h4>
    <ul style="font-size:13px;color:#78350f;line-height:2;padding-left:20px;margin:0">
        <li>OpenAI API 사용 시 비용이 발생합니다. (글 1건당 약 $0.01~0.05)</li>
        <li>자동 실행은 관리자가 사이트에 접속할 때 간격을 체크하여 실행됩니다.</li>
        <li>생성된 글은 검토 후 필요하면 수정하세요.</li>
        <li>과도한 자동 글 생성은 검색엔진 평판에 영향을 줄 수 있습니다.</li>
    </ul>
</div>

<script>
function addPrompt(){
    var list=document.getElementById('promptList');
    var count=list.querySelectorAll('.prompt-item').length+1;
    var div=document.createElement('div');
    div.className='prompt-item';
    div.style='margin-bottom:12px;position:relative;border:1px solid #e2e8f0;border-radius:8px;padding:12px';
    div.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><strong style="font-size:13px;color:#475569">프롬프트 #<span class="prompt-num">'+count+'</span></strong><button type="button" class="btn btn-sm btn-danger" onclick="removePrompt(this)" style="padding:2px 8px;font-size:11px">삭제</button></div><textarea name="prompts[]" rows="8" style="width:100%;resize:vertical;font-family:monospace;font-size:13px" placeholder="프롬프트를 입력하세요..."></textarea>';
    list.appendChild(div);
    div.querySelector('textarea').focus();
}
function removePrompt(btn){
    var item=btn.closest('.prompt-item');
    var list=document.getElementById('promptList');
    if(list.querySelectorAll('.prompt-item').length<=1){alert('최소 1개의 프롬프트가 필요합니다.');return;}
    item.remove();
    list.querySelectorAll('.prompt-num').forEach(function(s,i){s.textContent=i+1;});
}
function testApi(type){
    var btn=document.getElementById('btn_test_'+type);
    var result=document.getElementById('result_'+type);
    btn.disabled=true;btn.textContent='확인 중...';
    result.textContent='';

    if(type==='openai'){
        var key=document.getElementById('openai_key').value.trim();
        if(!key){result.textContent='키를 입력하세요';result.style.color='#dc2626';btn.disabled=false;btn.textContent='테스트';return;}
        fetch('https://openrouter.ai/api/v1/models',{headers:{'Authorization':'Bearer '+key}})
        .then(function(r){
            btn.disabled=false;btn.textContent='테스트';
            result.textContent=r.ok?'성공':'실패';
            result.style.color=r.ok?'#059669':'#dc2626';
        }).catch(function(){btn.disabled=false;btn.textContent='테스트';result.textContent='실패';result.style.color='#dc2626';});
    }

    if(type==='unsplash'){
        var key=document.getElementById('unsplash_key').value.trim();
        if(!key){result.textContent='키를 입력하세요';result.style.color='#dc2626';btn.disabled=false;btn.textContent='테스트';return;}
        fetch('https://api.unsplash.com/search/photos?query=test&per_page=1&client_id='+encodeURIComponent(key),{headers:{'Accept-Version':'v1'}})
        .then(function(r){
            btn.disabled=false;btn.textContent='테스트';
            result.textContent=r.ok?'성공':'실패';
            result.style.color=r.ok?'#059669':'#dc2626';
        }).catch(function(){btn.disabled=false;btn.textContent='테스트';result.textContent='실패';result.style.color='#dc2626';});
    }
}
</script>

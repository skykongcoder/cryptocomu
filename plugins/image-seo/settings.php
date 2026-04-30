<?php
require_once __DIR__ . '/../_openrouter_models.php';
/**
 * 이미지 SEO 자동 생성 - 설정 페이지
 */

$_iseoConfigFile = (defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__, 2)) . '/data/image-seo/config.json';
if (!is_dir(dirname($_iseoConfigFile))) @mkdir(dirname($_iseoConfigFile), 0755, true);

$_iseoRaw    = file_exists($_iseoConfigFile) ? json_decode(file_get_contents($_iseoConfigFile), true) : [];
$_iseoConfig = array_merge([
    'target_board'   => '',
    'openai_api_key' => '',
], is_array($_iseoRaw) ? $_iseoRaw : []);

$_iseoMsg = '';

// ---- 설정 저장 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iseo_save'])) {
    $_iseoConfig['openai_api_key'] = trim($_POST['openai_api_key'] ?? '');
    // board_id는 string slug — int 캐스팅 절대 금지
    $_iseoConfig['target_board'] = trim($_POST['target_board'] ?? '');
    file_put_contents($_iseoConfigFile, json_encode($_iseoConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $_iseoMsg = '설정이 저장되었습니다.';
}

$_iseoBoardList = DB::fetchAll(
    "SELECT board_id, title FROM " . DB::getPrefix() . "boards WHERE is_active = 1 ORDER BY sort_order ASC"
);

$_iseoSiteUrl = rtrim(nb_setting('site_url', ''), '/');

// 생성된 글 목록 (target_board 설정된 경우)
$_iseoPosts = [];
if (!empty($_iseoConfig['target_board'])) {
    $prefix = DB::getPrefix();
    $_iseoPosts = DB::fetchAll(
        "SELECT id, title, created_at FROM {$prefix}posts WHERE board_id = ? ORDER BY id DESC LIMIT 50",
        [$_iseoConfig['target_board']]
    );
}
?>
<style>
.iseo-wrap { max-width: 860px; font-family: inherit; }
.iseo-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.iseo-section h3 { margin: 0 0 18px; font-size: 15px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.iseo-section h3 svg { flex-shrink: 0; }
.iseo-label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.iseo-input { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.iseo-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px #dcfce7; }
.iseo-select { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background: #fff; box-sizing: border-box; }
.iseo-hint { font-size: 12px; color: #94a3b8; margin-top: 4px; }
.iseo-row { margin-bottom: 16px; }
.iseo-btn { padding: 9px 20px; border: none; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; }
.iseo-btn-primary { background: #22c55e; color: #fff; }
.iseo-btn-primary:hover { background: #16a34a; }
.iseo-btn-danger { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
.iseo-btn-danger:hover { background: #fecaca; }
.iseo-btn-sm { padding: 5px 12px; font-size: 12px; }
.iseo-msg { padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.iseo-msg.error { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }
.iseo-api-row { display: flex; gap: 8px; }
.iseo-api-row .iseo-input { flex: 1; }
.iseo-result { padding: 8px 12px; border-radius: 6px; font-size: 12px; margin-top: 8px; display: none; }
.iseo-result.ok  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.iseo-result.err { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
.iseo-dropzone { border: 2px dashed #d1fae5; border-radius: 8px; padding: 32px; text-align: center; cursor: pointer; color: #94a3b8; font-size: 13px; transition: border-color .2s; }
.iseo-dropzone:hover, .iseo-dropzone.drag { border-color: #22c55e; color: #16a34a; }
.iseo-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; margin-top: 12px; }
.iseo-preview-grid img { width: 100%; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }
.iseo-check-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151; margin-bottom: 12px; }
.iseo-check-row input[type=checkbox] { width: 16px; height: 16px; accent-color: #22c55e; }
.iseo-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.iseo-table th { background: #f8fafc; padding: 9px 12px; text-align: left; font-weight: 500; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.iseo-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
.iseo-table tr:last-child td { border-bottom: none; }
.iseo-table a { color: #22c55e; text-decoration: none; }
.iseo-table a:hover { text-decoration: underline; }
.iseo-empty { text-align: center; color: #94a3b8; padding: 24px; font-size: 13px; }
.iseo-gen-result { padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-top: 12px; display: none; }
.iseo-gen-result.ok  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.iseo-gen-result.err { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
</style>

<div class="iseo-wrap">

<?php if ($_iseoMsg): ?>
<div class="iseo-msg"><?= htmlspecialchars($_iseoMsg) ?></div>
<?php endif; ?>

<!-- ===== 1. 기본 설정 ===== -->
<div class="iseo-section">
    <h3>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
        기본 설정
    </h3>
    <form method="POST">
        <input type="hidden" name="iseo_save" value="1">

        <div class="iseo-row">
            <label class="iseo-label">이미지 SEO 전용 게시판</label>
            <select name="target_board" class="iseo-select" autocomplete="off">
                <option value="">-- 게시판 선택 --</option>
                <?php foreach ($_iseoBoardList as $b): ?>
                <option value="<?= htmlspecialchars($b['board_id']) ?>"
                    <?= $b['board_id'] === $_iseoConfig['target_board'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['title']) ?> (<?= htmlspecialchars($b['board_id']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <p class="iseo-hint">이미지 SEO 글이 발행될 전용 게시판을 선택하세요. 별도 게시판을 미리 만들어두는 것을 권장합니다.</p>
        </div>

        <div class="iseo-row">
            <label class="iseo-label">OpenRouter API 키 (선택)</label>
            <div class="iseo-api-row">
                <input type="text" id="openai_api_key" name="openai_api_key" class="iseo-input"
                    value="<?= htmlspecialchars($_iseoConfig['openai_api_key']) ?>"
                    placeholder="sk-or-v1-..." autocomplete="off">
                <button type="button" class="iseo-btn iseo-btn-primary" onclick="iseoTestKey()">테스트</button>
            </div>
            <div id="iseo-key-result" class="iseo-result"></div>
            <p class="iseo-hint">입력 시 글 생성 시 AI 설명글을 자동으로 생성합니다. 비워두면 기본 설명이 삽입됩니다.</p>
        </div>

        <button type="submit" class="iseo-btn iseo-btn-primary">설정 저장</button>
    </form>
</div>

<!-- ===== 2. 이미지 SEO 글 생성 ===== -->
<div class="iseo-section">
    <h3>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        이미지 SEO 글 생성
    </h3>

    <?php if (empty($_iseoConfig['target_board'])): ?>
    <p style="color:#94a3b8;font-size:13px;">위에서 전용 게시판을 먼저 선택하고 저장하세요.</p>
    <?php else: ?>

    <div class="iseo-row">
        <label class="iseo-label">타겟 키워드</label>
        <input type="text" id="iseo_keyword" class="iseo-input" placeholder="예: 누리보드 플러그인 설치" autocomplete="off">
        <p class="iseo-hint">이미지 파일명과 alt 텍스트에 이 키워드가 자동 적용됩니다.</p>
    </div>

    <div class="iseo-row">
        <label class="iseo-label">이미지 업로드 (여러 장 선택 가능)</label>
        <div class="iseo-dropzone" id="iseo-dropzone" onclick="document.getElementById('iseo_images').click()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:8px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div>클릭하거나 이미지를 드래그하세요</div>
            <div style="font-size:11px;margin-top:4px;">JPG, PNG, WEBP, GIF 지원</div>
        </div>
        <input type="file" id="iseo_images" multiple accept="image/*" style="display:none" onchange="iseoPreview(this)">
        <div class="iseo-preview-grid" id="iseo-preview"></div>
    </div>

    <?php if (!empty($_iseoConfig['openai_api_key'])): ?>
    <div class="iseo-check-row">
        <input type="checkbox" id="iseo_use_ai" checked>
        <label for="iseo_use_ai">AI 설명글 자동 생성 (OpenAI)</label>
    </div>
    <?php endif; ?>

    <button type="button" class="iseo-btn iseo-btn-primary" onclick="iseoGenerate()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        SEO 글 생성
    </button>

    <div id="iseo-gen-result" class="iseo-gen-result"></div>

    <?php endif; ?>
</div>

<!-- ===== 3. 생성된 글 목록 ===== -->
<div class="iseo-section">
    <h3>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        생성된 글 목록
        <span style="font-size:12px;font-weight:400;color:#94a3b8;margin-left:4px;">(최근 50개)</span>
    </h3>

    <?php if (empty($_iseoPosts)): ?>
    <div class="iseo-empty">아직 생성된 글이 없습니다.</div>
    <?php else: ?>
    <table class="iseo-table">
        <thead>
            <tr>
                <th>키워드(제목)</th>
                <th style="width:140px;">생성일</th>
                <th style="width:100px;">바로가기</th>
                <th style="width:60px;"></th>
            </tr>
        </thead>
        <tbody id="iseo-post-list">
        <?php foreach ($_iseoPosts as $p): ?>
        <tr id="iseo-row-<?= (int)$p['id'] ?>">
            <td><?= htmlspecialchars($p['title']) ?></td>
            <td style="color:#94a3b8;"><?= htmlspecialchars(substr($p['created_at'], 0, 16)) ?></td>
            <td>
                <a href="<?= htmlspecialchars($_iseoSiteUrl . '/board/' . $_iseoConfig['target_board'] . '/' . $p['id']) ?>"
                   target="_blank">보기</a>
            </td>
            <td>
                <button class="iseo-btn iseo-btn-danger iseo-btn-sm"
                    onclick="iseoDelete(<?= (int)$p['id'] ?>, this)">삭제</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div><!-- .iseo-wrap -->

<script>
// ---- API 키 테스트 (브라우저 직접 호출) ----
function iseoTestKey() {
    var key = document.getElementById('openai_api_key').value.trim();
    var res = document.getElementById('iseo-key-result');
    if (!key) { res.textContent = 'API 키를 입력하세요'; res.className = 'iseo-result err'; res.style.display = 'block'; return; }
    res.textContent = '확인 중...'; res.className = 'iseo-result'; res.style.display = 'block';
    fetch('https://openrouter.ai/api/v1/models', {
        headers: { 'Authorization': 'Bearer ' + key }
    }).then(function(r) {
        if (r.ok) { res.textContent = 'API 키가 유효합니다'; res.className = 'iseo-result ok'; }
        else       { res.textContent = 'API 키가 올바르지 않습니다 (HTTP ' + r.status + ')'; res.className = 'iseo-result err'; }
        res.style.display = 'block';
    }).catch(function() {
        res.textContent = '네트워크 오류가 발생했습니다'; res.className = 'iseo-result err'; res.style.display = 'block';
    });
}

// ---- 이미지 미리보기 ----
function iseoPreview(input) {
    var grid = document.getElementById('iseo-preview');
    grid.innerHTML = '';
    Array.from(input.files).forEach(function(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.createElement('img');
            img.src = e.target.result;
            grid.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// ---- 드래그앤드롭 ----
(function() {
    var dz = document.getElementById('iseo-dropzone');
    if (!dz) return;
    dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.classList.add('drag'); });
    dz.addEventListener('dragleave', function() { dz.classList.remove('drag'); });
    dz.addEventListener('drop', function(e) {
        e.preventDefault(); dz.classList.remove('drag');
        var input = document.getElementById('iseo_images');
        input.files = e.dataTransfer.files;
        iseoPreview(input);
    });
})();

// ---- 글 생성 ----
function iseoGenerate() {
    var keyword = document.getElementById('iseo_keyword').value.trim();
    var files   = document.getElementById('iseo_images').files;
    var result  = document.getElementById('iseo-gen-result');
    var useAi   = document.getElementById('iseo_use_ai');

    if (!keyword) { result.textContent = '키워드를 입력하세요'; result.className = 'iseo-gen-result err'; result.style.display = 'block'; return; }
    if (!files || files.length === 0) { result.textContent = '이미지를 1장 이상 업로드하세요'; result.className = 'iseo-gen-result err'; result.style.display = 'block'; return; }

    result.textContent = '글 생성 중...'; result.className = 'iseo-gen-result'; result.style.display = 'block';

    var fd = new FormData();
    fd.append('action', 'create_post');
    fd.append('keyword', keyword);
    if (useAi && useAi.checked) fd.append('use_ai', '1');
    for (var i = 0; i < files.length; i++) {
        fd.append('images[]', files[i]);
    }

    fetch('/admin/plugin/image-seo/api', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                result.innerHTML = data.count + '개 글이 생성되었습니다. <a href="' + data.first_url + '" target="_blank" style="color:#15803d;font-weight:600;">첫 번째 글 확인하기</a>';
                result.className = 'iseo-gen-result ok';
                // 목록에 추가 (각 이미지별 글)
                if (data.post_ids) {
                    data.post_ids.slice().reverse().forEach(function(pid, i) {
                        var realIdx = data.post_ids.length - i;
                        var url = data.first_url.replace(/\/\d+$/, '/' + pid);
                        addPostRow(pid, keyword + ' ' + realIdx, url);
                    });
                }
                // 입력 초기화
                document.getElementById('iseo_keyword').value = '';
                document.getElementById('iseo_images').value = '';
                document.getElementById('iseo-preview').innerHTML = '';
            } else {
                result.textContent = data.message || '오류가 발생했습니다';
                result.className = 'iseo-gen-result err';
            }
            result.style.display = 'block';
        })
        .catch(function() {
            result.textContent = '네트워크 오류가 발생했습니다'; result.className = 'iseo-gen-result err'; result.style.display = 'block';
        });
}

// ---- 목록에 행 추가 ----
function addPostRow(postId, keyword, url) {
    var tbody = document.getElementById('iseo-post-list');
    if (!tbody) return;
    var now = new Date();
    var dateStr = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    var tr = document.createElement('tr');
    tr.id = 'iseo-row-' + postId;
    tr.innerHTML =
        '<td>' + escHtml(keyword) + '</td>' +
        '<td style="color:#94a3b8;">' + dateStr + '</td>' +
        '<td><a href="' + escHtml(url) + '" target="_blank">보기</a></td>' +
        '<td><button class="iseo-btn iseo-btn-danger iseo-btn-sm" onclick="iseoDelete(' + postId + ', this)">삭제</button></td>';
    tbody.insertBefore(tr, tbody.firstChild);
}

// ---- 글 삭제 ----
function iseoDelete(postId, btn) {
    if (!confirm('이 글을 삭제하시겠습니까?')) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'delete_post');
    fd.append('post_id', postId);
    fetch('/admin/plugin/image-seo/api', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var row = document.getElementById('iseo-row-' + postId);
                if (row) row.remove();
            } else {
                alert(data.message || '삭제 실패');
                btn.disabled = false;
            }
        })
        .catch(function() { alert('네트워크 오류'); btn.disabled = false; });
}

function pad(n) { return n < 10 ? '0' + n : n; }
function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php
/**
 * NuriBoard 관리자 - 띠공지 관리
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_ticker') {
        $fields = [
            'ticker_enabled'    => $_POST['ticker_enabled'] ?? '0',
            'ticker_text'       => $_POST['ticker_text'] ?? '',
            'ticker_bg_color'   => $_POST['ticker_bg_color'] ?? '#1e293b',
            'ticker_text_color' => $_POST['ticker_text_color'] ?? '#e2e8f0',
            'ticker_effect'     => $_POST['ticker_effect'] ?? 'scroll-left',
        ];
        foreach ($fields as $key => $value) {
            $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                DB::update("{$prefix}settings", ['setting_value' => $value], 'setting_key = ?', [$key]);
            } else {
                DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $value]);
            }
        }
        AdminLog::write('ticker_save', '', 0, '띠공지 설정 변경');
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

adminHeader('ticker');
?>

<div class="page-header">
    <h1>띠공지 관리</h1>
</div>

<form onsubmit="saveTicker(event)">
    <div class="card">
        <div class="card-header">
            <h2>띠공지 설정</h2>
        </div>
        <div class="card-body">

            <!-- ON/OFF 토글 -->
            <div class="form-group">
                <label>표시 여부</label>
                <div class="toggle-switch">
                    <input type="checkbox" id="tickerEnabled" <?= nb_setting('ticker_enabled') === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider" onclick="document.getElementById('tickerEnabled').click()"></span>
                    <span class="toggle-label" id="toggleLabel"><?= nb_setting('ticker_enabled') === '1' ? 'ON' : 'OFF' ?></span>
                </div>
            </div>

            <!-- 공지 내용 -->
            <div class="form-group">
                <label>공지 내용</label>
                <textarea id="tickerText" rows="3" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;resize:vertical"><?= nb_e(nb_setting('ticker_text')) ?></textarea>
                <small>메인 페이지 상단에 표시될 공지 내용을 입력하세요.</small>
            </div>

            <!-- 색상 설정 -->
            <div class="form-group">
                <label>배경 색상</label>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <span class="nb-color-preview" id="tickerBgPreview" style="background:<?= nb_e(nb_setting('ticker_bg_color', '#1e293b')) ?>"></span>
                    <span style="font-size:13px;color:#64748b" id="tickerBgLabel"><?= nb_e(nb_setting('ticker_bg_color', '#1e293b')) ?></span>
                </div>
                <input type="hidden" id="tickerBgColor" value="<?= nb_e(nb_setting('ticker_bg_color', '#1e293b')) ?>">
                <div id="tickerBgColor_palette"></div>
            </div>
            <div class="form-group">
                <label>글자 색상</label>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <span class="nb-color-preview" id="tickerTextPreview" style="background:<?= nb_e(nb_setting('ticker_text_color', '#e2e8f0')) ?>"></span>
                    <span style="font-size:13px;color:#64748b" id="tickerTextLabel"><?= nb_e(nb_setting('ticker_text_color', '#e2e8f0')) ?></span>
                </div>
                <input type="hidden" id="tickerTextColor" value="<?= nb_e(nb_setting('ticker_text_color', '#e2e8f0')) ?>">
                <div id="tickerTextColor_palette"></div>
            </div>

            <!-- 효과 선택 -->
            <div class="form-group">
                <label>애니메이션 효과</label>
                <select id="tickerEffect" style="width:100%;max-width:300px;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px" onchange="updatePreview()">
                    <option value="none" <?= nb_setting('ticker_effect') === 'none' ? 'selected' : '' ?>>정적 표시 (효과 없음)</option>
                    <option value="scroll-left" <?= nb_setting('ticker_effect', 'scroll-left') === 'scroll-left' ? 'selected' : '' ?>>왼쪽으로 흐르기 ←</option>
                    <option value="scroll-right" <?= nb_setting('ticker_effect') === 'scroll-right' ? 'selected' : '' ?>>오른쪽으로 흐르기 →</option>
                    <option value="flash" <?= nb_setting('ticker_effect') === 'flash' ? 'selected' : '' ?>>깜빡이(플래시) 효과</option>
                    <option value="wave" <?= nb_setting('ticker_effect') === 'wave' ? 'selected' : '' ?>>물결 효과</option>
                </select>
            </div>

        </div>
    </div>

    <!-- 미리보기 -->
    <div class="card">
        <div class="card-header">
            <h2>미리보기</h2>
        </div>
        <div class="card-body" style="padding:0">
            <div id="tickerPreview" style="overflow:hidden;height:40px;display:flex;align-items:center;border-radius:0 0 12px 12px">
                <div style="padding:0 20px;display:flex;align-items:center;width:100%;gap:12px;height:100%">
                    <span style="font-weight:700;flex-shrink:0">[공지]</span>
                    <div id="previewContent" style="flex:1;overflow:hidden;white-space:nowrap"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 저장 버튼 -->
    <div style="margin-top:20px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary btn-lg">💾 저장</button>
    </div>
</form>

<style>
.toggle-switch{display:inline-flex!important;align-items:center;gap:12px;cursor:pointer;user-select:none}
.toggle-switch input{display:none}
.toggle-slider{position:relative;width:52px;height:28px;background:#cbd5e1;border-radius:28px;transition:background .25s}
.toggle-slider::after{content:'';position:absolute;top:3px;left:3px;width:22px;height:22px;background:#fff;border-radius:50%;transition:transform .25s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.toggle-switch input:checked+.toggle-slider{background:#2563eb}
.toggle-switch input:checked+.toggle-slider::after{transform:translateX(24px)}
.toggle-label{font-size:14px;font-weight:600;color:#475569}

/* 미리보기 애니메이션 */
@keyframes previewScrollLeft{0%{transform:translateX(100%)}100%{transform:translateX(-100%)}}
@keyframes previewScrollRight{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
@keyframes previewFlash{0%,100%{opacity:1}50%{opacity:0.2}}
@keyframes previewWave{0%,100%{transform:translateY(0)}25%{transform:translateY(-4px)}75%{transform:translateY(4px)}}
</style>

<script>
document.getElementById('tickerEnabled').addEventListener('change', function(){
    document.getElementById('toggleLabel').textContent = this.checked ? 'ON' : 'OFF';
});

document.getElementById('tickerText').addEventListener('input', function(){ updatePreview(); });

function updatePreview(){
    var preview = document.getElementById('tickerPreview');
    var content = document.getElementById('previewContent');
    var text = document.getElementById('tickerText').value || '띠공지 미리보기 텍스트입니다.';
    var bg = document.getElementById('tickerBgColor').value;
    var color = document.getElementById('tickerTextColor').value;
    var effect = document.getElementById('tickerEffect').value;

    preview.style.background = bg;
    preview.style.color = color;

    // 효과별 렌더링
    if(effect === 'wave'){
        var chars = text.split('').map(function(c, i){
            return '<span style="display:inline-block;animation:previewWave 2s ease-in-out infinite;animation-delay:'+((i*0.08)%1)+'s">' + c.replace(/ /,'&nbsp;') + '</span>';
        }).join('');
        content.innerHTML = chars;
        content.style.animation = '';
        content.style.whiteSpace = 'nowrap';
    } else if(effect === 'flash'){
        content.textContent = text;
        content.style.animation = 'previewFlash 1.5s ease-in-out infinite';
        content.style.whiteSpace = 'nowrap';
    } else if(effect === 'scroll-left'){
        content.innerHTML = '<span style="display:inline-block;animation:previewScrollLeft 10s linear infinite">' + escHtml(text) + '</span>';
        content.style.animation = '';
        content.style.whiteSpace = 'nowrap';
    } else if(effect === 'scroll-right'){
        content.innerHTML = '<span style="display:inline-block;animation:previewScrollRight 10s linear infinite">' + escHtml(text) + '</span>';
        content.style.animation = '';
        content.style.whiteSpace = 'nowrap';
    } else {
        content.textContent = text;
        content.style.animation = '';
        content.style.whiteSpace = 'nowrap';
    }
}

function escHtml(s){
    var d=document.createElement('div'); d.textContent=s; return d.innerHTML;
}

function saveTicker(e){
    e.preventDefault();
    var data = new FormData();
    data.append('action','save_ticker');
    data.append('ticker_enabled', document.getElementById('tickerEnabled').checked ? '1' : '0');
    data.append('ticker_text', document.getElementById('tickerText').value);
    data.append('ticker_bg_color', document.getElementById('tickerBgColor').value);
    data.append('ticker_text_color', document.getElementById('tickerTextColor').value);
    data.append('ticker_effect', document.getElementById('tickerEffect').value);

    ajaxPost(data).then(function(res){
        if(res.success){
            alert('저장되었습니다.');
        } else {
            alert('저장 실패');
        }
    });
}

// 초기 미리보기
updatePreview();
</script>

<?php adminFooter(); ?>

<script>
nbColorPalette('tickerBgColor', 'tickerBgPreview', function(c){
    document.getElementById('tickerBgLabel').textContent = c;
    updatePreview();
});
nbColorPalette('tickerTextColor', 'tickerTextPreview', function(c){
    document.getElementById('tickerTextLabel').textContent = c;
    updatePreview();
});
</script>

<?php
/**
 * OG 기본 이미지 설정 — 설정 페이지
 */

$cfg = _og_load_config();
$msg = '';
$msg_type = 'ok'; // ok | err

/* ── 이미지 업로드 처리 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_save'])) {

    /* 파일 업로드 */
    if (!empty($_FILES['og_upload']['tmp_name'])) {
        $file     = $_FILES['og_upload'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','gif','webp'];

        if (!in_array($ext, $allowed, true)) {
            $msg = '허용되지 않는 파일 형식입니다. (jpg, png, gif, webp 만 가능)';
            $msg_type = 'err';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $msg = '파일 크기가 5MB를 초과합니다.';
            $msg_type = 'err';
        } else {
            /* 저장 경로: /data/og-image/upload/ */
            $save_dir = _og_data_dir() . '/upload';
            if (!is_dir($save_dir)) @mkdir($save_dir, 0755, true);

            $filename  = 'og-default.' . $ext;
            $save_path = $save_dir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $save_path)) {
                /* URL 자동 세팅 */
                $base_url = rtrim(nb_url('/'), '/');
                $rel      = '/data/og-image/upload/' . $filename;
                $cfg['image_url'] = $base_url . $rel;
                $msg = '이미지가 업로드되었습니다.';
            } else {
                $msg = '업로드에 실패했습니다. 폴더 쓰기 권한을 확인해주세요.';
                $msg_type = 'err';
            }
        }
    }

    /* 나머지 설정 저장 (업로드 에러가 없을 때만 URL 덮어쓰기 허용) */
    if ($msg_type !== 'err') {
        $cfg['enabled']   = isset($_POST['enabled']);
        /* 직접 URL 입력이 있으면 업로드 URL보다 우선 */
        $manual_url = trim($_POST['image_url'] ?? '');
        if ($manual_url !== '') {
            $cfg['image_url'] = $manual_url;
        }
        _og_save_config($cfg);
        $cfg = _og_load_config();
        if (!$msg) $msg = '설정이 저장되었습니다.';
    }
}
?>

<style>
.og-wrap { max-width: 680px; font-family: -apple-system, sans-serif; }
.og-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.og-card h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; letter-spacing: .5px; }
.og-row { margin-bottom: 18px; }
.og-row:last-child { margin-bottom: 0; }
.og-row > label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.og-desc { font-size: 12px; color: #94a3b8; margin: 4px 0 0; line-height: 1.6; }
.og-input { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.og-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.12); }
.og-btn { padding: 10px 28px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.og-btn:hover { background: #15803d; }
.og-msg-ok  { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.og-msg-err { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

/* 업로드 드롭존 */
.og-dropzone {
    border: 2px dashed #cbd5e1; border-radius: 10px; padding: 32px 20px;
    text-align: center; cursor: pointer; transition: border-color .2s, background .2s;
    background: #f8fafc; position: relative;
}
.og-dropzone:hover, .og-dropzone.drag-over { border-color: #22c55e; background: #f0fdf4; }
.og-dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.og-dropzone-icon { font-size: 32px; margin-bottom: 8px; }
.og-dropzone-label { font-size: 14px; font-weight: 600; color: #475569; }
.og-dropzone-sub { font-size: 12px; color: #94a3b8; margin-top: 4px; }
.og-or { text-align: center; font-size: 12px; color: #94a3b8; margin: 14px 0; position: relative; }
.og-or::before, .og-or::after { content:''; display:inline-block; width:80px; height:1px; background:#e2e8f0; vertical-align: middle; margin: 0 8px; }

/* 현재 이미지 */
.og-current-img { border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 10px; max-width: 340px; }
.og-current-img img { width: 100%; height: 140px; object-fit: cover; display: block; }
.og-current-label { font-size: 11px; color: #94a3b8; padding: 6px 10px; background: #f8fafc; }

/* 미리보기 카카오톡 스타일 */
.og-preview-wrap { margin-top: 14px; }
.og-kakao-card { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; max-width: 340px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.og-kakao-img { width: 100%; height: 180px; object-fit: cover; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 13px; }
.og-kakao-img img { width: 100%; height: 100%; object-fit: cover; }
.og-kakao-body { padding: 12px 14px; background: #fff; }
.og-kakao-title { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.og-kakao-url { font-size: 12px; color: #94a3b8; }
</style>

<div class="og-wrap">

<?php if ($msg): ?>
<div class="og-msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- 안내 -->
<div class="og-card" style="background:#f0fdf4;border-color:#bbf7d0">
    <h2 style="border-color:#bbf7d0;color:#15803d">동작 방식</h2>
    <div style="font-size:13px;color:#374151;line-height:2">
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">게시글에 이미지가 있으면?</strong><br>
            첨부 이미지 또는 본문 첫 이미지가 우선 사용됩니다. 기본 이미지는 무시됩니다.
        </div>
        <div style="margin-bottom:6px">
            <strong style="color:#15803d">게시글에 이미지가 없으면?</strong><br>
            여기서 설정한 기본 이미지가 카카오톡·SNS 공유 썸네일로 표시됩니다.
        </div>
        <div>
            <strong style="color:#15803d">메인·게시판 목록 페이지는?</strong><br>
            항상 기본 이미지가 표시됩니다.
        </div>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="plugin_save" value="1">

<div class="og-card">
    <h2>기본 OG 이미지 설정</h2>

    <div class="og-row">
        <label>
            <input type="checkbox" name="enabled" value="1"
                   <?= !empty($cfg['enabled']) ? 'checked' : '' ?>
                   style="accent-color:#22c55e;width:15px;height:15px;vertical-align:middle;margin-right:4px">
            &nbsp;플러그인 활성화
        </label>
    </div>

    <!-- 이미지 업로드 -->
    <div class="og-row">
        <label>기본 이미지 업로드</label>

        <?php if (!empty($cfg['image_url'])): ?>
        <div class="og-current-img">
            <img src="<?= htmlspecialchars($cfg['image_url']) ?>" alt="현재 OG 이미지">
            <div class="og-current-label">현재 적용 중인 이미지</div>
        </div>
        <?php endif; ?>

        <div class="og-dropzone" id="og_dropzone">
            <input type="file" name="og_upload" accept="image/jpeg,image/png,image/gif,image/webp"
                   id="og_file_input" onchange="ogPreviewFile(this)">
            <div class="og-dropzone-icon">🖼️</div>
            <div class="og-dropzone-label" id="og_file_label">클릭하거나 이미지를 드래그해서 올려주세요</div>
            <div class="og-dropzone-sub">JPG · PNG · GIF · WEBP / 최대 5MB</div>
        </div>
        <p class="og-desc">권장 크기: <strong>1200 × 630px</strong> (카카오톡·페이스북·트위터 최적)</p>
    </div>

    <!-- URL 직접 입력 (선택) -->
    <div class="og-or">또는 URL 직접 입력</div>

    <div class="og-row">
        <label for="image_url">이미지 URL</label>
        <input type="url" id="image_url" name="image_url" class="og-input"
               placeholder="https://example.com/og-image.jpg"
               value="<?= htmlspecialchars($cfg['image_url'] ?? '') ?>"
               oninput="ogUpdatePreview(this.value)">
        <p class="og-desc">이미 서버에 있는 이미지라면 URL을 직접 입력하세요. (업로드보다 우선 적용됩니다)</p>
    </div>

    <!-- 미리보기 -->
    <div class="og-row">
        <label>카카오톡 공유 미리보기</label>
        <div class="og-preview-wrap">
            <div class="og-kakao-card">
                <div class="og-kakao-img" id="og_preview_box">
                    <?php if (!empty($cfg['image_url'])): ?>
                        <img src="<?= htmlspecialchars($cfg['image_url']) ?>" id="og_preview_img" alt="OG 미리보기">
                    <?php else: ?>
                        <span id="og_preview_empty">이미지를 업로드하면 미리보기가 표시됩니다</span>
                        <img src="" id="og_preview_img" alt="" style="display:none">
                    <?php endif; ?>
                </div>
                <div class="og-kakao-body">
                    <div class="og-kakao-title"><?= htmlspecialchars(nb_setting('site_title', 'NuriBoard')) ?></div>
                    <div class="og-kakao-url"><?= htmlspecialchars(nb_setting('site_url', '')) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="og-card" style="padding:18px 24px">
    <button type="submit" class="og-btn">설정 저장</button>
</div>

</form>

<!-- OG 검사 도구 링크 -->
<div class="og-card">
    <h2>공유 미리보기 검사 도구</h2>
    <div style="display:flex;flex-direction:column;gap:10px">
        <a href="https://developers.kakao.com/tool/debugger/sharing" target="_blank" rel="noopener"
           style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#1e293b;font-size:13px;font-weight:600;transition:background .15s"
           onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#FEE500"><path d="M12 2C6.48 2 2 5.92 2 10.76c0 3.02 1.74 5.68 4.37 7.27l-.96 3.58 3.93-2.06c.84.22 1.73.33 2.66.33 5.52 0 10-3.92 10-8.76C22 5.92 17.52 2 12 2z"/></svg>
            카카오 공유 디버거
            <span style="margin-left:auto;font-size:11px;color:#94a3b8">developers.kakao.com →</span>
        </a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(nb_setting('site_url', '')) ?>" target="_blank" rel="noopener"
           style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#1e293b;font-size:13px;font-weight:600;transition:background .15s"
           onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            페이스북 공유 테스트
            <span style="margin-left:auto;font-size:11px;color:#94a3b8">facebook.com →</span>
        </a>
    </div>
    <p style="font-size:12px;color:#94a3b8;margin-top:12px">저장 후 위 도구에서 사이트 URL로 검사하면 썸네일 적용 여부를 바로 확인할 수 있습니다.</p>
</div>

</div>

<script>
/* 드래그&드롭 효과 */
var dz = document.getElementById('og_dropzone');
dz.addEventListener('dragover',  function(e){ e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', function()  { dz.classList.remove('drag-over'); });
dz.addEventListener('drop',      function(e){ e.preventDefault(); dz.classList.remove('drag-over'); });

/* 파일 선택 시 미리보기 */
function ogPreviewFile(input) {
    if (!input.files || !input.files[0]) return;
    var file  = input.files[0];
    var label = document.getElementById('og_file_label');
    label.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';

    var reader = new FileReader();
    reader.onload = function(e) { ogUpdatePreview(e.target.result); };
    reader.readAsDataURL(file);
}

/* URL 입력 시 미리보기 */
function ogUpdatePreview(url) {
    var img   = document.getElementById('og_preview_img');
    var empty = document.getElementById('og_preview_empty');
    if (url) {
        img.src = url;
        img.style.display = 'block';
        if (empty) empty.style.display = 'none';
        img.onerror = function() {
            img.style.display = 'none';
            if (empty) { empty.textContent = '이미지를 불러올 수 없습니다.'; empty.style.display = ''; }
        };
    } else {
        img.style.display = 'none';
        if (empty) { empty.textContent = '이미지를 업로드하면 미리보기가 표시됩니다'; empty.style.display = ''; }
    }
}
</script>

<?php
/**
 * NuriBoard 관리자 - 메인 페이지 설정
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_main_settings') {
        $settings = $_POST['settings'] ?? [];
        foreach ($settings as $key => $value) {
            if (strpos($key, 'main_') !== 0) continue;
            $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                DB::update("{$prefix}settings", ['setting_value' => $value], 'setting_key = ?', [$key]);
            } else {
                DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $value]);
            }
        }
        AdminLog::write('settings_save', '', 0, '메인 페이지 설정 변경');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'upload_hero_image') {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => '파일을 선택하세요.']);
            exit;
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다.']);
            exit;
        }
        $dir = NB_ROOT . '/uploads/site';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $newName = 'hero_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $newName);
        $path = 'uploads/site/' . $newName;
        $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = 'main_hero_image'", []);
        if ($exists) {
            DB::update("{$prefix}settings", ['setting_value' => $path], "setting_key = 'main_hero_image'");
        } else {
            DB::insert("{$prefix}settings", ['setting_key' => 'main_hero_image', 'setting_value' => $path]);
        }
        echo json_encode(['success' => true, 'path' => $path]);
        exit;
    }

    if ($action === 'delete_hero_image') {
        $current = nb_setting('main_hero_image');
        if ($current && file_exists(NB_ROOT . '/' . $current)) {
            unlink(NB_ROOT . '/' . $current);
        }
        DB::update("{$prefix}settings", ['setting_value' => ''], "setting_key = 'main_hero_image'");
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

adminHeader('main-design');
?>

<div class="page-header">
    <h1>메인 페이지 설정</h1>
</div>

<form onsubmit="saveMainSettings(event)">

    <!-- 히어로 섹션 -->
    <div class="card">
        <div class="card-header"><h2>히어로 섹션 (상단 이미지)</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>히어로 섹션 표시</label>
                <div class="toggle-switch">
                    <input type="checkbox" id="main_hero_enabled" name="main_hero_enabled" <?= nb_setting('main_hero_enabled') === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider" onclick="this.previousElementSibling.click()"></span>
                    <span class="toggle-label"><?= nb_setting('main_hero_enabled') === '1' ? 'ON' : 'OFF' ?></span>
                </div>
            </div>

            <div class="form-group">
                <label>히어로 타입</label>
                <select name="main_hero_type" id="heroType" onchange="toggleHeroType()" style="width:100%;max-width:300px;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px">
                    <option value="slider" <?= nb_setting('main_hero_type', 'slider') === 'slider' ? 'selected' : '' ?>>이미지 슬라이더 (배너 관리 활용)</option>
                    <option value="single" <?= nb_setting('main_hero_type') === 'single' ? 'selected' : '' ?>>단일 이미지</option>
                </select>
            </div>

            <!-- 슬라이더 안내 -->
            <div id="heroSliderInfo" style="<?= nb_setting('main_hero_type') === 'single' ? 'display:none' : '' ?>">
                <div class="alert success">
                    슬라이더 이미지는 <a href="?page=banners" style="font-weight:700;text-decoration:underline">배너 관리</a>에서 "main" 위치로 등록하세요.
                    <?php $mc = count(Banner::listByPosition('main')); ?>
                    현재 등록된 메인 배너: <strong><?= $mc ?>개</strong>
                </div>
            </div>

            <!-- 단일 이미지 설정 -->
            <div id="heroSingleInfo" style="<?= nb_setting('main_hero_type') !== 'single' ? 'display:none' : '' ?>">
                <div class="form-group">
                    <label>히어로 이미지</label>
                    <?php if (nb_setting('main_hero_image')): ?>
                        <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px">
                            <img src="../<?= nb_e(nb_setting('main_hero_image')) ?>" style="height:80px;border:1px solid #e2e8f0;border-radius:6px">
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteHeroImage()">삭제</button>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="hero_file" accept="image/*" onchange="uploadHeroImage()">
                    <small>권장: 가로 1200px 이상, 세로 300~500px</small>
                </div>
                <div class="form-group">
                    <label>제목 텍스트 (선택)</label>
                    <input type="text" name="main_hero_title" value="<?= nb_e(nb_setting('main_hero_title')) ?>" placeholder="이미지 위에 표시할 제목">
                </div>
                <div class="form-group">
                    <label>설명 텍스트 (선택)</label>
                    <input type="text" name="main_hero_desc" value="<?= nb_e(nb_setting('main_hero_desc')) ?>" placeholder="이미지 위에 표시할 설명">
                </div>
                <div class="form-group">
                    <label>클릭 링크 (선택)</label>
                    <input type="text" name="main_hero_link" value="<?= nb_e(nb_setting('main_hero_link')) ?>" placeholder="https://...">
                </div>
            </div>
        </div>
    </div>

    <!-- 메인 섹션 ON/OFF -->
    <div class="card">
        <div class="card-header"><h2>메인 페이지 섹션</h2></div>
        <div class="card-body">
            <p style="font-size:13px;color:#64748b;margin-bottom:16px">각 섹션의 표시 여부를 설정합니다.</p>

            <?php
            $sections = [
                ['key' => 'main_section_gallery', 'label' => '이미지 게시판 갤러리', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_popular', 'label' => '인기글 / 최신글 / 최신댓글 탭', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_latestlist', 'label' => '최신글 목록', 'icon' => '', 'default' => '0'],
                ['key' => 'main_section_boards', 'label' => '게시판 카드 그리드', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_stats', 'label' => '오늘의 통계', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_notice', 'label' => '운영 공지', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_bestmember', 'label' => '베스트 회원', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_recentcomments', 'label' => '최근 댓글', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_attendance', 'label' => '출석체크', 'icon' => '', 'default' => '1'],
                ['key' => 'main_section_cta', 'label' => '하단 가입 유도 (비로그인 시)', 'icon' => '', 'default' => '1'],
            ];
            foreach ($sections as $sec):
                $val = nb_setting($sec['key'], $sec['default']);
            ?>
            <div class="section-toggle-row">
                <span class="str-icon"><?= $sec['icon'] ?></span>
                <span class="str-label"><?= nb_e($sec['label']) ?></span>
                <div class="toggle-switch">
                    <input type="checkbox" name="<?= $sec['key'] ?>" <?= $val === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider" onclick="this.previousElementSibling.click()"></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 표시 개수 설정 -->
    <div class="card">
        <div class="card-header"><h2>표시 개수 설정</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>갤러리 이미지 (개)</label>
                    <input type="number" name="main_count_gallery" value="<?= nb_e(nb_setting('main_count_gallery', '9')) ?>" min="3" max="18" style="width:100px">
                    <small>3의 배수 권장</small>
                </div>
                <div class="form-group">
                    <label>인기글 탭 (개)</label>
                    <input type="number" name="main_count_popular" value="<?= nb_e(nb_setting('main_count_popular', '8')) ?>" min="1" max="30" style="width:100px">
                </div>
                <div class="form-group">
                    <label>최신글 사이드바 (개)</label>
                    <input type="number" name="main_count_latest" value="<?= nb_e(nb_setting('main_count_latest', '8')) ?>" min="1" max="30" style="width:100px">
                </div>
                <div class="form-group">
                    <label>게시판 카드별 글 (개)</label>
                    <input type="number" name="main_count_board" value="<?= nb_e(nb_setting('main_count_board', '5')) ?>" min="1" max="20" style="width:100px">
                </div>
                <div class="form-group">
                    <label>최근 댓글 (개)</label>
                    <input type="number" name="main_count_comments" value="<?= nb_e(nb_setting('main_count_comments', '5')) ?>" min="1" max="20" style="width:100px">
                </div>
            </div>
        </div>
    </div>

    <!-- 저장 버튼 -->
    <div style="margin-top:20px">
        <button type="submit" class="btn btn-primary btn-lg">저장</button>
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

.section-toggle-row{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #f1f5f9}
.section-toggle-row:last-child{border-bottom:none}
.str-icon{font-size:18px;flex-shrink:0}
.str-label{flex:1;font-size:14px;font-weight:500;color:#1e293b}
.section-toggle-row .toggle-switch{flex-shrink:0}
</style>

<script>
// 토글 라벨 업데이트
document.querySelectorAll('.toggle-switch input').forEach(function(el){
    el.addEventListener('change', function(){
        var lbl = this.parentElement.querySelector('.toggle-label');
        if(lbl) lbl.textContent = this.checked ? 'ON' : 'OFF';
    });
});

function toggleHeroType(){
    var type = document.getElementById('heroType').value;
    document.getElementById('heroSliderInfo').style.display = type === 'slider' ? '' : 'none';
    document.getElementById('heroSingleInfo').style.display = type === 'single' ? '' : 'none';
}

function uploadHeroImage(){
    var file = document.getElementById('hero_file').files[0];
    if(!file) return;
    var data = new FormData();
    data.append('action','upload_hero_image');
    data.append('file', file);
    ajaxPost(data).then(function(res){
        if(res.success){ alert('업로드 완료!'); location.reload(); }
        else { alert(res.message || '업로드 실패'); }
    });
}

function deleteHeroImage(){
    if(!confirm('히어로 이미지를 삭제하시겠습니까?')) return;
    var data = new FormData();
    data.append('action','delete_hero_image');
    ajaxPost(data).then(function(res){
        if(res.success){ alert('삭제되었습니다.'); location.reload(); }
    });
}

function saveMainSettings(e){
    e.preventDefault();
    var data = new FormData();
    data.append('action','save_main_settings');

    // 히어로 설정
    data.append('settings[main_hero_enabled]', document.getElementById('main_hero_enabled').checked ? '1' : '0');
    data.append('settings[main_hero_type]', document.getElementById('heroType').value);

    // 텍스트 필드
    document.querySelectorAll('input[name^="main_hero_"]').forEach(function(el){
        if(el.type === 'text') data.append('settings['+el.name+']', el.value);
    });

    // 섹션 ON/OFF
    document.querySelectorAll('input[name^="main_section_"]').forEach(function(el){
        data.append('settings['+el.name+']', el.checked ? '1' : '0');
    });

    // 표시 개수
    document.querySelectorAll('input[name^="main_count_"]').forEach(function(el){
        data.append('settings['+el.name+']', el.value);
    });

    ajaxPost(data).then(function(res){
        if(res.success){ alert('저장되었습니다.'); }
        else { alert('저장 실패'); }
    });
}
</script>

<?php adminFooter(); ?>

<?php
/**
 * NuriBoard 관리자 - 회원 등급 관리 (무제한)
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    // 레벨 추가
    if ($action === 'add_level') {
        $newLv = Level::add();
        AdminLog::write('level_update', 'level', $newLv, 'Lv.' . $newLv . ' 새 등급 추가');
        echo json_encode(['success' => true, 'level' => $newLv]);
        exit;
    }

    // 레벨 삭제
    if ($action === 'delete_level') {
        $level = (int)($_POST['level'] ?? 0);
        if ($level <= 1) {
            echo json_encode(['success' => false, 'message' => 'Lv.1은 삭제할 수 없습니다.']); exit;
        }
        Level::delete($level);
        AdminLog::write('level_update', 'level', $level, 'Lv.' . $level . ' 등급 삭제');
        echo json_encode(['success' => true]);
        exit;
    }

    // 아이콘 이미지 업로드
    if ($action === 'upload_level_icon') {
        $level = (int)($_POST['level'] ?? 0);
        if ($level < 1) { echo json_encode(['success' => false, 'message' => '잘못된 레벨']); exit; }
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => '파일을 선택하세요.']); exit;
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
            echo json_encode(['success' => false, 'message' => '이미지 파일만 가능합니다.']); exit;
        }
        $dir = NB_ROOT . '/uploads/levels';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $newName = 'lv' . $level . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $newName);
        $path = 'uploads/levels/' . $newName;
        Level::update($level, ['icon' => $path, 'icon_type' => 'image']);
        AdminLog::write('level_update', 'level', $level, 'Lv.' . $level . ' 아이콘 이미지 변경');
        echo json_encode(['success' => true, 'path' => $path, 'url' => '../' . $path]);
        exit;
    }

    // 레벨 정보 저장
    if ($action === 'save_level') {
        $level = (int)($_POST['level'] ?? 0);
        if ($level < 1) { echo json_encode(['success' => false, 'message' => '잘못된 레벨']); exit; }
        $iconType = $_POST['icon_type'] ?? 'emoji';
        $data = [
            'name'         => mb_substr(trim($_POST['name'] ?? ''), 0, 50),
            'icon_type'    => $iconType,
            'min_point'    => max(0, (int)($_POST['min_point'] ?? 0)),
            'min_posts'    => max(0, (int)($_POST['min_posts'] ?? 0)),
            'min_comments' => max(0, (int)($_POST['min_comments'] ?? 0)),
            'can_write'    => isset($_POST['can_write'])   ? 1 : 0,
            'can_upload'   => isset($_POST['can_upload'])  ? 1 : 0,
            'can_comment'  => isset($_POST['can_comment']) ? 1 : 0,
            'description'  => mb_substr(trim($_POST['description'] ?? ''), 0, 200),
        ];
        if ($iconType === 'emoji') {
            $data['icon'] = mb_substr(trim($_POST['icon'] ?? ''), 0, 10);
        }
        Level::update($level, $data);
        AdminLog::write('level_update', 'level', $level, 'Lv.' . $level . ' ' . $data['name'] . ' 설정 변경');
        echo json_encode(['success' => true]);
        exit;
    }

    // 이모지로 되돌리기
    if ($action === 'reset_to_emoji') {
        $level = (int)($_POST['level'] ?? 0);
        $emoji = trim($_POST['emoji'] ?? '');
        if ($level >= 1 && $emoji) {
            Level::update($level, ['icon' => $emoji, 'icon_type' => 'emoji']);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// 레벨 데이터
$levels = Level::getAll();

// 레벨별 회원 수
$prefix = DB::getPrefix();
$memberCounts = [];
$rows = DB::fetchAll("SELECT level, COUNT(*) as cnt FROM {$prefix}members GROUP BY level");
foreach ($rows as $r) $memberCounts[(int)$r['level']] = (int)$r['cnt'];
$totalMembers = array_sum($memberCounts);
$maxLevel = Level::maxLevel();

adminHeader('levels');
?>

<div class="page-header">
    <h1>회원 등급 관리</h1>
    <div style="display:flex;align-items:center;gap:12px">
        <span style="font-size:13px;color:#64748b">총 <?= $totalMembers ?>명 · <?= $maxLevel ?>단계</span>
        <button class="btn btn-primary" onclick="addLevel()">+ 등급 추가</button>
    </div>
</div>

<!-- 등급 개요 카드 -->
<div id="overview-grid" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px">
    <?php foreach ($levels as $lv): ?>
    <div class="stat-card" id="overview-<?= $lv['level'] ?>" style="text-align:center;padding:14px 16px;min-width:100px;flex:1">
        <div style="font-size:26px;margin-bottom:4px">
            <?= $lv['icon_type'] === 'image' && $lv['icon']
                ? '<img src="../'.nb_e($lv['icon']).'" style="width:40px;height:40px;object-fit:contain">'
                : nb_e($lv['icon']) ?>
        </div>
        <div style="font-size:12px;font-weight:700"><?= nb_e($lv['name']) ?></div>
        <div style="font-size:11px;color:#94a3b8">Lv.<?= $lv['level'] ?></div>
        <div style="font-size:18px;font-weight:800;color:#2563eb;margin-top:6px"><?= number_format($memberCounts[$lv['level']] ?? 0) ?></div>
        <div style="font-size:11px;color:#94a3b8">명</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 등급별 상세 -->
<div id="levels-list">
<?php foreach ($levels as $lv): ?>
<?php $lvNum = (int)$lv['level']; ?>
<div class="card" id="level-card-<?= $lvNum ?>">
    <div class="card-header" style="cursor:pointer;user-select:none" onclick="toggleLevel(<?= $lvNum ?>)">
        <div style="display:flex;align-items:center;gap:12px">
            <div id="icon-preview-<?= $lvNum ?>" style="font-size:24px;width:36px;text-align:center;flex-shrink:0">
                <?php if ($lv['icon_type'] === 'image' && $lv['icon']): ?>
                    <img src="../<?= nb_e($lv['icon']) ?>" style="width:36px;height:36px;object-fit:contain;vertical-align:middle">
                <?php else: ?>
                    <?= nb_e($lv['icon']) ?>
                <?php endif; ?>
            </div>
            <div>
                <h2 style="font-size:15px">Lv.<?= $lvNum ?> &nbsp;<span id="name-preview-<?= $lvNum ?>"><?= nb_e($lv['name']) ?></span></h2>
                <div style="font-size:12px;color:#94a3b8;margin-top:2px"><?= nb_e($lv['description']) ?></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <div style="font-size:12px;color:#64748b;text-align:right;line-height:1.6">
                포인트 <?= number_format($lv['min_point']) ?>+ &nbsp;·&nbsp;
                글 <?= number_format($lv['min_posts']) ?>+ &nbsp;·&nbsp;
                댓글 <?= number_format($lv['min_comments']) ?>+
            </div>
            <?php if ($lvNum > 1): ?>
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="event.stopPropagation();deleteLevel(<?= $lvNum ?>)"
                    title="이 등급 삭제">✕</button>
            <?php else: ?>
            <span style="width:57px"></span><!-- Lv.1은 삭제 불가, 공간만 유지 -->
            <?php endif; ?>
            <span style="color:#94a3b8;font-size:16px;width:16px;text-align:center" id="toggle-arrow-<?= $lvNum ?>">▼</span>
        </div>
    </div>
    <div id="level-body-<?= $lvNum ?>" style="display:none">
    <form onsubmit="saveLevel(event, <?= $lvNum ?>)">
        <input type="hidden" name="level" value="<?= $lvNum ?>">
        <div class="card-body" style="padding-bottom:0">
            <div class="form-row">
                <div class="form-group" style="flex:1.5">
                    <label>등급 이름</label>
                    <input type="text" name="name" value="<?= nb_e($lv['name']) ?>" required maxlength="50"
                           oninput="document.getElementById('name-preview-<?= $lvNum ?>').textContent=this.value">
                </div>
                <div class="form-group" style="flex:2">
                    <label>등급 설명</label>
                    <input type="text" name="description" value="<?= nb_e($lv['description']) ?>" maxlength="200">
                </div>
            </div>

            <!-- 아이콘 -->
            <div class="form-group">
                <label>등급 아이콘</label>
                <div style="display:flex;gap:16px;flex-wrap:wrap">
                    <!-- 이모지 -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;flex:1;min-width:220px">
                        <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px">이모지</div>
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
                            <input type="text" name="icon" id="emoji-input-<?= $lvNum ?>"
                                   value="<?= nb_e($lv['icon_type'] === 'emoji' ? $lv['icon'] : '') ?>"
                                   maxlength="10" placeholder="붙여넣기" style="width:90px;font-size:20px;text-align:center"
                                   oninput="document.getElementById('emoji-preview-big-<?= $lvNum ?>').textContent=this.value">
                            <span id="emoji-preview-big-<?= $lvNum ?>" style="font-size:32px"><?= $lv['icon_type'] === 'emoji' ? nb_e($lv['icon']) : '' ?></span>
                            <button type="button" class="btn btn-sm" onclick="applyEmoji(<?= $lvNum ?>)">적용</button>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:5px">
                            <?php foreach (['🌱','🌿','🍀','🌸','⭐','🌳','💎','👑','🔥','🏆','🎖️','🎗️','🎀','🎊','🚀','🦁','🐯','🦊','🐉','⚡','🌙','☀️','🌈','🎵','🎮','🏅','🥇','🥈','🥉','🎯'] as $em): ?>
                            <button type="button" onclick="setEmoji(<?= $lvNum ?>, '<?= $em ?>')"
                                    style="background:none;border:1px solid #e2e8f0;border-radius:6px;padding:3px 5px;font-size:17px;cursor:pointer;line-height:1.3"><?= $em ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- 이미지 업로드 -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;flex:1;min-width:200px">
                        <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px">이미지 업로드 <span style="font-weight:400">(큰 이미지도 자동 축소)</span></div>
                        <?php if ($lv['icon_type'] === 'image' && $lv['icon']): ?>
                        <div style="margin-bottom:8px">
                            <img src="../<?= nb_e($lv['icon']) ?>" style="width:40px;height:40px;border-radius:4px;border:1px solid #e2e8f0">
                        </div>
                        <?php endif; ?>
                        <input type="file" id="icon-file-<?= $lvNum ?>" accept="image/*" style="font-size:13px;display:block;margin-bottom:8px">
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn btn-sm btn-primary" onclick="uploadLevelIcon(<?= $lvNum ?>)">업로드</button>
                            <?php if ($lv['icon_type'] === 'image'): ?>
                            <button type="button" class="btn btn-sm" onclick="resetToEmoji(<?= $lvNum ?>)">이모지로 전환</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 등업 조건 -->
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:14px">
                <div style="font-size:13px;font-weight:700;color:#1d4ed8;margin-bottom:10px">
                    자동 등업 조건
                    <span style="font-weight:400;color:#3b82f6;font-size:12px">— 아래 조건을 모두 충족해야 등업 (0 = 해당 조건 무시)</span>
                </div>
                <div class="form-row" style="margin-bottom:0">
                    <div class="form-group" style="margin-bottom:0">
                        <label>최소 포인트</label>
                        <input type="number" name="min_point" value="<?= (int)$lv['min_point'] ?>" min="0">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label>최소 글 수</label>
                        <input type="number" name="min_posts" value="<?= (int)$lv['min_posts'] ?>" min="0">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label>최소 댓글 수</label>
                        <input type="number" name="min_comments" value="<?= (int)$lv['min_comments'] ?>" min="0">
                    </div>
                </div>
            </div>

            <!-- 권한 -->
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;margin-bottom:20px">
                <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:10px">권한</div>
                <div style="display:flex;gap:24px;flex-wrap:wrap">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="can_write" value="1" <?= $lv['can_write'] ? 'checked' : '' ?>> 글쓰기
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="can_upload" value="1" <?= $lv['can_upload'] ? 'checked' : '' ?>> 파일 업로드
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="can_comment" value="1" <?= $lv['can_comment'] ? 'checked' : '' ?>> 댓글
                    </label>
                </div>
            </div>
        </div>
        <div style="padding:14px 20px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
            <?php if ($lvNum > 1): ?>
            <button type="button" class="btn btn-danger btn-sm" onclick="deleteLevel(<?= $lvNum ?>)">이 등급 삭제</button>
            <?php else: ?>
            <span style="font-size:12px;color:#94a3b8">Lv.1은 삭제할 수 없습니다</span>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" id="save-btn-<?= $lvNum ?>">Lv.<?= $lvNum ?> 저장</button>
        </div>
    </form>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- 등급 추가 버튼 (하단) -->
<div style="text-align:center;padding:24px 0">
    <button class="btn btn-primary btn-lg" onclick="addLevel()" style="padding:14px 32px;font-size:15px">
        + 새 등급 추가 (현재 <?= $maxLevel ?>단계 → <?= $maxLevel + 1 ?>단계)
    </button>
</div>

<script>
var currentMaxLevel = <?= $maxLevel ?>;

function toggleLevel(lv) {
    var body  = document.getElementById('level-body-' + lv);
    var arrow = document.getElementById('toggle-arrow-' + lv);
    var open  = body.style.display === 'none' || body.style.display === '';
    // 모든 패널 닫기 (원하면 제거 가능)
    // 토글
    if (body.style.display === 'none') {
        body.style.display = '';
        arrow.textContent  = '▲';
    } else {
        body.style.display = 'none';
        arrow.textContent  = '▼';
    }
}

function setEmoji(lv, emoji) {
    document.getElementById('emoji-input-' + lv).value = emoji;
    document.getElementById('emoji-preview-big-' + lv).textContent = emoji;
}

function applyEmoji(lv) {
    var emoji = document.getElementById('emoji-input-' + lv).value.trim();
    if (!emoji) { alert('이모지를 입력하세요.'); return; }
    var fd = new FormData();
    fd.append('action', 'reset_to_emoji');
    fd.append('level', lv);
    fd.append('emoji', emoji);
    ajaxPost(fd).then(function(res) {
        if (res.success) {
            document.getElementById('icon-preview-' + lv).textContent = emoji;
            // overview도 갱신
            var ov = document.getElementById('overview-' + lv);
            if (ov) ov.querySelector('div').textContent = emoji;
        }
    });
}

function uploadLevelIcon(lv) {
    var file = document.getElementById('icon-file-' + lv).files[0];
    if (!file) { alert('파일을 선택하세요.'); return; }
    var fd = new FormData();
    fd.append('action', 'upload_level_icon');
    fd.append('level', lv);
    fd.append('file', file);
    ajaxPost(fd).then(function(res) {
        if (res.success) {
            var img = '<img src="' + res.url + '" style="width:28px;height:28px;vertical-align:middle">';
            document.getElementById('icon-preview-' + lv).innerHTML = img;
            alert('아이콘이 변경되었습니다.');
            location.reload();
        } else {
            alert(res.message || '업로드 실패');
        }
    });
}

function resetToEmoji(lv) {
    var emoji = document.getElementById('emoji-input-' + lv).value.trim() || '🌟';
    var fd = new FormData();
    fd.append('action', 'reset_to_emoji');
    fd.append('level', lv);
    fd.append('emoji', emoji);
    ajaxPost(fd).then(function(res) {
        if (res.success) location.reload();
    });
}

function saveLevel(e, lv) {
    e.preventDefault();
    var form = e.target;
    var fd   = new FormData(form);
    fd.append('action', 'save_level');
    ['can_write','can_upload','can_comment'].forEach(function(n) {
        if (!form.querySelector('[name="' + n + '"]:checked')) fd.delete(n);
    });
    ajaxPost(fd).then(function(res) {
        if (res.success) {
            var nameEl = document.getElementById('name-preview-' + lv);
            if (nameEl) nameEl.textContent = form.querySelector('[name="name"]').value;
            var btn = document.getElementById('save-btn-' + lv);
            var orig = btn.textContent;
            btn.textContent = '✓ 저장됨';
            btn.disabled = true;
            setTimeout(function() { btn.textContent = orig; btn.disabled = false; }, 1500);
        } else {
            alert('저장 실패');
        }
    });
}

function addLevel() {
    var fd = new FormData();
    fd.append('action', 'add_level');
    ajaxPost(fd).then(function(res) {
        if (res.success) {
            var lv = res.level;
            // 페이지 새로고침해서 새 등급 카드 표시
            location.reload();
        } else {
            alert('등급 추가 실패');
        }
    });
}

function deleteLevel(lv) {
    if (lv <= 1) { alert('Lv.1은 삭제할 수 없습니다.'); return; }
    if (!confirm('Lv.' + lv + ' 등급을 삭제하시겠습니까?\n해당 레벨 회원은 Lv.' + (lv-1) + '으로 이동됩니다.')) return;
    var fd = new FormData();
    fd.append('action', 'delete_level');
    fd.append('level', lv);
    ajaxPost(fd).then(function(res) {
        if (res.success) {
            // 카드 제거
            var card = document.getElementById('level-card-' + lv);
            if (card) card.remove();
            var ov = document.getElementById('overview-' + lv);
            if (ov) ov.remove();
            currentMaxLevel--;
            // 하단 버튼 텍스트 갱신
            var addBtns = document.querySelectorAll('[onclick="addLevel()"]');
            addBtns.forEach(function(btn) {
                if (btn.tagName === 'BUTTON' && btn.textContent.includes('단계')) {
                    btn.textContent = '+ 새 등급 추가 (현재 ' + currentMaxLevel + '단계 → ' + (currentMaxLevel+1) + '단계)';
                }
            });
        } else {
            alert(res.message || '삭제 실패');
        }
    });
}
</script>

<?php adminFooter(); ?>

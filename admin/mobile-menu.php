<?php
/**
 * NuriBoard 관리자 - 햄버거 메뉴 (모바일 전체메뉴 + 하단 고정바)
 */

// 테이블 자동 생성
MobileMenu::ensureTables();

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    // === 배너 ===
    if ($action === 'mb_banner_create' || $action === 'mb_banner_update') {
        $image = '';
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다.']);
                exit;
            }
            $dir = NB_ROOT . '/uploads/mobile';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $newName = 'mb_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $newName);
            $image = 'uploads/mobile/' . $newName;
        }
        if ($action === 'mb_banner_create') {
            if (!$image) { echo json_encode(['success' => false, 'message' => '이미지를 선택하세요.']); exit; }
            MobileMenu::createBanner(['image' => $image, 'link' => trim($_POST['link'] ?? ''), 'target' => $_POST['target'] ?? '_blank', 'sort_order' => (int)($_POST['sort_order'] ?? 0)]);
            echo json_encode(['success' => true]);
        } else {
            $data = ['link' => trim($_POST['link'] ?? ''), 'target' => $_POST['target'] ?? '_blank', 'sort_order' => (int)($_POST['sort_order'] ?? 0), 'is_active' => (int)($_POST['is_active'] ?? 1)];
            if ($image) $data['image'] = $image;
            MobileMenu::updateBanner((int)$_POST['id'], $data);
            echo json_encode(['success' => true]);
        }
        exit;
    }
    if ($action === 'mb_banner_delete') {
        MobileMenu::deleteBanner((int)$_POST['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    // === 하단 고정바 ===
    if ($action === 'bottom_create' || $action === 'bottom_update') {
        $title = trim($_POST['title'] ?? '');
        if (!$title) { echo json_encode(['success' => false, 'message' => '메뉴 이름을 입력하세요.']); exit; }
        $icon = trim($_POST['icon'] ?? '');
        // 아이콘 이미지 업로드
        if (!empty($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['icon_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg','ico'])) {
                echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다.']);
                exit;
            }
            $dir = NB_ROOT . '/uploads/mobile';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $newName = 'icon_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['icon_file']['tmp_name'], $dir . '/' . $newName);
            $icon = 'img:uploads/mobile/' . $newName;
        }
        if ($action === 'bottom_create') {
            MobileMenu::createBottom(['title' => $title, 'icon' => $icon, 'link' => trim($_POST['link'] ?? ''), 'sort_order' => (int)($_POST['sort_order'] ?? 0)]);
            echo json_encode(['success' => true]);
        } else {
            MobileMenu::updateBottom((int)$_POST['id'], ['title' => $title, 'icon' => $icon, 'link' => trim($_POST['link'] ?? ''), 'sort_order' => (int)($_POST['sort_order'] ?? 0), 'is_active' => (int)($_POST['is_active'] ?? 1)]);
            echo json_encode(['success' => true]);
        }
        exit;
    }
    if ($action === 'bottom_delete') {
        MobileMenu::deleteBottom((int)$_POST['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$banners = MobileMenu::listBanners();
$bottomItems = MobileMenu::listBottom();
$menuTree = Menu::getTree();

adminHeader('mobile-menu');
?>

<div class="page-header">
    <h1>햄버거 메뉴</h1>
    <span style="font-size:13px;color:#64748b">모바일 전체메뉴 + 하단 고정바 관리</span>
</div>

<!-- 미리보기 안내 -->
<div class="card" style="background:#f0f9ff;border:1px solid #bae6fd">
    <div class="card-body" style="font-size:13px;color:#0369a1;padding:12px 16px">
        <strong>구조:</strong> 전체메뉴 헤더 → 퀵 버튼(출석체크·쪽지·내정보 고정) → 배너 → 메뉴 섹션(메뉴 관리 연동) → 회원정보
    </div>
</div>

<!-- 섹션 1: 배너 관리 -->
<div class="card">
    <div class="card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <h3 style="margin:0;font-size:16px">배너 관리</h3>
            <button class="btn btn-primary btn-sm" onclick="openBannerModal()">+ 배너 추가</button>
        </div>
        <p style="font-size:12px;color:#94a3b8;margin-bottom:12px">햄버거 메뉴 퀵버튼 아래에 표시되는 프로모션 배너입니다. 권장 사이즈: 가로 전체폭 x 높이 80~120px</p>
        <table class="table">
            <thead><tr><th>미리보기</th><th>링크</th><th>순서</th><th>상태</th><th>관리</th></tr></thead>
            <tbody>
            <?php foreach ($banners as $b): ?>
                <tr>
                    <td><img src="../<?= nb_e($b['image']) ?>" style="height:50px;border-radius:6px"></td>
                    <td style="font-size:12px;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= nb_e($b['link']) ?: '-' ?></td>
                    <td><?= $b['sort_order'] ?></td>
                    <td><span class="badge <?= $b['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $b['is_active'] ? '활성' : '비활성' ?></span></td>
                    <td>
                        <button class="btn btn-sm" onclick='editBanner(<?= json_encode($b, JSON_UNESCAPED_UNICODE) ?>)'>수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteBanner(<?= $b['id'] ?>)">삭제</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($banners)): ?>
                <tr><td colspan="5" class="text-center">배너가 없습니다.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 섹션 2: 메뉴 연동 -->
<div class="card">
    <div class="card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <h3 style="margin:0;font-size:16px">메뉴 섹션</h3>
            <a href="?page=menus" class="btn btn-sm">메뉴 관리 바로가기 →</a>
        </div>
        <p style="font-size:12px;color:#94a3b8;margin-bottom:12px">기존 메뉴 관리와 자동 연동됩니다. 부모 메뉴가 카테고리 제목, 하위 메뉴가 링크 버튼으로 표시됩니다.</p>
        <?php if (empty($menuTree)): ?>
            <div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px">등록된 메뉴가 없습니다. <a href="?page=menus">메뉴 관리</a>에서 메뉴를 추가하세요.</div>
        <?php else: ?>
            <?php foreach ($menuTree as $menu): ?>
            <div style="margin-bottom:12px;padding:10px;background:#f8fafc;border-radius:8px">
                <strong style="font-size:14px"><?= nb_e($menu['title']) ?></strong>
                <?php if (!empty($menu['children'])): ?>
                <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px">
                    <?php foreach ($menu['children'] as $child): ?>
                    <span style="background:#e2e8f0;padding:4px 10px;border-radius:4px;font-size:12px"><?= nb_e($child['title']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 섹션 3: 하단 고정바 -->
<div class="card">
    <div class="card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <h3 style="margin:0;font-size:16px">하단 고정바</h3>
            <button class="btn btn-primary btn-sm" onclick="openBottomModal()">+ 메뉴 추가</button>
        </div>
        <p style="font-size:12px;color:#94a3b8;margin-bottom:12px">모바일 하단에 고정되는 네비게이션 바입니다. 아이콘은 이모지 또는 SVG를 입력하세요.</p>
        <table class="table">
            <thead><tr><th>아이콘</th><th>이름</th><th>링크</th><th>순서</th><th>상태</th><th>관리</th></tr></thead>
            <tbody>
            <?php foreach ($bottomItems as $item): ?>
                <tr>
                    <td><?= MobileMenu::renderIcon($item['icon'], 22) ?></td>
                    <td><?= nb_e($item['title']) ?></td>
                    <td style="font-size:12px;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= nb_e($item['link']) ?></td>
                    <td><?= $item['sort_order'] ?></td>
                    <td><span class="badge <?= $item['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $item['is_active'] ? '활성' : '비활성' ?></span></td>
                    <td>
                        <button class="btn btn-sm" onclick='editBottom(<?= json_encode($item, JSON_UNESCAPED_UNICODE) ?>)'>수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteBottom(<?= $item['id'] ?>)">삭제</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($bottomItems)): ?>
                <tr><td colspan="6" class="text-center">하단 메뉴가 없습니다.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 배너 모달 -->
<div class="modal" id="bannerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="bannerModalTitle">배너 추가</h3>
            <button class="modal-close" onclick="closeModal('bannerModal')">&times;</button>
        </div>
        <form onsubmit="return saveBanner(event)">
            <input type="hidden" id="mb_banner_id">
            <div class="form-group">
                <label>배너 이미지</label>
                <input type="file" id="mb_banner_image" accept="image/*">
                <div id="mb_banner_preview" style="margin-top:8px"></div>
            </div>
            <div class="form-group">
                <label>클릭 시 이동 링크</label>
                <input type="text" id="mb_banner_link" placeholder="https://...">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>순서</label>
                    <input type="number" id="mb_banner_sort" value="0">
                </div>
                <div class="form-group">
                    <label>새창 열기</label>
                    <select id="mb_banner_target">
                        <option value="_blank">새 창</option>
                        <option value="">현재 창</option>
                    </select>
                </div>
            </div>
            <div class="form-group" id="mb_banner_active_wrap" style="display:none">
                <label><input type="checkbox" id="mb_banner_active" checked> 활성화</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('bannerModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>

<!-- 하단바 모달 -->
<div class="modal" id="bottomModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="bottomModalTitle">하단 메뉴 추가</h3>
            <button class="modal-close" onclick="closeModal('bottomModal')">&times;</button>
        </div>
        <form onsubmit="return saveBottom(event)">
            <input type="hidden" id="bottom_id">
            <div class="form-group">
                <label>아이콘</label>
                <input type="hidden" id="bottom_icon">
                <div class="icon-picker" id="iconPicker">
                    <div class="icon-option" data-icon="home" title="홈"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                    <div class="icon-option" data-icon="calendar-check" title="출석체크"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg></div>
                    <div class="icon-option" data-icon="mail" title="쪽지"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                    <div class="icon-option" data-icon="user" title="내정보"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                    <div class="icon-option" data-icon="edit" title="글쓰기"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
                    <div class="icon-option" data-icon="bell" title="알림"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg></div>
                    <div class="icon-option" data-icon="search" title="검색"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
                    <div class="icon-option" data-icon="heart" title="즐겨찾기"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></div>
                    <div class="icon-option" data-icon="gift" title="이벤트"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg></div>
                    <div class="icon-option" data-icon="star" title="인기"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
                    <div class="icon-option" data-icon="settings" title="설정"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></div>
                    <div class="icon-option" data-icon="grid" title="전체메뉴"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>
                </div>
                <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e2e8f0">
                    <label style="font-size:12px;color:#64748b;margin-bottom:4px;display:block">또는 직접 이미지 업로드</label>
                    <input type="file" id="bottom_icon_file" accept="image/*">
                    <div id="bottom_icon_preview" style="margin-top:6px"></div>
                </div>
            </div>
            <div class="form-group">
                <label>메뉴 이름</label>
                <input type="text" id="bottom_title" placeholder="홈" required>
            </div>
            <div class="form-group">
                <label>링크</label>
                <input type="text" id="bottom_link" placeholder="/" required>
            </div>
            <div class="form-group">
                <label>순서</label>
                <input type="number" id="bottom_sort" value="0">
            </div>
            <div class="form-group" id="bottom_active_wrap" style="display:none">
                <label><input type="checkbox" id="bottom_active" checked> 활성화</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('bottomModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>

<style>
.icon-picker{display:flex;flex-wrap:wrap;gap:8px;padding:8px 0}
.icon-option{width:44px;height:44px;display:flex;align-items:center;justify-content:center;border:2px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:all .15s;color:#64748b}
.icon-option:hover{border-color:#94a3b8;color:#1e293b}
.icon-option.selected{border-color:#2563eb;background:#eff6ff;color:#2563eb}
</style>
<script>
// === 아이콘 피커 ===
document.querySelectorAll('.icon-option').forEach(function(el){
    el.addEventListener('click',function(){
        document.querySelectorAll('.icon-option').forEach(function(o){o.classList.remove('selected')});
        this.classList.add('selected');
        document.getElementById('bottom_icon').value=this.getAttribute('data-icon');
    });
});
function selectIcon(key){
    document.querySelectorAll('.icon-option').forEach(function(o){
        o.classList.toggle('selected',o.getAttribute('data-icon')===key);
    });
    document.getElementById('bottom_icon').value=key;
}

// 파일 선택 시 SVG 선택 해제
document.getElementById('bottom_icon_file').addEventListener('change',function(){
    if(this.files.length){
        document.querySelectorAll('.icon-option').forEach(function(o){o.classList.remove('selected')});
        document.getElementById('bottom_icon').value='';
        document.getElementById('bottom_icon_preview').innerHTML='<img src="'+URL.createObjectURL(this.files[0])+'" style="height:32px;border-radius:4px">';
    }
});

// === 배너 ===
function openBannerModal(){
    document.getElementById('bannerModalTitle').textContent='배너 추가';
    document.getElementById('mb_banner_id').value='';
    document.getElementById('mb_banner_image').value='';
    document.getElementById('mb_banner_preview').innerHTML='';
    document.getElementById('mb_banner_link').value='';
    document.getElementById('mb_banner_sort').value='0';
    document.getElementById('mb_banner_target').value='_blank';
    document.getElementById('mb_banner_active_wrap').style.display='none';
    openModal('bannerModal');
}
function editBanner(b){
    document.getElementById('bannerModalTitle').textContent='배너 수정';
    document.getElementById('mb_banner_id').value=b.id;
    document.getElementById('mb_banner_image').value='';
    document.getElementById('mb_banner_preview').innerHTML='<img src="../'+b.image+'" style="height:50px;border-radius:6px">';
    document.getElementById('mb_banner_link').value=b.link||'';
    document.getElementById('mb_banner_sort').value=b.sort_order;
    document.getElementById('mb_banner_target').value=b.target||'_blank';
    document.getElementById('mb_banner_active').checked=b.is_active==1;
    document.getElementById('mb_banner_active_wrap').style.display='block';
    openModal('bannerModal');
}
function saveBanner(e){
    e.preventDefault();
    var editId=document.getElementById('mb_banner_id').value;
    var data=new FormData();
    data.append('action',editId?'mb_banner_update':'mb_banner_create');
    if(editId){data.append('id',editId);data.append('is_active',document.getElementById('mb_banner_active').checked?1:0)}
    var file=document.getElementById('mb_banner_image').files[0];
    if(file)data.append('image',file);
    data.append('link',document.getElementById('mb_banner_link').value);
    data.append('sort_order',document.getElementById('mb_banner_sort').value);
    data.append('target',document.getElementById('mb_banner_target').value);
    ajaxPost(data).then(function(res){if(res.success)location.reload();else alert(res.message||'오류')});
    return false;
}
function deleteBanner(id){
    if(!confirm('배너를 삭제하시겠습니까?'))return;
    var data=new FormData();data.append('action','mb_banner_delete');data.append('id',id);
    ajaxPost(data).then(function(res){if(res.success)location.reload()});
}

// === 하단 고정바 ===
function openBottomModal(){
    document.getElementById('bottomModalTitle').textContent='하단 메뉴 추가';
    document.getElementById('bottom_id').value='';
    document.getElementById('bottom_title').value='';
    document.getElementById('bottom_link').value='';
    document.getElementById('bottom_sort').value='0';
    document.getElementById('bottom_active_wrap').style.display='none';
    document.getElementById('bottom_icon_file').value='';
    document.getElementById('bottom_icon_preview').innerHTML='';
    selectIcon('home');
    openModal('bottomModal');
}
function editBottom(item){
    document.getElementById('bottomModalTitle').textContent='하단 메뉴 수정';
    document.getElementById('bottom_id').value=item.id;
    document.getElementById('bottom_title').value=item.title;
    document.getElementById('bottom_link').value=item.link;
    document.getElementById('bottom_sort').value=item.sort_order;
    document.getElementById('bottom_active').checked=item.is_active==1;
    document.getElementById('bottom_active_wrap').style.display='block';
    document.getElementById('bottom_icon_file').value='';
    if(item.icon && item.icon.indexOf('img:')===0){
        document.getElementById('bottom_icon_preview').innerHTML='<img src="../'+item.icon.substring(4)+'" style="height:32px;border-radius:4px">';
        document.querySelectorAll('.icon-option').forEach(function(o){o.classList.remove('selected')});
        document.getElementById('bottom_icon').value=item.icon;
    } else {
        document.getElementById('bottom_icon_preview').innerHTML='';
        selectIcon(item.icon||'home');
    }
    openModal('bottomModal');
}
function saveBottom(e){
    e.preventDefault();
    var editId=document.getElementById('bottom_id').value;
    var data=new FormData();
    data.append('action',editId?'bottom_update':'bottom_create');
    if(editId){data.append('id',editId);data.append('is_active',document.getElementById('bottom_active').checked?1:0)}
    data.append('icon',document.getElementById('bottom_icon').value);
    var iconFile=document.getElementById('bottom_icon_file').files[0];
    if(iconFile)data.append('icon_file',iconFile);
    data.append('title',document.getElementById('bottom_title').value);
    data.append('link',document.getElementById('bottom_link').value);
    data.append('sort_order',document.getElementById('bottom_sort').value);
    ajaxPost(data).then(function(res){if(res.success)location.reload();else alert(res.message||'오류')});
    return false;
}
function deleteBottom(id){
    if(!confirm('메뉴를 삭제하시겠습니까?'))return;
    var data=new FormData();data.append('action','bottom_delete');data.append('id',id);
    ajaxPost(data).then(function(res){if(res.success)location.reload()});
}
</script>

<?php adminFooter(); ?>

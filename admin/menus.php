<?php
/**
 * NuriBoard 관리자 - 메뉴 관리
 */

// badge 컬럼 자동 추가 (기존 사이트 호환)
try {
    $prefix = DB::getPrefix();
    DB::getInstance()->exec("ALTER TABLE {$prefix}menus ADD COLUMN badge VARCHAR(20) DEFAULT '' AFTER color");
} catch (Exception $e) {}

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'menu_create') {
        $id = Menu::create([
            'parent_id' => (int)($_POST['parent_id'] ?? 0),
            'title' => trim($_POST['title'] ?? ''),
            'link' => trim($_POST['link'] ?? ''),
            'board_id' => trim($_POST['board_id'] ?? ''),
            'target' => $_POST['target'] ?? '',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'color' => trim($_POST['color'] ?? ''),
            'badge' => trim($_POST['badge'] ?? ''),
        ]);
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'menu_update') {
        Menu::update((int)$_POST['id'], [
            'parent_id' => (int)($_POST['parent_id'] ?? 0),
            'title' => trim($_POST['title'] ?? ''),
            'link' => trim($_POST['link'] ?? ''),
            'board_id' => trim($_POST['board_id'] ?? ''),
            'target' => $_POST['target'] ?? '',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => (int)($_POST['is_active'] ?? 1),
            'color' => trim($_POST['color'] ?? ''),
            'badge' => trim($_POST['badge'] ?? ''),
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'menu_delete') {
        Menu::delete((int)$_POST['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$menus = Menu::listAll();
$boards = Board::listAll();
$parents = array_filter($menus, function($m) { return $m['parent_id'] == 0; });

adminHeader('menus');
?>

<div class="page-header">
    <h1>메뉴 관리</h1>
    <button class="btn btn-primary" onclick="openMenuModal()">+ 메뉴 추가</button>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>ID</th><th>메뉴명</th><th>유형</th><th>연결</th><th>순서</th><th>상태</th><th>관리</th></tr></thead>
        <tbody>
        <?php foreach ($menus as $m): ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td>
                    <?= $m['parent_id'] > 0 ? '&nbsp;&nbsp;&nbsp;└ ' : '' ?>
                    <?= nb_e($m['title']) ?>
                </td>
                <td>
                    <?php if ($m['board_id']): ?>
                        <span class="badge badge-green">게시판</span>
                    <?php elseif ($m['link']): ?>
                        <span class="badge" style="background:#eff6ff;color:#2563eb">링크</span>
                    <?php else: ?>
                        <span class="badge" style="background:#f1f5f9;color:#64748b">그룹</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#64748b">
                    <?= $m['board_id'] ? nb_e($m['board_id']) : nb_e($m['link']) ?>
                </td>
                <td><?= $m['sort_order'] ?></td>
                <td><span class="badge <?= $m['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $m['is_active'] ? '활성' : '비활성' ?></span></td>
                <td>
                    <button class="btn btn-sm" onclick='editMenu(<?= json_encode($m, JSON_UNESCAPED_UNICODE) ?>)'>수정</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteMenu(<?= $m['id'] ?>,'<?= nb_e($m['title']) ?>')">삭제</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($menus)): ?>
            <tr><td colspan="7" class="text-center">메뉴가 없습니다. 메뉴를 추가하세요.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-body" style="font-size:13px;color:#475569;line-height:1.8">
        <strong style="font-size:14px">메뉴 만드는 방법</strong>
        <div style="margin-top:10px;padding:14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
            <strong>1단계)</strong> <span style="color:var(--primary)">부모 메뉴</span> 추가 → 상위 메뉴: <strong>"없음 (최상위)"</strong>, 게시판 연결: <strong>"연결 안함"</strong><br>
            &nbsp;&nbsp;&nbsp;&nbsp;예: "커뮤니티" (이 메뉴 위에 마우스 올리면 하위 메뉴가 펼쳐집니다)<br><br>
            <strong>2단계)</strong> <span style="color:var(--primary)">자식 메뉴</span> 추가 → 상위 메뉴: <strong>"커뮤니티"</strong> 선택, 게시판 연결: 원하는 게시판 선택<br>
            &nbsp;&nbsp;&nbsp;&nbsp;예: "자유게시판", "질문답변", "갤러리" 등
        </div>
        <div style="margin-top:10px;font-size:12px;color:#94a3b8">
            💡 부모 메뉴에는 게시판을 연결하지 마세요 (그룹 역할만 합니다). 메뉴가 하나도 없으면 게시판 목록이 자동 표시됩니다.
        </div>
    </div>
</div>

<!-- 메뉴 추가/수정 모달 -->
<div class="modal" id="menuModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="menuModalTitle">메뉴 추가</h3>
            <button class="modal-close" onclick="closeModal('menuModal')">&times;</button>
        </div>
        <form onsubmit="return saveMenu(event)">
            <input type="hidden" id="menu_edit_id">
            <div class="form-group">
                <label>메뉴 이름</label>
                <input type="text" id="menu_title" required placeholder="예: 커뮤니티, 자료실, 소개">
            </div>
            <div class="form-group">
                <label>상위 메뉴</label>
                <select id="menu_parent_id" onchange="onParentChange()">
                    <option value="0">없음 (최상위 = 부모 메뉴)</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= $p['id'] ?>">└ <?= nb_e($p['title']) ?> (하위 메뉴로 들어감)</option>
                    <?php endforeach; ?>
                </select>
                <small id="parentHint" style="color:#f59e0b;display:none">💡 부모 메뉴를 먼저 만든 후 여기서 선택하세요</small>
            </div>
            <div class="form-group">
                <label>게시판 연결</label>
                <select id="menu_board_id">
                    <option value="">연결 안함 (부모 메뉴는 연결 안함)</option>
                    <?php foreach ($boards as $b): ?>
                        <option value="<?= nb_e($b['board_id']) ?>"><?= nb_e($b['title']) ?> (<?= nb_e($b['board_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small>자식 메뉴에만 게시판을 연결하세요</small>
            </div>
            <div class="form-group">
                <label>직접 링크 (게시판 연결 안할 때)</label>
                <input type="text" id="menu_link" placeholder="https://...">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>순서</label>
                    <input type="number" id="menu_sort_order" value="0">
                </div>
                <div class="form-group">
                    <label>새창 열기</label>
                    <select id="menu_target">
                        <option value="">현재 창</option>
                        <option value="_blank">새 창</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>글자 색상</label>
                    <input type="text" id="menu_color" placeholder="#ffffff (비우면 기본색)" style="width:120px">
                </div>
            </div>
            <div class="form-group">
                <label>뱃지</label>
                <select id="menu_badge">
                    <option value="">없음</option>
                    <option value="dot-green">도트 (초록)</option>
                    <option value="dot-red">도트 (빨강)</option>
                    <option value="dot-blue">도트 (파랑)</option>
                    <option value="dot-orange">도트 (주황)</option>
                    <option value="new">NEW</option>
                    <option value="hot">HOT</option>
                </select>
                <small style="color:#94a3b8">자식 메뉴에 포인트 표시</small>
            </div>
            <div class="form-group" id="menu_active_wrap" style="display:none">
                <label><input type="checkbox" id="menu_is_active" checked> 활성화</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('menuModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>

<script>
function openMenuModal(){
    document.getElementById('menuModalTitle').textContent='메뉴 추가';
    document.getElementById('menu_edit_id').value='';
    document.getElementById('menu_title').value='';
    document.getElementById('menu_parent_id').value='0';
    document.getElementById('menu_board_id').value='';
    document.getElementById('menu_link').value='';
    document.getElementById('menu_sort_order').value='0';
    document.getElementById('menu_target').value='';
    document.getElementById('menu_color').value='';
    document.getElementById('menu_badge').value='';
    document.getElementById('menu_active_wrap').style.display='none';
    openModal('menuModal');
}
function editMenu(m){
    document.getElementById('menuModalTitle').textContent='메뉴 수정';
    document.getElementById('menu_edit_id').value=m.id;
    document.getElementById('menu_title').value=m.title;
    document.getElementById('menu_parent_id').value=m.parent_id;
    document.getElementById('menu_board_id').value=m.board_id||'';
    document.getElementById('menu_link').value=m.link||'';
    document.getElementById('menu_sort_order').value=m.sort_order;
    document.getElementById('menu_target').value=m.target||'';
    document.getElementById('menu_color').value=m.color||'';
    document.getElementById('menu_badge').value=m.badge||'';
    document.getElementById('menu_is_active').checked=m.is_active==1;
    document.getElementById('menu_active_wrap').style.display='block';
    openModal('menuModal');
}
function saveMenu(e){
    e.preventDefault();
    var editId=document.getElementById('menu_edit_id').value;
    var data=new FormData();
    data.append('action',editId?'menu_update':'menu_create');
    if(editId){data.append('id',editId);data.append('is_active',document.getElementById('menu_is_active').checked?1:0)}
    data.append('parent_id',document.getElementById('menu_parent_id').value);
    data.append('title',document.getElementById('menu_title').value);
    data.append('board_id',document.getElementById('menu_board_id').value);
    data.append('link',document.getElementById('menu_link').value);
    data.append('sort_order',document.getElementById('menu_sort_order').value);
    data.append('target',document.getElementById('menu_target').value);
    data.append('color',document.getElementById('menu_color').value);
    data.append('badge',document.getElementById('menu_badge').value);
    ajaxPost(data).then(function(res){if(res.success)location.reload();else alert(res.message||'오류')});
    return false;
}
function onParentChange(){
    var sel=document.getElementById('menu_parent_id');
    var hint=document.getElementById('parentHint');
    hint.style.display = sel.value==='0' && sel.options.length<=1 ? 'block' : 'none';
}
function deleteMenu(id,title){
    if(!confirm('"'+title+'" 메뉴를 삭제하시겠습니까?\n하위 메뉴도 함께 삭제됩니다.'))return;
    var data=new FormData();data.append('action','menu_delete');data.append('id',id);
    ajaxPost(data).then(function(res){if(res.success)location.reload()});
}
</script>

<?php adminFooter(); ?>

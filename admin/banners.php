<?php
/**
 * NuriBoard 관리자 - 배너 관리
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'banner_create' || $action === 'banner_update') {
        $image = '';

        // 이미지 업로드
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다.']);
                exit;
            }
            $dir = NB_ROOT . '/uploads/banners';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $newName = 'banner_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $newName);
            // 이미지 → webp 자동 변환
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                $webp = Upload::convertToWebpPublic($dir . '/' . $newName, $ext);
                if ($webp) $newName = basename($webp);
            }
            $image = 'uploads/banners/' . $newName;
        }

        if ($action === 'banner_create') {
            if (!$image) { echo json_encode(['success' => false, 'message' => '이미지를 선택하세요.']); exit; }
            Banner::create([
                'position' => $_POST['position'] ?? 'main',
                'title' => trim($_POST['title'] ?? ''),
                'image' => $image,
                'link' => trim($_POST['link'] ?? ''),
                'target' => $_POST['target'] ?? '_blank',
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ]);
            echo json_encode(['success' => true]);
        } else {
            $data = [
                'position' => $_POST['position'] ?? 'main',
                'title' => trim($_POST['title'] ?? ''),
                'link' => trim($_POST['link'] ?? ''),
                'target' => $_POST['target'] ?? '_blank',
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'is_active' => (int)($_POST['is_active'] ?? 1),
            ];
            if ($image) $data['image'] = $image;
            Banner::update((int)$_POST['id'], $data);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'banner_delete') {
        Banner::delete((int)$_POST['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$banners = Banner::listAll();

adminHeader('banners');
?>

<div class="page-header">
    <h1>배너 관리</h1>
    <button class="btn btn-primary" onclick="openBannerModal()">+ 배너 추가</button>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>ID</th><th>미리보기</th><th>제목</th><th>위치</th><th>링크</th><th>순서</th><th>상태</th><th>관리</th></tr></thead>
        <tbody>
        <?php foreach ($banners as $b): ?>
            <tr>
                <td><?= $b['id'] ?></td>
                <td><img src="../<?= nb_e($b['image']) ?>" style="height:40px;border-radius:4px"></td>
                <td><?= nb_e($b['title']) ?: '-' ?></td>
                <td>
                    <?php
                    $posNames = ['main'=>'메인 슬라이더','left'=>'좌측 날개','right-wing'=>'우측 날개','right'=>'우측 사이드바','middle'=>'게시판 중간','bottom'=>'게시판 끝'];
                    echo $posNames[$b['position']] ?? $b['position'];
                    ?>
                </td>
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
            <tr><td colspan="8" class="text-center">배너가 없습니다.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-body" style="font-size:13px;color:#64748b">
        <strong>배너 위치:</strong>
        <b>메인 상단</b> — 메인 페이지 상단 슬라이더 |
        <b>좌/우측 날개</b> — PC에서 좌우 세로 배너 |
        <b>우측 사이드바</b> — 메인 우측 박스 아래 |
        <b>게시판 중간</b> — 게시판 목록 절반 지점에 가로 풀폭 삽입 |
        <b>게시판 끝</b> — 게시판 목록 맨 아래에 가로 풀폭 삽입
        <br><span style="color:#94a3b8">※ 배너관리에서 활성/비활성 토글로 온/오프 가능, 1~2개 권장</span>
    </div>
</div>

<!-- 배너 추가/수정 모달 -->
<div class="modal" id="bannerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="bannerModalTitle">배너 추가</h3>
            <button class="modal-close" onclick="closeModal('bannerModal')">&times;</button>
        </div>
        <form onsubmit="return saveBanner(event)">
            <input type="hidden" id="banner_edit_id">
            <div class="form-group">
                <label>배너 위치</label>
                <select id="banner_position" onchange="showBannerSizeHint()">
                    <option value="main">메인 슬라이더 (히어로)</option>
                    <option value="left">좌측 날개</option>
                    <option value="right-wing">우측 날개</option>
                    <option value="right">우측 사이드바</option>
                    <option value="middle">게시판 중간 (가로 풀폭)</option>
                    <option value="bottom">게시판 끝 (가로 풀폭)</option>
                </select>
                <small id="bannerSizeHint" style="color:var(--primary);font-weight:600"></small>
            </div>
            <div class="form-group" id="wingFixedWrap" style="display:none">
                <label>스크롤 고정</label>
                <select id="banner_wing_fixed">
                    <option value="0">고정 안함 (스크롤 시 같이 올라감)</option>
                    <option value="1">고정 (스크롤해도 화면에 고정)</option>
                </select>
            </div>
            <div class="form-group">
                <label>배너 이미지</label>
                <input type="file" id="banner_image" accept="image/*">
                <div id="banner_preview" style="margin-top:8px"></div>
            </div>
            <div class="form-group">
                <label>제목 (선택)</label>
                <input type="text" id="banner_title" placeholder="배너 설명">
            </div>
            <div class="form-group">
                <label>클릭 시 이동할 링크</label>
                <input type="text" id="banner_link" placeholder="https://...">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>순서</label>
                    <input type="number" id="banner_sort_order" value="0">
                </div>
                <div class="form-group">
                    <label>새창 열기</label>
                    <select id="banner_target">
                        <option value="_blank">새 창</option>
                        <option value="">현재 창</option>
                    </select>
                </div>
            </div>
            <div class="form-group" id="banner_active_wrap" style="display:none">
                <label><input type="checkbox" id="banner_is_active" checked> 활성화</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('bannerModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>

<script>
var bannerSizes={'main':'권장: 1200 x 240px (가로 넓은 이미지)','left':'권장: 160 x 600px (세로 긴 이미지)','right-wing':'권장: 160 x 600px (세로 긴 이미지)','right':'권장: 가로 250px 이하','middle':'권장: 1000 x 150px (게시판 카드 사이에 가로로 삽입)','bottom':'권장: 1000 x 150px (게시판 목록 맨 아래에 가로로 삽입)'};
function showBannerSizeHint(){var v=document.getElementById('banner_position').value;document.getElementById('bannerSizeHint').textContent=bannerSizes[v]||'';document.getElementById('wingFixedWrap').style.display=(v==='left'||v==='right-wing')?'':'none';}
function openBannerModal(){
    document.getElementById('bannerModalTitle').textContent='배너 추가';
    document.getElementById('banner_edit_id').value='';
    document.getElementById('banner_position').value='main';
    document.getElementById('banner_image').value='';
    document.getElementById('banner_preview').innerHTML='';
    document.getElementById('banner_title').value='';
    document.getElementById('banner_link').value='';
    document.getElementById('banner_sort_order').value='0';
    document.getElementById('banner_target').value='_blank';
    document.getElementById('banner_active_wrap').style.display='none';
    showBannerSizeHint();
    openModal('bannerModal');
}
function editBanner(b){
    document.getElementById('bannerModalTitle').textContent='배너 수정';
    document.getElementById('banner_edit_id').value=b.id;
    document.getElementById('banner_position').value=b.position;
    document.getElementById('banner_image').value='';
    document.getElementById('banner_preview').innerHTML='<img src="../'+b.image+'" style="height:60px;border-radius:4px">';
    document.getElementById('banner_title').value=b.title||'';
    document.getElementById('banner_link').value=b.link||'';
    document.getElementById('banner_sort_order').value=b.sort_order;
    document.getElementById('banner_target').value=b.target||'_blank';
    document.getElementById('banner_is_active').checked=b.is_active==1;
    document.getElementById('banner_active_wrap').style.display='block';
    showBannerSizeHint();
    openModal('bannerModal');
}
function saveBanner(e){
    e.preventDefault();
    var editId=document.getElementById('banner_edit_id').value;
    var data=new FormData();
    data.append('action',editId?'banner_update':'banner_create');
    if(editId){data.append('id',editId);data.append('is_active',document.getElementById('banner_is_active').checked?1:0)}
    var file=document.getElementById('banner_image').files[0];
    if(file) data.append('image',file);
    data.append('position',document.getElementById('banner_position').value);
    data.append('title',document.getElementById('banner_title').value);
    data.append('link',document.getElementById('banner_link').value);
    data.append('sort_order',document.getElementById('banner_sort_order').value);
    data.append('target',document.getElementById('banner_target').value);
    ajaxPost(data).then(function(res){if(res.success)location.reload();else alert(res.message||'오류')});
    return false;
}
function deleteBanner(id){
    if(!confirm('배너를 삭제하시겠습니까?'))return;
    var data=new FormData();data.append('action','banner_delete');data.append('id',id);
    ajaxPost(data).then(function(res){if(res.success)location.reload()});
}
</script>

<?php adminFooter(); ?>

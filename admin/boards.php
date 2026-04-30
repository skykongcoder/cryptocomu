<?php
/**
 * NuriBoard 관리자 - 게시판 관리
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'board_create') {
        $id = Board::create([
            'board_id' => trim($_POST['board_id'] ?? ''),
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'board_type' => $_POST['board_type'] ?? 'normal',
            'list_count' => (int)($_POST['list_count'] ?? 20),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'write_level' => (int)($_POST['write_level'] ?? 2),
            'comment_level' => (int)($_POST['comment_level'] ?? 2),
            'allow_delete' => (int)($_POST['allow_delete'] ?? 1),
            'allow_comment_delete' => (int)($_POST['allow_comment_delete'] ?? 1),
            'point_write_cost' => (int)($_POST['point_write_cost'] ?? 0),
            'allow_paid_file' => (int)($_POST['allow_paid_file'] ?? 0),
        ]);
        AdminLog::write('board_create', 'board', $id, trim($_POST['title'] ?? ''));
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'board_update') {
        Board::update((int)$_POST['id'], [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'board_type' => $_POST['board_type'] ?? 'normal',
            'list_count' => (int)($_POST['list_count'] ?? 20),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => (int)($_POST['is_active'] ?? 1),
            'write_level' => (int)($_POST['write_level'] ?? 2),
            'comment_level' => (int)($_POST['comment_level'] ?? 2),
            'allow_delete' => (int)($_POST['allow_delete'] ?? 1),
            'allow_comment_delete' => (int)($_POST['allow_comment_delete'] ?? 1),
            'point_write_cost' => (int)($_POST['point_write_cost'] ?? 0),
            'allow_paid_file' => (int)($_POST['allow_paid_file'] ?? 0),
        ]);
        AdminLog::write('board_update', 'board', (int)$_POST['id'], trim($_POST['title'] ?? ''));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'board_delete') {
        Board::delete((int)$_POST['id']);
        AdminLog::write('board_delete', 'board', (int)$_POST['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$boards = Board::listAll();

adminHeader('boards');
?>

<div class="page-header">
    <h1>게시판 관리</h1>
    <button class="btn btn-primary" onclick="openBoardModal()">+ 게시판 추가</button>
</div>
<div class="card">
    <table class="table">
        <thead><tr><th>ID</th><th>코드</th><th>게시판명</th><th>글 수</th><th>상태</th><th>정렬</th><th>관리</th></tr></thead>
        <tbody>
        <?php foreach ($boards as $board): ?>
            <tr>
                <td><?= $board['id'] ?></td>
                <td><a href="../board/<?= nb_e($board['board_id']) ?>" target="_blank" style="text-decoration:none"><code style="cursor:pointer;color:#2563eb"><?= nb_e($board['board_id']) ?></code></a></td>
                <td><?= nb_e($board['title']) ?></td>
                <td><?= number_format(Board::postCount($board['board_id'])) ?></td>
                <td><span class="badge <?= $board['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $board['is_active'] ? '활성' : '비활성' ?></span></td>
                <td><?= $board['sort_order'] ?></td>
                <td>
                    <button class="btn btn-sm" onclick='editBoard(<?= json_encode($board, JSON_UNESCAPED_UNICODE) ?>)'>수정</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteBoard(<?= $board['id'] ?>, '<?= nb_e($board['title']) ?>')">삭제</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 게시판 추가/수정 모달 -->
<div class="modal" id="boardModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="boardModalTitle">게시판 추가</h3>
            <button class="modal-close" onclick="closeModal('boardModal')">&times;</button>
        </div>
        <form id="boardForm" onsubmit="return saveBoard(event)">
            <input type="hidden" id="board_edit_id">
            <div class="form-group">
                <label>게시판 코드 (영문)</label>
                <input type="text" id="board_board_id" required pattern="[a-z0-9_\-]+" placeholder="예: free, qna, notice">
                <small>영소문자, 숫자, 하이픈, 언더스코어만 가능</small>
            </div>
            <div class="form-group">
                <label>게시판명</label>
                <input type="text" id="board_title" required placeholder="예: 자유게시판">
            </div>
            <div class="form-group">
                <label>설명</label>
                <input type="text" id="board_description" placeholder="게시판 설명">
            </div>
            <div class="form-group">
                <label>게시판 타입</label>
                <select id="board_type">
                    <option value="normal">일반 게시판</option>
                    <option value="gallery">이미지 게시판 (갤러리)</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>한 페이지에 보여줄 글 수</label>
                    <input type="number" id="board_list_count" value="20" min="5" max="100">
                </div>
                <div class="form-group">
                    <label>정렬 순서</label>
                    <input type="number" id="board_sort_order" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>누가 글을 쓸 수 있나요? (레벨)</label>
                    <input type="number" id="board_write_level" value="2" min="1" max="10">
                </div>
                <div class="form-group">
                    <label>누가 댓글을 달 수 있나요? (레벨)</label>
                    <input type="number" id="board_comment_level" value="2" min="1" max="10">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>회원 글 삭제</label>
                    <select id="board_allow_delete">
                        <option value="1">허용 (본인 글 삭제 가능)</option>
                        <option value="0">불가 (관리자만 삭제)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>회원 댓글 삭제</label>
                    <select id="board_allow_comment_delete">
                        <option value="1">허용 (본인 댓글 삭제 가능)</option>
                        <option value="0">불가 (관리자만 삭제)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>글쓰기 소모 포인트</label>
                    <input type="number" id="board_point_write_cost" value="0" min="0" style="width:120px">
                    <small>0이면 무료</small>
                </div>
                <div class="form-group">
                    <label>유료 첨부파일</label>
                    <select id="board_allow_paid_file">
                        <option value="0">사용안함</option>
                        <option value="1">사용 (글쓸 때 파일에 포인트 설정)</option>
                    </select>
                    <small>회원이 파일에 다운로드 포인트를 설정할 수 있습니다</small>
                </div>
            </div>
            <div class="form-group" id="board_active_wrap" style="display:none">
                <label><input type="checkbox" id="board_is_active" checked> 활성화</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('boardModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBoardModal(){document.getElementById('boardModalTitle').textContent='게시판 추가';document.getElementById('board_edit_id').value='';document.getElementById('board_board_id').value='';document.getElementById('board_board_id').disabled=false;document.getElementById('board_title').value='';document.getElementById('board_description').value='';document.getElementById('board_type').value='normal';document.getElementById('board_list_count').value='20';document.getElementById('board_sort_order').value='0';document.getElementById('board_write_level').value='2';document.getElementById('board_comment_level').value='2';document.getElementById('board_allow_delete').value='1';document.getElementById('board_allow_comment_delete').value='1';document.getElementById('board_point_write_cost').value='0';document.getElementById('board_allow_paid_file').value='0';document.getElementById('board_active_wrap').style.display='none';openModal('boardModal')}
function editBoard(b){document.getElementById('boardModalTitle').textContent='게시판 수정';document.getElementById('board_edit_id').value=b.id;document.getElementById('board_board_id').value=b.board_id;document.getElementById('board_board_id').disabled=true;document.getElementById('board_title').value=b.title;document.getElementById('board_description').value=b.description||'';document.getElementById('board_type').value=b.board_type||'normal';document.getElementById('board_list_count').value=b.list_count;document.getElementById('board_sort_order').value=b.sort_order;document.getElementById('board_write_level').value=b.write_level;document.getElementById('board_comment_level').value=b.comment_level;document.getElementById('board_allow_delete').value=b.allow_delete!=null?b.allow_delete:1;document.getElementById('board_allow_comment_delete').value=b.allow_comment_delete!=null?b.allow_comment_delete:1;document.getElementById('board_point_write_cost').value=b.point_write_cost||0;document.getElementById('board_allow_paid_file').value=b.allow_paid_file||0;document.getElementById('board_is_active').checked=b.is_active==1;document.getElementById('board_active_wrap').style.display='block';openModal('boardModal')}
function saveBoard(e){e.preventDefault();var editId=document.getElementById('board_edit_id').value;var data=new FormData();data.append('action',editId?'board_update':'board_create');if(editId){data.append('id',editId);data.append('is_active',document.getElementById('board_is_active').checked?1:0)}else{data.append('board_id',document.getElementById('board_board_id').value)}data.append('title',document.getElementById('board_title').value);data.append('description',document.getElementById('board_description').value);data.append('board_type',document.getElementById('board_type').value);data.append('list_count',document.getElementById('board_list_count').value);data.append('sort_order',document.getElementById('board_sort_order').value);data.append('write_level',document.getElementById('board_write_level').value);data.append('comment_level',document.getElementById('board_comment_level').value);data.append('allow_delete',document.getElementById('board_allow_delete').value);data.append('allow_comment_delete',document.getElementById('board_allow_comment_delete').value);data.append('point_write_cost',document.getElementById('board_point_write_cost').value);data.append('allow_paid_file',document.getElementById('board_allow_paid_file').value);ajaxPost(data).then(function(res){if(res.success){location.reload()}else{alert(res.message||'오류가 발생했습니다.')}});return false}
function deleteBoard(id,title){if(!confirm('"'+title+'" 게시판을 삭제하시겠습니까?\n게시판의 모든 글과 댓글이 삭제됩니다.'))return;var data=new FormData();data.append('action','board_delete');data.append('id',id);ajaxPost(data).then(function(res){if(res.success)location.reload()})}
</script>

<?php adminFooter(); ?>

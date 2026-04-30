<?php
/**
 * NuriBoard 관리자 - 위젯 관리
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'widget_create') {
        $config = [];
        // 위젯 타입별 config 구성
        $type = $_POST['widget_type'] ?? 'html';
        if ($type === 'html') {
            $config['content'] = $_POST['config_content'] ?? '';
        } elseif ($type === 'latest_posts' || $type === 'popular_posts') {
            $config['board_id'] = $_POST['config_board_id'] ?? '';
            $config['count'] = (int)($_POST['config_count'] ?? 5);
        } elseif ($type === 'board_preview') {
            $config['board_id'] = $_POST['config_board_id'] ?? '';
            $config['count'] = (int)($_POST['config_count'] ?? 5);
        }
        $id = Widget::create([
            'widget_type' => $type,
            'position' => $_POST['position'] ?? 'center',
            'title' => trim($_POST['title'] ?? ''),
            'config' => $config,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => (int)($_POST['is_active'] ?? 1),
        ]);
        AdminLog::write('widget_create', 'widget', $id, $_POST['title'] ?? '');
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'widget_update') {
        $id = (int)$_POST['id'];
        $type = $_POST['widget_type'] ?? 'html';
        $config = [];
        if ($type === 'html') {
            $config['content'] = $_POST['config_content'] ?? '';
        } elseif ($type === 'latest_posts' || $type === 'popular_posts') {
            $config['board_id'] = $_POST['config_board_id'] ?? '';
            $config['count'] = (int)($_POST['config_count'] ?? 5);
        } elseif ($type === 'board_preview') {
            $config['board_id'] = $_POST['config_board_id'] ?? '';
            $config['count'] = (int)($_POST['config_count'] ?? 5);
        }
        Widget::update($id, [
            'widget_type' => $type,
            'position' => $_POST['position'] ?? 'center',
            'title' => trim($_POST['title'] ?? ''),
            'config' => $config,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => (int)($_POST['is_active'] ?? 1),
        ]);
        AdminLog::write('widget_update', 'widget', $id, $_POST['title'] ?? '');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'widget_delete') {
        $id = (int)$_POST['id'];
        Widget::delete($id);
        AdminLog::write('widget_delete', 'widget', $id, '');
        echo json_encode(['success' => true]);
        exit;
    }

    // 이미지 업로드 (배너/슬라이더용)
    if ($action === 'widget_upload_image') {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => '파일을 선택하세요.']);
            exit;
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다.']);
            exit;
        }
        $dir = NB_ROOT . '/uploads/widgets';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $newName = 'w_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $newName);
        echo json_encode(['success' => true, 'path' => 'uploads/widgets/' . $newName]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$widgets = Widget::listAll();
$boards = Board::listAll();

adminHeader('widgets');
?>

<div class="page-header">
    <h1>위젯 관리</h1>
    <button class="btn btn-primary" onclick="openWidgetModal()">+ 위젯 추가</button>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px">ID</th>
                <th>제목</th>
                <th style="width:120px">유형</th>
                <th style="width:120px">위치</th>
                <th style="width:50px">순서</th>
                <th style="width:80px">상태</th>
                <th style="width:140px">관리</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($widgets)): ?>
            <tr><td colspan="7" class="text-center" style="padding:40px;color:#94a3b8">등록된 위젯이 없습니다.</td></tr>
        <?php endif; ?>
        <?php foreach ($widgets as $w): ?>
            <tr>
                <td class="text-center" style="color:#94a3b8"><?= $w['id'] ?></td>
                <td style="font-weight:500"><?= nb_e($w['title'] ?: '(제목 없음)') ?></td>
                <td><span class="badge badge-green"><?= nb_e(Widget::typeLabel($w['widget_type'])) ?></span></td>
                <td style="font-size:13px;color:#64748b"><?= nb_e(Widget::positionLabel($w['position'])) ?></td>
                <td class="text-center"><?= $w['sort_order'] ?></td>
                <td><span class="badge <?= $w['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $w['is_active'] ? '활성' : '비활성' ?></span></td>
                <td>
                    <button class="btn btn-sm" onclick='editWidget(<?= json_encode($w, JSON_UNESCAPED_UNICODE) ?>)'>수정</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteWidget(<?= $w['id'] ?>,'<?= nb_e($w['title']) ?>')">삭제</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-body" style="font-size:13px;color:#475569;line-height:1.8">
        <strong>위젯 위치 안내</strong>
        <div style="margin-top:8px;padding:12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
            <strong>상단</strong> - 메인 콘텐츠 위 전체 너비<br>
            <strong>중앙</strong> - 왼쪽 콘텐츠 영역 하단 (게시판 카드 아래)<br>
            <strong>우측</strong> - 우측 사이드바 하단
        </div>
    </div>
</div>

<!-- 위젯 추가/수정 모달 -->
<div class="modal" id="widgetModal">
    <div class="modal-content" style="max-width:550px">
        <div class="modal-header">
            <h3 id="widgetModalTitle">위젯 추가</h3>
            <button class="modal-close" onclick="closeModal('widgetModal')">&times;</button>
        </div>
        <form onsubmit="return saveWidget(event)">
            <input type="hidden" id="widget_edit_id">
            <div class="form-group">
                <label>위젯 제목</label>
                <input type="text" id="widget_title" placeholder="위젯 제목 (비워도 됨)">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>위젯 유형</label>
                    <select id="widget_type" onchange="onWidgetTypeChange()">
                        <option value="html">HTML 자유</option>
                        <option value="latest_posts">최근글</option>
                        <option value="popular_posts">인기글</option>
                        <option value="board_preview">게시판 미리보기</option>
                        <option value="banner">이미지 배너</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>위치</label>
                    <select id="widget_position">
                        <option value="top">상단 (전체너비)</option>
                        <option value="center">중앙</option>
                        <option value="right">우측 사이드</option>
                    </select>
                </div>
            </div>

            <!-- HTML 자유 설정 -->
            <div class="widget-config" id="config_html">
                <div class="form-group">
                    <label>HTML 내용</label>
                    <textarea id="config_content" rows="6" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:monospace;resize:vertical" placeholder="HTML 코드를 입력하세요"></textarea>
                </div>
            </div>

            <!-- 게시판 관련 설정 -->
            <div class="widget-config" id="config_board" style="display:none">
                <div class="form-row">
                    <div class="form-group">
                        <label>게시판</label>
                        <select id="config_board_id">
                            <option value="">전체</option>
                            <?php foreach ($boards as $b): ?>
                                <option value="<?= nb_e($b['board_id']) ?>"><?= nb_e($b['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>표시 개수</label>
                        <input type="number" id="config_count" value="5" min="1" max="20">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>순서</label>
                    <input type="number" id="widget_sort_order" value="0">
                </div>
                <div class="form-group">
                    <label>상태</label>
                    <select id="widget_is_active">
                        <option value="1">활성</option>
                        <option value="0">비활성</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('widgetModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>

<script>
function onWidgetTypeChange() {
    var type = document.getElementById('widget_type').value;
    document.querySelectorAll('.widget-config').forEach(function(el) { el.style.display = 'none'; });
    if (type === 'html' || type === 'banner') {
        document.getElementById('config_html').style.display = '';
    } else {
        document.getElementById('config_board').style.display = '';
    }
}

function openWidgetModal() {
    document.getElementById('widgetModalTitle').textContent = '위젯 추가';
    document.getElementById('widget_edit_id').value = '';
    document.getElementById('widget_title').value = '';
    document.getElementById('widget_type').value = 'html';
    document.getElementById('widget_position').value = 'center';
    document.getElementById('config_content').value = '';
    document.getElementById('config_board_id').value = '';
    document.getElementById('config_count').value = '5';
    document.getElementById('widget_sort_order').value = '0';
    document.getElementById('widget_is_active').value = '1';
    onWidgetTypeChange();
    openModal('widgetModal');
}

function editWidget(w) {
    document.getElementById('widgetModalTitle').textContent = '위젯 수정';
    document.getElementById('widget_edit_id').value = w.id;
    document.getElementById('widget_title').value = w.title || '';
    document.getElementById('widget_type').value = w.widget_type;
    document.getElementById('widget_position').value = w.position;
    document.getElementById('widget_sort_order').value = w.sort_order;
    document.getElementById('widget_is_active').value = w.is_active;

    var cfg = w.config || {};
    document.getElementById('config_content').value = cfg.content || '';
    document.getElementById('config_board_id').value = cfg.board_id || '';
    document.getElementById('config_count').value = cfg.count || 5;

    onWidgetTypeChange();
    openModal('widgetModal');
}

function saveWidget(e) {
    e.preventDefault();
    var editId = document.getElementById('widget_edit_id').value;
    var data = new FormData();
    data.append('action', editId ? 'widget_update' : 'widget_create');
    if (editId) data.append('id', editId);
    data.append('title', document.getElementById('widget_title').value);
    data.append('widget_type', document.getElementById('widget_type').value);
    data.append('position', document.getElementById('widget_position').value);
    data.append('sort_order', document.getElementById('widget_sort_order').value);
    data.append('is_active', document.getElementById('widget_is_active').value);

    // config
    data.append('config_content', document.getElementById('config_content').value);
    data.append('config_board_id', document.getElementById('config_board_id').value);
    data.append('config_count', document.getElementById('config_count').value);

    ajaxPost(data).then(function(res) {
        if (res.success) location.reload();
        else alert(res.message || '오류');
    });
    return false;
}

function deleteWidget(id, title) {
    if (!confirm('"' + (title || '위젯') + '" 을(를) 삭제하시겠습니까?')) return;
    var data = new FormData();
    data.append('action', 'widget_delete');
    data.append('id', id);
    ajaxPost(data).then(function(res) { if (res.success) location.reload(); });
}
</script>

<?php adminFooter(); ?>

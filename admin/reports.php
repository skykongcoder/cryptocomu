<?php
/**
 * NuriBoard 관리자 - 신고 관리
 */
require_once NB_ROOT . '/core/Report.php';

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'resolve') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['approved', 'rejected'])) {
            echo json_encode(['success' => false]); exit;
        }
        $report = Report::find($id);
        if (!$report) { echo json_encode(['success' => false]); exit; }

        Report::resolve($id, $status, Auth::id());
        AdminLog::write('report_' . ($status === 'approved' ? 'approve' : 'reject'), 'report', $id);

        // 신고 승인 시 콘텐츠 숨김 처리
        if ($status === 'approved') {
            if ($report['type'] === 'post') {
                Post::update($report['target_id'], ['is_hidden' => 1]);
            } elseif ($report['type'] === 'comment') {
                Comment::hide($report['target_id']);
            }
        }
        echo json_encode(['success' => true]); exit;
    }

    echo json_encode(['success' => false]); exit;
}

$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$data = Report::list($page, 20, $statusFilter);
$pendingCount = Report::pendingCount();

adminHeader('reports');
?>

<div class="page-header">
    <h1>신고 관리 <?php if ($pendingCount): ?><span style="font-size:14px;color:#dc2626;font-weight:normal">(미처리 <?= $pendingCount ?>건)</span><?php endif; ?></h1>
</div>

<!-- 필터 탭 -->
<div style="display:flex;gap:4px;margin-bottom:16px">
    <?php foreach (['' => '전체', 'pending' => '미처리', 'approved' => '처리완료', 'rejected' => '기각'] as $val => $label): ?>
    <a href="?page=reports&status=<?= $val ?>"
       style="padding:7px 16px;border-radius:6px;font-size:13px;text-decoration:none;<?= $statusFilter === $val ? 'background:var(--primary);color:#fff' : 'background:#fff;color:#475569;border:1px solid #d1d5db' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width:60px">번호</th>
                <th style="width:70px">종류</th>
                <th>대상 내용</th>
                <th style="width:120px">신고 사유</th>
                <th style="width:90px">신고자</th>
                <th style="width:90px">신고일</th>
                <th style="width:80px">상태</th>
                <th style="width:130px">처리</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data['items'])): ?>
            <tr><td colspan="8" class="text-center" style="padding:40px;color:#94a3b8">신고 내역이 없습니다.</td></tr>
        <?php endif; ?>
        <?php foreach ($data['items'] as $r): ?>
        <?php
            // 대상 내용 미리보기
            $preview = '';
            $targetUrl = '';
            if ($r['type'] === 'post') {
                $target = Post::find($r['target_id']);
                if ($target) {
                    $preview = mb_strimwidth($target['title'], 0, 40, '...');
                    $targetUrl = nb_url("board/{$target['board_id']}/{$target['id']}");
                }
            } elseif ($r['type'] === 'comment') {
                $prefix = DB::getPrefix();
                $target = DB::fetch("SELECT c.*, p.board_id FROM {$prefix}comments c LEFT JOIN {$prefix}posts p ON c.post_id = p.id WHERE c.id = ?", [$r['target_id']]);
                if ($target) {
                    $preview = mb_strimwidth($target['content'], 0, 40, '...');
                    $targetUrl = nb_url("board/{$target['board_id']}/{$target['post_id']}") . '#comments';
                }
            }
            $statusLabel = ['pending' => ['미처리', '#f59e0b'], 'approved' => ['처리완료', '#059669'], 'rejected' => ['기각', '#94a3b8']];
            [$slabel, $scolor] = $statusLabel[$r['status']] ?? ['?', '#999'];
        ?>
        <tr id="row-<?= $r['id'] ?>">
            <td class="text-center" style="color:#94a3b8"><?= $r['id'] ?></td>
            <td class="text-center">
                <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;<?= $r['type'] === 'post' ? 'background:#eff6ff;color:#2563eb' : 'background:#fdf4ff;color:#7c3aed' ?>">
                    <?= $r['type'] === 'post' ? '게시글' : '댓글' ?>
                </span>
            </td>
            <td>
                <?php if ($targetUrl && $preview): ?>
                    <a href="<?= nb_e($targetUrl) ?>" target="_blank" style="color:var(--text);font-size:13px"><?= nb_e($preview) ?></a>
                <?php elseif ($preview): ?>
                    <span style="font-size:13px;color:#94a3b8"><?= nb_e($preview) ?> (삭제됨)</span>
                <?php else: ?>
                    <span style="color:#94a3b8;font-size:13px">삭제된 콘텐츠</span>
                <?php endif; ?>
            </td>
            <td style="font-size:13px"><?= nb_e($r['reason']) ?></td>
            <td style="font-size:13px;color:#64748b"><?= nb_e($r['reporter_name'] ?? '탈퇴회원') ?></td>
            <td style="font-size:12px;color:#94a3b8"><?= date('m.d H:i', strtotime($r['created_at'])) ?></td>
            <td class="text-center">
                <span style="font-size:12px;font-weight:600;color:<?= $scolor ?>"><?= $slabel ?></span>
            </td>
            <td class="text-center">
                <?php if ($r['status'] === 'pending'): ?>
                <div style="display:flex;gap:4px;justify-content:center">
                    <button class="btn btn-sm btn-danger" onclick="resolve(<?= $r['id'] ?>,'approved')">콘텐츠 숨김</button>
                    <button class="btn btn-sm" onclick="resolve(<?= $r['id'] ?>,'rejected')">기각</button>
                </div>
                <?php else: ?>
                <span style="font-size:12px;color:#94a3b8"><?= nb_e($r['admin_name'] ?? '') ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($data['total_pages'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
            <a href="?page=reports&status=<?= nb_e($statusFilter) ?>&p=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function resolve(id, status) {
    var msg = status === 'approved' ? '신고를 승인하고 해당 콘텐츠를 숨기겠습니까?' : '신고를 기각하겠습니까?';
    if (!confirm(msg)) return;
    var fd = new FormData();
    fd.append('action', 'resolve');
    fd.append('id', id);
    fd.append('status', status);
    ajaxPost(fd).then(function(r) {
        if (r.success) {
            var row = document.getElementById('row-' + id);
            if (row) {
                var statusCell = row.querySelector('td:nth-child(7) span');
                var actionCell = row.querySelector('td:nth-child(8)');
                if (status === 'approved') { statusCell.textContent = '처리완료'; statusCell.style.color = '#059669'; }
                else { statusCell.textContent = '기각'; statusCell.style.color = '#94a3b8'; }
                actionCell.innerHTML = '<span style="font-size:12px;color:#94a3b8">처리됨</span>';
            }
        } else { alert('처리 중 오류가 발생했습니다.'); }
    });
}
</script>

<?php adminFooter(); ?>

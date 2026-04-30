<?php
/**
 * 포인트 출금 관리자 설정
 */
$_pwFile = __DIR__ . '/config.json';
$_pwRaw = file_exists($_pwFile) ? json_decode(file_get_contents($_pwFile), true) : [];
if (!is_array($_pwRaw)) $_pwRaw = [];
$_pw = array_merge([
    'enabled' => '1',
    'min_amount' => '10000',
    'amount_unit' => '10000',
    'point_to_won' => '1',
    'page_path' => 'withdraw',
    'menu_label' => '포인트 출금',
    'admin_member_ids' => '',
    'notify_admin' => '1',
    'notify_member_on_complete' => '1',
    'notify_member_on_reject' => '1',
    'page_per' => '10',
    'max_pending_per_member' => '3',
], $_pwRaw);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw_save'])) {
    foreach (['enabled','min_amount','amount_unit','point_to_won','page_path','menu_label',
              'admin_member_ids','notify_admin','notify_member_on_complete','notify_member_on_reject',
              'page_per','max_pending_per_member'] as $k) {
        if (in_array($k, ['enabled','notify_admin','notify_member_on_complete','notify_member_on_reject'])) {
            $_pw[$k] = isset($_POST[$k]) ? '1' : '0';
        } else {
            $_pw[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : $_pw[$k];
        }
    }
    file_put_contents($_pwFile, json_encode($_pw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = '<div class="alert success">설정이 저장되었습니다.</div>';
}

// 신청 목록 로드
$listItems = []; $statCount = ['pending'=>0,'completed'=>0,'rejected'=>0]; $totalRequests = 0;
$filter = $_GET['filter'] ?? 'pending';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if (class_exists('DB')) {
    try {
        $prefix = DB::getPrefix();
        $stats = DB::fetchAll("SELECT status, COUNT(*) AS c FROM {$prefix}pw_withdrawals GROUP BY status") ?: [];
        foreach ($stats as $s) {
            if (isset($statCount[$s['status']])) $statCount[$s['status']] = (int)$s['c'];
        }

        $where = '';
        $params = [];
        if (in_array($filter, ['pending','completed','rejected'])) {
            $where = "WHERE w.status = ?";
            $params[] = $filter;
        }
        $tot = DB::fetch("SELECT COUNT(*) AS c FROM {$prefix}pw_withdrawals w {$where}", $params);
        $totalRequests = (int)($tot['c'] ?? 0);

        $listItems = DB::fetchAll(
            "SELECT w.*, m.nickname, m.user_id, m.point AS current_point
             FROM {$prefix}pw_withdrawals w
             LEFT JOIN {$prefix}members m ON w.member_id = m.id
             {$where}
             ORDER BY w.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        ) ?: [];
    } catch (Exception $e) {}
}
$totalPages = max(1, ceil($totalRequests / $perPage));

$tab = $_GET['tab'] ?? 'list';
$baseUrl = '?page=plugins&settings=' . (int)($_GET['settings'] ?? 0);
?>
<style>
.pw-nav{display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:20px}
.pw-nav a{padding:10px 16px;text-decoration:none;color:#6b7280;font-weight:500;border-bottom:2px solid transparent;margin-bottom:-2px}
.pw-nav a.active{color:#22c55e;border-color:#22c55e}
.pw-section{background:#fff;padding:20px;border-radius:8px;margin-bottom:16px;border:1px solid #e5e7eb}
.pw-section h3{margin:0 0 12px;font-size:15px;font-weight:600}
.pw-stat-row{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.pw-stat{flex:1;min-width:120px;background:#fff;padding:14px;border-radius:10px;border:1px solid #e5e7eb;text-align:center}
.pw-stat .n{font-size:22px;font-weight:700;color:#22c55e}
.pw-stat .l{font-size:12px;color:#6b7280;margin-top:2px}
.pw-filter{display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap}
.pw-filter a{padding:6px 12px;border:1px solid #e5e7eb;border-radius:18px;font-size:12px;text-decoration:none;color:#6b7280}
.pw-filter a.active{background:#22c55e;color:#fff;border-color:#22c55e}
.pw-table{width:100%;border-collapse:collapse;font-size:13px}
.pw-table th{background:#f9fafb;padding:10px 8px;text-align:left;font-weight:600;color:#374151;font-size:12px;border-bottom:1px solid #e5e7eb}
.pw-table td{padding:10px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top}
.pw-table tr:hover{background:#f9fafb}
.pw-status{font-size:11px;padding:3px 8px;border-radius:10px;font-weight:600;display:inline-block}
.pw-status.pending{background:#fef3c7;color:#92400e}
.pw-status.completed{background:#dcfce7;color:#15803d}
.pw-status.rejected{background:#fee2e2;color:#991b1b}
.pw-actions button{font-size:11px;padding:4px 8px;border-radius:5px;cursor:pointer;margin-right:4px;border:1px solid}
.pw-btn-complete{background:#22c55e;color:#fff;border-color:#22c55e}
.pw-btn-reject{background:#fff;color:#dc2626;border-color:#fecaca}
.pw-btn-del{background:#fff;color:#9ca3af;border-color:#e5e7eb}
.pw-pagination{display:flex;justify-content:center;gap:4px;margin-top:14px}
.pw-pagination a, .pw-pagination span{padding:5px 10px;font-size:12px;border:1px solid #e5e7eb;border-radius:6px;text-decoration:none;color:#374151}
.pw-pagination a.active, .pw-pagination span.active{background:#22c55e;color:#fff;border-color:#22c55e}
.pw-account{font-family:monospace;font-size:12px}
</style>

<?= $msg ?>

<div class="pw-nav">
    <a href="<?= $baseUrl ?>&tab=list" class="<?= $tab==='list'?'active':'' ?>">신청 목록</a>
    <a href="<?= $baseUrl ?>&tab=settings" class="<?= $tab==='settings'?'active':'' ?>">설정</a>
    <a href="/?pw_page=1" target="_blank" style="margin-left:auto;color:#22c55e">사용자 페이지 보기 →</a>
</div>

<?php if ($tab === 'list'): ?>

<div class="pw-stat-row">
    <div class="pw-stat"><div class="n"><?= number_format($statCount['pending']) ?></div><div class="l">대기중</div></div>
    <div class="pw-stat"><div class="n"><?= number_format($statCount['completed']) ?></div><div class="l">완료</div></div>
    <div class="pw-stat"><div class="n"><?= number_format($statCount['rejected']) ?></div><div class="l">거절</div></div>
</div>

<div class="pw-filter">
    <a href="<?= $baseUrl ?>&tab=list&filter=pending" class="<?= $filter==='pending'?'active':'' ?>">대기중</a>
    <a href="<?= $baseUrl ?>&tab=list&filter=completed" class="<?= $filter==='completed'?'active':'' ?>">완료</a>
    <a href="<?= $baseUrl ?>&tab=list&filter=rejected" class="<?= $filter==='rejected'?'active':'' ?>">거절</a>
    <a href="<?= $baseUrl ?>&tab=list&filter=all" class="<?= $filter==='all'?'active':'' ?>">전체</a>
    <span style="margin-left:auto;font-size:12px;color:#6b7280;align-self:center">총 <?= number_format($totalRequests) ?>건</span>
</div>

<div class="pw-section" style="padding:0;overflow:hidden">
    <?php if (empty($listItems)): ?>
        <div style="padding:40px;text-align:center;color:#9ca3af;font-size:13px">신청 내역이 없습니다.</div>
    <?php else: ?>
    <table class="pw-table">
        <thead>
            <tr>
                <th style="width:30px"><input type="checkbox" id="pwAdminCheckAll"></th>
                <th>회원</th>
                <th>금액</th>
                <th>은행/예금주/계좌</th>
                <th>신청/처리</th>
                <th>상태</th>
                <th>처리</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($listItems as $w): ?>
            <tr data-id="<?= (int)$w['id'] ?>">
                <td><input type="checkbox" class="pw-admin-check" value="<?= (int)$w['id'] ?>"></td>
                <td>
                    <strong><?= htmlspecialchars($w['nickname'] ?? '?') ?></strong><br>
                    <small style="color:#9ca3af"><?= htmlspecialchars($w['user_id'] ?? '') ?> (#<?= (int)$w['member_id'] ?>)</small><br>
                    <small style="color:#6b7280">현재 <?= number_format((int)($w['current_point'] ?? 0)) ?>P</small>
                </td>
                <td><strong><?= number_format((int)$w['amount']) ?>원</strong></td>
                <td>
                    <?= htmlspecialchars($w['bank_name']) ?><br>
                    <strong><?= htmlspecialchars($w['account_holder']) ?></strong><br>
                    <span class="pw-account"><?= htmlspecialchars($w['account_number']) ?></span>
                </td>
                <td>
                    <small><?= htmlspecialchars($w['created_at']) ?></small>
                    <?php if ($w['processed_at']): ?>
                        <br><small style="color:#9ca3af">처리: <?= htmlspecialchars($w['processed_at']) ?></small>
                    <?php endif; ?>
                    <?php if (!empty($w['admin_note'])): ?>
                        <br><small style="color:#dc2626">사유: <?= htmlspecialchars($w['admin_note']) ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="pw-status <?= htmlspecialchars($w['status']) ?>"><?= ['pending'=>'대기','completed'=>'완료','rejected'=>'거절'][$w['status']] ?? $w['status'] ?></span></td>
                <td class="pw-actions">
                    <?php if ($w['status'] === 'pending'): ?>
                        <button type="button" class="pw-btn-complete" data-act="complete" data-id="<?= (int)$w['id'] ?>">완료</button>
                        <button type="button" class="pw-btn-reject" data-act="reject" data-id="<?= (int)$w['id'] ?>">거절</button>
                    <?php endif; ?>
                    <button type="button" class="pw-btn-del" data-act="delete" data-id="<?= (int)$w['id'] ?>">삭제</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
    <button type="button" class="pw-btn-del" id="pwAdminDelSel" disabled style="font-size:12px;padding:5px 12px">선택 삭제 (<span id="pwAdminSelCount">0</span>)</button>
</div>

<?php if ($totalPages > 1): ?>
<div class="pw-pagination">
    <?php if ($page > 1): ?>
        <a href="<?= $baseUrl ?>&tab=list&filter=<?= $filter ?>&p=<?= $page-1 ?>">이전</a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 4); $end = min($totalPages, $start + 9);
    for ($i = $start; $i <= $end; $i++):
    ?>
        <a href="<?= $baseUrl ?>&tab=list&filter=<?= $filter ?>&p=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="<?= $baseUrl ?>&tab=list&filter=<?= $filter ?>&p=<?= $page+1 ?>">다음</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function(){
    var checkAll = document.getElementById('pwAdminCheckAll');
    var checks = document.querySelectorAll('.pw-admin-check');
    var delSel = document.getElementById('pwAdminDelSel');
    var selCount = document.getElementById('pwAdminSelCount');

    function update(){
        var n = document.querySelectorAll('.pw-admin-check:checked').length;
        selCount.textContent = n;
        delSel.disabled = n === 0;
        if (checkAll) checkAll.checked = (n === checks.length && n > 0);
    }
    checks.forEach(function(c){ c.addEventListener('change', update); });
    if (checkAll) checkAll.addEventListener('change', function(){
        checks.forEach(function(c){ c.checked = checkAll.checked; });
        update();
    });

    document.querySelectorAll('button[data-act]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var act = btn.dataset.act;
            var id = parseInt(btn.dataset.id, 10);
            if (act === 'complete') {
                if (!confirm('출금을 완료 처리할까요? 회원에게 쪽지가 발송됩니다.')) return;
                doAjax('admin_complete', { id: id });
            } else if (act === 'reject') {
                var reason = prompt('거절 사유를 입력하세요 (회원에게 쪽지로 전달):', '');
                if (reason === null) return;
                doAjax('admin_reject', { id: id, reason: reason });
            } else if (act === 'delete') {
                if (!confirm('이 신청을 영구 삭제할까요?')) return;
                doDelete([id]);
            }
        });
    });

    delSel.addEventListener('click', function(){
        var ids = Array.from(document.querySelectorAll('.pw-admin-check:checked')).map(function(c){ return parseInt(c.value, 10); });
        if (!ids.length) return;
        if (!confirm('선택한 ' + ids.length + '건을 영구 삭제할까요?')) return;
        doDelete(ids);
    });

    function doAjax(action, data){
        var fd = new FormData();
        Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
        fetch('/?pw_api=' + action, { method:'POST', credentials:'same-origin', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.ok) location.reload();
                else alert('실패: ' + (res.error || ''));
            });
    }
    function doDelete(ids){
        fetch('/?pw_api=admin_delete', {
            method:'POST', credentials:'same-origin',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({ ids: ids }),
        }).then(function(r){ return r.json(); }).then(function(res){
            if (res.ok) location.reload();
            else alert('실패: ' + (res.error || ''));
        });
    }
})();
</script>

<?php elseif ($tab === 'settings'): ?>

<div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:14px 16px;margin-bottom:16px;color:#065f46;font-size:13px;line-height:1.6">
    <strong>사용자 페이지 URL</strong>: <code>https://yoursite.com/?pw_page=1</code><br>
    이 링크를 사이트 헤더 메뉴 / 마이페이지 / 푸터에 추가하면 회원들이 출금 신청할 수 있어요.
</div>

<form method="post">
    <input type="hidden" name="pw_save" value="1">

    <div class="pw-section">
        <h3>활성화</h3>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="enabled" <?= $_pw['enabled']==='1'?'checked':'' ?>>
            플러그인 활성화
        </label>
    </div>

    <div class="pw-section">
        <h3>출금 정책</h3>
        <div class="form-row">
            <label>최소 출금 금액 (원)</label>
            <input type="number" name="min_amount" value="<?= (int)$_pw['min_amount'] ?>" min="1000" step="1000">
            <small>이 금액 미만으로는 신청 불가</small>
        </div>
        <div class="form-row">
            <label>출금 단위 (원)</label>
            <input type="number" name="amount_unit" value="<?= (int)$_pw['amount_unit'] ?>" min="1000" step="1000">
            <small>예: 10000원 = 만원 단위로만 신청 가능</small>
        </div>
        <div class="form-row">
            <label>1포인트 = ? 원</label>
            <input type="number" name="point_to_won" value="<?= (int)$_pw['point_to_won'] ?>" min="1">
            <small>예: 1포인트=1원이면 1, 1포인트=10원이면 10 입력</small>
        </div>
        <div class="form-row">
            <label>회원당 동시 진행 가능 신청 수</label>
            <input type="number" name="max_pending_per_member" value="<?= (int)$_pw['max_pending_per_member'] ?>" min="1" max="20">
            <small>대기 중인 신청이 이 수 이상이면 추가 신청 불가</small>
        </div>
        <div class="form-row">
            <label>이력 페이지당 표시 수</label>
            <input type="number" name="page_per" value="<?= (int)$_pw['page_per'] ?>" min="5" max="100">
        </div>
    </div>

    <div class="pw-section">
        <h3>사용자 페이지 표시</h3>
        <div class="form-row">
            <label>메뉴 라벨</label>
            <input type="text" name="menu_label" value="<?= htmlspecialchars($_pw['menu_label']) ?>">
        </div>
    </div>

    <div class="pw-section">
        <h3>관리자 알림 대상</h3>
        <div class="form-row">
            <label>관리자 회원 ID (쉼표 구분, 비우면 level≥10 자동 검색)</label>
            <input type="text" name="admin_member_ids" value="<?= htmlspecialchars($_pw['admin_member_ids']) ?>" placeholder="예: 1,2,5">
            <small>이 ID들에게 출금 신청 쪽지가 발송됩니다</small>
        </div>
    </div>

    <div class="pw-section">
        <h3>알림 설정</h3>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px">
            <input type="checkbox" name="notify_admin" <?= $_pw['notify_admin']==='1'?'checked':'' ?>>
            출금 신청 시 관리자에게 쪽지 발송
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px">
            <input type="checkbox" name="notify_member_on_complete" <?= $_pw['notify_member_on_complete']==='1'?'checked':'' ?>>
            출금 완료 시 회원에게 쪽지 발송
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="notify_member_on_reject" <?= $_pw['notify_member_on_reject']==='1'?'checked':'' ?>>
            출금 거절 시 회원에게 쪽지 발송 (포인트 자동 복구됨)
        </label>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<?php endif; ?>

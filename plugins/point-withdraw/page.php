<?php
/**
 * 포인트 출금 사용자 페이지
 * URL: /?pw_page=1
 */
$cfg = $_pwConfig;

// 로그인 체크
$me = null;
if (class_exists('Auth') && method_exists('Auth', 'user')) {
    $u = Auth::user();
    if (!empty($u['id'])) $me = $u;
}

if (!$me) {
    echo '<!doctype html><meta charset="utf-8"><title>로그인 필요</title>';
    echo '<div style="text-align:center;padding:60px;font-family:sans-serif"><h2>로그인이 필요합니다</h2><a href="/login">로그인 하러가기</a></div>';
    exit;
}

$myPoint = _pw_get_member_point((int)$me['id']);
$myWonAvailable = (int)floor($myPoint * max(1, (int)$cfg['point_to_won']));
$minAmount = max(1000, (int)$cfg['min_amount']);
$unit = max(1000, (int)$cfg['amount_unit']);
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($cfg['menu_label']) ?></title>
<style>
* { box-sizing: border-box; }
body { margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans KR',sans-serif; background:#f5f7fa; color:#111827; line-height:1.5; }
.pw-wrap { max-width:680px; margin:0 auto; padding:20px 16px 60px; }
.pw-top { display:flex; align-items:center; gap:8px; margin-bottom:14px; }
.pw-top a { color:#6b7280; text-decoration:none; padding:6px; border-radius:6px; display:inline-flex; align-items:center; gap:4px; font-size:13px; }
.pw-top a:hover { background:#fff; }
.pw-top h1 { margin:0; font-size:20px; font-weight:700; flex:1; }

.card { background:#fff; border-radius:14px; padding:20px; margin-bottom:14px; box-shadow:0 1px 4px rgba(0,0,0,.04); }
.card h3 { margin:0 0 14px; font-size:15px; font-weight:700; color:#111827; }

.balance { display:flex; justify-content:space-between; align-items:center; padding:18px 20px; background:linear-gradient(135deg,#22c55e 0%,#16a34a 100%); color:#fff; border-radius:14px; margin-bottom:14px; }
.balance .label { font-size:12px; opacity:.9; margin-bottom:4px; }
.balance .num { font-size:24px; font-weight:800; letter-spacing:-.5px; }
.balance .sub { font-size:11px; opacity:.85; margin-top:2px; }

.form-row { margin-bottom:14px; }
.form-row label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
.form-row input, .form-row select { width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; font-family:inherit; }
.form-row input:focus, .form-row select:focus { outline:none; border-color:#22c55e; }
.form-row small { display:block; font-size:11px; color:#9ca3af; margin-top:4px; }

.amount-row { display:flex; gap:8px; align-items:center; }
.amount-row input { flex:1; }
.btn-all { background:#f3f4f6; color:#374151; border:1px solid #d1d5db; padding:11px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; }
.btn-all:hover { background:#e5e7eb; }

.btn-submit { width:100%; background:#22c55e; color:#fff; border:none; padding:14px; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; }
.btn-submit:hover { background:#16a34a; }
.btn-submit:disabled { background:#d1d5db; cursor:not-allowed; }

.flash { padding:12px 14px; border-radius:8px; margin-bottom:12px; font-size:13px; display:none; }
.flash.show { display:block; }
.flash.ok { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
.flash.err { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

.list-controls { display:flex; gap:8px; align-items:center; margin-bottom:10px; flex-wrap:wrap; }
.list-controls label { display:flex; align-items:center; gap:5px; font-size:12px; cursor:pointer; }
.list-controls button { padding:5px 11px; font-size:12px; border-radius:6px; cursor:pointer; }
.btn-del { background:#fff; border:1px solid #fecaca; color:#dc2626; }
.btn-del:hover { background:#fef2f2; }
.btn-del:disabled { color:#d1d5db; border-color:#e5e7eb; cursor:not-allowed; }

.history-row { padding:14px; border:1px solid #f1f3f5; border-radius:10px; margin-bottom:8px; display:flex; gap:10px; align-items:flex-start; }
.history-row .check { margin-top:2px; }
.history-row .body { flex:1; min-width:0; }
.history-row .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; flex-wrap:wrap; gap:6px; }
.history-row .amount { font-size:15px; font-weight:700; color:#111827; }
.history-row .status { font-size:11px; padding:3px 8px; border-radius:10px; font-weight:600; }
.status.pending { background:#fef3c7; color:#92400e; }
.status.completed { background:#dcfce7; color:#15803d; }
.status.rejected { background:#fee2e2; color:#991b1b; }
.history-row .info { font-size:12px; color:#6b7280; line-height:1.55; }
.history-row .note { font-size:11px; color:#dc2626; margin-top:4px; padding-top:4px; border-top:1px dashed #fee2e2; }

.empty { padding:40px 20px; text-align:center; color:#9ca3af; font-size:14px; }
.pagination { display:flex; justify-content:center; align-items:center; gap:6px; margin-top:14px; flex-wrap:wrap; }
.pagination button { padding:5px 10px; font-size:12px; border:1px solid #e5e7eb; background:#fff; border-radius:6px; cursor:pointer; }
.pagination button.active { background:#22c55e; color:#fff; border-color:#22c55e; }
.pagination button:disabled { color:#d1d5db; cursor:not-allowed; }
</style>
</head>
<body>
<div class="pw-wrap">
    <div class="pw-top">
        <a href="javascript:history.back()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            돌아가기
        </a>
        <h1><?= htmlspecialchars($cfg['menu_label']) ?></h1>
    </div>

    <div class="balance">
        <div>
            <div class="label">출금 가능 금액</div>
            <div class="num"><span id="pwAvailable"><?= number_format($myWonAvailable) ?></span>원</div>
            <div class="sub">보유 포인트 <span id="pwPoint"><?= number_format($myPoint) ?></span> P</div>
        </div>
    </div>

    <div class="flash" id="pwFlash"></div>

    <div class="card">
        <h3>출금 신청</h3>
        <div class="form-row">
            <label>출금 금액 (<?= number_format($unit) ?>원 단위)</label>
            <div class="amount-row">
                <input type="number" id="pwAmount" placeholder="<?= number_format($minAmount) ?>" min="<?= $minAmount ?>" step="<?= $unit ?>">
                <button type="button" class="btn-all" id="pwAllBtn">전체출금</button>
            </div>
            <small>최소 <?= number_format($minAmount) ?>원, <?= number_format($unit) ?>원 단위로만 신청 가능</small>
        </div>

        <div class="form-row">
            <label>은행</label>
            <select id="pwBank">
                <option value="">은행 선택</option>
                <option>국민은행</option><option>신한은행</option><option>우리은행</option><option>하나은행</option>
                <option>농협은행</option><option>기업은행</option><option>SC제일은행</option><option>씨티은행</option>
                <option>카카오뱅크</option><option>케이뱅크</option><option>토스뱅크</option>
                <option>부산은행</option><option>대구은행</option><option>광주은행</option><option>전북은행</option>
                <option>경남은행</option><option>제주은행</option><option>우체국</option><option>새마을금고</option>
                <option>신협</option><option>산업은행</option><option>수협은행</option>
            </select>
        </div>

        <div class="form-row">
            <label>예금주</label>
            <input type="text" id="pwHolder" placeholder="홍길동" maxlength="50">
        </div>

        <div class="form-row">
            <label>계좌번호</label>
            <input type="text" id="pwAccount" placeholder="-없이 숫자만" maxlength="50">
        </div>

        <button type="button" class="btn-submit" id="pwSubmit">출금 신청</button>
    </div>

    <div class="card">
        <h3>신청 이력</h3>
        <div class="list-controls">
            <label><input type="checkbox" id="pwCheckAll"> 전체 선택</label>
            <button type="button" class="btn-del" id="pwDelSel" disabled>선택 삭제 (<span id="pwSelCount">0</span>)</button>
            <small style="color:#9ca3af">대기중인 신청은 삭제 불가</small>
        </div>
        <div id="pwHistory"><div class="empty">불러오는 중...</div></div>
        <div class="pagination" id="pwPagination"></div>
    </div>
</div>

<script>
(function(){
    var state = { page: 1, items: [], total: 0, totalPages: 1 };
    var el = function(id){ return document.getElementById(id); };

    function flash(msg, type){
        var f = el('pwFlash');
        f.textContent = msg;
        f.className = 'flash show ' + (type || 'ok');
        setTimeout(function(){ f.classList.remove('show'); }, type === 'err' ? 6000 : 3000);
    }
    function fmtTime(iso){
        if(!iso) return '';
        var d = new Date(iso.replace(' ','T'));
        if (isNaN(d.getTime())) return iso;
        return (d.getMonth()+1) + '/' + d.getDate() + ' ' + (d.getHours()<10?'0':'') + d.getHours() + ':' + (d.getMinutes()<10?'0':'') + d.getMinutes();
    }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

    // 전체 출금 버튼
    el('pwAllBtn').addEventListener('click', function(){
        var available = parseInt(el('pwAvailable').textContent.replace(/,/g,''), 10);
        var unit = <?= $unit ?>;
        var amount = Math.floor(available / unit) * unit;
        if (amount < <?= $minAmount ?>) {
            flash('출금 가능 금액이 ' + amount.toLocaleString() + '원으로 최소 신청액보다 적습니다.', 'err');
            return;
        }
        el('pwAmount').value = amount;
    });

    // 출금 신청
    el('pwSubmit').addEventListener('click', function(){
        var fd = new FormData();
        fd.append('amount', el('pwAmount').value);
        fd.append('bank_name', el('pwBank').value);
        fd.append('account_holder', el('pwHolder').value);
        fd.append('account_number', el('pwAccount').value);

        el('pwSubmit').disabled = true;
        el('pwSubmit').textContent = '신청 중...';

        fetch('/?pw_api=submit', { method:'POST', credentials:'same-origin', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                el('pwSubmit').disabled = false;
                el('pwSubmit').textContent = '출금 신청';
                if (res.ok) {
                    flash('출금 신청이 완료되었습니다. 관리자 확인 후 입금됩니다.', 'ok');
                    el('pwAmount').value = '';
                    el('pwHolder').value = '';
                    el('pwAccount').value = '';
                    el('pwBank').selectedIndex = 0;
                    if (res.remaining_point !== undefined) {
                        el('pwPoint').textContent = res.remaining_point.toLocaleString();
                        el('pwAvailable').textContent = res.remaining_point.toLocaleString();
                    }
                    loadHistory();
                } else {
                    flash(res.error || '실패', 'err');
                }
            })
            .catch(function(e){
                el('pwSubmit').disabled = false;
                el('pwSubmit').textContent = '출금 신청';
                flash('네트워크 오류', 'err');
            });
    });

    function loadHistory(){
        fetch('/?pw_api=list&page=' + state.page, { credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.ok) { el('pwHistory').innerHTML = '<div class="empty">' + (res.error || '실패') + '</div>'; return; }
                state.items = res.items || [];
                state.total = res.total;
                state.totalPages = res.total_pages;
                if (res.point !== undefined) {
                    el('pwPoint').textContent = res.point.toLocaleString();
                    el('pwAvailable').textContent = res.point.toLocaleString();
                }
                renderHistory();
                renderPagination();
                updateSelCount();
            });
    }

    function renderHistory(){
        if (state.items.length === 0) {
            el('pwHistory').innerHTML = '<div class="empty">아직 신청 내역이 없습니다.</div>';
            return;
        }
        var statusLabel = { pending:'대기중', completed:'완료', rejected:'거절' };
        var html = '';
        state.items.forEach(function(it){
            var canDel = it.status !== 'pending';
            html += '<div class="history-row" data-id="' + it.id + '">' +
                '<input type="checkbox" class="check pw-item-check" value="' + it.id + '"' + (canDel ? '' : ' disabled title="대기중인 신청은 삭제 불가"') + '>' +
                '<div class="body">' +
                    '<div class="top">' +
                        '<div class="amount">' + parseInt(it.amount).toLocaleString() + '원</div>' +
                        '<div class="status ' + it.status + '">' + (statusLabel[it.status] || it.status) + '</div>' +
                    '</div>' +
                    '<div class="info">' +
                        escapeHtml(it.bank_name) + ' · ' + escapeHtml(it.account_holder) + ' · ' + escapeHtml(it.account_number) +
                        '<br>신청: ' + fmtTime(it.created_at) +
                        (it.processed_at ? ' · 처리: ' + fmtTime(it.processed_at) : '') +
                    '</div>' +
                    (it.admin_note ? '<div class="note">사유: ' + escapeHtml(it.admin_note) + '</div>' : '') +
                '</div>' +
            '</div>';
        });
        el('pwHistory').innerHTML = html;

        document.querySelectorAll('.pw-item-check').forEach(function(c){
            c.addEventListener('change', updateSelCount);
        });
    }

    function renderPagination(){
        var p = el('pwPagination');
        if (state.totalPages <= 1) { p.innerHTML = ''; return; }
        var html = '';
        html += '<button ' + (state.page === 1 ? 'disabled' : '') + ' data-go="' + (state.page-1) + '">이전</button>';
        var start = Math.max(1, state.page - 2);
        var end = Math.min(state.totalPages, start + 4);
        for (var i = start; i <= end; i++) {
            html += '<button class="' + (i === state.page ? 'active' : '') + '" data-go="' + i + '">' + i + '</button>';
        }
        html += '<button ' + (state.page >= state.totalPages ? 'disabled' : '') + ' data-go="' + (state.page+1) + '">다음</button>';
        p.innerHTML = html;
        p.querySelectorAll('button[data-go]').forEach(function(b){
            b.addEventListener('click', function(){
                state.page = parseInt(b.dataset.go, 10);
                loadHistory();
            });
        });
    }

    function updateSelCount(){
        var checks = document.querySelectorAll('.pw-item-check:checked');
        el('pwSelCount').textContent = checks.length;
        el('pwDelSel').disabled = checks.length === 0;
        var allCheck = el('pwCheckAll');
        var enabled = document.querySelectorAll('.pw-item-check:not(:disabled)');
        allCheck.checked = enabled.length > 0 && checks.length === enabled.length;
    }

    el('pwCheckAll').addEventListener('change', function(){
        document.querySelectorAll('.pw-item-check:not(:disabled)').forEach(function(c){
            c.checked = el('pwCheckAll').checked;
        });
        updateSelCount();
    });

    el('pwDelSel').addEventListener('click', function(){
        var ids = Array.from(document.querySelectorAll('.pw-item-check:checked')).map(function(c){ return parseInt(c.value, 10); });
        if (!ids.length) return;
        if (!confirm('선택한 ' + ids.length + '건을 삭제할까요?')) return;
        fetch('/?pw_api=delete', {
            method:'POST', credentials:'same-origin',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({ ids: ids }),
        }).then(function(r){ return r.json(); }).then(function(res){
            if (res.ok) loadHistory();
            else flash(res.error || '실패', 'err');
        });
    });

    loadHistory();
})();
</script>
</body>
</html>

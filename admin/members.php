<?php
/**
 * NuriBoard 관리자 - 회원 관리 (경고/정지 포함)
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'member_update') {
        $data = ['level' => (int)$_POST['level'], 'is_admin' => (int)($_POST['is_admin'] ?? 0)];
        if (!empty($_POST['nickname'])) $data['nickname'] = trim($_POST['nickname']);
        Member::update($id, $data);
        AdminLog::write('member_update', 'member', $id, "레벨:{$data['level']} 관리자:{$data['is_admin']}");
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'member_warn') {
        $reason = trim($_POST['reason'] ?? '경고');
        $count  = Member::addWarning($id, $reason);
        // admin_id를 경고 내역에 업데이트
        $wTable = DB::getPrefix() . 'member_warnings';
        $lastId = DB::fetch("SELECT id FROM {$wTable} WHERE member_id = ? ORDER BY id DESC LIMIT 1", [$id]);
        if ($lastId) DB::update($wTable, ['admin_id' => Auth::id()], 'id = ?', [$lastId['id']]);
        AdminLog::write('member_warn', 'member', $id, "사유:{$reason} 누적:{$count}회");
        // 회원에게 경고 알림 쪽지 자동 발송
        $warnMsg = "회원님께 경고가 부여되었습니다.\n\n사유: {$reason}\n누적 경고: {$count}회";
        if ($count >= 3) $warnMsg .= "\n\n[경고] 경고 3회 누적으로 3일 이용 정지되었습니다.";
        Message::send(Auth::id(), $id, '[시스템] 경고 알림', $warnMsg);
        // 경고 3회 이상이면 자동 3일 정지
        if ($count >= 3) {
            Member::ban($id, 3);
            AdminLog::write('member_ban', 'member', $id, "경고 누적으로 자동 3일 정지");
        }
        echo json_encode(['success' => true, 'warnings' => $count]); exit;
    }

    if ($action === 'member_ban') {
        $days = max(1, (int)($_POST['days'] ?? 7));
        Member::ban($id, $days);
        AdminLog::write('member_ban', 'member', $id, "{$days}일 정지");
        $banUntil = date('Y.m.d H:i', strtotime("+{$days} days"));
        Message::send(Auth::id(), $id, '[시스템] 이용 정지 알림', "회원님의 계정이 {$days}일간 이용 정지되었습니다.\n정지 해제 예정: {$banUntil}");
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'member_unban') {
        Member::unban($id);
        AdminLog::write('member_unban', 'member', $id, '정지 해제');
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'member_delete') {
        $member = Member::find($id);
        Member::delete($id);
        AdminLog::write('member_delete', 'member', $id, "아이디:{$member['user_id']}");
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'get_warnings') {
        $warnings = Member::warnings($id);
        echo json_encode(['success' => true, 'warnings' => $warnings]); exit;
    }

    if ($action === 'get_detail') {
        $m = Member::find($id);
        if (!$m) { echo json_encode(['success' => false]); exit; }
        $postCount = DB::count("{$prefix}posts", "member_id = ?", [$id]);
        $commentCount = DB::count("{$prefix}comments", "member_id = ?", [$id]);
        $points = DB::fetchAll("SELECT * FROM {$prefix}points WHERE member_id = ? ORDER BY id DESC LIMIT 20", [$id]);
        $warnings = Member::warnings($id);
        $recentPosts = DB::fetchAll("SELECT p.id, p.board_id, p.title, p.created_at FROM {$prefix}posts p WHERE p.member_id = ? ORDER BY p.id DESC LIMIT 10", [$id]);
        echo json_encode([
            'success' => true,
            'member' => [
                'id' => $m['id'], 'user_id' => $m['user_id'], 'nickname' => $m['nickname'],
                'email' => $m['email'], 'level' => $m['level'], 'point' => $m['point'],
                'is_admin' => $m['is_admin'], 'warnings' => $m['warnings'] ?? 0,
                'ban_until' => $m['ban_until'], 'created_at' => $m['created_at'],
                'last_login' => $m['last_login'], 'profile_image' => $m['profile_image'] ?? '',
            ],
            'post_count' => $postCount,
            'comment_count' => $commentCount,
            'points' => $points,
            'warnings_log' => $warnings,
            'recent_posts' => $recentPosts,
        ]);
        exit;
    }

    echo json_encode(['success' => false]); exit;
}

$p      = max(1, (int)($_GET['p'] ?? 1));
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? ''; // banned
$result = Member::list($p, 20, $search, $filter);

adminHeader('members');
?>

<div class="page-header"><h1>회원 관리</h1></div>

<div class="card">
    <div class="card-header" style="gap:12px;flex-wrap:wrap">
        <form method="get" class="search-form" style="flex:1">
            <input type="hidden" name="page" value="members">
            <input type="hidden" name="filter" value="<?= nb_e($filter) ?>">
            <input type="text" name="search" value="<?= nb_e($search) ?>" placeholder="아이디, 닉네임, 이메일 검색">
            <button class="btn btn-sm">검색</button>
        </form>
        <div style="display:flex;gap:6px">
            <a href="?page=members" class="btn btn-sm <?= !$filter ? 'btn-primary' : '' ?>">전체</a>
            <a href="?page=members&filter=banned" class="btn btn-sm <?= $filter === 'banned' ? 'btn-primary' : '' ?>">정지 회원</a>
        </div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px">ID</th>
                <th>아이디</th>
                <th>닉네임</th>
                <th>이메일</th>
                <th style="width:50px">Lv</th>
                <th style="width:80px">포인트</th>
                <th style="width:70px">경고</th>
                <th style="width:100px">상태</th>
                <th style="width:90px">가입일</th>
                <th style="width:160px">관리</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($result['members'] as $m): ?>
        <?php
            $isBanned = !empty($m['ban_until']) && strtotime($m['ban_until']) > time();
            $warnCount = (int)($m['warnings'] ?? 0);
        ?>
        <tr id="mrow-<?= $m['id'] ?>">
            <td class="text-center" style="color:#94a3b8"><?= $m['id'] ?></td>
            <td style="font-size:13px"><?= nb_e($m['user_id']) ?></td>
            <td style="font-size:13px;font-weight:500"><a href="#" onclick="showDetail(<?= $m['id'] ?>);return false" style="color:var(--primary);text-decoration:none;font-weight:600"><?= nb_e($m['nickname']) ?></a></td>
            <td style="font-size:12px;color:#64748b"><?= nb_e($m['email']) ?></td>
            <td class="text-center"><?= $m['level'] ?></td>
            <td class="text-center" style="font-size:13px"><?= number_format($m['point']) ?></td>
            <td class="text-center">
                <?php if ($warnCount > 0): ?>
                <span style="color:<?= $warnCount >= 3 ? '#dc2626' : '#f59e0b' ?>;font-weight:700;cursor:pointer"
                      onclick="showWarnings(<?= $m['id'] ?>, '<?= nb_e($m['nickname']) ?>')"><?= $warnCount ?>회</span>
                <?php else: ?>
                <span style="color:#94a3b8">0</span>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <?php if ($m['is_admin']): ?>
                    <span class="badge badge-green">관리자</span>
                <?php elseif ($isBanned): ?>
                    <span class="badge badge-red" title="<?= date('m.d H:i', strtotime($m['ban_until'])) ?> 해제">정지중</span>
                <?php else: ?>
                    <span style="font-size:12px;color:#94a3b8">정상</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#94a3b8"><?= date('Y.m.d', strtotime($m['created_at'])) ?></td>
            <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                    <button class="btn btn-sm" onclick='editMember(<?= json_encode($m, JSON_UNESCAPED_UNICODE) ?>)'>수정</button>
                    <?php if (!$m['is_admin']): ?>
                        <button class="btn btn-sm" style="color:#f59e0b;border-color:#fde68a"
                                onclick="warnMember(<?= $m['id'] ?>, '<?= nb_e($m['nickname']) ?>')">경고</button>
                        <?php if ($isBanned): ?>
                            <button class="btn btn-sm" style="color:#059669;border-color:#a7f3d0"
                                    onclick="unbanMember(<?= $m['id'] ?>, '<?= nb_e($m['nickname']) ?>')">해제</button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-danger"
                                    onclick="banMember(<?= $m['id'] ?>, '<?= nb_e($m['nickname']) ?>')">정지</button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-danger"
                                onclick="deleteMember(<?= $m['id'] ?>, '<?= nb_e($m['user_id']) ?>')">삭제</button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($result['total_pages'] > 1): ?>
    <?php
        $cp = $result['page'];
        $tp = $result['total_pages'];
        $range = 2;
        $start = max(1, $cp - $range);
        $end = min($tp, $cp + $range);
        $qs = '&search=' . urlencode($search) . '&filter=' . nb_e($filter);
    ?>
    <div class="pagination">
        <?php if ($cp > 1): ?><a href="?page=members&p=<?= $cp - 1 ?><?= $qs ?>">&laquo;</a><?php endif; ?>
        <?php if ($start > 1): ?><a href="?page=members&p=1<?= $qs ?>">1</a><?php if ($start > 2): ?><span style="padding:0 4px;color:#94a3b8">...</span><?php endif; endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=members&p=<?= $i ?><?= $qs ?>" class="<?= $i === $cp ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end < $tp): ?><?php if ($end < $tp - 1): ?><span style="padding:0 4px;color:#94a3b8">...</span><?php endif; ?><a href="?page=members&p=<?= $tp ?><?= $qs ?>"><?= $tp ?></a><?php endif; ?>
        <?php if ($cp < $tp): ?><a href="?page=members&p=<?= $cp + 1 ?><?= $qs ?>">&raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 회원 수정 모달 -->
<div class="modal" id="memberModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>회원 정보 수정</h3>
            <button class="modal-close" onclick="closeModal('memberModal')">&times;</button>
        </div>
        <form onsubmit="return saveMember(event)">
            <input type="hidden" id="member_edit_id">
            <div class="form-group">
                <label>닉네임</label>
                <input type="text" id="member_nickname" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>레벨 (1~10)</label>
                    <input type="number" id="member_level" min="1" max="10">
                </div>
                <div class="form-group">
                    <label>권한</label>
                    <select id="member_is_admin">
                        <option value="0">일반 회원</option>
                        <option value="1">관리자</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('memberModal')">취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
    </div>
</div>

<!-- 경고 부여 모달 -->
<div class="modal" id="warnModal">
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header">
            <h3>경고 부여</h3>
            <button class="modal-close" onclick="closeModal('warnModal')">&times;</button>
        </div>
        <div style="padding:20px">
            <input type="hidden" id="warn_member_id">
            <p id="warn_member_name" style="font-weight:600;margin-bottom:14px;font-size:15px"></p>
            <div class="form-group">
                <label>경고 사유</label>
                <select id="warn_reason_select" onchange="if(this.value)document.getElementById('warn_reason').value=this.value">
                    <option value="">직접 입력</option>
                    <option value="욕설/비방">욕설/비방</option>
                    <option value="스팸/도배">스팸/도배</option>
                    <option value="음란물 게시">음란물 게시</option>
                    <option value="개인정보 유포">개인정보 유포</option>
                    <option value="광고/홍보성 게시물">광고/홍보성 게시물</option>
                    <option value="기타 규정 위반">기타 규정 위반</option>
                </select>
            </div>
            <div class="form-group">
                <label>상세 사유</label>
                <input type="text" id="warn_reason" placeholder="경고 사유를 입력하세요">
            </div>
            <p style="font-size:12px;color:#f59e0b;margin-bottom:16px">경고 3회 누적 시 자동으로 3일 정지됩니다.</p>
            <div class="modal-footer" style="padding:0">
                <button class="btn" onclick="closeModal('warnModal')">취소</button>
                <button class="btn btn-primary" onclick="submitWarn()">경고 부여</button>
            </div>
        </div>
    </div>
</div>

<!-- 정지 모달 -->
<div class="modal" id="banModal">
    <div class="modal-content" style="max-width:380px">
        <div class="modal-header">
            <h3>회원 이용 정지</h3>
            <button class="modal-close" onclick="closeModal('banModal')">&times;</button>
        </div>
        <div style="padding:20px">
            <input type="hidden" id="ban_member_id">
            <p id="ban_member_name" style="font-weight:600;margin-bottom:14px;font-size:15px"></p>
            <div class="form-group">
                <label>정지 기간</label>
                <select id="ban_days">
                    <option value="1">1일</option>
                    <option value="3">3일</option>
                    <option value="7" selected>7일</option>
                    <option value="14">14일</option>
                    <option value="30">30일</option>
                    <option value="180">180일</option>
                    <option value="36500">영구 정지</option>
                </select>
            </div>
            <div class="modal-footer" style="padding:0">
                <button class="btn" onclick="closeModal('banModal')">취소</button>
                <button class="btn btn-danger" onclick="submitBan()">정지 처리</button>
            </div>
        </div>
    </div>
</div>

<!-- 경고 내역 모달 -->
<div class="modal" id="warningsModal">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3 id="warnings_title">경고 내역</h3>
            <button class="modal-close" onclick="closeModal('warningsModal')">&times;</button>
        </div>
        <div style="padding:0 24px 20px" id="warningsList"></div>
    </div>
</div>

<script>
function editMember(m) {
    document.getElementById('member_edit_id').value = m.id;
    document.getElementById('member_nickname').value = m.nickname;
    document.getElementById('member_level').value = m.level;
    document.getElementById('member_is_admin').value = m.is_admin;
    openModal('memberModal');
}
function saveMember(e) {
    e.preventDefault();
    var fd = new FormData();
    fd.append('action','member_update');
    fd.append('id', document.getElementById('member_edit_id').value);
    fd.append('nickname', document.getElementById('member_nickname').value);
    fd.append('level', document.getElementById('member_level').value);
    fd.append('is_admin', document.getElementById('member_is_admin').value);
    ajaxPost(fd).then(function(r){ if(r.success) location.reload(); });
    return false;
}
function warnMember(id, name) {
    document.getElementById('warn_member_id').value = id;
    document.getElementById('warn_member_name').textContent = name + ' 님에게 경고를 부여합니다.';
    document.getElementById('warn_reason').value = '';
    document.getElementById('warn_reason_select').value = '';
    openModal('warnModal');
}
function submitWarn() {
    var reason = document.getElementById('warn_reason').value.trim();
    if (!reason) { alert('경고 사유를 입력하세요.'); return; }
    var fd = new FormData();
    fd.append('action','member_warn');
    fd.append('id', document.getElementById('warn_member_id').value);
    fd.append('reason', reason);
    ajaxPost(fd).then(function(r){
        if (r.success) {
            closeModal('warnModal');
            alert('경고가 부여되었습니다. (누적 ' + r.warnings + '회)');
            location.reload();
        }
    });
}
function banMember(id, name) {
    document.getElementById('ban_member_id').value = id;
    document.getElementById('ban_member_name').textContent = name + ' 님을 정지합니다.';
    openModal('banModal');
}
function submitBan() {
    var fd = new FormData();
    fd.append('action','member_ban');
    fd.append('id', document.getElementById('ban_member_id').value);
    fd.append('days', document.getElementById('ban_days').value);
    ajaxPost(fd).then(function(r){ if(r.success){ closeModal('banModal'); location.reload(); } });
}
function unbanMember(id, name) {
    if (!confirm(name + ' 님의 정지를 해제하겠습니까?')) return;
    var fd = new FormData();
    fd.append('action','member_unban');
    fd.append('id', id);
    ajaxPost(fd).then(function(r){ if(r.success) location.reload(); });
}
function deleteMember(id, userId) {
    if (!confirm('"' + userId + '" 회원을 삭제하겠습니까?\n작성한 게시글과 댓글은 남습니다.')) return;
    var fd = new FormData();
    fd.append('action','member_delete');
    fd.append('id', id);
    ajaxPost(fd).then(function(r){ if(r.success) location.reload(); });
}
function showWarnings(id, name) {
    document.getElementById('warnings_title').textContent = name + ' 님 경고 내역';
    document.getElementById('warningsList').innerHTML = '<p style="padding:20px;text-align:center;color:#94a3b8">불러오는 중...</p>';
    openModal('warningsModal');
    var fd = new FormData(); fd.append('action','get_warnings'); fd.append('id', id);
    ajaxPost(fd).then(function(r){
        if (!r.warnings || !r.warnings.length) {
            document.getElementById('warningsList').innerHTML = '<p style="padding:20px;text-align:center;color:#94a3b8">경고 내역이 없습니다.</p>';
            return;
        }
        var html = '<table style="width:100%;font-size:13px;margin-top:16px"><thead><tr style="border-bottom:1px solid #e2e8f0"><th style="padding:8px;text-align:left">사유</th><th style="padding:8px;text-align:left">처리자</th><th style="padding:8px;text-align:left">일시</th></tr></thead><tbody>';
        r.warnings.forEach(function(w){
            html += '<tr style="border-bottom:1px solid #f8fafc"><td style="padding:8px">' + (w.reason||'-') + '</td><td style="padding:8px;color:#64748b">' + (w.admin_name||'시스템') + '</td><td style="padding:8px;color:#94a3b8">' + w.created_at.substring(0,16) + '</td></tr>';
        });
        html += '</tbody></table>';
        document.getElementById('warningsList').innerHTML = html;
    });
}
function showDetail(id){
    document.getElementById('detailBody').innerHTML='<p style="padding:40px;text-align:center;color:#94a3b8">불러오는 중...</p>';
    openModal('detailModal');
    var fd=new FormData();fd.append('action','get_detail');fd.append('id',id);
    ajaxPost(fd).then(function(r){
        if(!r.success)return;
        var m=r.member;
        var h='<div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">';
        h+='<div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-size:24px;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden">';
        if(m.profile_image){h+='<img src="../'+m.profile_image+'" style="width:100%;height:100%;object-fit:cover">';}
        else{h+=m.nickname.charAt(0).toUpperCase();}
        h+='</div><div><div style="font-size:18px;font-weight:700">'+m.nickname+'</div><div style="font-size:12px;color:#94a3b8">'+m.user_id+' · '+m.email+'</div></div></div>';

        h+='<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:20px">';
        h+='<div style="text-align:center;background:#f8fafc;padding:10px;border-radius:8px"><div style="font-size:18px;font-weight:700;color:#2563eb">Lv.'+m.level+'</div><div style="font-size:11px;color:#94a3b8">레벨</div></div>';
        h+='<div style="text-align:center;background:#f8fafc;padding:10px;border-radius:8px"><div style="font-size:18px;font-weight:700;color:#2563eb">'+r.post_count+'</div><div style="font-size:11px;color:#94a3b8">게시글</div></div>';
        h+='<div style="text-align:center;background:#f8fafc;padding:10px;border-radius:8px"><div style="font-size:18px;font-weight:700;color:#2563eb">'+r.comment_count+'</div><div style="font-size:11px;color:#94a3b8">댓글</div></div>';
        h+='<div style="text-align:center;background:#f8fafc;padding:10px;border-radius:8px"><div style="font-size:18px;font-weight:700;color:#2563eb">'+Number(m.point).toLocaleString()+'</div><div style="font-size:11px;color:#94a3b8">포인트</div></div>';
        h+='</div>';

        h+='<table style="width:100%;font-size:13px;margin-bottom:16px"><tbody>';
        h+='<tr><td style="padding:6px 0;color:#64748b;width:100px">가입일</td><td style="padding:6px 0">'+m.created_at.substring(0,10)+'</td></tr>';
        h+='<tr><td style="padding:6px 0;color:#64748b">최종 로그인</td><td style="padding:6px 0">'+(m.last_login||'없음')+'</td></tr>';
        h+='<tr><td style="padding:6px 0;color:#64748b">경고</td><td style="padding:6px 0;color:'+(m.warnings>=3?'#dc2626':'#475569')+'">'+m.warnings+'회</td></tr>';
        h+='<tr><td style="padding:6px 0;color:#64748b">상태</td><td style="padding:6px 0">'+(m.is_admin?'<span class="badge badge-green">관리자</span>':(m.ban_until?'<span class="badge badge-red">정지 (~'+m.ban_until.substring(0,10)+')</span>':'정상'))+'</td></tr>';
        h+='</tbody></table>';

        // 포인트 내역
        if(r.points&&r.points.length){
            h+='<details style="margin-bottom:12px"><summary style="font-size:13px;font-weight:600;cursor:pointer;padding:6px 0">포인트 내역 (최근 '+r.points.length+'건)</summary>';
            h+='<table style="width:100%;font-size:12px;margin-top:8px"><tbody>';
            r.points.forEach(function(p){
                var color=p.point>0?'#059669':'#dc2626';
                h+='<tr><td style="padding:4px 0;color:'+color+';font-weight:600;width:60px">'+(p.point>0?'+':'')+p.point+'P</td><td style="padding:4px 0;color:#64748b">'+p.reason+'</td><td style="padding:4px 0;color:#94a3b8;text-align:right">'+p.created_at.substring(5,16)+'</td></tr>';
            });
            h+='</tbody></table></details>';
        }

        // 최근 게시글
        if(r.recent_posts&&r.recent_posts.length){
            h+='<details><summary style="font-size:13px;font-weight:600;cursor:pointer;padding:6px 0">최근 게시글 ('+r.recent_posts.length+'건)</summary>';
            h+='<div style="margin-top:8px">';
            r.recent_posts.forEach(function(p){
                h+='<a href="../board/'+p.board_id+'/'+p.id+'" target="_blank" style="display:block;padding:6px 0;font-size:13px;color:#475569;text-decoration:none;border-bottom:1px solid #f8fafc">'+p.title+' <span style="color:#94a3b8;font-size:11px">'+p.created_at.substring(0,10)+'</span></a>';
            });
            h+='</div></details>';
        }

        document.getElementById('detailBody').innerHTML=h;
    });
}
</script>

<!-- 회원 상세 모달 -->
<div class="modal" id="detailModal">
    <div class="modal-content" style="max-width:580px">
        <div class="modal-header">
            <h3>회원 상세 정보</h3>
            <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div style="padding:24px" id="detailBody"></div>
    </div>
</div>

<?php adminFooter(); ?>

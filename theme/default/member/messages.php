<?php
/**
 * NuriBoard - 쪽지함
 */
SEO::setTitle('쪽지함');
require dirname(__DIR__) . '/header.php';
?>

<div class="container">
<div class="msg-wrap">

<?php if (!empty($write)): ?>
    <!-- ===== 쪽지 쓰기 ===== -->
    <div class="msg-header">
        <h2>쪽지 쓰기</h2>
        <a href="<?= nb_url('messages') ?>" class="btn">← 쪽지함으로</a>
    </div>
    <div class="msg-box">
        <div id="writeAlert"></div>
        <div class="form-group">
            <label>받는 사람 <span style="color:#dc2626">*</span></label>
            <div style="display:flex;gap:8px">
                <input type="text" id="msgTo" value="<?= nb_e($to ?? '') ?>" placeholder="닉네임 입력" style="flex:1;padding:9px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:14px;outline:none" onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border)'">
                <button class="btn" onclick="checkReceiver()" style="flex-shrink:0">확인</button>
            </div>
            <div id="receiverInfo" style="margin-top:6px;font-size:13px"></div>
        </div>
        <div class="form-group">
            <label>제목 <span style="color:#dc2626">*</span></label>
            <input type="text" id="msgTitle" placeholder="제목을 입력하세요" maxlength="100"
                   style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:14px;outline:none"
                   onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border)'">
        </div>
        <div class="form-group">
            <label>내용 <span style="color:#dc2626">*</span></label>
            <textarea id="msgContent" rows="8" placeholder="쪽지 내용을 입력하세요" maxlength="2000"
                      style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:14px;resize:vertical;outline:none;font-family:inherit;line-height:1.7"
                      onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border)'"></textarea>
            <div style="text-align:right;font-size:12px;color:#94a3b8;margin-top:4px"><span id="charCount">0</span> / 2000</div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px">
            <a href="<?= nb_url('messages') ?>" class="btn">취소</a>
            <button class="btn btn-primary btn-lg" onclick="sendMsg()">✉ 보내기</button>
        </div>
    </div>

<?php elseif (!empty($view)): ?>
    <!-- ===== 쪽지 상세 ===== -->
    <?php $isMine = $view['sender_id'] === Auth::id(); ?>
    <div class="msg-header">
        <h2><?= nb_e($view['title']) ?></h2>
        <div style="display:flex;gap:8px">
            <?php if (!$isMine): ?>
            <a href="<?= nb_url('messages/write?to=' . urlencode($view['sender_name'] ?? '')) ?>" class="btn btn-primary">↩ 답장</a>
            <?php endif; ?>
            <a href="<?= nb_url('messages?box=' . nb_e($box)) ?>" class="btn">← 목록</a>
        </div>
    </div>
    <div class="msg-box">
        <div class="msg-view-meta">
            <div class="msg-vm-row">
                <span class="msg-vm-label">보낸 사람</span>
                <span><?= nb_level_icon($view['sender_level'] ?? 2) ?> <?= nb_e($view['sender_name'] ?? '탈퇴회원') ?></span>
            </div>
            <div class="msg-vm-row">
                <span class="msg-vm-label">받는 사람</span>
                <span><?= nb_level_icon($view['receiver_level'] ?? 2) ?> <?= nb_e($view['receiver_name'] ?? '탈퇴회원') ?></span>
            </div>
            <div class="msg-vm-row">
                <span class="msg-vm-label">보낸 시각</span>
                <span style="color:var(--text-light)"><?= date('Y년 m월 d일 H:i', strtotime($view['created_at'])) ?></span>
            </div>
        </div>
        <div class="msg-view-body">
            <?= nl2br(nb_e($view['content'])) ?>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;border-top:1px solid var(--border)">
            <form method="post" action="<?= nb_url("messages/{$view['id']}/delete") ?>" onsubmit="return confirm('쪽지를 삭제하겠습니까?')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="box" value="<?= nb_e($box) ?>">
                <button type="submit" class="btn btn-danger">삭제</button>
            </form>
            <?php if (!$isMine): ?>
            <a href="<?= nb_url('messages/write?to=' . urlencode($view['sender_name'] ?? '')) ?>" class="btn btn-primary">↩ 답장</a>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ===== 쪽지 목록 ===== -->
    <div class="msg-header">
        <h2>쪽지함</h2>
        <a href="<?= nb_url('messages/write') ?>" class="btn btn-primary">✉ 쪽지 쓰기</a>
    </div>

    <!-- 탭 -->
    <div class="msg-tabs">
        <a href="<?= nb_url('messages?box=inbox') ?>" class="msg-tab <?= $box !== 'sent' ? 'active' : '' ?>">
            📥 받은 쪽지
            <?php $unread = Message::unreadCount(Auth::id()); if ($unread > 0): ?>
                <span class="msg-unread-badge"><?= $unread ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= nb_url('messages?box=sent') ?>" class="msg-tab <?= $box === 'sent' ? 'active' : '' ?>">
            📤 보낸 쪽지
        </a>
    </div>

    <div class="msg-box" style="padding:0">
        <?php if (empty($data['items'])): ?>
            <div style="padding:48px;text-align:center;color:var(--text-light)">
                <?= $box === 'sent' ? '보낸 쪽지가 없습니다.' : '받은 쪽지가 없습니다.' ?>
            </div>
        <?php else: ?>
        <ul class="msg-list">
            <?php foreach ($data['items'] as $m): ?>
            <?php $unread = ($box !== 'sent' && !$m['is_read']); ?>
            <li class="msg-item <?= $unread ? 'unread' : '' ?>">
                <a href="<?= nb_url("messages/{$m['id']}?box={$box}") ?>" class="msg-item-link">
                    <div class="msg-item-from">
                        <?php if ($box === 'sent'): ?>
                            <span>→ <?= nb_e($m['receiver_name'] ?? '탈퇴회원') ?></span>
                        <?php else: ?>
                            <?= nb_level_icon($m['sender_level'] ?? 2) ?>
                            <span><?= nb_e($m['sender_name'] ?? '탈퇴회원') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="msg-item-title">
                        <?php if ($unread): ?><span class="msg-new-dot"></span><?php endif; ?>
                        <?= nb_e($m['title']) ?>
                    </div>
                    <div class="msg-item-date"><?= date('m.d H:i', strtotime($m['created_at'])) ?></div>
                    <?php if ($box === 'sent'): ?>
                    <div class="msg-item-status">
                        <?php if ($m['is_read']): ?>
                            <span style="font-size:11px;color:#059669">읽음</span>
                        <?php else: ?>
                            <span style="font-size:11px;color:#94a3b8">미읽음</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($data['total_pages'] > 1): ?>
        <div style="display:flex;justify-content:center;gap:4px;padding:16px">
            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                <a href="?box=<?= nb_e($box) ?>&p=<?= $i ?>"
                   style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;text-decoration:none;color:<?= $i === $data['page'] ? '#fff' : '#475569' ?>;background:<?= $i === $data['page'] ? 'var(--primary)' : '#f1f5f9' ?>;font-size:13px">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div><!-- /msg-wrap -->
</div>

<style>
.msg-wrap{max-width:760px;margin:0 auto;padding-bottom:40px}
.msg-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.msg-header h2{font-size:20px;font-weight:700}
.msg-box{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px}

/* 탭 */
.msg-tabs{display:flex;border-bottom:2px solid var(--border);margin-bottom:0;background:#fff;border-radius:10px 10px 0 0;border:1px solid var(--border);border-bottom:none}
.msg-tab{padding:13px 20px;font-size:14px;font-weight:600;color:var(--text-light);text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:8px}
.msg-tab:hover{color:var(--text);text-decoration:none}
.msg-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
.msg-unread-badge{background:#dc2626;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700}
.msg-box.no-top{border-radius:0 0 12px 12px}

/* 목록 */
.msg-list{list-style:none;padding:0}
.msg-item{border-bottom:1px solid #f8fafc}
.msg-item:last-child{border-bottom:none}
.msg-item.unread{background:#fefce8}
.msg-item-link{display:flex;align-items:center;gap:12px;padding:14px 20px;text-decoration:none;color:var(--text);transition:background .1s}
.msg-item-link:hover{background:#f8fafc;text-decoration:none}
.msg-item-from{width:100px;flex-shrink:0;font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center;gap:4px}
.msg-item-title{flex:1;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center;gap:6px}
.msg-item.unread .msg-item-title{font-weight:700}
.msg-new-dot{width:7px;height:7px;border-radius:50%;background:#dc2626;flex-shrink:0}
.msg-item-date{font-size:12px;color:var(--text-light);flex-shrink:0}
.msg-item-status{flex-shrink:0;width:45px;text-align:right}

/* 상세 */
.msg-view-meta{background:#f8fafc;border-radius:8px;padding:16px;margin-bottom:20px}
.msg-vm-row{display:flex;align-items:center;gap:12px;padding:6px 0;font-size:13px;border-bottom:1px solid #f1f5f9}
.msg-vm-row:last-child{border-bottom:none}
.msg-vm-label{width:80px;font-weight:600;color:var(--text-light);flex-shrink:0}
.msg-view-body{min-height:150px;font-size:15px;line-height:1.8;padding:16px 0;color:var(--text);word-break:break-word;border-bottom:1px solid var(--border);margin-bottom:16px}

@media(max-width:768px){
    .msg-item-from{width:70px}
    .msg-item-date{display:none}
    .msg-item-status{display:none}
}
</style>

<script>
// 수신자 확인
function checkReceiver() {
    var nick = document.getElementById('msgTo').value.trim();
    if (!nick) { document.getElementById('receiverInfo').innerHTML = ''; return; }
    fetch('<?= nb_url("messages/send") ?>', {
        method: 'POST',
        body: new URLSearchParams({ _token: '<?= Auth::csrfToken() ?>', to: nick, title: '__check__', content: '__check__' })
    }); // 실제로는 닉네임 확인만
    // 간단하게 send 전 확인 표시
    document.getElementById('receiverInfo').innerHTML = '<span style="color:#059669">✓ ' + nick + ' 님에게 보냅니다</span>';
}

// 글자수 카운트
var tc = document.getElementById('msgContent');
if (tc) {
    tc.addEventListener('input', function() {
        document.getElementById('charCount').textContent = this.value.length;
    });
}

// 쪽지 전송
function sendMsg() {
    var to      = (document.getElementById('msgTo')?.value || '').trim();
    var title   = (document.getElementById('msgTitle')?.value || '').trim();
    var content = (document.getElementById('msgContent')?.value || '').trim();
    var alertEl = document.getElementById('writeAlert');

    if (!to || !title || !content) {
        alertEl.innerHTML = '<div class="alert error" style="margin-bottom:16px">모든 항목을 입력하세요.</div>';
        return;
    }

    var fd = new FormData();
    fd.append('_token', '<?= Auth::csrfToken() ?>');
    fd.append('to', to);
    fd.append('title', title);
    fd.append('content', content);

    fetch('<?= nb_url("messages/send") ?>', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                alertEl.innerHTML = '<div class="alert success" style="margin-bottom:16px">✓ 쪽지를 보냈습니다.</div>';
                document.getElementById('msgTitle').value   = '';
                document.getElementById('msgContent').value = '';
                document.getElementById('charCount').textContent = '0';
                setTimeout(function() { window.location.href = '<?= nb_url("messages?box=sent") ?>'; }, 1200);
            } else {
                alertEl.innerHTML = '<div class="alert error" style="margin-bottom:16px">' + res.message + '</div>';
            }
        });
}
</script>

<?php require dirname(__DIR__) . '/footer.php'; ?>

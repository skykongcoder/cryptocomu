<?php
/**
 * NuriBoard - 팔로워/팔로잉 목록
 */
$isFollowers = ($tab ?? 'followers') === 'followers';
$title = $member['nickname'] . ($isFollowers ? ' - 팔로워' : ' - 팔로잉');
SEO::setTitle($title);

$followerCount = Follow::followerCount((int)$member['id']);
$followingCount = Follow::followingCount((int)$member['id']);
$isMe = Auth::check() && Auth::id() === (int)$member['id'];

require dirname(__DIR__) . '/header.php';
?>
<div class="board-wrap">
<div class="pub-profile">
    <div class="pub-header">
        <div class="pub-avatar">
            <?php if (!empty($member['profile_image'])): ?><img src="<?= nb_url($member['profile_image']) ?>">
            <?php else: ?><?= strtoupper(mb_substr($member['nickname'], 0, 1)) ?><?php endif; ?>
        </div>
        <div class="pub-info">
            <div class="pub-nick"><?= nb_level_icon($member['level']) ?> <?= nb_e($member['nickname']) ?></div>
            <div class="pub-meta">가입일 <?= date('Y.m.d', strtotime($member['created_at'])) ?></div>
        </div>
        <a href="<?= nb_url('member/' . $member['id']) ?>" class="btn btn-secondary" style="margin-left:auto">← 프로필로</a>
    </div>

    <div class="follow-tabs">
        <a href="<?= nb_url('member/' . $member['id'] . '/followers') ?>" class="follow-tab <?= $isFollowers ? 'active' : '' ?>">
            팔로워 <span><?= number_format($followerCount) ?></span>
        </a>
        <a href="<?= nb_url('member/' . $member['id'] . '/following') ?>" class="follow-tab <?= !$isFollowers ? 'active' : '' ?>">
            팔로잉 <span><?= number_format($followingCount) ?></span>
        </a>
    </div>

    <div class="pub-section">
        <?php if (empty($data['rows'])): ?>
            <p class="pub-empty"><?= $isFollowers ? '아직 팔로워가 없습니다.' : '아직 팔로우한 회원이 없습니다.' ?></p>
        <?php else: ?>
            <div class="follow-list">
                <?php foreach ($data['rows'] as $m): ?>
                <?php
                    $viewerId = Auth::check() ? Auth::id() : 0;
                    $isFollowingThis = $viewerId && $viewerId !== (int)$m['id'] ? Follow::isFollowing($viewerId, (int)$m['id']) : false;
                ?>
                <div class="follow-item">
                    <a href="<?= nb_url('member/' . $m['id']) ?>" class="follow-avatar">
                        <?php if (!empty($m['profile_image'])): ?><img src="<?= nb_url($m['profile_image']) ?>">
                        <?php else: ?><?= strtoupper(mb_substr($m['nickname'], 0, 1)) ?><?php endif; ?>
                    </a>
                    <div class="follow-body">
                        <a href="<?= nb_url('member/' . $m['id']) ?>" class="follow-nick"><?= nb_level_icon($m['level']) ?> <?= nb_e($m['nickname']) ?></a>
                        <div class="follow-date">가입 <?= date('Y.m.d', strtotime($m['created_at'])) ?> · <?= date('Y.m.d', strtotime($m['followed_at'])) ?> 팔로우</div>
                    </div>
                    <?php if (Auth::check() && Auth::id() !== (int)$m['id']): ?>
                        <button type="button" class="btn-follow-toggle <?= $isFollowingThis ? 'following' : '' ?>" data-mid="<?= (int)$m['id'] ?>">
                            <?= $isFollowingThis ? '팔로잉' : '+ 팔로우' ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($data['total_pages'] > 1): ?>
        <div class="pub-pagination">
            <?php for ($i = 1; $i <= $data['total_pages']; $i++): ?>
                <?php if ($i === $data['page']): ?>
                    <span class="pp-cur"><?= $i ?></span>
                <?php else: ?>
                    <a href="?p=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<style>
.pub-profile{max-width:860px;margin:0 auto}
.pub-header{display:flex;align-items:center;gap:16px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:12px}
.pub-avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#7c3aed);color:#fff;font-size:28px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.pub-avatar img{width:100%;height:100%;object-fit:cover}
.pub-nick{font-size:20px;font-weight:700}
.pub-meta{font-size:13px;color:var(--text-light);margin-top:4px}
.pub-section{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:12px}
.pub-empty{text-align:center;padding:40px 20px;color:var(--text-light);font-size:14px}
.follow-tabs{display:flex;gap:8px;margin-bottom:12px}
.follow-tab{flex:1;text-align:center;padding:14px;background:#fff;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:var(--text-light);font-size:14px;font-weight:600;transition:all .15s}
.follow-tab span{display:inline-block;margin-left:4px;color:var(--primary);font-weight:700}
.follow-tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.follow-tab.active span{color:#fff}
.follow-list{display:flex;flex-direction:column;gap:4px}
.follow-item{display:flex;align-items:center;gap:12px;padding:10px 4px;border-bottom:1px solid #f1f5f9}
.follow-item:last-child{border-bottom:none}
.follow-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#7c3aed);color:#fff;font-size:16px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;text-decoration:none}
.follow-avatar img{width:100%;height:100%;object-fit:cover}
.follow-body{flex:1;min-width:0}
.follow-nick{display:block;font-size:14px;font-weight:700;color:var(--text);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.follow-nick:hover{color:var(--primary)}
.follow-date{font-size:11px;color:#94a3b8;margin-top:2px}
.btn-follow-toggle{padding:7px 14px;border-radius:7px;font-size:12px;font-weight:600;background:var(--primary);color:#fff;border:1px solid var(--primary);cursor:pointer;flex-shrink:0;transition:all .15s}
.btn-follow-toggle:hover{background:#2563eb}
.btn-follow-toggle.following{background:#fff;color:#64748b;border-color:#e2e8f0}
.btn-follow-toggle.following:hover{background:#fee2e2;color:#dc2626;border-color:#fecaca}
.pub-pagination{display:flex;justify-content:center;gap:6px;margin-top:20px}
.pub-pagination a,.pub-pagination .pp-cur{padding:6px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;text-decoration:none;color:var(--text)}
.pub-pagination .pp-cur{background:var(--primary);color:#fff;border-color:var(--primary)}
@media(max-width:768px){.pub-header{flex-direction:column;text-align:center}.follow-item{flex-wrap:wrap}}
</style>

<script>
document.querySelectorAll('.btn-follow-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
        var mid = parseInt(btn.dataset.mid, 10);
        btn.disabled = true;
        fetch('<?= nb_url("api/follow") ?>', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({target_id: mid})
        }).then(function(r){return r.json()}).then(function(res){
            btn.disabled = false;
            if (res.success) {
                if (res.is_following) { btn.classList.add('following'); btn.textContent = '팔로잉'; }
                else { btn.classList.remove('following'); btn.textContent = '+ 팔로우'; }
            } else {
                alert(res.message || '오류가 발생했습니다.');
            }
        }).catch(function(){btn.disabled = false;});
    });
});
</script>

<?php require dirname(__DIR__) . '/footer.php'; ?>

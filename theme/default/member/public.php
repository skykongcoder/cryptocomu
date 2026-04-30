<?php
/**
 * NuriBoard - 공개 프로필
 */
SEO::setTitle($member['nickname'] . ' - 프로필');
$prefix = DB::getPrefix();

$postCount = DB::count("{$prefix}posts", "member_id = ?", [$member['id']]);
$commentCount = DB::count("{$prefix}comments", "member_id = ?", [$member['id']]);
Follow::ensureTable();
$followerCount = Follow::followerCount((int)$member['id']);
$followingCount = Follow::followingCount((int)$member['id']);
$isMe = Auth::check() && Auth::id() === (int)$member['id'];
$isFollowingProfile = Auth::check() && !$isMe ? Follow::isFollowing(Auth::id(), (int)$member['id']) : false;
$recentPosts = DB::fetchAll("SELECT p.id, p.board_id, p.title, p.hit, p.comment_count, p.created_at, b.title as board_title FROM {$prefix}posts p LEFT JOIN {$prefix}boards b ON p.board_id = b.board_id WHERE p.member_id = ? ORDER BY p.id DESC LIMIT 10", [$member['id']]);
$recentComments = DB::fetchAll("SELECT c.id, c.content, c.created_at, p.id as post_id, p.board_id, p.title as post_title FROM {$prefix}comments c LEFT JOIN {$prefix}posts p ON c.post_id = p.id WHERE c.member_id = ? ORDER BY c.id DESC LIMIT 10", [$member['id']]);

require dirname(__DIR__) . '/header.php';
?>

<div class="board-wrap">
<div class="pub-profile">
    <!-- 프로필 헤더 -->
    <div class="pub-header">
        <div class="pub-avatar"><?php if (!empty($member['profile_image'])): ?><img src="<?= nb_url($member['profile_image']) ?>"><?php else: ?><?= strtoupper(mb_substr($member['nickname'], 0, 1)) ?><?php endif; ?></div>
        <div class="pub-info">
            <div class="pub-nick"><?= nb_level_icon($member['level']) ?> <?= nb_e($member['nickname']) ?></div>
            <div class="pub-meta">가입일 <?= date('Y.m.d', strtotime($member['created_at'])) ?></div>
        </div>
        <div class="pub-actions" style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
        <?php if (Auth::check() && !$isMe): ?>
            <button type="button" id="profFollowBtn" class="btn <?= $isFollowingProfile ? 'btn-secondary following' : 'btn-primary' ?>" data-mid="<?= (int)$member['id'] ?>">
                <?= $isFollowingProfile ? '팔로잉' : '+ 팔로우' ?>
            </button>
            <a href="<?= nb_url('messages/write?to=' . urlencode($member['nickname'])) ?>" class="btn btn-secondary">쪽지</a>
        <?php endif; ?>
        </div>
    </div>

    <!-- 통계 -->
    <div class="pub-stats">
        <div class="pub-stat"><strong><?= number_format($member['point']) ?></strong><span>포인트</span></div>
        <div class="pub-stat"><strong>Lv.<?= $member['level'] ?></strong><span>레벨</span></div>
        <a class="pub-stat pub-stat-link" href="<?= nb_url('member/' . $member['id'] . '/followers') ?>"><strong id="profFollowerCount"><?= number_format($followerCount) ?></strong><span>팔로워</span></a>
        <a class="pub-stat pub-stat-link" href="<?= nb_url('member/' . $member['id'] . '/following') ?>"><strong><?= number_format($followingCount) ?></strong><span>팔로잉</span></a>
        <div class="pub-stat"><strong><?= number_format($postCount) ?></strong><span>게시글</span></div>
        <div class="pub-stat"><strong><?= number_format($commentCount) ?></strong><span>댓글</span></div>
    </div>

    <!-- 최근 게시글 -->
    <div class="pub-section">
        <h3>최근 게시글</h3>
        <?php if (!empty($recentPosts)): ?>
        <div class="pub-list">
            <?php foreach ($recentPosts as $p): ?>
            <a href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>" class="pub-row">
                <span class="pub-row-title"><?= nb_e($p['title']) ?> <?php if ($p['comment_count'] > 0): ?><span style="color:var(--primary)">[<?= $p['comment_count'] ?>]</span><?php endif; ?></span>
                <span class="pub-row-date"><?= date('m.d', strtotime($p['created_at'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="pub-empty">작성한 게시글이 없습니다.</p>
        <?php endif; ?>
    </div>

    <!-- 최근 댓글 -->
    <div class="pub-section">
        <h3>최근 댓글</h3>
        <?php if (!empty($recentComments)): ?>
        <div class="pub-list">
            <?php foreach ($recentComments as $c): ?>
            <a href="<?= nb_url("board/{$c['board_id']}/{$c['post_id']}") ?>#comments" class="pub-row">
                <span class="pub-row-title"><?= nb_e(mb_strimwidth(strip_tags($c['content']), 0, 50, '...')) ?> <span style="color:#94a3b8;font-size:11px"><?= nb_e(mb_strimwidth($c['post_title'] ?? '', 0, 20, '..')) ?></span></span>
                <span class="pub-row-date"><?= date('m.d', strtotime($c['created_at'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="pub-empty">작성한 댓글이 없습니다.</p>
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
.pub-stats{display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap}
.pub-stat{flex:1;min-width:calc(16.66% - 10px);text-align:center;background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px 8px;text-decoration:none;color:inherit}
.pub-stat.pub-stat-link{cursor:pointer;transition:all .15s}
.pub-stat.pub-stat-link:hover{border-color:var(--primary);background:#f8faff}
.pub-stat strong{display:block;font-size:18px;font-weight:700;color:var(--primary)}
.pub-stat span{font-size:11px;color:var(--text-light)}
.btn.following{background:#fff;color:#64748b;border:1px solid #e2e8f0}
.btn.following:hover{background:#fee2e2;color:#dc2626;border-color:#fecaca}
.pub-section{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:12px}
.pub-section h3{font-size:15px;font-weight:700;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f1f5f9}
.pub-list{}
.pub-row{display:flex;align-items:center;padding:8px 0;border-bottom:1px solid #f8fafc;text-decoration:none;color:var(--text)}
.pub-row:last-child{border-bottom:none}
.pub-row:hover{color:var(--primary)}
.pub-row-title{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px}
.pub-row-date{font-size:12px;color:#94a3b8;flex-shrink:0;margin-left:10px}
.pub-empty{text-align:center;padding:20px;color:var(--text-light);font-size:13px}
@media(max-width:768px){
    .pub-header{flex-direction:column;text-align:center}
    .pub-header .btn{margin:0!important}
    .pub-stats{flex-wrap:wrap}
    .pub-stat{min-width:calc(50% - 8px)}
}
</style>

<script>
(function(){
    var btn = document.getElementById('profFollowBtn');
    if (!btn) return;
    btn.addEventListener('click', function(){
        btn.disabled = true;
        fetch('<?= nb_url("api/follow") ?>', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({target_id: parseInt(btn.dataset.mid, 10)})
        }).then(function(r){return r.json()}).then(function(res){
            btn.disabled = false;
            if (res.success) {
                if (res.is_following) {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary','following');
                    btn.textContent = '팔로잉';
                } else {
                    btn.classList.remove('btn-secondary','following');
                    btn.classList.add('btn-primary');
                    btn.textContent = '+ 팔로우';
                }
                var fc = document.getElementById('profFollowerCount');
                if (fc) fc.textContent = (res.follower_count || 0).toLocaleString();
            } else {
                alert(res.message || '오류가 발생했습니다.');
            }
        }).catch(function(){btn.disabled = false;});
    });
})();
</script>

<?php require dirname(__DIR__) . '/footer.php'; ?>

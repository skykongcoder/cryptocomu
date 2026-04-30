<?php
/**
 * NuriBoard 기본 테마 - 게시판 목록 (말머리 필터)
 */
SEO::setTitle($board['title']);
SEO::setDescription($board['description'] ?? $board['title'] . ' 게시판');
SEO::setBreadcrumb([
    ['name' => '홈', 'url' => nb_setting('site_url', '')],
    ['name' => $board['title'], 'url' => nb_setting('site_url') . '/board/' . $board['board_id']],
]);

$categories = array_filter(array_map('trim', explode(',', $board['categories'] ?? '')));
$currentCat = $category ?? '';

require dirname(__DIR__) . '/header.php';
$canDelete = Auth::check() && (Auth::isAdmin() || ($board['allow_delete'] ?? 1));
$isMyPost = function($p) { return Auth::check() && (Auth::isAdmin() || (int)($p['member_id'] ?? 0) === Auth::id()); };
?>

<div class="board-wrap">
<article class="board-page">
    <div class="board-header">
        <h1><?= nb_e($board['title']) ?></h1>
        <?php if ($board['description']): ?>
            <p class="board-desc"><?= nb_e($board['description']) ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($categories)): ?>
    <div class="category-tabs">
        <a href="<?= nb_url("board/{$board['board_id']}") ?>" class="cat-tab <?= !$currentCat ? 'active' : '' ?>">전체</a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= nb_url("board/{$board['board_id']}") ?>?category=<?= urlencode($cat) ?>" class="cat-tab <?= $currentCat === $cat ? 'active' : '' ?>"><?= nb_e($cat) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="board-toolbar">
        <form method="get" action="<?= nb_url("board/{$board['board_id']}") ?>" class="board-search">
            <?php if ($currentCat): ?><input type="hidden" name="category" value="<?= nb_e($currentCat) ?>"><?php endif; ?>
            <input type="text" name="search" value="<?= nb_e($search) ?>" placeholder="검색어를 입력하세요">
            <button type="submit" class="btn">검색</button>
        </form>
        <?php if (Auth::check() && Auth::level() >= $board['write_level']): ?>
            <a href="<?= nb_url("board/{$board['board_id']}/write") ?>" class="btn btn-primary">글쓰기</a>
        <?php endif; ?>
    </div>

    <?php if (($board['board_type'] ?? 'normal') === 'gallery'): ?>
    <!-- 갤러리 뷰 -->
    <div class="board-gallery-grid">
        <?php
        foreach ($posts['posts'] as $p):
            $_thumbInfo = Post::extractThumbInfo((int)$p['id'], $p['content'] ?? '');
            $thumb = $_thumbInfo['thumb'];
            $isVideo = $_thumbInfo['is_video'];
        ?>
        <div class="board-gallery-wrap">
            <?php if ($canDelete && $isMyPost($p)): ?>
            <label class="gallery-chk"><input type="checkbox" class="post-chk" value="<?= $p['id'] ?>"></label>
            <?php endif; ?>
            <a href="<?= nb_url("board/{$board['board_id']}/{$p['id']}") ?>" class="board-gallery-item<?= $isVideo ? ' is-video' : '' ?>">
                <div class="bgi-thumb">
                    <?php if ($thumb): ?>
                        <img src="<?= $isVideo ? nb_e($thumb) : nb_url($thumb) ?>" alt="" loading="lazy">
                        <?php if ($isVideo): ?>
                        <span class="gallery-play" aria-hidden="true">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/></svg>
                        </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="gallery-noimg">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="bgi-info-below">
                    <div class="bgi-title-below"><?= nb_e(mb_strimwidth($p['title'], 0, 30, '...')) ?></div>
                    <div class="bgi-meta-below"><?= nb_e($p['writer_name'] ?? '') ?> · <?= date('m.d', strtotime($p['created_at'])) ?> · 💬<?= $p['comment_count'] ?? 0 ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        <?php if (empty($posts['posts'])): ?>
            <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-light)">게시글이 없습니다.</div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- 일반 리스트 뷰 -->
    <div class="post-table">
        <div class="post-row post-header">
            <?php if ($canDelete): ?><span class="col-chk"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></span><?php endif; ?>
            <span class="col-num">번호</span>
            <?php if (!empty($categories)): ?><span class="col-cat">말머리</span><?php endif; ?>
            <span class="col-title">제목</span>
            <span class="col-writer">글쓴이</span>
            <span class="col-date">날짜</span>
            <span class="col-hit">조회</span>
            <span class="col-vote">추천</span>
        </div>
        <?php foreach ($posts['posts'] as $p): ?>
        <div class="post-row <?= $p['is_notice'] ? 'notice' : '' ?>">
            <?php if ($canDelete): ?>
            <span class="col-chk"><?php if ($isMyPost($p)): ?><input type="checkbox" class="post-chk" value="<?= $p['id'] ?>"><?php endif; ?></span>
            <?php endif; ?>
            <span class="col-num">
                <?= $p['is_notice'] ? '<span class="badge-notice">공지</span>' : $p['id'] ?>
            </span>
            <?php if (!empty($categories)): ?>
                <span class="col-cat"><?= $p['category'] ? nb_e($p['category']) : '-' ?></span>
            <?php endif; ?>
            <span class="col-title">
                <?php if (!empty($p['is_secret'])): ?><span class="icon-secret" title="비밀글">&#128274;</span><?php endif; ?>
                <?php
                    $titleStyle = '';
                    if (!empty($p['title_color'])) $titleStyle .= 'color:' . nb_e($p['title_color']) . ';';
                    if (!empty($p['title_bg'])) $titleStyle .= 'background:' . nb_e($p['title_bg']) . ';padding:1px 6px;border-radius:3px;';
                ?>
                <a href="<?= nb_url("board/{$board['board_id']}/{$p['id']}") ?>"<?= $titleStyle ? ' style="' . $titleStyle . '"' : '' ?>>
                    <?= nb_e(Plugin::applyFilter('post_title', $p['title'])) ?>
                    <?php if ($p['comment_count'] > 0): ?>
                        <span class="comment-count">[<?= $p['comment_count'] ?>]</span>
                    <?php endif; ?>
                    <?php if (strtotime($p['created_at']) > strtotime('-24 hours')): ?>
                        <span class="icon-new">N</span>
                    <?php endif; ?>
                </a>
            </span>
            <span class="col-writer<?= !empty($p['member_id']) ? ' nick-popup-trigger' : '' ?>"<?= !empty($p['member_id']) ? ' data-mid="' . (int)$p['member_id'] . '" data-nick="' . nb_e($p['writer_name'] ?? '') . '"' : '' ?>><?= nb_level_icon($p['writer_level'] ?? 2) ?><?= nb_e($p['writer_name'] ?? '탈퇴회원') ?></span>
            <span class="col-date"><?= date('m.d', strtotime($p['created_at'])) ?></span>
            <span class="col-hit"><?= number_format($p['hit']) ?></span>
            <span class="col-vote"><?= ($p['vote_up'] ?? 0) > 0 ? '+' . $p['vote_up'] : '0' ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($posts['posts'])): ?>
            <div class="post-row empty"><span>게시글이 없습니다.</span></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
    <div class="post-delete-bar">
        <button type="button" class="btn btn-sm btn-danger" onclick="deleteSelected()">선택 삭제</button>
        <?php if (Auth::isAdmin()): ?>
        <button type="button" class="btn btn-sm" onclick="showListCmModal('copy')">선택 복사</button>
        <button type="button" class="btn btn-sm" onclick="showListCmModal('move')">선택 이동</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($posts['total_pages'] > 1): ?>
    <nav class="pagination" aria-label="페이지 네비게이션">
        <?php
        $qs = [];
        if ($currentCat) $qs[] = 'category=' . urlencode($currentCat);
        if ($search) $qs[] = 'search=' . urlencode($search);
        $qsStr = $qs ? '&' . implode('&', $qs) : '';
        ?>
        <?php if ($posts['page'] > 1): ?>
            <a href="?page=<?= $posts['page'] - 1 ?><?= $qsStr ?>">&laquo;</a>
        <?php endif; ?>
        <?php for ($i = max(1, $posts['page'] - 4); $i <= min($posts['total_pages'], $posts['page'] + 4); $i++): ?>
            <a href="?page=<?= $i ?><?= $qsStr ?>" class="<?= $i === $posts['page'] ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($posts['page'] < $posts['total_pages']): ?>
            <a href="?page=<?= $posts['page'] + 1 ?><?= $qsStr ?>">&raquo;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</article>
</div><!-- /board-wrap -->

<style>
.category-tabs{display:flex;gap:4px;padding:12px 24px;border-bottom:1px solid var(--border);flex-wrap:wrap}
.cat-tab{padding:6px 14px;border-radius:20px;font-size:13px;color:var(--text-light);text-decoration:none;transition:all .15s;border:1px solid var(--border)}
.cat-tab:hover{background:#f1f5f9;text-decoration:none}
.cat-tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.col-cat{width:80px;text-align:center;flex-shrink:0;font-size:12px;color:var(--primary)}
.col-vote{width:50px;text-align:center;flex-shrink:0;color:var(--primary);font-size:13px;font-weight:600}
.icon-new{display:inline-block;background:#dc2626;color:#fff;font-size:9px;font-weight:800;padding:1px 4px;border-radius:3px;margin-left:4px;vertical-align:middle}
.icon-secret{font-size:12px;margin-right:2px}
/* 갤러리 뷰 */
.board-gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:16px}
.board-gallery-wrap{min-width:0}
.board-gallery-item{display:block;border-radius:10px;overflow:hidden;background:#fff;border:1px solid var(--border);text-decoration:none;transition:box-shadow .15s;min-width:0;max-width:100%}
.board-gallery-item:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);text-decoration:none}
.bgi-thumb{aspect-ratio:1;overflow:hidden}
.bgi-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .2s}
.board-gallery-item:hover .bgi-thumb img{transform:scale(1.05)}
.bgi-info-below{padding:10px 12px}
.bgi-title-below{font-size:14px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bgi-meta-below{font-size:12px;color:#94a3b8;margin-top:4px}
.board-gallery-wrap{position:relative}
.gallery-chk{position:absolute;top:8px;right:8px;z-index:10;background:rgba(0,0,0,.5);border-radius:6px;padding:4px 6px;cursor:pointer;display:flex;align-items:center}
.gallery-chk input{cursor:pointer;width:16px;height:16px;accent-color:#2563eb}
.col-chk{width:30px;text-align:center;flex-shrink:0}
.col-chk input{cursor:pointer}
.post-delete-bar{padding:10px 16px;display:flex;gap:8px}
@media(max-width:768px){.col-cat,.col-vote{display:none}.board-gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:12px}}
</style>

<script>
function toggleAll(el){
    document.querySelectorAll('.post-chk').forEach(function(c){c.checked=el.checked});
}
function deleteSelected(){
    var ids=[];
    document.querySelectorAll('.post-chk:checked').forEach(function(c){ids.push(c.value)});
    if(!ids.length){alert('삭제할 글을 선택하세요.');return;}
    if(!confirm(ids.length+'개의 글을 삭제하시겠습니까?'))return;
    fetch('<?= nb_url("board/{$board['board_id']}/delete-posts") ?>',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ids:ids,_token:'<?= Auth::csrfToken() ?>'})
    }).then(function(r){return r.json()}).then(function(res){
        if(res.success){alert(res.deleted+'개 삭제 완료');location.reload();}
        else{alert(res.message||'삭제 실패');}
    });
}
</script>
<?php if (Auth::isAdmin()): ?>
<!-- 일괄 복사/이동 모달 -->
<div class="cm-modal-overlay" id="listCmOverlay" style="display:none" onclick="closeListCmModal()">
    <div class="cm-modal" onclick="event.stopPropagation()">
        <h3 id="listCmTitle">선택 복사</h3>
        <div class="cm-modal-body">
            <label>대상 게시판 선택</label>
            <select id="listCmTarget" class="cm-select">
                <?php foreach (Board::listAll(true) as $b): ?>
                    <?php if ($b['board_id'] !== $board['board_id']): ?>
                    <option value="<?= nb_e($b['board_id']) ?>"><?= nb_e($b['title']) ?> (<?= nb_e($b['board_id']) ?>)</option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cm-modal-footer">
            <button type="button" class="btn" onclick="closeListCmModal()">취소</button>
            <button type="button" class="btn btn-primary" id="listCmBtn" onclick="execListCm()">확인</button>
        </div>
    </div>
</div>
<style>
.cm-modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:10000;display:flex;align-items:center;justify-content:center}
.cm-modal{background:#fff;border-radius:12px;padding:24px;width:360px;max-width:90vw;box-shadow:0 12px 40px rgba(0,0,0,.15)}
.cm-modal h3{margin:0 0 16px;font-size:17px}
.cm-modal-body{margin-bottom:20px}
.cm-modal-body label{display:block;font-size:13px;color:#64748b;margin-bottom:6px}
.cm-select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none}
.cm-select:focus{border-color:var(--primary)}
.cm-modal-footer{display:flex;gap:8px;justify-content:flex-end}
</style>
<script>
var _listCmAction='';
function showListCmModal(action){
    var ids=[];
    document.querySelectorAll('.post-chk:checked').forEach(function(c){ids.push(c.value)});
    if(!ids.length){alert('게시글을 선택하세요.');return;}
    _listCmAction=action;
    document.getElementById('listCmTitle').textContent=action==='copy'?'선택 복사':'선택 이동';
    document.getElementById('listCmBtn').textContent=action==='copy'?'복사':'이동';
    document.getElementById('listCmOverlay').style.display='flex';
}
function closeListCmModal(){document.getElementById('listCmOverlay').style.display='none';}
function execListCm(){
    var target=document.getElementById('listCmTarget').value;
    if(!target){alert('대상 게시판을 선택하세요.');return;}
    var ids=[];
    document.querySelectorAll('.post-chk:checked').forEach(function(c){ids.push(c.value)});
    var url='<?= nb_url("board/{$board['board_id']}") ?>/'+_listCmAction+'-posts';
    fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids:ids,target_board_id:target,_token:'<?= Auth::csrfToken() ?>'})})
    .then(function(r){return r.json()}).then(function(res){
        if(res.success){alert(res.message);location.reload();}
        else{alert(res.message||'실패했습니다.');}
    });
}
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/footer.php'; ?>

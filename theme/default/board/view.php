<?php
/**
 * NuriBoard 기본 테마 - 게시글 상세 (대댓글 + 첨부파일)
 */
SEO::setTitle($post['title']);
SEO::setDescription(mb_strimwidth(strip_tags($post['content']), 0, 160, '...'));
SEO::setArticle($post, $writer['nickname'] ?? '탈퇴회원');
SEO::setCanonical(nb_setting('site_url') . "/board/{$board['board_id']}/{$post['id']}");
// OG 이미지: 첨부파일 첫 이미지 또는 본문 첫 이미지 자동 추출
$_ogImg = '';
$_att = DB::fetch("SELECT file_name FROM " . DB::getPrefix() . "attachments WHERE post_id = ? AND is_image = 1 ORDER BY id ASC LIMIT 1", [$post['id']]);
if ($_att) {
    $_ogImg = nb_setting('site_url') . '/' . $_att['file_name'];
} elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $post['content'] ?? '', $_imgM)) {
    $_ogImg = $_imgM[1];
    if (strpos($_ogImg, 'http') !== 0) $_ogImg = nb_setting('site_url') . '/' . $_ogImg;
}
if ($_ogImg) SEO::setOgImage($_ogImg);
SEO::setBreadcrumb([
    ['name' => '홈', 'url' => nb_setting('site_url', '')],
    ['name' => $board['title'], 'url' => nb_setting('site_url') . '/board/' . $board['board_id']],
    ['name' => $post['title']],
]);

// 첨부파일 목록
$attachments = Upload::listByPost($post['id']);
$userVote = Auth::check() ? Vote::getUserVote($post['id'], Auth::id()) : 0;


require dirname(__DIR__) . '/header.php';
?>

<div class="board-wrap">
<article class="post-view">
    <div class="post-view-header">
        <?php if ($post['category']): ?>
            <span class="post-category">[<?= nb_e($post['category']) ?>]</span>
        <?php endif; ?>
        <?php
            $hStyle = '';
            if (!empty($post['title_color'])) $hStyle .= 'color:' . nb_e($post['title_color']) . ';';
            if (!empty($post['title_bg'])) $hStyle .= 'background:' . nb_e($post['title_bg']) . ';display:inline-block;padding:2px 10px;border-radius:4px;';
        ?>
        <h1<?= $hStyle ? ' style="' . $hStyle . '"' : '' ?>><?= nb_e(Plugin::applyFilter('post_title', $post['title'])) ?></h1>
        <div class="post-meta">
            <span class="writer">
            <?php if ($writer): ?>
                <span class="nick-popup-trigger" data-mid="<?= $writer['id'] ?>" data-nick="<?= nb_e($writer['nickname']) ?>">
                    <?= nb_level_icon($writer['level'] ?? 2) ?> <?= nb_e($writer['nickname']) ?>
                </span>
            <?php else: ?>
                <?= nb_level_icon(2) ?> 탈퇴회원
            <?php endif; ?>
        </span>
            <span class="date"><?= date('Y.m.d H:i', strtotime($post['created_at'])) ?></span>
            <span class="hit">조회 <?= number_format($post['hit']) ?></span>
        </div>
        <?php if (!empty($post['link1']) || !empty($post['link2'])): ?>
        <div class="post-links-bar">
            <?php if (!empty($post['link1'])): ?>
                <a href="<?= nb_e($post['link1']) ?>" target="_blank" rel="noopener" class="post-link-item">
                    <span class="link-icon">&#128279;</span> <?= nb_e($post['link1']) ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($post['link2'])): ?>
                <a href="<?= nb_e($post['link2']) ?>" target="_blank" rel="noopener" class="post-link-item">
                    <span class="link-icon">&#128279;</span> <?= nb_e($post['link2']) ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php Plugin::doHook('before_post_content', $post); ?>
    <div class="post-content">
        <?= Plugin::applyFilter('post_content', nb_purify(Post::autoEmbedYoutube($post['content']))) ?>
    </div>
    <?php Plugin::doHook('after_post_content', $post); ?>

    <?php if (!empty($post['tags'])): ?>
    <div class="post-tags">
        <?php foreach (array_filter(array_map('trim', explode(',', $post['tags']))) as $tag): ?>
            <span class="tag">#<?= nb_e($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 추천/비추천 + 북마크 -->
    <div class="vote-box" id="voteBox">
        <button class="vote-btn up <?= $userVote === 1 ? 'voted' : '' ?>" onclick="doVote(1)" title="추천">
            &#9650; <span id="voteUp"><?= number_format($post['vote_up'] ?? 0) ?></span>
        </button>
        <button class="vote-btn down <?= $userVote === -1 ? 'voted' : '' ?>" onclick="doVote(-1)" title="비추천">
            &#9660; <span id="voteDown"><?= number_format($post['vote_down'] ?? 0) ?></span>
        </button>
        <?php if (Auth::check()): ?>
            <?php $isBookmarked = DB::fetch("SELECT id FROM " . DB::getPrefix() . "bookmarks WHERE member_id = ? AND post_id = ?", [Auth::id(), $post['id']]); ?>
            <button class="vote-btn bookmark <?= $isBookmarked ? 'voted' : '' ?>" id="bookmarkBtn" onclick="toggleBookmark(<?= $post['id'] ?>)" title="북마크">
                <span id="bmIcon"><?= $isBookmarked ? '★' : '☆' ?></span> 북마크
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($attachments)): ?>
    <div class="post-attachments">
        <h3>첨부파일</h3>
        <?php foreach ($attachments as $att): ?>
        <?php
            $attPoint = (int)($att['download_point'] ?? 0);
            $attFree = !$attPoint || (Auth::check() && ((int)$post['member_id'] === Auth::id() || Auth::isAdmin()));
            $attPurchased = $attPoint && Auth::check() ? DB::fetch("SELECT id FROM " . DB::getPrefix() . "file_purchases WHERE member_id = ? AND attachment_id = ?", [Auth::id(), $att['id']]) : false;
        ?>
        <a href="<?= nb_url("download/{$att['id']}") ?>" class="attachment-item <?= ($attPoint && !$attFree && !$attPurchased) ? 'att-paid' : '' ?>" <?= ($attPoint && !$attFree && !$attPurchased) ? 'onclick="return confirm(\'이 파일은 ' . $attPoint . 'P가 필요합니다. 다운로드하시겠습니까?\')"' : '' ?>>
            <span class="att-icon"><?= $attPoint && !$attFree && !$attPurchased ? '🔒' : ($att['is_image'] ? '📷' : '📄') ?></span>
            <span class="att-name"><?= nb_e($att['orig_name']) ?></span>
            <span class="att-size"><?= Upload::formatSize($att['file_size']) ?></span>
            <?php if ($attPoint > 0): ?>
                <?php if ($attFree || $attPurchased): ?>
                    <span class="att-point-free">무료</span>
                <?php else: ?>
                    <span class="att-point-cost"><?= $attPoint ?>P</span>
                <?php endif; ?>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php if (array_filter($attachments, function($a){ return (int)($a['download_point'] ?? 0) > 0; })): ?>
        <div style="margin-top:8px;padding:8px 12px;background:#f8fafc;border-radius:6px;font-size:12px;color:#64748b">
            💡 한번 구매한 파일은 재다운로드 시 포인트가 차감되지 않습니다.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="post-actions">
        <a href="<?= nb_url("board/{$board['board_id']}") ?>" class="btn">목록</a>
        <div class="post-actions-right">
            <?php if (Auth::isAdmin()): ?>
                <button type="button" class="btn" onclick="showPostCopyMoveModal('copy')">복사</button>
                <button type="button" class="btn" onclick="showPostCopyMoveModal('move')">이동</button>
            <?php endif; ?>
            <?php if (Auth::check() && (Auth::id() === $post['member_id'] || Auth::isAdmin())): ?>
                <a href="<?= nb_url("board/{$board['board_id']}/{$post['id']}/edit") ?>" class="btn">수정</a>
                <form method="post" action="<?= nb_url("board/{$board['board_id']}/{$post['id']}/delete") ?>" style="display:inline"
                      onsubmit="return confirm('정말 삭제하시겠습니까?')">
                    <?= Auth::csrfField() ?>
                    <button type="submit" class="btn btn-danger">삭제</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- 이전글 / 다음글 -->
    <?php if (!empty($prevPost) || !empty($nextPost)): ?>
    <nav class="post-nav">
        <?php if (!empty($prevPost)): ?>
        <a href="<?= nb_url("board/{$board['board_id']}/{$prevPost['id']}") ?>" class="post-nav-item prev">
            <span class="post-nav-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                이전글
            </span>
            <span class="post-nav-title"><?= nb_e($prevPost['title']) ?></span>
        </a>
        <?php else: ?>
        <span class="post-nav-item empty">
            <span class="post-nav-label">이전글</span>
            <span class="post-nav-title">이전 글이 없습니다</span>
        </span>
        <?php endif; ?>
        <?php if (!empty($nextPost)): ?>
        <a href="<?= nb_url("board/{$board['board_id']}/{$nextPost['id']}") ?>" class="post-nav-item next">
            <span class="post-nav-label">
                다음글
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
            <span class="post-nav-title"><?= nb_e($nextPost['title']) ?></span>
        </a>
        <?php else: ?>
        <span class="post-nav-item empty">
            <span class="post-nav-label">다음글</span>
            <span class="post-nav-title">다음 글이 없습니다</span>
        </span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <!-- 댓글 -->
    <section class="comments" id="comments">
        <h2>댓글 <span class="count"><?= count($comments) ?></span></h2>

        <div class="comment-list">
            <?php foreach ($comments as $c): ?>
            <?php
            $_canAdopt = Auth::check()
                && empty($c['is_reply'])
                && empty($c['is_adopted'])
                && empty($post['adopted_comment_id'])
                && (int)Auth::id() === (int)$post['member_id']
                && (int)$c['member_id'] !== (int)Auth::id()
                && !empty($c['member_id'])
                && empty($c['is_hidden']);
            ?>
            <div class="comment-item <?= !empty($c['is_reply']) ? 'reply' : '' ?><?= !empty($c['is_adopted']) ? ' adopted' : '' ?>" id="comment-<?= $c['id'] ?>">
                <?php if (!empty($c['is_adopted'])): ?>
                <div class="adopted-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>채택된 답변</span>
                </div>
                <?php endif; ?>
                <div class="comment-header">
                    <?php if (!empty($c['member_id'])): ?>
                    <strong class="comment-writer nick-popup-trigger" data-mid="<?= (int)$c['member_id'] ?>" data-nick="<?= nb_e($c['writer_name'] ?? '') ?>"><?= nb_level_icon($c['writer_level'] ?? 2) ?> <?= nb_e($c['writer_name'] ?? '탈퇴회원') ?></strong>
                    <?php else: ?>
                    <strong class="comment-writer"><?= nb_level_icon($c['writer_level'] ?? 2) ?> <?= nb_e($c['writer_name'] ?? '탈퇴회원') ?></strong>
                    <?php endif; ?>
                    <span class="comment-date"><?= date('Y.m.d H:i', strtotime($c['created_at'])) ?></span>
                    <?php if (Auth::check() && empty($c['is_reply'])): ?>
                        <button type="button" class="btn-link" onclick="showReplyForm(<?= $c['id'] ?>)">답글</button>
                    <?php endif; ?>
                    <?php if ($_canAdopt): ?>
                        <form method="post" action="<?= nb_url("comment/{$c['id']}/adopt") ?>" style="display:inline"
                              onsubmit="return confirm('이 댓글을 채택하시겠습니까?\n\n채택 후에는 취소할 수 없으며, 1개의 댓글만 채택할 수 있습니다.\n댓글 작성자에게 포인트가 지급됩니다.')">
                            <?= Auth::csrfField() ?>
                            <button type="submit" class="btn-link adopt">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:2px">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>채택하기
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if (Auth::check() && (Auth::id() === $c['member_id'] || Auth::isAdmin())): ?>
                        <form method="post" action="<?= nb_url("comment/{$c['id']}/delete") ?>" style="display:inline"
                              onsubmit="return confirm('댓글을 삭제하시겠습니까?')">
                            <?= Auth::csrfField() ?>
                            <button type="submit" class="btn-link delete">삭제</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="comment-body">
                    <?php if (!empty($c['is_hidden'])): ?>
                        <span style="color:#94a3b8;font-style:italic">신고 처리된 댓글입니다.</span>
                    <?php else: ?>
                        <?= nl2br(nb_e(Plugin::applyFilter('comment_content', $c['content']))) ?>
                    <?php endif; ?>
                </div>
                <!-- 대댓글 작성 폼 (숨김) -->
                <div class="reply-form-wrap" id="reply-form-<?= $c['id'] ?>" style="display:none">
                    <form method="post" action="<?= nb_url('comment/write') ?>" class="reply-form">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
                        <textarea name="content" rows="2" placeholder="답글을 입력하세요" required></textarea>
                        <button type="submit" class="btn btn-primary">답글</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (Auth::check()): ?>
        <form method="post" action="<?= nb_url('comment/write') ?>" class="comment-form">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <input type="hidden" name="parent_id" value="0">
            <textarea name="content" rows="3" placeholder="댓글을 입력하세요" required></textarea>
            <button type="submit" class="btn btn-primary">댓글 작성</button>
        </form>
        <?php else: ?>
            <p class="login-notice">댓글을 작성하려면 <a href="<?= nb_url('login?redirect=' . urlencode($_SERVER['REQUEST_URI'])) ?>">로그인</a>하세요.</p>
        <?php endif; ?>
    </section>

    <!-- 게시판 전체 목록 (지연 로드) -->
    <section class="board-list-below" id="boardListBelow" data-board="<?= nb_e($board['board_id']) ?>" data-current="<?= (int)$post['id'] ?>">
        <div class="bl-skeleton">
            <div class="bl-sk-line" style="width:40%"></div>
            <div class="bl-sk-line" style="width:100%"></div>
            <div class="bl-sk-line" style="width:100%"></div>
            <div class="bl-sk-line" style="width:100%"></div>
        </div>
    </section>
</article>
</div><!-- /board-wrap -->

<style>
.board-list-below{margin-top:28px;padding:20px 24px 24px;background:#fff;border:1px solid var(--border);border-radius:12px}
.bl-skeleton{padding:8px 0}
.bl-sk-line{height:14px;background:linear-gradient(90deg,#f1f5f9 0%,#e2e8f0 50%,#f1f5f9 100%);background-size:200% 100%;border-radius:4px;margin-bottom:12px;animation:bl-pulse 1.2s infinite}
@keyframes bl-pulse{0%{background-position:200% 0}100%{background-position:-200% 0}}
[data-theme="dark"] .bl-sk-line{background:linear-gradient(90deg,#1e293b 0%,#334155 50%,#1e293b 100%);background-size:200% 100%}
.board-list-below .board-list-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px}
.board-list-below h2{margin:0;font-size:18px;font-weight:700}
.board-list-below .board-list-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.board-list-below .board-search{display:flex;gap:6px;align-items:center}
.board-list-below .board-search select,.board-list-below .board-search input{height:34px;border:1px solid var(--border);border-radius:6px;padding:0 10px;font-size:13px;background:#fff}
.board-list-below .board-search input{min-width:160px}
.board-list-below .btn-sm{display:inline-flex;align-items:center;gap:4px;height:34px;padding:0 12px;font-size:13px}
.board-list-below .post-row.current{background:#eff6ff}
.board-list-below .post-row.current a{color:var(--primary);font-weight:700}
.board-list-below .icon-new{display:inline-block;background:#10b981;color:#fff;font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:4px;vertical-align:middle;text-transform:uppercase}
[data-theme="dark"] .board-list-below{background:#1e293b;border-color:#334155}
[data-theme="dark"] .board-list-below .board-search select,[data-theme="dark"] .board-list-below .board-search input{background:#0f172a;border-color:#334155;color:#e2e8f0}
[data-theme="dark"] .board-list-below .post-row.current{background:#1e3a5f}
@media (max-width:640px){
.board-list-below{padding:14px;margin-top:20px}
.board-list-below h2{font-size:16px}
.board-list-below .board-list-header{flex-direction:column;align-items:stretch}
.board-list-below .board-list-toolbar{width:100%;flex-direction:column;align-items:stretch;gap:8px}
.board-list-below .board-search{width:100%;flex-wrap:wrap;gap:6px}
.board-list-below .board-search select{flex:0 0 110px;min-width:0}
.board-list-below .board-search input{flex:1 1 0;min-width:0;width:auto}
.board-list-below .board-search button{flex:1 1 100%;width:100%;justify-content:center;order:3}
.board-list-below .board-list-toolbar>a.btn-sm{width:100%;justify-content:center}
}
.post-nav{display:flex;flex-direction:column;margin:16px 0;border:1px solid var(--border);border-radius:10px;overflow:hidden;background:#fff}
.post-nav-item{display:flex;align-items:center;gap:14px;padding:12px 18px;text-decoration:none;color:var(--text);font-size:14px;border-bottom:1px solid var(--border);transition:background .15s}
.post-nav-item:last-child{border-bottom:0}
.post-nav-item:hover{background:#f8fafc;color:var(--primary);text-decoration:none}
.post-nav-item.empty{color:#94a3b8;cursor:default}
.post-nav-item.empty:hover{background:transparent;color:#94a3b8}
.post-nav-item .post-nav-label{display:inline-flex;align-items:center;gap:4px;min-width:72px;font-size:12px;font-weight:700;color:#64748b;flex-shrink:0}
.post-nav-item.next{flex-direction:row-reverse}
.post-nav-item.next .post-nav-label{justify-content:flex-end}
.post-nav-item .post-nav-title{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.post-nav-item.next .post-nav-title{text-align:right}
[data-theme="dark"] .post-nav{background:#1e293b;border-color:#334155}
[data-theme="dark"] .post-nav-item{border-color:#334155;color:#e2e8f0}
[data-theme="dark"] .post-nav-item:hover{background:#0f172a}
[data-theme="dark"] .post-nav-item .post-nav-label{color:#94a3b8}
@media (max-width:640px){.post-nav-item{padding:10px 14px;font-size:13px;gap:10px}.post-nav-item .post-nav-label{min-width:58px;font-size:11px}}
.post-category{color:var(--primary);font-size:13px;font-weight:600}
.post-links-bar{margin-top:10px;display:flex;flex-direction:column;gap:4px}
.post-link-item{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#f8fafc;border:1px solid var(--border);border-radius:6px;font-size:13px;color:var(--text);text-decoration:none;word-break:break-all}
.post-link-item:hover{background:#eff6ff;border-color:var(--primary);color:var(--primary);text-decoration:none}
.link-icon{font-size:14px}
.post-tags{padding:12px 24px;display:flex;flex-wrap:wrap;gap:6px}
.tag{display:inline-block;padding:4px 10px;background:#eff6ff;color:var(--primary);border-radius:20px;font-size:12px}
.vote-box{display:flex;justify-content:center;gap:12px;padding:20px;border-top:1px solid var(--border);flex-wrap:wrap}
.vote-btn.bookmark{font-size:14px;color:#f59e0b;border-color:#fde68a;flex-direction:row;gap:6px;padding:12px 20px}
.vote-btn.bookmark.voted{background:#fffbeb;border-color:#f59e0b}
.vote-btn{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 24px;border:1px solid var(--border);border-radius:10px;background:#fff;cursor:pointer;font-size:16px;transition:all .15s;color:var(--text-light)}
.vote-btn:hover{border-color:var(--primary);color:var(--primary)}
.vote-btn.up.voted{border-color:#dc2626;color:#dc2626;background:#fef2f2}
.vote-btn.down.voted{border-color:#2563eb;color:#2563eb;background:#eff6ff}
.vote-btn span{font-size:14px;font-weight:700}
.video-embed{margin:16px 0;position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:8px}
.video-embed iframe{position:absolute;top:0;left:0;width:100%;height:100%}
.post-content iframe[src*="youtube"]{max-width:100%;border-radius:8px}
.post-links{margin-top:12px}
.post-attachments{padding:16px 24px;border-top:1px solid var(--border);background:#fafbfc}
.post-attachments h3{font-size:14px;margin-bottom:10px;color:#475569}
.attachment-item{display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border:1px solid var(--border);border-radius:6px;margin-bottom:6px;font-size:13px;color:var(--text);text-decoration:none}
.attachment-item:hover{background:#f0f4ff;border-color:var(--primary);text-decoration:none}
.att-icon{font-size:16px}.att-name{flex:1}.att-size{color:#94a3b8;font-size:12px}
.comment-item.reply{margin-left:40px;padding-left:16px;border-left:2px solid var(--border)}
.reply-form{display:flex;gap:8px;margin-top:8px;align-items:flex-start}
.reply-form textarea{flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;resize:none;font-family:inherit;outline:none}
.reply-form textarea:focus{border-color:var(--primary)}
.reply-form .btn{padding:8px 14px;font-size:12px}
.msg-send-link{margin-left:6px;font-size:13px;color:var(--text-light);text-decoration:none;padding:2px 6px;border:1px solid var(--border);border-radius:4px;transition:all .15s}
.msg-send-link:hover{color:var(--primary);border-color:var(--primary);text-decoration:none}
.att-paid{border-color:#fde68a;background:#fffbeb}
.att-point-cost{background:#f59e0b;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;margin-left:auto}
.att-point-free{background:#ecfdf5;color:#059669;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;margin-left:auto}
.btn-bookmark{color:#f59e0b;border-color:#fde68a}
.btn-bookmark.active{background:#fffbeb;color:#f59e0b;border-color:#f59e0b}
</style>
<script>
function doVote(type){
    <?php if (!Auth::check()): ?>
    if(confirm('로그인 후 이용할 수 있습니다. 로그인 페이지로 이동할까요?')){
        location.href='<?= nb_url("login?redirect=" . urlencode($_SERVER["REQUEST_URI"])) ?>';
    }
    return;
    <?php endif; ?>
    fetch('<?= nb_url("vote") ?>',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'post_id=<?= $post['id'] ?>&type='+type+'&_token=<?= Auth::csrfToken() ?>'
    }).then(function(r){return r.json()}).then(function(res){
        if(res.success){
            document.getElementById('voteUp').textContent=res.vote_up;
            document.getElementById('voteDown').textContent=res.vote_down;
            document.querySelectorAll('.vote-btn.up,.vote-btn.down').forEach(function(b){b.classList.remove('voted')});
            if(type===1)document.querySelector('.vote-btn.up').classList.add('voted');
            if(type===-1)document.querySelector('.vote-btn.down').classList.add('voted');
        }else{alert(res.message)}
    });
}
function showReplyForm(id){
    var el=document.getElementById('reply-form-'+id);
    el.style.display=el.style.display==='none'?'block':'none';
    if(el.style.display==='block')el.querySelector('textarea').focus();
}
// 북마크 토글
function toggleBookmark(postId){
    fetch('<?= nb_url("api/bookmark") ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:postId})})
    .then(function(r){return r.json()}).then(function(res){
        if(res.success){
            var btn=document.getElementById('bookmarkBtn');
            var icon=document.getElementById('bmIcon');
            if(res.bookmarked){btn.classList.add('active');icon.textContent='★';}
            else{btn.classList.remove('active');icon.textContent='☆';}
        }
    });
}
// 하단 게시판 목록 지연 로드
(function(){
    var el = document.getElementById('boardListBelow');
    if (!el) return;
    var boardId = el.dataset.board, current = el.dataset.current;
    function load(page){
        fetch('<?= nb_url("api/board-list-fragment/") ?>' + encodeURIComponent(boardId) + '?p=' + page + '&current=' + current)
            .then(function(r){return r.text()})
            .then(function(html){
                el.innerHTML = html;
                el.querySelectorAll('.bl-pagination a').forEach(function(a){
                    a.addEventListener('click', function(e){
                        e.preventDefault();
                        el.scrollIntoView({behavior:'smooth',block:'start'});
                        load(parseInt(a.dataset.p, 10));
                    });
                });
            }).catch(function(){el.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:20px">목록을 불러올 수 없습니다.</p>';});
    }
    if ('requestIdleCallback' in window) requestIdleCallback(function(){load(1)});
    else setTimeout(function(){load(1)}, 100);
})();
</script>

<?php if (Auth::isAdmin()): ?>
<!-- 게시글 복사/이동 모달 -->
<div class="cm-modal-overlay" id="cmModalOverlay" style="display:none" onclick="closeCmModal()">
    <div class="cm-modal" onclick="event.stopPropagation()">
        <h3 id="cmModalTitle">게시글 복사</h3>
        <div class="cm-modal-body">
            <label>대상 게시판 선택</label>
            <select id="cmTargetBoard" class="cm-select">
                <?php foreach (Board::listAll(true) as $b): ?>
                    <?php if ($b['board_id'] !== $board['board_id']): ?>
                    <option value="<?= nb_e($b['board_id']) ?>"><?= nb_e($b['title']) ?> (<?= nb_e($b['board_id']) ?>)</option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cm-modal-footer">
            <button type="button" class="btn" onclick="closeCmModal()">취소</button>
            <button type="button" class="btn btn-primary" id="cmConfirmBtn" onclick="execCopyMove()">확인</button>
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
var _cmAction='';
function showPostCopyMoveModal(action){
    _cmAction=action;
    document.getElementById('cmModalTitle').textContent=action==='copy'?'게시글 복사':'게시글 이동';
    document.getElementById('cmConfirmBtn').textContent=action==='copy'?'복사':'이동';
    document.getElementById('cmModalOverlay').style.display='flex';
}
function closeCmModal(){document.getElementById('cmModalOverlay').style.display='none';}
function execCopyMove(){
    var target=document.getElementById('cmTargetBoard').value;
    if(!target){alert('대상 게시판을 선택하세요.');return;}
    var url='<?= nb_url("board/{$board['board_id']}/{$post['id']}") ?>/'+_cmAction;
    fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({target_board_id:target,_token:'<?= Auth::csrfToken() ?>'})})
    .then(function(r){return r.json()}).then(function(res){
        if(res.success){
            alert(res.message);
            if(_cmAction==='move'){location.href='<?= nb_url("board/{$board['board_id']}") ?>';}
            else{closeCmModal();}
        }else{alert(res.message||'실패했습니다.');}
    });
}
</script>
<?php endif; ?>

<?php
// 본문에 코드블록이 있으면 highlight.js 로드
if (strpos($post['content'] ?? '', '<pre') !== false):
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/atom-one-dark.min.css">
<script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/highlight.min.js"></script>
<script>
(function(){
    document.querySelectorAll('.post-content pre code').forEach(function(el){
        try { hljs.highlightElement(el); } catch(e) {}
    });
    document.querySelectorAll('.post-content pre').forEach(function(pre){
        if (pre.querySelector('.nb-code-copy')) return;
        pre.classList.add('nb-code-block');
        var code = pre.querySelector('code');
        var lang = '';
        if (code && code.className) {
            var m = code.className.match(/language-(\w+)/);
            if (m) lang = m[1];
            else if (code.className.indexOf('hljs') !== -1) {
                var cls = code.className.split(/\s+/);
                for (var i=0;i<cls.length;i++){
                    if (cls[i].indexOf('language-') === 0) { lang = cls[i].replace('language-',''); break; }
                }
            }
        }
        var header = document.createElement('div');
        header.className = 'nb-code-head';
        header.innerHTML = '<span class="nb-code-lang">' + (lang || 'code') + '</span>' +
            '<button type="button" class="nb-code-copy" aria-label="복사">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' +
            '<span>복사</span></button>';
        pre.insertBefore(header, pre.firstChild);
    });
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.nb-code-copy');
        if (!btn) return;
        var pre = btn.closest('pre');
        var code = pre ? pre.querySelector('code') : null;
        if (!code) return;
        var text = code.innerText;
        var done = function(){
            var label = btn.querySelector('span');
            if (!label) return;
            var orig = label.textContent;
            label.textContent = '복사됨!';
            btn.classList.add('copied');
            setTimeout(function(){ label.textContent = orig; btn.classList.remove('copied'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } catch(e) {}
            document.body.removeChild(ta);
        }
    });
})();
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/footer.php'; ?>

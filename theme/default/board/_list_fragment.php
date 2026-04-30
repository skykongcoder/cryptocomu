<?php
/**
 * NuriBoard - 게시판 하단 목록 HTML 조각 (AJAX 지연 로드용)
 * 레이아웃 없이 <section> 한 덩어리만 출력
 */
?>
<div class="board-list-header">
    <h2><?= nb_e($board['title']) ?></h2>
    <div class="board-list-toolbar">
        <form method="get" action="<?= nb_url("board/{$board['board_id']}") ?>" class="board-search">
            <select name="search_type">
                <option value="subject_content">제목+내용</option>
                <option value="subject">제목</option>
                <option value="content">내용</option>
                <option value="writer">글쓴이</option>
            </select>
            <input type="text" name="search" placeholder="검색어">
            <button type="submit" class="btn btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                검색
            </button>
        </form>
        <?php if (Auth::check() && Auth::level() >= $board['write_level']): ?>
        <a href="<?= nb_url("board/{$board['board_id']}/write") ?>" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            글쓰기
        </a>
        <?php endif; ?>
    </div>
</div>
<div class="post-table below-table">
    <div class="post-row post-header">
        <span class="col-title">제목</span>
        <span class="col-writer">글쓴이</span>
        <span class="col-date">날짜</span>
        <span class="col-hit">조회</span>
    </div>
    <?php foreach ($boardList['posts'] as $bp): ?>
    <div class="post-row<?= $bp['is_notice'] ? ' notice' : '' ?><?= (int)$bp['id'] === $currentId ? ' current' : '' ?>">
        <span class="col-title">
            <?php if (!empty($bp['is_secret'])): ?><span class="icon-secret">&#128274;</span><?php endif; ?>
            <a href="<?= nb_url("board/{$board['board_id']}/{$bp['id']}") ?>">
                <?= nb_e(Plugin::applyFilter('post_title', $bp['title'])) ?>
                <?php if ($bp['comment_count'] > 0): ?>
                    <span class="comment-count">[<?= $bp['comment_count'] ?>]</span>
                <?php endif; ?>
                <?php if (strtotime($bp['created_at']) > strtotime('-24 hours')): ?>
                    <span class="icon-new">new</span>
                <?php endif; ?>
            </a>
        </span>
        <span class="col-writer<?= !empty($bp['member_id']) ? ' nick-popup-trigger' : '' ?>"<?= !empty($bp['member_id']) ? ' data-mid="' . (int)$bp['member_id'] . '" data-nick="' . nb_e($bp['writer_name'] ?? '') . '"' : '' ?>><?= nb_level_icon($bp['writer_level'] ?? 2) ?><?= nb_e($bp['writer_name'] ?? '탈퇴회원') ?></span>
        <span class="col-date">
            <?php
            $_ts = strtotime($bp['created_at']);
            $_diff = time() - $_ts;
            if ($_diff < 60) echo '방금';
            elseif ($_diff < 3600) echo floor($_diff/60) . '분 전';
            elseif ($_diff < 86400) echo floor($_diff/3600) . '시간 전';
            elseif ($_diff < 172800) echo '어제';
            elseif ($_diff < 604800) echo floor($_diff/86400) . '일 전';
            else echo date('m.d', $_ts);
            ?>
        </span>
        <span class="col-hit"><?= number_format($bp['hit']) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php if ($boardList['total_pages'] > 1): ?>
<nav class="pagination bl-pagination" style="margin-top:16px">
    <?php if ($boardList['page'] > 1): ?>
        <a href="#" data-p="<?= $boardList['page'] - 1 ?>">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = max(1, $boardList['page'] - 4); $i <= min($boardList['total_pages'], $boardList['page'] + 4); $i++): ?>
        <a href="#" data-p="<?= $i ?>" class="<?= $i === $boardList['page'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($boardList['page'] < $boardList['total_pages']): ?>
        <a href="#" data-p="<?= $boardList['page'] + 1 ?>">&raquo;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>

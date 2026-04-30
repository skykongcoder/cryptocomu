<?php require __DIR__ . '/header.php'; ?>

<div class="board-wrap">
    <div class="search-page">
        <h1 style="font-size:20px;font-weight:700;margin-bottom:16px">
            <?php if ($q): ?>
                "<strong><?= nb_e($q) ?></strong>" 검색결과 <span style="color:var(--primary);font-size:16px"><?= number_format($total) ?>건</span>
            <?php else: ?>
                통합검색
            <?php endif; ?>
        </h1>

        <form method="get" action="<?= nb_url('search') ?>" style="margin-bottom:20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="text" name="q" value="<?= nb_e($q) ?>" placeholder="검색어 입력" style="flex:1;min-width:200px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px">
            <select name="stype" style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px">
                <option value="title_content"<?= ($stype ?? '') === 'title_content' ? ' selected' : '' ?>>제목+내용</option>
                <option value="title"<?= ($stype ?? '') === 'title' ? ' selected' : '' ?>>제목</option>
                <option value="content"<?= ($stype ?? '') === 'content' ? ' selected' : '' ?>>내용</option>
                <option value="writer"<?= ($stype ?? '') === 'writer' ? ' selected' : '' ?>>작성자</option>
            </select>
            <label style="font-size:13px;display:flex;align-items:center;gap:3px"><input type="radio" name="sop" value="and"<?= ($sop ?? 'and') === 'and' ? ' checked' : '' ?>> and</label>
            <label style="font-size:13px;display:flex;align-items:center;gap:3px"><input type="radio" name="sop" value="or"<?= ($sop ?? '') === 'or' ? ' checked' : '' ?>> or</label>
            <button type="submit" class="btn btn-primary">검색</button>
        </form>

        <?php if ($q && !empty($results)): ?>
        <div class="search-results">
            <?php foreach ($results as $r): ?>
            <a href="<?= nb_url("board/{$r['board_id']}/{$r['id']}") ?>" class="search-item">
                <div class="si-board"><?= nb_e($r['board_title'] ?? $r['board_id']) ?></div>
                <div class="si-title">
                    <?= nb_e($r['title']) ?>
                    <?php if ($r['comment_count'] > 0): ?><span style="color:var(--primary);font-size:12px">[<?= $r['comment_count'] ?>]</span><?php endif; ?>
                </div>
                <div class="si-meta">
                    <span<?= !empty($r['member_id']) ? ' class="nick-popup-trigger" data-mid="' . (int)$r['member_id'] . '"' : '' ?>><?= nb_e($r['writer_name'] ?? '탈퇴회원') ?></span>
                    <span><?= date('Y.m.d', strtotime($r['created_at'])) ?></span>
                    <span>조회 <?= number_format($r['hit']) ?></span>
                </div>
                <div class="si-content"><?= nb_e(mb_strimwidth(strip_tags($r['content']), 0, 120, '...')) ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php
        $totalPages = ceil($total / 20);
        if ($totalPages > 1):
        ?>
        <div class="pagination" style="margin-top:20px">
            <?php if ($page > 1): ?><a href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">&laquo;</a><?php endif; ?>
            <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
                <a href="?q=<?= urlencode($q) ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">&raquo;</a><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php elseif ($q): ?>
        <div style="text-align:center;padding:60px 0;color:var(--text-light)">
            <p style="font-size:40px;margin-bottom:12px">🔍</p>
            <p>"<?= nb_e($q) ?>"에 대한 검색결과가 없습니다.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.search-page{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px}
.search-results{}
.search-item{display:block;padding:16px 0;border-bottom:1px solid #f1f5f9;text-decoration:none;color:var(--text)}
.search-item:last-child{border-bottom:none}
.search-item:hover{background:#f8fafc;margin:0 -24px;padding:16px 24px}
.si-board{display:inline-block;font-size:11px;color:var(--primary);background:#eff6ff;padding:2px 8px;border-radius:3px;margin-bottom:6px}
.si-title{font-size:15px;font-weight:600;margin-bottom:4px}
.si-meta{font-size:12px;color:#94a3b8;display:flex;gap:10px;margin-bottom:6px}
.si-content{font-size:13px;color:#64748b;line-height:1.5}
</style>

<?php require __DIR__ . '/footer.php'; ?>

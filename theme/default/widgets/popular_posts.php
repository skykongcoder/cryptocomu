<?php /** 위젯: 인기글 */
$count = (int)($config['count'] ?? 10);
$boardId = $config['board_id'] ?? '';
$orderBy = $config['order_by'] ?? 'vote_up';
$prefix = DB::getPrefix();
$where = '1';
$params = [];
if ($boardId) { $where = 'p.board_id = ?'; $params[] = $boardId; }
$order = $orderBy === 'hit' ? 'p.hit DESC' : 'p.vote_up DESC, p.hit DESC';
$posts = DB::fetchAll("SELECT p.*, m.nickname as writer_name FROM {$prefix}posts p LEFT JOIN {$prefix}members m ON p.member_id = m.id WHERE {$where} ORDER BY {$order} LIMIT {$count}", $params);
$boardNames = [];
foreach (Board::listAll() as $_b) $boardNames[$_b['board_id']] = $_b['title'];
?>
<div class="widget widget-popular-posts" data-widget-id="<?= $widget['id'] ?>">
    <?php if (!empty($widget['title'])): ?><h3 class="widget-title"><?= nb_e($widget['title']) ?></h3><?php endif; ?>
    <ul class="widget-post-list">
        <?php foreach ($posts as $p): ?>
        <li>
            <a href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>">
                <span class="wpl-board">[<?= nb_e($boardNames[$p['board_id']] ?? $p['board_id']) ?>]</span>
                <span class="wpl-title"><?= nb_e($p['title']) ?></span>
                <?php if (($p['vote_up'] ?? 0) > 0): ?><span class="wpl-vote">+<?= $p['vote_up'] ?></span><?php endif; ?>
                <span class="wpl-date">조회 <?= number_format($p['hit']) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?><li class="wpl-empty">게시글이 없습니다.</li><?php endif; ?>
    </ul>
</div>

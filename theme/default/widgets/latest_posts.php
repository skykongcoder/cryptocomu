<?php /** 위젯: 최근글 */
$count = (int)($config['count'] ?? 10);
$boardId = $config['board_id'] ?? '';
$showBoard = $config['show_board_name'] ?? true;
$posts = Post::recentPosts($count, $boardId ?: null);
$boardNames = [];
if ($showBoard) {
    foreach (Board::listAll() as $_b) $boardNames[$_b['board_id']] = $_b['title'];
}
?>
<div class="widget widget-latest-posts" data-widget-id="<?= $widget['id'] ?>">
    <?php if (!empty($widget['title'])): ?><h3 class="widget-title"><?= nb_e($widget['title']) ?></h3><?php endif; ?>
    <ul class="widget-post-list">
        <?php foreach ($posts as $p): ?>
        <li>
            <a href="<?= nb_url("board/{$p['board_id']}/{$p['id']}") ?>">
                <?php if ($showBoard): ?><span class="wpl-board">[<?= nb_e($boardNames[$p['board_id']] ?? $p['board_id']) ?>]</span><?php endif; ?>
                <span class="wpl-title"><?= nb_e($p['title']) ?></span>
                <?php if ($p['comment_count'] > 0): ?><span class="wpl-comment">[<?= $p['comment_count'] ?>]</span><?php endif; ?>
                <?php if (strtotime($p['created_at']) > strtotime('-24 hours')): ?><span class="icon-new">N</span><?php endif; ?>
                <span class="wpl-date"><?= date('m.d', strtotime($p['created_at'])) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?><li class="wpl-empty">게시글이 없습니다.</li><?php endif; ?>
    </ul>
</div>

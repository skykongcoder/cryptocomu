<?php /** 위젯: 게시판 미리보기 */
$boardId = $config['board_id'] ?? '';
$count = (int)($config['count'] ?? 5);
if (!$boardId) return;
$board = Board::findById($boardId);
if (!$board) return;
$posts = Post::recentPosts($count, $boardId);
?>
<div class="widget widget-board-preview" data-widget-id="<?= $widget['id'] ?>">
    <div class="board-preview">
        <div class="board-preview-header">
            <h2><a href="<?= nb_url("board/{$boardId}") ?>"><?= nb_e($board['title']) ?></a></h2>
            <a href="<?= nb_url("board/{$boardId}") ?>" class="more">더보기</a>
        </div>
        <ul class="post-list-mini">
            <?php foreach ($posts as $post): ?>
            <li>
                <a href="<?= nb_url("board/{$boardId}/{$post['id']}") ?>">
                    <?php
                    $mStyle = '';
                    if (!empty($post['title_color'])) $mStyle .= 'color:' . nb_e($post['title_color']) . ';';
                    if (!empty($post['title_bg'])) $mStyle .= 'background:' . nb_e($post['title_bg']) . ';padding:1px 4px;border-radius:2px;';
                    ?>
                    <span class="post-title"<?= $mStyle ? ' style="' . $mStyle . '"' : '' ?>><?= nb_e($post['title']) ?></span>
                    <?php if ($post['comment_count'] > 0): ?><span class="comment-count">[<?= $post['comment_count'] ?>]</span><?php endif; ?>
                    <?php if (strtotime($post['created_at']) > strtotime('-24 hours')): ?><span class="icon-new">N</span><?php endif; ?>
                    <span class="post-date"><?= date('m.d', strtotime($post['created_at'])) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
            <?php if (empty($posts)): ?><li class="empty">등록된 글이 없습니다.</li><?php endif; ?>
        </ul>
    </div>
</div>

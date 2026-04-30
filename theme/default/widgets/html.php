<?php /** 위젯: HTML 자유 */ ?>
<div class="widget widget-html" data-widget-id="<?= $widget['id'] ?>">
    <?php if (!empty($widget['title'])): ?><h3 class="widget-title"><?= nb_e($widget['title']) ?></h3><?php endif; ?>
    <?= $config['content'] ?? '' ?>
</div>

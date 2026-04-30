<?php /** 위젯: 이미지 배너 */ ?>
<div class="widget widget-banner" data-widget-id="<?= $widget['id'] ?>">
    <?php if (!empty($widget['title'])): ?><h3 class="widget-title"><?= nb_e($widget['title']) ?></h3><?php endif; ?>
    <?php if (!empty($config['image'])): ?>
        <?php if (!empty($config['link'])): ?>
            <a href="<?= nb_e($config['link']) ?>" target="<?= nb_e($config['target'] ?? '_blank') ?>">
                <img src="<?= nb_url($config['image']) ?>" alt="<?= nb_e($widget['title']) ?>">
            </a>
        <?php else: ?>
            <img src="<?= nb_url($config['image']) ?>" alt="<?= nb_e($widget['title']) ?>">
        <?php endif; ?>
    <?php endif; ?>
</div>

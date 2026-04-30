<?php /** 위젯: 로그인 박스 */ ?>
<div class="widget widget-login" data-widget-id="<?= $widget['id'] ?>">
    <?php if (!empty($widget['title'])): ?><h3 class="widget-title"><?= nb_e($widget['title']) ?></h3><?php endif; ?>
    <?php if (Auth::check()): ?>
        <div class="login-user-info">
            <div class="login-user-name"><?= nb_level_icon(Auth::level()) ?> <strong><?= nb_e(Auth::user()['nickname']) ?></strong>님</div>
            <div class="login-user-meta">
                <span>포인트 <?= number_format(Auth::user()['point'] ?? 0) ?></span>
                <span>Lv.<?= Auth::level() ?></span>
            </div>
            <div class="login-user-links">
                <a href="<?= nb_url('profile') ?>">내 정보</a>
                <?php if (Auth::isAdmin()): ?><a href="<?= nb_url('admin/') ?>">관리자</a><?php endif; ?>
                <a href="<?= nb_url('logout') ?>">로그아웃</a>
            </div>
        </div>
    <?php else: ?>
        <form method="post" action="<?= nb_url('login') ?>" class="login-widget-form">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="redirect" value="<?= nb_e($_SERVER['REQUEST_URI']) ?>">
            <input type="text" name="user_id" placeholder="아이디" required>
            <input type="password" name="password" placeholder="비밀번호" required>
            <button type="submit">로그인</button>
            <div class="login-widget-links">
                <a href="<?= nb_url('register') ?>">회원가입</a>
            </div>
        </form>
    <?php endif; ?>
</div>

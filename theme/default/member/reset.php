<?php
/**
 * NuriBoard - 비밀번호 재설정
 */
SEO::setTitle('비밀번호 재설정');
require dirname(__DIR__) . '/header.php';
?>

<div class="auth-page">
    <div class="auth-box">
        <h1>비밀번호 재설정</h1>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= nb_e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= nb_url('reset') ?>">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="token" value="<?= nb_e($token) ?>">
            <div class="form-group">
                <label for="password">새 비밀번호</label>
                <input type="password" id="password" name="password" required minlength="6" autofocus placeholder="6자 이상">
            </div>
            <div class="form-group">
                <label for="password2">새 비밀번호 확인</label>
                <input type="password" id="password2" name="password2" required minlength="6" placeholder="동일하게 입력">
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg">비밀번호 변경</button>
        </form>

        <p class="auth-link"><a href="<?= nb_url('login') ?>">← 로그인으로 돌아가기</a></p>
    </div>
</div>

<?php require dirname(__DIR__) . '/footer.php'; ?>

<?php
/**
 * NuriBoard - 비밀번호 찾기
 */
SEO::setTitle('비밀번호 찾기');
require dirname(__DIR__) . '/header.php';
?>

<div class="auth-page">
    <div class="auth-box">
        <h1>비밀번호 찾기</h1>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:20px;text-align:center">
            가입 시 사용한 아이디 또는 이메일을 입력하면<br>재설정 링크를 보내드립니다.
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= nb_e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert success"><?= nb_e($success) ?></div>
        <?php else: ?>
        <form method="post" action="<?= nb_url('forgot') ?>">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label for="user_id_or_email">아이디 또는 이메일</label>
                <input type="text" id="user_id_or_email" name="user_id_or_email" required autofocus placeholder="아이디 또는 이메일 주소">
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg">재설정 링크 발송</button>
        </form>
        <?php endif; ?>

        <p class="auth-link"><a href="<?= nb_url('login') ?>">← 로그인으로 돌아가기</a></p>
    </div>
</div>

<?php require dirname(__DIR__) . '/footer.php'; ?>

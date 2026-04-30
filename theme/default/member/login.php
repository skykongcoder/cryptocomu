<?php
/**
 * NuriBoard 기본 테마 - 로그인
 */
SEO::setTitle('로그인');
require dirname(__DIR__) . '/header.php';
?>

<div class="auth-page">
    <div class="auth-box">
        <h1>로그인</h1>
        <?php if (!empty($error)): ?>
            <div class="alert error"><?= nb_e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert success"><?= nb_e($success) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= nb_url('login') ?>">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="redirect" value="<?= nb_e($_GET['redirect'] ?? nb_url('/')) ?>">
            <div class="form-group">
                <label for="user_id">아이디</label>
                <input type="text" id="user_id" name="user_id" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div style="margin-bottom:16px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:#64748b">
                    <input type="checkbox" name="remember" value="1"> 자동로그인
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg">로그인</button>
        </form>

        <?php
        $hasKakao  = nb_setting('social_login_enabled') === '1' && !empty(nb_setting('kakao_client_id'));
        $hasNaver  = nb_setting('social_login_enabled') === '1' && !empty(nb_setting('naver_client_id'));
        $hasGoogle = nb_setting('social_login_enabled') === '1' && !empty(nb_setting('google_client_id'));
        $hasSocial = $hasKakao || $hasNaver || $hasGoogle;
        ?>
        <?php if ($hasSocial): ?>
        <div class="social-divider"><span>소셜 계정으로 간편 로그인</span></div>
        <div class="social-btns">
            <?php if ($hasKakao): ?>
            <a href="<?= nb_url('oauth/kakao') ?>" class="social-btn kakao-btn">
                <img src="<?= nb_asset('img/kakao.png') ?>" width="20" height="20" alt="카카오" style="border-radius:4px">
                카카오로 로그인
            </a>
            <?php endif; ?>
            <?php if ($hasNaver): ?>
            <a href="<?= nb_url('oauth/naver') ?>" class="social-btn naver-btn">
                <img src="<?= nb_asset('img/naver.png') ?>" width="20" height="20" alt="네이버" style="border-radius:4px">
                네이버로 로그인
            </a>
            <?php endif; ?>
            <?php if ($hasGoogle): ?>
            <a href="<?= nb_url('oauth/google') ?>" class="social-btn google-btn">
                <img src="<?= nb_asset('img/google.png') ?>" width="20" height="20" alt="구글" style="border-radius:4px">
                구글로 로그인
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p class="auth-link">
            <a href="<?= nb_url('forgot') ?>">비밀번호를 잊으셨나요?</a>
            &nbsp;·&nbsp;
            계정이 없으신가요? <a href="<?= nb_url('register') ?>">회원가입</a>
        </p>
    </div>
</div>

<style>
.social-divider{display:flex;align-items:center;gap:12px;margin:20px 0 16px;color:#94a3b8;font-size:13px}
.social-divider::before,.social-divider::after{content:'';flex:1;height:1px;background:#e2e8f0}
.social-btns{display:flex;flex-direction:column;gap:10px}
.social-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 16px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;transition:opacity .15s}
.social-btn:hover{opacity:.88;text-decoration:none}
.kakao-btn{background:#FEE500;color:#3C1E1E}
.naver-btn{background:#03C75A;color:#fff}
.google-btn{background:#fff;color:#333;border:1px solid #d1d5db}
</style>

<?php require dirname(__DIR__) . '/footer.php'; ?>

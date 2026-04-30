<?php
/**
 * NuriBoard 기본 테마 - 회원가입
 */
SEO::setTitle('회원가입');
require dirname(__DIR__) . '/header.php';
?>

<div class="auth-page">
    <div class="auth-box">
        <h1>회원가입</h1>
        <?php if (!empty($error)): ?>
            <div class="alert error"><?= nb_e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= nb_url('register') ?>" id="registerForm" enctype="multipart/form-data">
            <?= Auth::csrfField() ?>
            <div class="form-group" style="text-align:center;margin-bottom:20px">
                <label>프로필 이미지 <small style="font-weight:normal;color:#999">(선택)</small></label>
                <div class="reg-avatar-wrap">
                    <div class="reg-avatar-preview" id="avatarPreview">
                        <span>📷</span>
                    </div>
                    <input type="file" name="profile_image" id="profileImageInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                    <button type="button" class="btn btn-sm" onclick="document.getElementById('profileImageInput').click()" style="margin-top:8px">이미지 선택</button>
                </div>
            </div>
            <div class="form-group">
                <label for="user_id">아이디</label>
                <input type="text" id="user_id" name="user_id" required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_]+" placeholder="영문, 숫자, 밑줄 (3~20자)">
            </div>
            <div class="form-group">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" required minlength="4" placeholder="4자 이상">
            </div>
            <div class="form-group">
                <label for="password2">비밀번호 확인</label>
                <input type="password" id="password2" required minlength="4">
            </div>
            <div class="form-group">
                <label for="nickname">닉네임</label>
                <input type="text" id="nickname" name="nickname" required minlength="2" maxlength="20">
            </div>
            <div class="form-group">
                <label for="email">이메일 (선택)</label>
                <input type="email" id="email" name="email">
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg">회원가입</button>
        </form>

        <?php
        $hasKakao  = nb_setting('social_login_enabled') === '1' && !empty(nb_setting('kakao_client_id'));
        $hasNaver  = nb_setting('social_login_enabled') === '1' && !empty(nb_setting('naver_client_id'));
        $hasGoogle = nb_setting('social_login_enabled') === '1' && !empty(nb_setting('google_client_id'));
        $hasSocial = $hasKakao || $hasNaver || $hasGoogle;
        ?>
        <?php if ($hasSocial): ?>
        <div class="social-divider"><span>소셜 계정으로 간편 가입</span></div>
        <div class="social-btns">
            <?php if ($hasKakao): ?>
            <a href="<?= nb_url('oauth/kakao') ?>" class="social-btn kakao-btn">
                <img src="<?= nb_asset('img/kakao.png') ?>" width="20" height="20" alt="카카오" style="border-radius:4px">
                카카오로 시작하기
            </a>
            <?php endif; ?>
            <?php if ($hasNaver): ?>
            <a href="<?= nb_url('oauth/naver') ?>" class="social-btn naver-btn">
                <img src="<?= nb_asset('img/naver.png') ?>" width="20" height="20" alt="네이버" style="border-radius:4px">
                네이버로 시작하기
            </a>
            <?php endif; ?>
            <?php if ($hasGoogle): ?>
            <a href="<?= nb_url('oauth/google') ?>" class="social-btn google-btn">
                <img src="<?= nb_asset('img/google.png') ?>" width="20" height="20" alt="구글" style="border-radius:4px">
                구글로 시작하기
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p class="auth-link">이미 계정이 있으신가요? <a href="<?= nb_url('login') ?>">로그인</a></p>
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

<style>
.reg-avatar-wrap{display:flex;flex-direction:column;align-items:center}
.reg-avatar-preview{width:80px;height:80px;border-radius:50%;background:#f1f5f9;border:2px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:28px;overflow:hidden;cursor:pointer}
.reg-avatar-preview img{width:100%;height:100%;object-fit:cover}
</style>
<script>
function previewAvatar(input){
    if(input.files&&input.files[0]){
        var reader=new FileReader();
        reader.onload=function(e){
            document.getElementById('avatarPreview').innerHTML='<img src="'+e.target.result+'">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
document.getElementById('avatarPreview').addEventListener('click',function(){document.getElementById('profileImageInput').click()});
document.getElementById('registerForm').addEventListener('submit', function(e) {
    var pw = document.getElementById('password').value;
    var pw2 = document.getElementById('password2').value;
    if (pw !== pw2) {
        e.preventDefault();
        alert('비밀번호가 일치하지 않습니다.');
    }
});
</script>

<?php require dirname(__DIR__) . '/footer.php'; ?>

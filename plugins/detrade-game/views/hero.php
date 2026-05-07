<?php
/**
 * 메인 페이지 히어로 — Detrade 게임 배너
 * Plugin filter 'home.hero.extra' 로 메인 페이지에 자동 삽입됨
 */
$_isLoggedIn = class_exists('Auth') && Auth::check();
$_balance = $_isLoggedIn ? dt_get_point(Auth::id()) : null;
?>
<style>
.dt-hero-banner {
    margin:18px auto; max-width:1200px;
    background:
        linear-gradient(135deg, rgba(251,191,36,0.15) 0%, rgba(245,158,11,0.05) 50%, rgba(0,255,212,0.1) 100%),
        radial-gradient(circle at 80% 50%, rgba(251,191,36,0.2), transparent 60%);
    border:1px solid rgba(251,191,36,0.3);
    border-radius:16px; padding:24px 28px;
    display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap;
    position:relative; overflow:hidden;
}
.dt-hero-banner::before {
    content:""; position:absolute; top:-30px; right:-30px;
    width:200px; height:200px;
    background:radial-gradient(circle, rgba(251,191,36,0.15), transparent 70%);
    pointer-events:none;
}
.dt-hero-text h2 { margin:0 0 6px; font-size:22px; font-weight:800; }
.dt-hero-text h2 .sparkle { display:inline-block; animation:dt-spin 4s linear infinite; }
@keyframes dt-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
.dt-hero-text p { margin:0; font-size:13px; color:var(--text-light,#94a3b8); line-height:1.6; }
.dt-hero-text p b { color:var(--accent,#00ffd4); }
.dt-hero-cta { display:flex; gap:10px; align-items:center; flex-wrap:wrap; z-index:1; }
.dt-hero-cta .play-btn {
    padding:14px 26px; background:linear-gradient(90deg,#fbbf24,#f59e0b);
    color:#1c1917; font-weight:700; font-size:14px;
    border-radius:12px; text-decoration:none; box-shadow:0 6px 20px rgba(251,191,36,0.4);
    transition:transform .15s;
}
.dt-hero-cta .play-btn:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(251,191,36,0.5); }
.dt-hero-cta .balance-mini { font-size:12px; color:var(--text-light,#94a3b8); }
.dt-hero-cta .balance-mini b { color:#fbbf24; font-size:14px; }
.dt-hero-tag {
    position:absolute; top:12px; right:14px;
    background:rgba(245,158,11,0.2); color:#fbbf24;
    padding:3px 10px; border-radius:10px; font-size:10px; font-weight:700;
    letter-spacing:0.05em; z-index:1;
}
@media (max-width:640px) {
    .dt-hero-banner { padding:18px 20px; }
    .dt-hero-text h2 { font-size:18px; }
}
</style>

<div class="dt-hero-banner">
    <div class="dt-hero-tag">재미용 · 환금불가</div>
    <div class="dt-hero-text">
        <h2><span class="sparkle">🎮</span> 코인 트레이딩 게임</h2>
        <p>
            업/다운 가격 예측 게임을 사이트 포인트로 즐겨보세요.<br>
            <b>매일 출석 보너스 +<?= number_format(dt_config()['daily_bonus'] ?? 10000) ?>PT</b> · 글쓰기 보상 + 댓글 보상으로 포인트 획득.
        </p>
    </div>
    <div class="dt-hero-cta">
        <?php if ($_isLoggedIn): ?>
            <span class="balance-mini">💎 <b><?= number_format($_balance) ?></b> PT</span>
            <a href="<?= nb_url('game') ?>" class="play-btn">🚀 게임 시작</a>
        <?php else: ?>
            <a href="<?= nb_url('login') ?>" class="play-btn">로그인 후 플레이 →</a>
        <?php endif; ?>
    </div>
</div>

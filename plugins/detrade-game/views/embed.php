<?php
/**
 * /game — 게임 iframe 페이지
 * 로그인된 유저에게 Detrade login API 호출 후 임베드 URL 을 받아 iframe 으로 표시.
 */
require __DIR__ . '/../../../theme/default/header.php';

$user = Auth::user();
$balance = dt_get_point((int)$user['id']);
$cfg = dt_config();

$loginResult = dt_login_get_embed_url($user);
$embedUrl = $loginResult['ok'] ? ($loginResult['embed_url'] ?? '') : '';
$loginError = $loginResult['ok'] ? '' : ($loginResult['error'] ?? '알 수 없는 오류');
?>
<style>
.dt-game-page { padding:24px 0 60px; }
.dt-game-head { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
.dt-game-head h1 { margin:0; font-size:22px; }
.dt-game-head .balance-pill { display:flex; align-items:center; gap:8px; padding:8px 14px; background:linear-gradient(90deg,#fbbf24,#f59e0b); color:#1c1917; border-radius:20px; font-weight:700; font-size:13px; box-shadow:0 4px 12px rgba(251,191,36,0.25); }
.dt-game-head .balance-pill b { font-size:16px; }
.dt-game-warning {
    background:linear-gradient(90deg, rgba(251,191,36,0.1), rgba(251,191,36,0.05));
    border:1px solid #fbbf24; border-left:4px solid #f59e0b;
    border-radius:10px; padding:12px 16px; margin-bottom:14px;
    font-size:13px; color:#92400e;
    display:flex; gap:10px; align-items:flex-start;
}
.dt-game-warning .icon { font-size:20px; line-height:1; }
.dt-game-warning b { color:#78350f; }
.dt-game-frame-wrap {
    position:relative; width:100%; max-width:1200px; margin:0 auto;
    border:1px solid var(--border-color, #2a2a3e); border-radius:14px; overflow:hidden;
    box-shadow:0 8px 30px rgba(0,0,0,0.3);
    background:#0a0a1f;
}
.dt-game-frame-wrap iframe { display:block; width:100%; height:780px; border:0; }
@media (max-width:768px) {
    .dt-game-frame-wrap iframe { height:600px; }
}
.dt-game-error {
    background:rgba(239,68,68,0.08); border:1px solid #ef4444; color:#fca5a5;
    padding:24px; border-radius:12px; text-align:center;
}
.dt-game-error b { color:#fff; display:block; margin-bottom:8px; font-size:15px; }
.dt-game-error .raw { font-family:monospace; font-size:11px; opacity:0.7; margin-top:10px; }
.dt-game-foot { margin-top:14px; font-size:11px; color:var(--text-light, #94a3b8); text-align:center; line-height:1.7; }
.dt-game-foot a { color:var(--accent, #00ffd4); }
</style>

<div class="container dt-game-page">
    <div class="dt-game-head">
        <div>
            <h1>🎮 코인 트레이딩 게임</h1>
            <div style="font-size:12px;color:var(--text-light,#94a3b8)">사이트 포인트로 즐기는 가격 예측 게임 — 100% 재미용</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="balance-pill">
                💎 보유 <b><?= number_format($balance) ?></b> PT
            </span>
            <a href="<?= nb_url('wallet') ?>" style="font-size:12px;color:var(--accent,#00ffd4);text-decoration:none;padding:8px 12px;border:1px solid var(--border-color,#2a2a3e);border-radius:8px">지갑 →</a>
        </div>
    </div>

    <div class="dt-game-warning">
        <span class="icon">⚠️</span>
        <div>
            <b>이 게임은 100% 재미·시뮬레이션용입니다.</b><br>
            <span style="font-size:12px">사용되는 포인트는 사이트 자체 가상 포인트이며, <b>현금/암호화폐로 환전하거나 출금할 수 없습니다</b>. 실제 투자가 아니며, 게임 결과는 어떤 금전적 가치도 없습니다.</span>
        </div>
    </div>

    <?php if ($embedUrl): ?>
    <div class="dt-game-frame-wrap">
        <iframe src="<?= htmlspecialchars($embedUrl, ENT_QUOTES) ?>"
                allow="autoplay; fullscreen; clipboard-read; clipboard-write"
                referrerpolicy="strict-origin-when-cross-origin"
                title="Detrade 코인 트레이딩 게임"></iframe>
    </div>
    <?php else: ?>
    <div class="dt-game-error">
        <b>🛠 게임을 불러올 수 없습니다</b>
        <div><?= htmlspecialchars($loginError) ?></div>
        <?php if (Auth::isAdmin()): ?>
        <div class="raw">관리자 → 플러그인 → Detrade 게임에서 apiKey/privateKey/baseurl 확인</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="dt-game-foot">
        포인트가 부족하신가요? <a href="<?= nb_url('wallet') ?>">지갑에서 일일 보너스</a>를 받으세요 ·
        글/댓글을 작성하면 자동으로 포인트가 쌓입니다.<br>
        ⚠️ 본 게임은 사행성 도박이 아니며, 청소년 보호 대상이 아닙니다 — 환금 기능 없음.
    </div>
</div>

<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

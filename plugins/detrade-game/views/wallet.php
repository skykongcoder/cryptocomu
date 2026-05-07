<?php
/**
 * /wallet — 내 포인트 지갑 + 베팅 내역 + 일일 보너스 청구
 */
require __DIR__ . '/../../../theme/default/header.php';

$user = Auth::user();
$userId = (int)$user['id'];
$balance = dt_get_point($userId);
$cfg = dt_config();

$prefix = DB::getPrefix();

// 오늘 일일 보너스 받았는지
$today = date('Y-m-d');
$bonusToday = DB::fetch("SELECT amount FROM {$prefix}dt_daily_bonus WHERE user_id = ? AND claimed_date = ?", [$userId, $today]);
$canClaimBonus = !$bonusToday;

// 최근 50개 거래 내역
$orders = DB::fetchAll(
    "SELECT * FROM {$prefix}dt_orders WHERE user_id = ? ORDER BY id DESC LIMIT 50",
    [$userId]
);

// 통계
$stats = DB::fetch("
    SELECT
        SUM(CASE WHEN op_type='deduction' THEN amount ELSE 0 END) AS total_bet,
        SUM(CASE WHEN op_type='add'       THEN amount ELSE 0 END) AS total_win,
        COUNT(CASE WHEN op_type='deduction' THEN 1 END) AS bet_count
    FROM {$prefix}dt_orders WHERE user_id = ?
", [$userId]);
$totalBet = (float)($stats['total_bet'] ?? 0);
$totalWin = (float)($stats['total_win'] ?? 0);
$net = $totalWin - $totalBet;
?>
<style>
.dt-wallet-page { padding:24px 0 60px; }
.dt-wallet-head h1 { margin:0 0 8px; font-size:24px; }
.dt-wallet-head p { color:var(--text-light,#94a3b8); margin:0 0 18px; font-size:13px; }

.dt-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; margin-bottom:22px; }
.dt-card { background:var(--card-bg,#1a1a2e); border:1px solid var(--border-color,#2a2a3e); border-radius:14px; padding:18px; }
.dt-card .label { font-size:11px; color:var(--text-light,#94a3b8); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px; }
.dt-card .value { font-size:24px; font-weight:700; color:var(--text,#fff); }
.dt-card .sub { font-size:11px; color:var(--text-light,#94a3b8); margin-top:4px; }
.dt-card.balance { background:linear-gradient(135deg,#fbbf24,#f59e0b); color:#1c1917; }
.dt-card.balance .label { color:rgba(0,0,0,0.6); }
.dt-card.balance .value { color:#1c1917; font-size:28px; }
.dt-card.balance .sub { color:rgba(0,0,0,0.6); }
.dt-card.profit { color:<?= $net >= 0 ? '#10b981' : '#ef4444' ?>; }

.dt-bonus-box { background:linear-gradient(90deg, rgba(0,255,212,0.1), rgba(96,165,250,0.1)); border:1px solid var(--accent,#00ffd4); border-radius:14px; padding:18px; margin-bottom:22px; display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; }
.dt-bonus-box .info b { color:var(--accent,#00ffd4); font-size:18px; }
.dt-bonus-btn { padding:12px 24px; background:var(--accent,#00ffd4); color:#0a0a1f; border:0; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px; }
.dt-bonus-btn:disabled { opacity:0.4; cursor:not-allowed; }

.dt-action-row { display:flex; gap:10px; margin-bottom:22px; flex-wrap:wrap; }
.dt-action-row a { padding:12px 22px; border-radius:10px; text-decoration:none; font-weight:600; }
.dt-action-row .primary { background:linear-gradient(90deg,#fbbf24,#f59e0b); color:#1c1917; }
.dt-action-row .secondary { background:var(--card-bg,#1a1a2e); color:var(--text,#fff); border:1px solid var(--border-color,#2a2a3e); }

.dt-history { background:var(--card-bg,#1a1a2e); border:1px solid var(--border-color,#2a2a3e); border-radius:14px; padding:18px; }
.dt-history h3 { margin:0 0 12px; font-size:15px; }
.dt-history table { width:100%; border-collapse:collapse; font-size:12px; }
.dt-history th, .dt-history td { padding:8px 10px; text-align:left; border-bottom:1px solid var(--border-color,#2a2a3e); }
.dt-history th { font-size:11px; color:var(--text-light,#94a3b8); text-transform:uppercase; }
.dt-history .amt-pos { color:#10b981; }
.dt-history .amt-neg { color:#ef4444; }
.dt-history .empty { color:var(--text-light,#94a3b8); padding:30px; text-align:center; }
.dt-history .badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:10px; font-weight:600; }
.dt-history .badge-deduction { background:rgba(239,68,68,0.15); color:#ef4444; }
.dt-history .badge-add { background:rgba(16,185,129,0.15); color:#10b981; }
.dt-history .badge-order_push { background:rgba(148,163,184,0.15); color:#94a3b8; }

.dt-disclaimer { margin-top:24px; padding:14px 18px; background:rgba(245,158,11,0.05); border:1px dashed #f59e0b; border-radius:10px; font-size:11px; color:#fbbf24; line-height:1.7; }
.dt-disclaimer b { color:#fcd34d; }
</style>

<div class="container dt-wallet-page">
    <div class="dt-wallet-head">
        <h1>💎 내 포인트 지갑</h1>
        <p>사이트 가상 포인트는 환금/출금 불가 — 게임·게시판 활동에만 사용됩니다.</p>
    </div>

    <div class="dt-cards">
        <div class="dt-card balance">
            <div class="label">현재 잔액</div>
            <div class="value"><?= number_format($balance) ?> <span style="font-size:14px">PT</span></div>
            <div class="sub">≈ 게임 약 <?= number_format($balance / max(1, (int)($cfg['min_amount'] ?? 1000))) ?>회 가능</div>
        </div>
        <div class="dt-card">
            <div class="label">총 베팅</div>
            <div class="value"><?= number_format($totalBet) ?></div>
            <div class="sub"><?= number_format($stats['bet_count'] ?? 0) ?>회</div>
        </div>
        <div class="dt-card">
            <div class="label">총 정산</div>
            <div class="value"><?= number_format($totalWin) ?></div>
        </div>
        <div class="dt-card profit">
            <div class="label">순손익</div>
            <div class="value"><?= ($net >= 0 ? '+' : '') . number_format($net) ?></div>
            <div class="sub"><?= $totalBet > 0 ? round($totalWin / $totalBet * 100, 1) . '% 회수' : '아직 베팅 없음' ?></div>
        </div>
    </div>

    <div class="dt-bonus-box">
        <div class="info">
            <div style="font-size:13px;color:var(--text-light,#94a3b8);margin-bottom:4px">🎁 일일 출석 보너스</div>
            <div>매일 <b><?= number_format($cfg['daily_bonus'] ?? 10000) ?> PT</b> 무료 충전</div>
        </div>
        <button class="dt-bonus-btn" id="dt-claim-btn" <?= $canClaimBonus ? '' : 'disabled' ?>>
            <?= $canClaimBonus ? '오늘 보너스 받기 →' : '✓ 오늘 받음 (내일 다시 오세요)' ?>
        </button>
    </div>

    <div class="dt-action-row">
        <a href="<?= nb_url('game') ?>" class="primary">🎮 게임 시작 →</a>
        <a href="<?= nb_url('') ?>" class="secondary">메인으로</a>
    </div>

    <div class="dt-history">
        <h3>최근 거래 내역 (50건)</h3>
        <?php if (empty($orders)): ?>
        <div class="empty">아직 거래 내역이 없습니다. 게임을 시작해 보세요!</div>
        <?php else: ?>
        <table>
            <thead>
            <tr><th>시각</th><th>유형</th><th>금액</th><th>잔액</th><th>주문ID</th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o):
                $sign = $o['op_type'] === 'deduction' ? '-' : ($o['op_type'] === 'add' ? '+' : '');
                $cls = $o['op_type'] === 'deduction' ? 'amt-neg' : ($o['op_type'] === 'add' ? 'amt-pos' : '');
                $opLabel = ['deduction'=>'베팅','add'=>'정산','order_push'=>'주문기록'][$o['op_type']] ?? $o['op_type'];
            ?>
            <tr>
                <td><?= htmlspecialchars($o['created_at']) ?></td>
                <td><span class="badge badge-<?= $o['op_type'] ?>"><?= $opLabel ?></span></td>
                <td class="<?= $cls ?>"><?= $sign ?><?= number_format($o['amount']) ?> <?= htmlspecialchars($o['currency']) ?></td>
                <td><?= $o['balance_after'] !== null ? number_format($o['balance_after']) : '-' ?></td>
                <td style="font-family:monospace;font-size:11px;opacity:0.7"><?= htmlspecialchars(mb_substr($o['biz_id'], 0, 24)) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="dt-disclaimer">
        <b>⚠️ 안내</b><br>
        ・ 본 사이트의 포인트(PT)는 가상 포인트이며 실제 화폐가치를 갖지 않습니다.<br>
        ・ 환전·출금·양도 기능을 일체 제공하지 않습니다.<br>
        ・ 게임은 단순 재미·시뮬레이션 콘텐츠이며 사행행위가 아닙니다.<br>
        ・ 청소년 보호법, 정보통신망법, 형법 도박죄에 해당하지 않도록 환금성을 차단했습니다.
    </div>
</div>

<script>
document.getElementById('dt-claim-btn')?.addEventListener('click', function(e) {
    var btn = e.currentTarget;
    if (btn.disabled) return;
    btn.disabled = true;
    btn.textContent = '청구 중...';
    fetch('<?= nb_url('api/wallet/daily-bonus') ?>', { method: 'POST', credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (j.ok) {
                btn.textContent = '✓ +' + Number(j.amount).toLocaleString() + ' PT 받음!';
                setTimeout(function(){ location.reload(); }, 1200);
            } else {
                btn.textContent = j.error || '실패';
            }
        });
});
</script>

<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

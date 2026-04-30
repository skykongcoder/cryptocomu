<?php
require NB_ROOT . '/theme/default/header.php';
$rate = $ticker['signed_change_rate'] ?? 0;
$cls  = $rate > 0 ? 'up' : ($rate < 0 ? 'down' : 'flat');
$code = explode('-', $market)[1] ?? $market;
$name = $info['korean_name'] ?? $code;
?>
<link rel="stylesheet" href="<?= nb_url('plugins/crypto-market/assets/market.css') ?>">

<div class="container cm-page" style="padding:24px 16px;max-width:1200px;margin:0 auto">
    <nav class="cm-breadcrumb"><a href="<?= nb_url('coin') ?>">코인 시세</a> &raquo; <?= htmlspecialchars($name) ?></nav>

    <div class="cm-detail-head">
        <div>
            <h1 class="cm-detail-title">
                <?= htmlspecialchars($name) ?>
                <span class="cm-detail-symbol"><?= htmlspecialchars($code) ?> · <?= htmlspecialchars($market) ?></span>
            </h1>
            <?php if (!empty($info['english_name'])): ?>
            <div class="cm-detail-eng"><?= htmlspecialchars($info['english_name']) ?></div>
            <?php endif; ?>
        </div>
        <div class="cm-detail-price <?= $cls ?>">
            <div class="cm-detail-now"><?= cm_fmt_price($ticker['trade_price'] ?? 0) ?> <small><?= explode('-', $market)[0] ?></small></div>
            <div class="cm-detail-rate">
                <?= cm_fmt_pct($rate) ?>
                <span class="cm-detail-change">(<?= ($ticker['signed_change_price'] ?? 0) >= 0 ? '+' : '' ?><?= cm_fmt_price($ticker['signed_change_price'] ?? 0) ?>)</span>
            </div>
        </div>
    </div>

    <div class="cm-detail-grid">
        <div class="cm-stat"><div class="cm-stat-l">고가(24H)</div><div class="cm-stat-v up"><?= cm_fmt_price($ticker['high_price'] ?? 0) ?></div></div>
        <div class="cm-stat"><div class="cm-stat-l">저가(24H)</div><div class="cm-stat-v down"><?= cm_fmt_price($ticker['low_price'] ?? 0) ?></div></div>
        <div class="cm-stat"><div class="cm-stat-l">거래량(24H)</div><div class="cm-stat-v"><?= number_format($ticker['acc_trade_volume_24h'] ?? 0, 3) ?></div></div>
        <div class="cm-stat"><div class="cm-stat-l">거래대금(24H)</div><div class="cm-stat-v"><?= cm_fmt_volume($ticker['acc_trade_price_24h'] ?? 0) ?></div></div>
        <div class="cm-stat"><div class="cm-stat-l">52주 최고</div><div class="cm-stat-v up"><?= cm_fmt_price($ticker['highest_52_week_price'] ?? 0) ?></div></div>
        <div class="cm-stat"><div class="cm-stat-l">52주 최저</div><div class="cm-stat-v down"><?= cm_fmt_price($ticker['lowest_52_week_price'] ?? 0) ?></div></div>
    </div>

    <div class="cm-chart-card">
        <h3>30일 가격 차트</h3>
        <canvas id="cmChart" height="300"></canvas>
    </div>

    <div class="cm-detail-actions">
        <a href="https://upbit.com/exchange?code=CRIX.UPBIT.<?= htmlspecialchars($market) ?>" target="_blank" rel="noopener" class="cm-btn-primary">업비트에서 거래하기 ↗</a>
        <a href="<?= nb_url('coin') ?>" class="cm-btn-secondary">시세 목록</a>
    </div>

    <p class="cm-disclaimer">* 데이터: 업비트 공개 API. 투자 책임은 본인에게 있습니다.</p>
</div>

<script>
(function () {
    const candles = <?= json_encode(array_reverse($candles), JSON_UNESCAPED_UNICODE) ?>;
    if (!candles || !candles.length) return;
    const canvas = document.getElementById('cmChart');
    const ctx = canvas.getContext('2d');
    canvas.width = canvas.offsetWidth;
    const W = canvas.width, H = 300;

    const prices = candles.map(c => c.trade_price);
    const dates  = candles.map(c => (c.candle_date_time_kst || '').slice(5,10));
    const min = Math.min.apply(null, prices), max = Math.max.apply(null, prices);
    const pad = (max - min) * 0.1 || 1;
    const lo = min - pad, hi = max + pad;

    function x(i) { return 50 + (W - 70) * i / (prices.length - 1); }
    function y(p) { return H - 30 - (H - 60) * (p - lo) / (hi - lo); }

    // grid
    ctx.strokeStyle = '#eef0f3'; ctx.lineWidth = 1;
    for (let i=0;i<5;i++) {
        const py = 20 + (H-50) * i / 4;
        ctx.beginPath(); ctx.moveTo(50, py); ctx.lineTo(W-20, py); ctx.stroke();
        const v = hi - (hi-lo) * i / 4;
        ctx.fillStyle = '#9ca3af'; ctx.font = '11px sans-serif'; ctx.textAlign='right';
        ctx.fillText(v.toLocaleString('ko-KR',{maximumFractionDigits:0}), 45, py+4);
    }
    // x labels (5개만)
    ctx.textAlign='center';
    for (let i=0;i<prices.length;i+=Math.ceil(prices.length/5)) {
        ctx.fillText(dates[i], x(i), H-10);
    }
    // line
    const last = prices[prices.length-1], first = prices[0];
    const color = last >= first ? '#dc2626' : '#2563eb';
    ctx.strokeStyle = color; ctx.lineWidth = 2;
    ctx.beginPath();
    prices.forEach((p,i) => i===0 ? ctx.moveTo(x(i), y(p)) : ctx.lineTo(x(i), y(p)));
    ctx.stroke();
    // fill
    const grad = ctx.createLinearGradient(0,20,0,H-30);
    grad.addColorStop(0, color + '33');
    grad.addColorStop(1, color + '00');
    ctx.fillStyle = grad;
    ctx.lineTo(x(prices.length-1), H-30);
    ctx.lineTo(x(0), H-30);
    ctx.closePath();
    ctx.fill();
})();
</script>

<?php require NB_ROOT . '/theme/default/footer.php'; ?>

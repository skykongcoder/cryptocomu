<?php
/**
 * 위젯: 코인 시세 (메인 페이지용)
 *
 * config:
 *   markets: ["KRW-BTC","KRW-ETH",...]  - 표시할 마켓 목록
 *   limit  : 5                           - 미설정시 markets 만큼
 */
$markets = $config['markets'] ?? ['KRW-BTC','KRW-ETH','KRW-XRP','KRW-SOL','KRW-DOGE','KRW-ADA','KRW-TRX','KRW-AVAX'];
$tickers = function_exists('cm_tickers') ? cm_tickers($markets) : [];
$names   = [];
if (function_exists('cm_markets')) {
    foreach (cm_markets('KRW') as $m) $names[$m['market']] = $m['korean_name'] ?? '';
}
?>
<div class="widget widget-crypto-market" data-widget-id="<?= $widget['id'] ?>">
    <?php if (!empty($widget['title'])): ?>
        <h3 class="widget-title">
            <?= nb_e($widget['title']) ?>
            <a href="<?= nb_url('coin') ?>" style="float:right;font-size:12px;font-weight:500;color:#2563eb;text-decoration:none">전체보기 ›</a>
        </h3>
    <?php endif; ?>
    <div class="cm-widget-list">
        <?php if (empty($tickers)): ?>
        <div class="cm-widget-empty">시세를 불러올 수 없습니다.</div>
        <?php else: foreach ($tickers as $t):
            $rate = $t['signed_change_rate'] ?? 0;
            $cls  = $rate > 0 ? 'up' : ($rate < 0 ? 'down' : 'flat');
            $code = explode('-', $t['market'])[1] ?? '';
        ?>
        <a href="<?= nb_url('coin') ?>/<?= htmlspecialchars($t['market']) ?>" class="cm-widget-row">
            <div class="cm-widget-name">
                <strong><?= htmlspecialchars($names[$t['market']] ?? $code) ?></strong>
                <small><?= htmlspecialchars($code) ?></small>
            </div>
            <div class="cm-widget-price">
                <div class="<?= $cls ?>"><?= function_exists('cm_fmt_price') ? cm_fmt_price($t['trade_price'] ?? 0) : number_format($t['trade_price'] ?? 0) ?></div>
                <div class="<?= $cls ?> cm-widget-rate">
                    <?= function_exists('cm_fmt_pct') ? cm_fmt_pct($rate) : number_format($rate * 100, 2) . '%' ?>
                </div>
            </div>
        </a>
        <?php endforeach; endif; ?>
    </div>
</div>

<style>
.widget-crypto-market .widget-title { margin-bottom:10px; }
.cm-widget-list { display:flex; flex-direction:column; }
.cm-widget-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 4px; border-bottom:1px solid #f1f5f9;
    text-decoration:none; color:inherit; transition:background .12s;
}
.cm-widget-row:hover { background:#fafbfc; text-decoration:none; }
.cm-widget-row:last-child { border-bottom:0; }
.cm-widget-name strong { display:block; font-weight:700; font-size:14px; color:var(--text); }
.cm-widget-name small { font-size:11px; color:var(--text-light); }
.cm-widget-price { text-align:right; font-variant-numeric:tabular-nums; }
.cm-widget-price > div:first-child { font-size:14px; font-weight:600; }
.cm-widget-rate { font-size:12px; font-weight:700; margin-top:1px; }
.cm-widget-empty { padding:20px; text-align:center; color:var(--text-light); font-size:13px; }
.widget-crypto-market .up   { color:#dc2626; }
.widget-crypto-market .down { color:#2563eb; }
.widget-crypto-market .flat { color:var(--text); }
</style>

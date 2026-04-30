<?php
require NB_ROOT . '/theme/default/header.php';
?>
<link rel="stylesheet" href="<?= nb_url('plugins/crypto-market/assets/market.css') ?>">

<div class="container cm-page" style="padding:24px 16px;max-width:1200px;margin:0 auto">
    <div class="cm-page-head">
        <h1>코인 시세</h1>
        <p>업비트 실시간 시세 · 30초마다 자동 갱신</p>
    </div>

    <form method="get" class="cm-filter">
        <div class="cm-tabs">
            <a href="<?= nb_url('coin') ?>?quote=KRW&sort=<?= htmlspecialchars($sort) ?>" class="cm-tab <?= $quote === 'KRW' ? 'active' : '' ?>">원화 (KRW)</a>
            <a href="<?= nb_url('coin') ?>?quote=BTC&sort=<?= htmlspecialchars($sort) ?>" class="cm-tab <?= $quote === 'BTC' ? 'active' : '' ?>">BTC 마켓</a>
        </div>
        <div class="cm-tools">
            <input type="hidden" name="quote" value="<?= htmlspecialchars($quote) ?>">
            <input type="hidden" name="sort"  value="<?= htmlspecialchars($sort) ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="비트코인, BTC..." class="cm-search">
            <button type="submit" class="cm-search-btn">검색</button>
        </div>
    </form>

    <div class="cm-sort">
        정렬:
        <?php
            $sorts = ['volume'=>'거래대금','change'=>'상승률','fall'=>'하락률','price'=>'가격','name'=>'이름'];
            foreach ($sorts as $k => $label):
                $href = nb_url('coin') . '?quote=' . $quote . '&sort=' . $k . ($q ? '&q=' . urlencode($q) : '');
        ?>
        <a href="<?= $href ?>" class="cm-sort-pill <?= $sort === $k ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <div class="cm-table-wrap">
        <table class="cm-table" id="cmTable" data-quote="<?= htmlspecialchars($quote) ?>">
            <thead>
                <tr>
                    <th class="cm-th-name">이름</th>
                    <th class="cm-th-price">현재가</th>
                    <th class="cm-th-rate">변동률(24H)</th>
                    <th class="cm-th-vol">거래대금(24H)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="4" class="cm-empty">시세를 불러올 수 없습니다. 잠시 후 다시 시도해주세요.</td></tr>
                <?php else: foreach ($rows as $r):
                    $rate  = $r['signed_change_rate'] ?? 0;
                    $cls   = $rate > 0 ? 'up' : ($rate < 0 ? 'down' : 'flat');
                    $code  = explode('-', $r['market'])[1] ?? $r['market'];
                ?>
                <tr class="cm-row" data-market="<?= htmlspecialchars($r['market']) ?>" onclick="location.href='<?= nb_url('coin') ?>/<?= htmlspecialchars($r['market']) ?>'">
                    <td class="cm-name">
                        <div class="cm-name-ko"><?= htmlspecialchars($r['korean_name'] ?: $code) ?></div>
                        <div class="cm-name-sym"><?= htmlspecialchars($code) ?>/<?= htmlspecialchars($quote) ?></div>
                    </td>
                    <td class="cm-price <?= $cls ?>" data-field="price"><?= cm_fmt_price($r['trade_price'] ?? 0) ?></td>
                    <td class="cm-rate <?= $cls ?>" data-field="rate"><?= cm_fmt_pct($rate) ?></td>
                    <td class="cm-vol" data-field="vol"><?= $quote === 'KRW' ? cm_fmt_volume($r['acc_trade_price_24h'] ?? 0) : number_format($r['acc_trade_price_24h'] ?? 0, 4) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <p class="cm-disclaimer">* 본 시세는 <a href="https://upbit.com" target="_blank" rel="noopener">업비트</a> 공개 API 데이터를 사용하며, 실제 거래 가격과 차이가 있을 수 있습니다. 투자 책임은 본인에게 있습니다.</p>
</div>

<script>
(function () {
    // 30초 간격 가격만 살짝 갱신 (페이지 리로드 없이)
    const table = document.getElementById('cmTable');
    if (!table) return;
    const quote = table.dataset.quote || 'KRW';
    const rows  = Array.from(table.querySelectorAll('tr.cm-row'));
    if (!rows.length) return;
    const markets = rows.map(r => r.dataset.market);

    function fmt(n) {
        n = Number(n);
        if (n >= 1000) return n.toLocaleString('ko-KR', {maximumFractionDigits:0});
        if (n >= 1)    return n.toLocaleString('ko-KR', {maximumFractionDigits:2});
        return n.toLocaleString('ko-KR', {maximumFractionDigits:8});
    }
    function fmtVol(n) {
        n = Number(n);
        if (n >= 1e12) return (n/1e12).toFixed(2) + '조';
        if (n >= 1e8)  return (n/1e8).toFixed(2)  + '억';
        if (n >= 1e4)  return (n/1e4).toFixed(2)  + '만';
        return n.toFixed(0);
    }
    function refresh() {
        // 200개 단위 청크
        const chunks = [];
        for (let i=0;i<markets.length;i+=100) chunks.push(markets.slice(i,i+100));
        Promise.all(chunks.map(c =>
            fetch('<?= nb_url("coin/api/tickers") ?>?markets=' + encodeURIComponent(c.join(',')), {cache:'no-store'})
                .then(r => r.json()).then(j => j.tickers || [])
        )).then(results => {
            const all = [].concat.apply([], results);
            all.forEach(t => {
                const tr = table.querySelector('tr[data-market="' + t.market + '"]');
                if (!tr) return;
                const rate = (t.signed_change_rate || 0) * 100;
                const cls  = rate > 0 ? 'up' : (rate < 0 ? 'down' : 'flat');
                const $price = tr.querySelector('[data-field=price]');
                const $rate  = tr.querySelector('[data-field=rate]');
                const $vol   = tr.querySelector('[data-field=vol]');
                $price.textContent = fmt(t.trade_price);
                $price.className = 'cm-price ' + cls;
                $rate.textContent = (rate>=0?'+':'') + rate.toFixed(2) + '%';
                $rate.className = 'cm-rate ' + cls;
                if ($vol) $vol.textContent = quote === 'KRW' ? fmtVol(t.acc_trade_price_24h) : Number(t.acc_trade_price_24h).toFixed(4);
                tr.classList.remove('flash-up','flash-down');
                void tr.offsetWidth;
                tr.classList.add(rate >= 0 ? 'flash-up' : 'flash-down');
            });
        }).catch(()=>{});
    }
    setInterval(refresh, 30000);
})();
</script>

<?php require NB_ROOT . '/theme/default/footer.php'; ?>

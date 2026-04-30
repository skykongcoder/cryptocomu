<?php
/**
 * 포트폴리오 트래커 — localStorage 기반
 * 회원 가입 안 해도 사용 가능, 외부 API 호출 없이 우리 /coin/api/tickers 활용
 */
require __DIR__ . '/../../../theme/default/header.php';

// 사용자 선택용 KRW 마켓 리스트 (정렬: 시총 추정 = 거래대금)
$markets = function_exists('cm_markets') ? cm_markets('KRW') : [];
$tickers = function_exists('cm_tickers') ? cm_tickers(array_map(fn($m) => $m['market'], $markets)) : [];
$tMap = [];
foreach ($tickers as $t) $tMap[$t['market']] = $t;
// 거래대금 정렬
usort($markets, fn($a, $b) => ($tMap[$b['market']]['acc_trade_price_24h'] ?? 0) <=> ($tMap[$a['market']]['acc_trade_price_24h'] ?? 0));
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1>📊 포트폴리오 트래커</h1>
            <div class="cx-sub">보유 코인의 실시간 손익 자동 계산 · 데이터는 브라우저(localStorage)에만 저장됩니다</div>
        </div>
    </div>

    <!-- 요약 통계 -->
    <div class="cx-pf-summary">
        <div class="cx-card">
            <div class="cx-pf-stat-l">총 매수금액</div>
            <div class="cx-pf-stat-v" id="pfTotalCost">0 원</div>
        </div>
        <div class="cx-card">
            <div class="cx-pf-stat-l">총 평가금액</div>
            <div class="cx-pf-stat-v" id="pfTotalValue">0 원</div>
        </div>
        <div class="cx-card">
            <div class="cx-pf-stat-l">평가 손익</div>
            <div class="cx-pf-stat-v" id="pfTotalPnl">0 원</div>
        </div>
        <div class="cx-card">
            <div class="cx-pf-stat-l">수익률</div>
            <div class="cx-pf-stat-v" id="pfTotalRate">0.00%</div>
        </div>
    </div>

    <!-- 추가 폼 -->
    <div class="cx-card">
        <h3 class="cx-card-title">+ 보유 코인 추가</h3>
        <form class="cx-pf-form" onsubmit="return pfAdd(event)">
            <div>
                <label style="display:block;font-size:11px;color:var(--text-light);margin-bottom:4px">코인 (KRW 마켓)</label>
                <select id="pfMarket" required style="width:100%">
                    <?php foreach ($markets as $m):
                        $code = $m['market']; $name = $m['korean_name'] ?? '';
                        $sym = explode('-', $code)[1];
                    ?>
                    <option value="<?= htmlspecialchars($code) ?>" data-name="<?= htmlspecialchars($name) ?>">
                        <?= htmlspecialchars($name) ?> (<?= $sym ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;color:var(--text-light);margin-bottom:4px">수량</label>
                <input type="number" id="pfQty" step="any" min="0" required placeholder="예: 0.5" style="width:100%">
            </div>
            <div>
                <label style="display:block;font-size:11px;color:var(--text-light);margin-bottom:4px">평균 매수가 (원)</label>
                <input type="number" id="pfBuy" step="any" min="0" required placeholder="예: 100000000" style="width:100%">
            </div>
            <button type="submit">추가</button>
        </form>
    </div>

    <!-- 보유 목록 -->
    <div class="cx-card" style="padding:0;overflow:hidden">
        <div id="pfTableWrap">
            <div class="cx-pf-empty">아직 등록된 코인이 없습니다. 위 폼에서 추가하세요.</div>
        </div>
    </div>

    <div style="margin-top:14px;padding:12px 16px;background:rgba(0,0,0,0.3);border-radius:10px;font-size:12px;color:var(--text-light)">
        💾 모든 데이터는 이 브라우저에만 저장됩니다 (서버로 전송 안 됨). 다른 기기/브라우저에서는 보이지 않습니다.
        <button onclick="if(confirm('정말 모두 삭제할까요?')){localStorage.removeItem('cx_portfolio');pfRender();}"
                style="margin-left:14px;background:rgba(220,38,38,0.2);border:1px solid rgba(220,38,38,0.4);color:#fca5a5;padding:4px 12px;border-radius:6px;cursor:pointer;font-size:11px">
            전체 삭제
        </button>
    </div>
</div>

<script>
(function () {
    'use strict';
    var STORAGE_KEY = 'cx_portfolio';
    var TICKER_URL = '<?= nb_url("coin/api/tickers") ?>';
    var COIN_URL = '<?= nb_url("coin") ?>';

    function load() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
    }
    function save(items) { localStorage.setItem(STORAGE_KEY, JSON.stringify(items)); }
    function fmt(n) {
        n = Number(n);
        if (Math.abs(n) >= 1000) return n.toLocaleString('ko-KR', { maximumFractionDigits: 0 });
        if (Math.abs(n) >= 1)    return n.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
        return n.toLocaleString('ko-KR', { maximumFractionDigits: 6 });
    }

    window.pfAdd = function (ev) {
        ev.preventDefault();
        var sel = document.getElementById('pfMarket');
        var market = sel.value;
        var name = sel.options[sel.selectedIndex].dataset.name || '';
        var qty = parseFloat(document.getElementById('pfQty').value);
        var buy = parseFloat(document.getElementById('pfBuy').value);
        if (!market || !qty || !buy) return false;
        var items = load();
        // 같은 코인 추가 시 평단 자동 계산
        var idx = items.findIndex(function (i) { return i.market === market; });
        if (idx >= 0) {
            var oldQty = items[idx].qty, oldBuy = items[idx].buy;
            var newQty = oldQty + qty;
            var newBuy = (oldQty * oldBuy + qty * buy) / newQty;
            items[idx] = { market: market, name: name, qty: newQty, buy: newBuy };
        } else {
            items.push({ market: market, name: name, qty: qty, buy: buy });
        }
        save(items);
        document.getElementById('pfQty').value = '';
        document.getElementById('pfBuy').value = '';
        pfRender();
        return false;
    };

    window.pfRemove = function (market) {
        var items = load().filter(function (i) { return i.market !== market; });
        save(items); pfRender();
    };

    function pfRender() {
        var items = load();
        var wrap = document.getElementById('pfTableWrap');

        if (!items.length) {
            wrap.innerHTML = '<div class="cx-pf-empty">아직 등록된 코인이 없습니다. 위 폼에서 추가하세요.</div>';
            updateSummary({ cost: 0, value: 0, pnl: 0, rate: 0 });
            return;
        }

        var markets = items.map(function (i) { return i.market; }).join(',');
        fetch(TICKER_URL + '?markets=' + encodeURIComponent(markets), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var priceMap = {};
                (j.tickers || []).forEach(function (t) { priceMap[t.market] = t; });
                renderTable(items, priceMap);
            }).catch(function () {
                renderTable(items, {});
            });
    }
    window.pfRender = pfRender;

    function renderTable(items, priceMap) {
        var totalCost = 0, totalValue = 0;
        var rowsHtml = items.map(function (item) {
            var t = priceMap[item.market] || {};
            var current = t.trade_price || 0;
            var rate24h = (t.signed_change_rate || 0) * 100;
            var cost = item.qty * item.buy;
            var value = item.qty * current;
            var pnl = value - cost;
            var pct = cost > 0 ? (pnl / cost * 100) : 0;
            totalCost += cost; totalValue += value;
            var cls = pnl >= 0 ? 'up' : 'down';
            var sym = (item.market || '').split('-')[1] || '';
            var rate24Cls = rate24h >= 0 ? 'up' : 'down';
            return '<tr>' +
                '<td class="cx-pf-name"><a href="' + COIN_URL + '/' + item.market + '" style="color:var(--text)">' +
                    (item.name || sym) + ' <small style="font-family:var(--font-mono);color:var(--text-light)">' + sym + '</small></a></td>' +
                '<td>' + fmt(item.qty) + '</td>' +
                '<td>' + fmt(item.buy) + ' 원</td>' +
                '<td>' + fmt(current) + ' 원<br><small class="' + rate24Cls + '" style="font-size:10px">' + (rate24h >= 0 ? '+' : '') + rate24h.toFixed(2) + '%</small></td>' +
                '<td>' + fmt(value) + ' 원</td>' +
                '<td class="' + cls + '">' + (pnl >= 0 ? '+' : '') + fmt(pnl) + ' 원<br><small style="font-size:11px">' + (pct >= 0 ? '+' : '') + pct.toFixed(2) + '%</small></td>' +
                '<td><button class="cx-pf-del" onclick="pfRemove(\'' + item.market + '\')">삭제</button></td>' +
                '</tr>';
        }).join('');

        document.getElementById('pfTableWrap').innerHTML =
            '<table class="cx-pf-table">' +
            '<thead><tr><th>코인</th><th>수량</th><th>평단</th><th>현재가</th><th>평가금</th><th>손익</th><th></th></tr></thead>' +
            '<tbody>' + rowsHtml + '</tbody></table>';

        var totalPnl = totalValue - totalCost;
        var totalRate = totalCost > 0 ? (totalPnl / totalCost * 100) : 0;
        updateSummary({ cost: totalCost, value: totalValue, pnl: totalPnl, rate: totalRate });
    }

    function updateSummary(s) {
        document.getElementById('pfTotalCost').textContent = fmt(s.cost) + ' 원';
        document.getElementById('pfTotalValue').textContent = fmt(s.value) + ' 원';
        var pnlEl = document.getElementById('pfTotalPnl');
        var rateEl = document.getElementById('pfTotalRate');
        pnlEl.textContent = (s.pnl >= 0 ? '+' : '') + fmt(s.pnl) + ' 원';
        rateEl.textContent = (s.rate >= 0 ? '+' : '') + s.rate.toFixed(2) + '%';
        var cls = s.pnl >= 0 ? 'up' : (s.pnl < 0 ? 'down' : '');
        pnlEl.className = 'cx-pf-stat-v ' + cls;
        rateEl.className = 'cx-pf-stat-v ' + cls;
    }

    pfRender();
    setInterval(pfRender, 30000);  // 30초마다 가격 갱신
})();
</script>

<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

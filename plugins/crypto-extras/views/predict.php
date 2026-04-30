<?php
/**
 * 재미로 보는 코인 운세 — 엔터테인먼트 ONLY
 * 단순 선형회귀 + 결정적 노이즈로 "오늘의 코인 운세" 제공
 * ⚠️ 절대 투자 조언 아님. 모든 페이지에 명시적 disclaimer 표시.
 */
require __DIR__ . '/../../../theme/default/header.php';

$markets = function_exists('cm_markets') ? cm_markets('KRW') : [];
$tickers = function_exists('cm_tickers') ? cm_tickers(array_map(fn($m) => $m['market'], $markets)) : [];
$tMap = [];
foreach ($tickers as $t) $tMap[$t['market']] = $t;
usort($markets, fn($a, $b) => ($tMap[$b['market']]['acc_trade_price_24h'] ?? 0) <=> ($tMap[$a['market']]['acc_trade_price_24h'] ?? 0));
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1>🔮 오늘의 코인 운세</h1>
            <div class="cx-sub">재미로 보는 가격 예측 — 별의 기운으로 점쳐보는 코인의 미래</div>
        </div>
    </div>

    <!-- 큰 경고 박스 -->
    <div class="cx-predict-warning">
        <span class="icon">⚠️</span>
        <div>
            <b>이 페이지는 100% 재미·엔터테인먼트용입니다.</b><br>
            <span style="font-size:13px;color:var(--text-light)">
                실제 가격 예측은 누구도 할 수 없습니다. 표시되는 결과는 단순한 통계 추세 + 무작위 노이즈로 만든 <b>운세풍 시뮬레이션</b>이며,
                <b>절대 투자 의사결정에 사용하지 마세요</b>. 투자는 본인 책임이며, 이 페이지의 결과로 발생한 손실에 대해 사이트는 책임지지 않습니다.
            </span>
        </div>
    </div>

    <div class="cx-card">
        <h3 class="cx-card-title">🌟 코인을 선택하면 별이 답해드립니다</h3>
        <div class="cx-predict-controls">
            <select id="cpMarket">
                <?php foreach (array_slice($markets, 0, 30) as $m):
                    $code = $m['market']; $sym = explode('-', $code)[1];
                    $name = $m['korean_name'] ?? '';
                ?>
                <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($name) ?> (<?= $sym ?>)</option>
                <?php endforeach; ?>
            </select>
            <select id="cpDays">
                <option value="7">7일 후</option>
                <option value="14">14일 후</option>
                <option value="30" selected>30일 후</option>
                <option value="60">60일 후</option>
                <option value="90">90일 후</option>
            </select>
            <button id="cpRun">🔮 운세 보기</button>
        </div>
    </div>

    <div id="cpResult" style="display:none">
        <div class="cx-predict-result">
            <div class="cx-predict-message" id="cpMessage">...</div>
            <div class="cx-predict-numbers">
                <div class="cx-predict-num">
                    <div class="cx-predict-num-l">현재가</div>
                    <div class="cx-predict-num-v" id="cpCurrent">-</div>
                </div>
                <div class="cx-predict-num">
                    <div class="cx-predict-num-l">N일 후 (재미)</div>
                    <div class="cx-predict-num-v" id="cpFinal">-</div>
                </div>
                <div class="cx-predict-num">
                    <div class="cx-predict-num-l">변화 (재미)</div>
                    <div class="cx-predict-num-v" id="cpRate">-</div>
                </div>
            </div>
            <div style="font-size:11px;color:var(--text-dim);margin-top:14px">
                ※ 이 결과는 매일 같은 시드로 생성되어 하루 동안 같은 값을 보여줍니다 (운세 컨셉)
            </div>
        </div>

        <div class="cx-card">
            <h3 class="cx-card-title">📈 차트로 보는 별의 기운</h3>
            <canvas id="cpChart" class="cx-predict-chart"></canvas>
            <div style="font-size:11px;color:var(--text-light);margin-top:8px">
                <span style="color:#fb7185">━ 실제 60일 가격</span> &nbsp;&nbsp;
                <span style="color:var(--accent)">┄ 운세 예측 (재미)</span>
            </div>
        </div>
    </div>

    <div style="margin-top:30px;padding:18px;background:linear-gradient(135deg, rgba(255,184,0,0.08), rgba(255,45,146,0.08));border:1px solid rgba(255,184,0,0.3);border-radius:14px;text-align:center;font-size:13px;color:var(--text-light);line-height:1.7">
        💫 코인 가격을 정확히 예측하는 알고리즘은 존재하지 않습니다.<br>
        이 페이지는 단순한 추세 시각화 + 운세풍 메시지로 즐거움을 드리기 위한 콘텐츠입니다.<br>
        진짜 시세 분석은 <a href="<?= nb_url('coin') ?>" style="color:var(--primary)">시세 페이지</a>에서, 정보 공유는 게시판에서 활용해주세요.
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('cpRun');
    var resultBox = document.getElementById('cpResult');
    var marketSel = document.getElementById('cpMarket');
    var daysSel = document.getElementById('cpDays');

    function fmt(n) {
        n = Number(n);
        if (Math.abs(n) >= 1000) return n.toLocaleString('ko-KR', { maximumFractionDigits: 0 });
        if (Math.abs(n) >= 1)    return n.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
        return n.toLocaleString('ko-KR', { maximumFractionDigits: 6 });
    }

    btn.addEventListener('click', function () {
        var market = marketSel.value;
        var days = daysSel.value;
        btn.disabled = true; btn.textContent = '⏳ 별이 답하는 중...';

        fetch('<?= nb_url("api/predict") ?>?market=' + encodeURIComponent(market) + '&days=' + days)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                btn.disabled = false; btn.textContent = '🔮 운세 보기';
                if (!d.ok) { alert('데이터 부족 - 다른 코인을 선택해보세요'); return; }
                renderResult(d);
            });
    });

    function renderResult(d) {
        resultBox.style.display = '';
        document.getElementById('cpMessage').textContent = d.message;
        document.getElementById('cpCurrent').textContent = fmt(d.current) + ' 원';
        document.getElementById('cpFinal').textContent = fmt(d.final) + ' 원';
        var rate = d.change_pct;
        var rateEl = document.getElementById('cpRate');
        rateEl.textContent = (rate >= 0 ? '+' : '') + rate.toFixed(2) + '%';
        rateEl.className = 'cx-predict-num-v ' + (rate >= 0 ? 'up' : 'down');

        drawChart(d);
        resultBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function drawChart(d) {
        var canvas = document.getElementById('cpChart');
        var dpr = window.devicePixelRatio || 1;
        var W = canvas.offsetWidth, H = canvas.offsetHeight;
        canvas.width = W * dpr; canvas.height = H * dpr;
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr); ctx.clearRect(0, 0, W, H);

        var hist = (d.history || []).map(function (h) { return h.price; });
        var fut  = (d.future || []).map(function (f) { return f.price; });
        var all = hist.concat(fut);
        var min = Math.min.apply(null, all), max = Math.max.apply(null, all);
        var pad = (max - min) * 0.1 || 1;
        var lo = min - pad, hi = max + pad;

        var totalPoints = hist.length + fut.length;
        var x = function (i) { return 6 + (W - 12) * i / (totalPoints - 1); };
        var y = function (p) { return H - 16 - (H - 32) * (p - lo) / (hi - lo); };

        // grid
        ctx.strokeStyle = 'rgba(0,255,212,0.06)'; ctx.lineWidth = 1;
        for (var i = 0; i < 5; i++) {
            var py = 16 + (H - 32) * i / 4;
            ctx.beginPath(); ctx.moveTo(0, py); ctx.lineTo(W, py); ctx.stroke();
        }

        // 실제 가격 (빨강)
        ctx.beginPath();
        hist.forEach(function (p, i) { i === 0 ? ctx.moveTo(x(i), y(p)) : ctx.lineTo(x(i), y(p)); });
        ctx.strokeStyle = '#fb7185'; ctx.lineWidth = 2.5; ctx.stroke();

        // 구분선
        ctx.beginPath();
        var sx = x(hist.length - 1);
        ctx.moveTo(sx, 16); ctx.lineTo(sx, H - 16);
        ctx.strokeStyle = 'rgba(255,184,0,0.4)'; ctx.lineWidth = 1; ctx.setLineDash([4, 4]); ctx.stroke();
        ctx.setLineDash([]);

        // 예측 (대시 골드)
        ctx.beginPath();
        fut.forEach(function (p, i) {
            var idx = hist.length + i;
            i === 0 ? ctx.moveTo(x(idx), y(p)) : ctx.lineTo(x(idx), y(p));
        });
        ctx.strokeStyle = '#ffb800'; ctx.lineWidth = 2; ctx.setLineDash([6, 4]); ctx.stroke();
        ctx.setLineDash([]);
    }
})();
</script>

<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

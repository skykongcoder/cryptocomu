<?php
/**
 * 🐋 고래 신호 탐지기
 * 업비트 거래량+가격 데이터로 시장의 큰 움직임을 자동 감지
 */
require __DIR__ . '/../../../theme/default/header.php';

$signals = cx_detect_whales();

// 타입별 그룹화 카운트 (필터 버튼용)
$typeCount = ['ALL' => count($signals), 'ATH' => 0, 'PUMP' => 0, 'DUMP' => 0, 'BUY' => 0, 'SELL' => 0, 'ACCUM' => 0];
foreach ($signals as $s) $typeCount[$s['type']] = ($typeCount[$s['type']] ?? 0) + 1;

$typeLabels = [
    'ALL'   => ['전체',     '🔍'],
    'ATH'   => ['신고가',   '🚀'],
    'PUMP'  => ['폭등',     '🔥'],
    'DUMP'  => ['폭락',     '🔻'],
    'BUY'   => ['강한매수', '💰'],
    'SELL'  => ['강한매도', '💸'],
    'ACCUM' => ['거래량',   '👀'],
];
?>
<div class="container cx-page">
    <div class="cx-page-head">
        <div>
            <h1>🐋 고래 신호 탐지기</h1>
            <div class="cx-sub">거래량 급증 · 가격 급변동 · 신고가 돌파 — 시장의 큰 손 움직임을 자동 감지 · 5분마다 갱신</div>
        </div>
        <div style="font-size:11px;color:var(--text-light);text-align:right">
            <span style="display:inline-flex;align-items:center;gap:6px;color:var(--primary);font-weight:600">
                <span class="cx-live-dot"></span> LIVE
            </span><br>
            현재 감지: <strong id="cxWhaleCount" style="color:var(--primary);font-size:14px"><?= count($signals) ?></strong>개 ·
            <span id="cxWhaleUpdatedAt">방금</span> 갱신
        </div>
    </div>

    <!-- 안내 -->
    <div style="padding:14px 18px;background:linear-gradient(135deg, rgba(0,255,212,0.06), rgba(255,184,0,0.06));border:1px solid var(--border-strong);border-radius:12px;margin-bottom:20px;font-size:13px;color:var(--text-light);line-height:1.6">
        💡 <strong style="color:var(--primary)">탐지 기준</strong>: 7일 평균 대비 거래량 3배 이상 급증 + 가격 5% 이상 변동.
        실제 온체인 고래 지갑 추적이 아닌 <strong>거래소 단위 신호</strong>이며,
        매매 결정을 위한 정보가 아닌 <strong>시장 동향 참고용</strong>입니다.
    </div>

    <!-- 필터 -->
    <div class="cx-glossary-cats" style="margin-bottom:20px">
        <?php foreach ($typeLabels as $key => [$label, $icon]):
            $count = $typeCount[$key] ?? 0;
            $disabled = $count === 0 && $key !== 'ALL';
        ?>
        <button class="cx-glossary-cat <?= $key === 'ALL' ? 'active' : '' ?>"
                data-filter="<?= $key ?>"
                <?= $disabled ? 'style="opacity:0.4"' : '' ?>>
            <?= $icon ?> <?= htmlspecialchars($label) ?> <?= $count ? '<small style="opacity:0.7">'.$count.'</small>' : '' ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- 신호 목록 (빈 상태 / 데이터 영역) -->
    <div id="cxWhaleEmpty" class="cx-card" style="text-align:center;padding:60px 20px;<?= empty($signals) ? '' : 'display:none' ?>">
        <div style="font-size:64px;margin-bottom:14px">🌊</div>
        <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px">현재 감지된 고래 신호가 없습니다</div>
        <div style="color:var(--text-light);font-size:13px">바다가 잔잔하네요. 60초마다 자동으로 다시 분석합니다.</div>
    </div>
    <div id="cxWhaleList" class="cx-whale-list" style="<?= empty($signals) ? 'display:none' : '' ?>">
            <?php foreach ($signals as $s):
                $cls = strtolower($s['type']);
                $rateCls = $s['change_rate'] >= 0 ? 'up' : 'down';
                $sign = $s['change_rate'] >= 0 ? '+' : '';
                $vol_b = $s['volume_24h'] >= 1e8 ? number_format($s['volume_24h']/1e8, 1) . '억' : number_format($s['volume_24h']);
            ?>
            <a href="<?= nb_url('coin/' . $s['market']) ?>"
               class="cx-whale-card cx-whale-<?= $cls ?>"
               data-type="<?= $s['type'] ?>">
                <!-- 심각도 게이지 -->
                <div class="cx-whale-severity">
                    <div class="cx-whale-severity-bar" style="width:<?= $s['severity'] ?>%"></div>
                </div>

                <!-- 메인 정보 -->
                <div class="cx-whale-main">
                    <div class="cx-whale-coin">
                        <div class="cx-whale-symbol"><?= htmlspecialchars($s['symbol']) ?></div>
                        <div class="cx-whale-name"><?= htmlspecialchars($s['name'] ?: $s['symbol']) ?></div>
                    </div>
                    <div class="cx-whale-desc">
                        <?= htmlspecialchars($s['desc']) ?>
                    </div>
                </div>

                <!-- 수치 -->
                <div class="cx-whale-stats">
                    <div class="cx-whale-stat">
                        <div class="cx-whale-stat-l">현재가</div>
                        <div class="cx-whale-stat-v"><?= cm_fmt_price($s['price']) ?>원</div>
                    </div>
                    <div class="cx-whale-stat">
                        <div class="cx-whale-stat-l">24h 변동</div>
                        <div class="cx-whale-stat-v <?= $rateCls ?>"><?= $sign . number_format($s['change_rate'], 2) ?>%</div>
                    </div>
                    <div class="cx-whale-stat">
                        <div class="cx-whale-stat-l">거래량</div>
                        <div class="cx-whale-stat-v" style="color:var(--accent)"><?= htmlspecialchars((string)$s['vol_multiple']) ?>x</div>
                    </div>
                    <div class="cx-whale-stat">
                        <div class="cx-whale-stat-l">거래대금 24h</div>
                        <div class="cx-whale-stat-v" style="font-size:13px"><?= $vol_b ?>원</div>
                    </div>
                </div>

                <div class="cx-whale-score">
                    <span style="font-size:11px;color:var(--text-light)">신호 강도</span>
                    <strong style="color:<?= $s['severity'] >= 80 ? '#fb7185' : ($s['severity'] >= 60 ? 'var(--accent)' : 'var(--primary)') ?>"><?= $s['severity'] ?></strong>
                </div>
            </a>
            <?php endforeach; ?>
    </div>

    <div style="margin-top:30px;padding:14px 18px;background:rgba(0,0,0,0.3);border-radius:10px;font-size:12px;color:var(--text-light);text-align:center">
        🔄 60초마다 자동 라이브 갱신 · 데이터 출처: <a href="https://upbit.com" target="_blank" style="color:var(--primary)">업비트 공개 API</a>
    </div>
</div>

<script>
(function () {
    'use strict';
    var buttons = document.querySelectorAll('[data-filter]');
    var listEl = document.getElementById('cxWhaleList');
    var emptyEl = document.getElementById('cxWhaleEmpty');
    var countEl = document.getElementById('cxWhaleCount');
    var updatedEl = document.getElementById('cxWhaleUpdatedAt');
    var coinUrl = '<?= nb_url("coin") ?>';
    var apiUrl = '<?= nb_url("api/whales") ?>';
    var currentFilter = 'ALL';
    var prevSignatures = new Set();   // 새 신호 깜빡임 효과용

    function fmt(n) {
        n = Number(n);
        if (Math.abs(n) >= 1000) return n.toLocaleString('ko-KR', { maximumFractionDigits: 0 });
        if (Math.abs(n) >= 1)    return n.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
        return n.toLocaleString('ko-KR', { maximumFractionDigits: 6 });
    }
    function fmtVol(v) {
        return v >= 1e8 ? (v / 1e8).toLocaleString('ko-KR', { maximumFractionDigits: 1 }) + '억' : fmt(v);
    }
    function severityColor(s) {
        return s >= 80 ? '#fb7185' : (s >= 60 ? '#ffb800' : '#00ffd4');
    }
    function buildCard(s, isNew) {
        var rateCls = s.change_rate >= 0 ? 'up' : 'down';
        var sign = s.change_rate >= 0 ? '+' : '';
        return '<a href="' + coinUrl + '/' + s.market + '"' +
            ' class="cx-whale-card cx-whale-' + s.type.toLowerCase() + (isNew ? ' cx-whale-new' : '') + '"' +
            ' data-type="' + s.type + '">' +
            '<div class="cx-whale-severity"><div class="cx-whale-severity-bar" style="width:' + s.severity + '%"></div></div>' +
            '<div class="cx-whale-main">' +
                '<div class="cx-whale-coin">' +
                    '<div class="cx-whale-symbol">' + s.symbol + '</div>' +
                    '<div class="cx-whale-name">' + (s.name || s.symbol) + '</div>' +
                '</div>' +
                '<div class="cx-whale-desc">' + s.desc + '</div>' +
            '</div>' +
            '<div class="cx-whale-stats">' +
                '<div class="cx-whale-stat"><div class="cx-whale-stat-l">현재가</div><div class="cx-whale-stat-v">' + fmt(s.price) + '원</div></div>' +
                '<div class="cx-whale-stat"><div class="cx-whale-stat-l">24h 변동</div><div class="cx-whale-stat-v ' + rateCls + '">' + sign + s.change_rate.toFixed(2) + '%</div></div>' +
                '<div class="cx-whale-stat"><div class="cx-whale-stat-l">거래량</div><div class="cx-whale-stat-v" style="color:var(--accent)">' + s.vol_multiple + 'x</div></div>' +
                '<div class="cx-whale-stat"><div class="cx-whale-stat-l">거래대금 24h</div><div class="cx-whale-stat-v" style="font-size:13px">' + fmtVol(s.volume_24h) + '원</div></div>' +
            '</div>' +
            '<div class="cx-whale-score"><span style="font-size:11px;color:var(--text-light)">신호 강도</span>' +
                '<strong style="color:' + severityColor(s.severity) + '">' + s.severity + '</strong></div>' +
            '</a>';
    }

    function applyFilter() {
        document.querySelectorAll('.cx-whale-card').forEach(function (c) {
            c.style.display = (currentFilter === 'ALL' || c.dataset.type === currentFilter) ? '' : 'none';
        });
    }

    function updateFilterCounts(byType) {
        buttons.forEach(function (btn) {
            var key = btn.dataset.filter;
            var icon = btn.textContent.trim().split(' ')[0];
            var label = ({ALL:'전체',ATH:'신고가',PUMP:'폭등',DUMP:'폭락',BUY:'강한매수',SELL:'강한매도',ACCUM:'거래량'})[key];
            var count = key === 'ALL' ? Object.values(byType).reduce(function(a,b){return a+b;},0) : (byType[key] || 0);
            btn.innerHTML = icon + ' ' + label + (count ? ' <small style="opacity:0.7">' + count + '</small>' : '');
            btn.style.opacity = (count === 0 && key !== 'ALL') ? '0.4' : '';
        });
    }

    function refresh() {
        fetch(apiUrl, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) return;
                var whales = j.whales || [];
                // 새 신호 식별: market+type 시그니처 비교
                var newSignatures = new Set(whales.map(function(s){return s.market+'|'+s.type;}));
                var html = whales.map(function (s) {
                    var sig = s.market + '|' + s.type;
                    return buildCard(s, !prevSignatures.has(sig));
                }).join('');

                if (whales.length === 0) {
                    listEl.style.display = 'none';
                    emptyEl.style.display = '';
                } else {
                    emptyEl.style.display = 'none';
                    listEl.style.display = '';
                    listEl.innerHTML = html;
                }
                countEl.textContent = whales.length;
                updatedEl.textContent = '방금';
                setTimeout(function(){ updatedEl.textContent = '1분 전'; }, 60000);

                // 필터 카운트 업데이트
                var byType = {};
                whales.forEach(function (s) { byType[s.type] = (byType[s.type] || 0) + 1; });
                updateFilterCounts(byType);

                applyFilter();
                prevSignatures = newSignatures;
            }).catch(function () {});
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            buttons.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            applyFilter();
        });
    });

    // 초기 카드들의 시그니처 등록
    document.querySelectorAll('.cx-whale-card').forEach(function (c) {
        var symbol = c.querySelector('.cx-whale-symbol')?.textContent;
        if (symbol) prevSignatures.add('KRW-' + symbol + '|' + c.dataset.type);
    });

    // 60초마다 라이브 갱신
    setInterval(refresh, 60 * 1000);
})();
</script>

<?php require __DIR__ . '/../../../theme/default/footer.php'; ?>

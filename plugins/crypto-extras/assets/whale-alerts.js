/**
 * 🐋 사이트 전역 고래 신호 알림
 * - 60초마다 /api/whales 폴링
 * - 새 신호 감지 시 화면 우측 상단 토스트 + (옵션) 데스크톱 알림
 * - 설정: localStorage 'cx_whale_alerts' = { enabled, desktop, minSeverity }
 */
(function () {
    'use strict';
    if (window.cxWhaleAlertsLoaded) return;
    window.cxWhaleAlertsLoaded = true;

    var API_URL = '/api/whales';
    var COIN_URL = '/coin';
    var POLL_INTERVAL = 60 * 1000;

    var STORAGE_KEY = 'cx_whale_alerts';
    var defaults = { enabled: true, desktop: false, minSeverity: 60 };

    function loadPrefs() {
        try {
            var raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            return Object.assign({}, defaults, raw);
        } catch (e) { return Object.assign({}, defaults); }
    }
    function savePrefs(p) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(p));
    }
    function loadSeen() {
        try { return new Set(JSON.parse(sessionStorage.getItem('cx_whale_seen') || '[]')); } catch (e) { return new Set(); }
    }
    function saveSeen(set) {
        sessionStorage.setItem('cx_whale_seen', JSON.stringify(Array.from(set)));
    }

    var prefs = loadPrefs();
    var seen = loadSeen();   // tab session 동안 본 신호들 (중복 알림 방지)

    // ===== 컨테이너 = =====
    function ensureContainer() {
        var c = document.getElementById('cxWhaleToastContainer');
        if (c) return c;
        c = document.createElement('div');
        c.id = 'cxWhaleToastContainer';
        c.style.cssText = 'position:fixed;top:80px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;max-width:360px;pointer-events:none';
        document.body.appendChild(c);
        return c;
    }

    // ===== 토스트 알림 =====
    function fmt(n) {
        n = Number(n);
        if (Math.abs(n) >= 1000) return n.toLocaleString('ko-KR', { maximumFractionDigits: 0 });
        if (Math.abs(n) >= 1)    return n.toLocaleString('ko-KR', { maximumFractionDigits: 2 });
        return n.toLocaleString('ko-KR', { maximumFractionDigits: 6 });
    }
    var typeIcons = { ATH:'🚀', PUMP:'🔥', DUMP:'🔻', BUY:'💰', SELL:'💸', ACCUM:'👀' };
    var typeColors = { ATH:'#fde047', PUMP:'#fb7185', DUMP:'#60a5fa', BUY:'#fb923c', SELL:'#818cf8', ACCUM:'#00ffd4' };

    function showToast(s) {
        var container = ensureContainer();
        var color = typeColors[s.type] || '#00ffd4';
        var sevColor = s.severity >= 80 ? '#fb7185' : (s.severity >= 60 ? '#ffb800' : '#00ffd4');
        var rateCls = s.change_rate >= 0 ? 'up' : 'down';
        var rateColor = s.change_rate >= 0 ? '#fb7185' : '#60a5fa';
        var sign = s.change_rate >= 0 ? '+' : '';

        var toast = document.createElement('a');
        toast.className = 'cx-whale-toast';
        toast.href = COIN_URL + '/' + s.market;
        toast.style.cssText = [
            'pointer-events:auto',
            'background:linear-gradient(180deg, #0d1320 0%, #080c16 100%)',
            'border:1px solid rgba(0,255,212,0.3)',
            'border-left:4px solid ' + color,
            'border-radius:12px',
            'padding:14px 16px',
            'display:grid',
            'grid-template-columns:auto 1fr auto',
            'gap:12px',
            'align-items:center',
            'color:#e6f3ff',
            'text-decoration:none',
            'box-shadow:0 10px 40px rgba(0,0,0,0.6), 0 0 30px rgba(0,255,212,0.08)',
            'font-family:Pretendard, sans-serif',
            'font-size:13px',
            'transform:translateX(420px)',
            'opacity:0',
            'transition:all .35s cubic-bezier(.2,.7,.3,1)',
            'cursor:pointer',
        ].join(';');
        toast.innerHTML =
            '<div style="font-size:28px;line-height:1;flex-shrink:0">🐋</div>' +
            '<div style="min-width:0">' +
                '<div style="font-weight:700;font-size:14px;margin-bottom:4px">' +
                    typeIcons[s.type] + ' ' + escapeHtml(s.name || s.symbol) +
                    ' <small style="color:' + color + ';font-weight:600">' + escapeHtml(s.symbol) + '</small>' +
                '</div>' +
                '<div style="color:#7fa3c5;font-size:12px;line-height:1.4;margin-bottom:6px">' + escapeHtml(s.desc) + '</div>' +
                '<div style="font-size:11px;font-family:JetBrains Mono, monospace">' +
                    '<span style="color:' + sevColor + ';font-weight:700">강도 ' + s.severity + '</span> · ' +
                    '<span style="color:' + rateColor + '">' + sign + s.change_rate.toFixed(2) + '%</span> · ' +
                    '<span style="color:#ffb800">' + s.vol_multiple + 'x</span>' +
                '</div>' +
            '</div>' +
            '<button class="cx-toast-close" style="background:transparent;border:0;color:#4a6585;font-size:20px;cursor:pointer;padding:0 4px;line-height:1;flex-shrink:0">&times;</button>';

        // X 버튼 클릭 시 닫기 (링크 이동 막음)
        toast.querySelector('.cx-toast-close').addEventListener('click', function (ev) {
            ev.preventDefault(); ev.stopPropagation();
            dismiss(toast);
        });

        container.appendChild(toast);
        // slide in
        requestAnimationFrame(function () {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });
        // 자동 제거
        setTimeout(function () { dismiss(toast); }, 10000);
    }
    function dismiss(toast) {
        toast.style.transform = 'translateX(420px)';
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 400);
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c];
        });
    }

    // ===== 데스크톱 알림 =====
    function notifyDesktop(s) {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        var icons = { ATH:'🚀', PUMP:'🔥', DUMP:'🔻', BUY:'💰', SELL:'💸', ACCUM:'👀' };
        try {
            var n = new Notification('🐋 ' + (s.name || s.symbol) + ' ' + icons[s.type] + ' (강도 ' + s.severity + ')', {
                body: s.desc,
                tag: s.market + '_' + s.type,        // 같은 코인+타입은 덮어씀
                requireInteraction: false,
                silent: false,
            });
            n.onclick = function () {
                window.focus();
                window.location.href = COIN_URL + '/' + s.market;
                n.close();
            };
            setTimeout(function () { n.close(); }, 8000);
        } catch (e) {}
    }

    // ===== 폴링 =====
    function poll() {
        if (!prefs.enabled) return;
        fetch(API_URL, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok || !Array.isArray(j.whales)) return;
                j.whales.forEach(function (s) {
                    if (s.severity < prefs.minSeverity) return;     // 임계값 미만 스킵
                    var key = s.market + '|' + s.type + '|' + s.severity;
                    if (seen.has(key)) return;
                    seen.add(key);
                    showToast(s);
                    if (prefs.desktop) notifyDesktop(s);
                });
                saveSeen(seen);
            }).catch(function () {});
    }

    // ===== 알림 설정 위젯 (footer 근처에 작은 floating 버튼) =====
    function injectControlWidget() {
        if (document.getElementById('cxWhaleAlertWidget')) return;
        var w = document.createElement('div');
        w.id = 'cxWhaleAlertWidget';
        w.style.cssText = [
            'position:fixed','bottom:90px','right:20px','z-index:9998',
            'background:linear-gradient(180deg, #0d1320, #080c16)',
            'border:1px solid rgba(0,255,212,0.3)','border-radius:24px',
            'padding:6px 10px 6px 12px','display:flex','align-items:center','gap:8px',
            'cursor:pointer','user-select:none','color:#e6f3ff','font-size:12px',
            'box-shadow:0 4px 16px rgba(0,0,0,0.5)','transition:all .15s',
            'font-family:Pretendard, sans-serif',
        ].join(';');
        w.title = '클릭하여 알림 설정';
        w.innerHTML = '<span id="cxAlertIcon" style="font-size:16px">🐋</span>' +
                      '<span id="cxAlertLabel" style="font-weight:600">고래 알림</span>' +
                      '<span id="cxAlertStatus" style="font-size:10px;color:#7fa3c5"></span>';
        document.body.appendChild(w);

        function render() {
            var statusEl = document.getElementById('cxAlertStatus');
            var iconEl = document.getElementById('cxAlertIcon');
            if (!prefs.enabled) {
                statusEl.textContent = 'OFF';
                statusEl.style.color = '#4a6585';
                iconEl.style.opacity = '0.4';
            } else if (prefs.desktop) {
                statusEl.textContent = '🔔 ON';
                statusEl.style.color = '#00ffd4';
                iconEl.style.opacity = '1';
            } else {
                statusEl.textContent = 'ON';
                statusEl.style.color = '#ffb800';
                iconEl.style.opacity = '1';
            }
        }
        render();

        w.addEventListener('click', function () {
            // 3-state cycle: enabled+desktop → enabled-only → off → enabled+desktop
            if (!prefs.enabled) {
                prefs.enabled = true; prefs.desktop = false;
            } else if (!prefs.desktop) {
                prefs.desktop = true;
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            } else {
                prefs.enabled = false; prefs.desktop = false;
            }
            savePrefs(prefs);
            render();
        });
    }

    // ===== 시작 =====
    function start() {
        if (document.body.classList.contains('cx-no-whale-alerts')) return;   // opt-out 클래스
        injectControlWidget();
        // 첫 폴링은 5초 후 (페이지 로드 완료 + 다른 fetch 끝난 뒤)
        setTimeout(poll, 5000);
        setInterval(poll, POLL_INTERVAL);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();

<?php
/**
 * NuriBoard - 출석체크 (도장 달력)
 */
SEO::setTitle('출석체크');
require dirname(__DIR__) . '/header.php';

$isLoggedIn  = Auth::check();
$today       = date('Y-m-d');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDow    = (int)date('w', mktime(0, 0, 0, $month, 1, $year));
$prevY = $month === 1 ? $year - 1 : $year;
$prevM = $month === 1 ? 12        : $month - 1;
$nextY = $month === 12 ? $year + 1 : $year;
$nextM = $month === 12 ? 1         : $month + 1;
$stampedCount = count($attendDates);
?>

<div class="board-wrap">
<div class="att-wrap">

  <div class="att-header">
    <h2 class="att-title">출석체크</h2>
    <div class="att-streak">
      이번 달 출석 <strong><?= $stampedCount ?></strong>일
    </div>
  </div>

  <div class="att-nav">
    <a class="att-btn" href="<?= nb_url("attendance?y={$prevY}&m={$prevM}") ?>">이전달</a>
    <div class="att-month"><?= sprintf('%04d.%02d', $year, $month) ?></div>
    <a class="att-btn" href="<?= nb_url("attendance?y={$nextY}&m={$nextM}") ?>">다음달</a>
  </div>

  <div class="att-grid att-grid--head">
    <div class="att-dow att-dow--sun">일</div>
    <div class="att-dow">월</div>
    <div class="att-dow">화</div>
    <div class="att-dow">수</div>
    <div class="att-dow">목</div>
    <div class="att-dow">금</div>
    <div class="att-dow att-dow--sat">토</div>
  </div>

  <div class="att-grid att-grid--body" id="att-grid"
       data-year="<?= $year ?>" data-month="<?= $month ?>" data-today="<?= $today ?>">
    <?php
    for ($i = 0; $i < $firstDow; $i++) {
        echo '<div class="att-cell att-cell--empty"></div>';
    }
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dow      = ($firstDow + $d - 1) % 7;
        $isToday  = ($dateStr === $today);
        $stamped  = in_array($dateStr, $attendDates);
        $isFuture = strtotime($dateStr) > strtotime($today);

        $classes = ['att-cell'];
        if ($isToday)  $classes[] = 'att-cell--today';
        if ($stamped)  $classes[] = 'att-cell--stamped';
        if ($isFuture) $classes[] = 'att-cell--future';
        if ($dow === 0) $classes[] = 'att-cell--sun';
        if ($dow === 6) $classes[] = 'att-cell--sat';
    ?>
        <div class="<?= implode(' ', $classes) ?>" data-date="<?= $dateStr ?>">
          <span class="att-num"><?= $d ?></span>
          <div class="att-stamp" aria-hidden="true">
            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <filter id="rough-<?= $d ?>">
                  <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="<?= $d ?>"/>
                  <feDisplacementMap in="SourceGraphic" scale="1.6"/>
                </filter>
              </defs>
              <g filter="url(#rough-<?= $d ?>)" fill="none" stroke="currentColor" stroke-width="3">
                <circle cx="50" cy="50" r="42"/>
                <circle cx="50" cy="50" r="38" stroke-width="1.2"/>
              </g>
              <g filter="url(#rough-<?= $d ?>)" fill="currentColor">
                <text x="50" y="58" text-anchor="middle"
                      font-family="'Nanum Myeongjo','Noto Serif KR',serif"
                      font-weight="900" font-size="20" letter-spacing="1">출석체크</text>
                <text x="50" y="30" text-anchor="middle" font-size="7" letter-spacing="2">★ ★ ★</text>
                <text x="50" y="80" text-anchor="middle" font-size="7" letter-spacing="2">★ ★ ★</text>
              </g>
            </svg>
          </div>
        </div>
    <?php } ?>
  </div>

  <!-- 출석 버튼 -->
  <div class="att-action-row">
    <?php if ($isLoggedIn && $todayDone): ?>
      <input class="att-msg-input" type="text" value="오늘 출석 완료! 내일 또 만나요 :)" disabled>
      <button class="att-stamp-btn att-stamp-btn--done" disabled>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        출석 완료
      </button>
    <?php else: ?>
      <input class="att-msg-input" type="text" id="att-msg-input" placeholder="출석!" maxlength="50">
      <button class="att-stamp-btn" id="att-main-btn">
        출석체크 도장찍기
        <svg width="28" height="28" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="opacity:.85;flex-shrink:0">
          <circle cx="50" cy="50" r="44" fill="none" stroke="currentColor" stroke-width="5"/>
          <circle cx="50" cy="50" r="38" fill="none" stroke="currentColor" stroke-width="2"/>
          <text x="50" y="56" text-anchor="middle" fill="currentColor" font-size="22" font-weight="900" font-family="serif">도장</text>
        </svg>
      </button>
    <?php endif; ?>
  </div>

  <!-- 오늘 출석한 멤버 -->
  <div class="att-list-wrap">
    <div class="att-list-header">
      오늘 출석한 멤버
      <span class="att-list-count" id="att-today-count"><?= count($todayList) ?>명</span>
    </div>
    <?php if (empty($todayList)): ?>
    <div class="att-list-empty" id="att-list-empty">아직 출석한 멤버가 없습니다. 첫 번째로 출석해보세요!</div>
    <?php endif; ?>
    <div class="att-tbl-wrap">
      <table class="att-tbl">
        <thead>
          <tr>
            <th class="att-th-rank">등수</th>
            <th class="att-th-time">출석시간</th>
            <th class="att-th-nick">닉네임</th>
            <th class="att-th-msg">출석인사</th>
            <th class="att-th-pt">적립포인트</th>
            <th class="att-th-days">개근일</th>
          </tr>
        </thead>
        <tbody id="att-tbl-body">
          <?php foreach ($todayList as $i => $a):
            $ts   = strtotime($a['created_at']);
            $ampm = (int)date('H', $ts) < 12 ? '오전' : '오후';
            $time = $ampm . ' ' . date('h:i:s', $ts);
          ?>
          <tr>
            <td class="att-td-rank"><?= $i + 1 ?></td>
            <td class="att-td-time"><?= $time ?></td>
            <td class="att-td-nick"><?= nb_level_icon($a['level'] ?? 2) ?> <?= nb_e($a['nickname'] ?? '') ?></td>
            <td class="att-td-msg"><?= nb_e($a['message'] ?? '') ?></td>
            <td class="att-td-pt"><?= $attendPoint > 0 ? number_format($attendPoint) . ' P' : '-' ?></td>
            <td class="att-td-days"><?= (int)($a['total_days'] ?? 1) ?>일째</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&family=Nanum+Myeongjo:wght@800&display=swap');

.att-wrap{--line:#e5e7eb;--line-strong:#cbd1d8;--ink:#1f2937;--muted:#9ca3af;--sun:#d33b3b;--sat:#2c5fb3;--stamp:#c9302c;--today-bg:#f0fdf4;--today-line:#22c55e;max-width:720px;margin:24px auto;padding:12px 16px 24px;font-family:'Noto Sans KR',system-ui,'Apple SD Gothic Neo','Malgun Gothic',sans-serif;color:var(--ink);font-size:14px;line-height:1.4;-webkit-font-smoothing:antialiased}

.att-header{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:14px}
.att-title{font-size:16px;font-weight:700;color:#4b5563;margin:0;letter-spacing:-0.2px}
.att-streak{font-size:13px;color:#6b7280}
.att-streak strong{color:var(--stamp);font-weight:700;margin:0 2px}

.att-nav{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:12px;margin-bottom:10px}
.att-btn{display:inline-block;padding:7px 14px;border:1px solid var(--line-strong);border-radius:6px;background:#fff;color:#374151;font-size:13px;text-decoration:none;cursor:pointer;transition:background .12s,border-color .12s}
.att-btn:hover{background:#f9fafb;border-color:#9aa3af;text-decoration:none}
.att-month{text-align:center;font-size:18px;font-weight:700;letter-spacing:.5px;color:#111827}

.att-grid{display:grid;grid-template-columns:repeat(7,1fr)}
.att-grid--head{border:1px solid var(--line);border-bottom:0;background:#fafbfc}
.att-grid--body{border:1px solid var(--line)}

.att-dow{text-align:center;padding:10px 0;font-weight:700;font-size:13px;color:#4b5563;border-right:1px solid var(--line)}
.att-dow:last-child{border-right:0}
.att-dow--sun{color:var(--sun)}
.att-dow--sat{color:var(--sat)}

.att-cell{position:relative;aspect-ratio:1/1;border-right:1px solid var(--line);border-bottom:1px solid var(--line);padding:6px 8px;cursor:pointer;user-select:none;background:#fff;transition:background .12s}
.att-cell:nth-child(7n){border-right:0}
.att-cell:hover:not(.att-cell--future):not(.att-cell--empty){background:#fafafa}
.att-cell--empty{background:#fafbfc;cursor:default}
.att-cell--future{cursor:not-allowed}
.att-cell--future .att-num{color:#c9ced4}
.att-num{position:relative;z-index:2;font-size:13px;font-weight:500;color:#374151}
.att-cell--sun .att-num{color:var(--sun)}
.att-cell--sat .att-num{color:var(--sat)}
.att-cell--today{box-shadow:inset 0 0 0 1.5px var(--today-line);background:var(--today-bg)}
.att-cell--today:hover{background:#dcfce7}

.att-stamp{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:0;transform:scale(0.6) rotate(-8deg);transition:opacity .18s ease,transform .18s ease;color:var(--stamp)}
.att-stamp svg{width:78%;height:78%;opacity:.86}
.att-cell:not(.att-cell--stamped):not(.att-cell--future):not(.att-cell--empty) .att-stamp{opacity:.08;transform:scale(0.92) rotate(-4deg);color:#9aa0a6}
.att-cell:not(.att-cell--stamped):not(.att-cell--future):not(.att-cell--empty):hover .att-stamp{opacity:.18;color:var(--stamp)}
.att-cell--stamped .att-stamp{opacity:1}

.att-cell--stamped[data-date$="-01"] .att-stamp,.att-cell--stamped[data-date$="-08"] .att-stamp,.att-cell--stamped[data-date$="-15"] .att-stamp,.att-cell--stamped[data-date$="-22"] .att-stamp,.att-cell--stamped[data-date$="-29"] .att-stamp{transform:rotate(-9deg) scale(0.96)}
.att-cell--stamped[data-date$="-02"] .att-stamp,.att-cell--stamped[data-date$="-09"] .att-stamp,.att-cell--stamped[data-date$="-16"] .att-stamp,.att-cell--stamped[data-date$="-23"] .att-stamp,.att-cell--stamped[data-date$="-30"] .att-stamp{transform:rotate(6deg) scale(0.94)}
.att-cell--stamped[data-date$="-03"] .att-stamp,.att-cell--stamped[data-date$="-10"] .att-stamp,.att-cell--stamped[data-date$="-17"] .att-stamp,.att-cell--stamped[data-date$="-24"] .att-stamp,.att-cell--stamped[data-date$="-31"] .att-stamp{transform:rotate(-3deg) scale(1)}
.att-cell--stamped[data-date$="-04"] .att-stamp,.att-cell--stamped[data-date$="-11"] .att-stamp,.att-cell--stamped[data-date$="-18"] .att-stamp,.att-cell--stamped[data-date$="-25"] .att-stamp{transform:rotate(11deg) scale(0.93)}
.att-cell--stamped[data-date$="-05"] .att-stamp,.att-cell--stamped[data-date$="-12"] .att-stamp,.att-cell--stamped[data-date$="-19"] .att-stamp,.att-cell--stamped[data-date$="-26"] .att-stamp{transform:rotate(-7deg) scale(0.97)}
.att-cell--stamped[data-date$="-06"] .att-stamp,.att-cell--stamped[data-date$="-13"] .att-stamp,.att-cell--stamped[data-date$="-20"] .att-stamp,.att-cell--stamped[data-date$="-27"] .att-stamp{transform:rotate(4deg) scale(0.95)}
.att-cell--stamped[data-date$="-07"] .att-stamp,.att-cell--stamped[data-date$="-14"] .att-stamp,.att-cell--stamped[data-date$="-21"] .att-stamp,.att-cell--stamped[data-date$="-28"] .att-stamp{transform:rotate(-12deg) scale(0.98)}

@keyframes att-stamp-in{0%{opacity:0;transform:scale(1.5) rotate(0deg)}60%{opacity:1;transform:scale(0.88) rotate(var(--r,-6deg))}80%{transform:scale(1.04) rotate(var(--r,-6deg))}100%{opacity:1;transform:scale(0.96) rotate(var(--r,-6deg))}}
.att-cell--just-stamped .att-stamp{animation:att-stamp-in .45s cubic-bezier(.2,.8,.3,1.2) both}

.att-hint{margin:12px 2px 0;font-size:12.5px;color:#6b7280}
.att-hint--cta{color:var(--stamp);font-weight:500;text-decoration:none}
.att-hint--cta:hover{text-decoration:underline}

.att-toast{position:fixed;left:50%;bottom:32px;transform:translateX(-50%) translateY(20px);background:#1f2937;color:#fff;padding:9px 16px;border-radius:6px;font-size:13px;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;z-index:9999}
.att-toast.is-show{opacity:1;transform:translateX(-50%) translateY(0)}

/* 포인트 팝업 */
.att-popup-bg{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.att-popup-bg.is-show{opacity:1;pointer-events:auto}
.att-popup{background:#fff;border-radius:16px;padding:32px 28px 24px;text-align:center;max-width:280px;width:90%;transform:scale(.85);transition:transform .25s cubic-bezier(.2,.8,.3,1.2);box-shadow:0 8px 32px rgba(0,0,0,.18)}
.att-popup-bg.is-show .att-popup{transform:scale(1)}
.att-popup-icon{font-size:48px;line-height:1;margin-bottom:12px}
.att-popup-title{font-size:17px;font-weight:700;color:#111827;margin-bottom:6px}
.att-popup-point{font-size:28px;font-weight:800;color:#22c55e;margin:8px 0 4px}
.att-popup-sub{font-size:13px;color:#6b7280;margin-bottom:20px}
.att-popup-close{display:inline-block;padding:9px 28px;background:#22c55e;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:background .15s}
.att-popup-close:hover{background:#16a34a;color:#fff;text-decoration:none}
.att-popup-desc{font-size:13px;color:#6b7280;margin-bottom:20px;line-height:1.6}
.att-popup-actions{display:flex;gap:10px;justify-content:center}
.att-popup-login{display:inline-block;padding:9px 20px;background:#f3f4f6;color:#374151;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;transition:background .15s}
.att-popup-login:hover{background:#e5e7eb;color:#374151;text-decoration:none}

/* 출석 입력+버튼 행 */
.att-action-row{display:flex;align-items:stretch;margin:16px 0 0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.att-msg-input{flex:1;min-width:0;padding:14px 16px;border:none;outline:none;font-size:15px;color:#374151;background:#fff;font-family:inherit}
.att-msg-input::placeholder{color:#9ca3af}
.att-msg-input:disabled{background:#f9fafb;color:#9ca3af}
.att-stamp-btn{display:inline-flex;align-items:center;gap:10px;padding:0 22px;background:#dc2626;color:#fff;border:none;font-size:15px;font-weight:700;cursor:pointer;white-space:nowrap;transition:background .15s;letter-spacing:-.3px;min-height:52px}
.att-stamp-btn:hover{background:#b91c1c}
.att-stamp-btn:active{background:#991b1b}
.att-stamp-btn--done{background:#d1fae5;color:#15803d;cursor:default;padding:0 20px}
.att-stamp-btn--done:hover{background:#d1fae5}

@media(max-width:480px){
  .att-stamp-btn{padding:0 14px;font-size:14px;gap:7px}
  .att-stamp-btn svg{display:none}
}

/* 오늘 출석 멤버 */
.att-list-wrap{margin-top:20px;border:1px solid var(--line);border-radius:8px;overflow:hidden}
.att-list-header{display:flex;align-items:center;gap:8px;padding:12px 16px;background:#fafbfc;border-bottom:1px solid var(--line);font-size:14px;font-weight:700;color:#374151}
.att-list-count{display:inline-block;padding:2px 8px;background:#22c55e;color:#fff;border-radius:20px;font-size:12px;font-weight:700}
.att-list-empty{padding:20px;text-align:center;font-size:13px;color:#9ca3af}
.att-tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.att-tbl{width:100%;border-collapse:collapse;font-size:13px}
.att-tbl thead tr{background:#f8fafc}
.att-tbl th{padding:10px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:12px;white-space:nowrap;border-bottom:1px solid var(--line)}
.att-tbl td{padding:10px 12px;border-bottom:1px solid #f3f4f6;color:#374151;vertical-align:middle}
.att-tbl tbody tr:last-child td{border-bottom:0}
.att-tbl tbody tr:hover td{background:#fafafa}
.att-th-rank,.att-td-rank{text-align:center;width:52px}
.att-th-time,.att-td-time{white-space:nowrap;color:#6b7280;font-size:12px}
.att-td-nick{display:flex;align-items:center;gap:5px;white-space:nowrap;font-weight:600}
.att-td-msg{color:#4b5563;max-width:160px}
.att-tbl th.att-th-pt,.att-td-pt{white-space:nowrap;font-weight:700;color:#15803d;text-align:center}
.att-tbl th.att-th-days,.att-td-days{white-space:nowrap;color:#6b7280;text-align:center}

@media(max-width:600px){
  .att-th-msg,.att-td-msg{display:none}
  .att-th-days,.att-td-days{display:none}
  .att-stamp-btn{width:100%;justify-content:center;padding:14px 20px}
}

@media(max-width:480px){
  .att-wrap{font-size:13px;padding:8px 10px 16px}
  .att-month{font-size:16px}
  .att-num{font-size:12px}
  .att-cell{padding:4px 6px}
}
</style>

<script>
var NB_IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
var NB_LOGIN_URL    = '<?= nb_url('login') ?>';
var NB_CHECK_URL    = '<?= nb_url('attendance/check') ?>';
var NB_CSRF         = '<?= Auth::csrfToken() ?>';

(function () {
  var grid = document.getElementById('att-grid');
  if (!grid) return;
  var today = grid.dataset.today;

  function showPointPopup(point, monthCount) {
    var bg = document.getElementById('att-point-popup');
    if (!bg) return;
    bg.querySelector('.att-popup-point').textContent = (point > 0 ? '+' + point + 'P' : '출석 완료');
    bg.querySelector('.att-popup-sub').textContent = '이번 달 ' + monthCount + '일째 출석';
    bg.classList.add('is-show');
  }

  function showToast(msg) {
    var t = document.querySelector('.att-toast');
    if (!t) { t = document.createElement('div'); t.className = 'att-toast'; document.body.appendChild(t); }
    t.textContent = msg;
    t.classList.add('is-show');
    clearTimeout(t._hideTimer);
    t._hideTimer = setTimeout(function () { t.classList.remove('is-show'); }, 2000);
  }

  // 랜덤 메시지 입력창에 채우기
  var NB_MSGS = ['출석!','오늘도 화이팅!','좋은 하루 보내세요 :)','즐거운 하루 되세요','열심히 살자!','오늘도 파이팅','반갑습니다!','굿모닝!','힘차게 출석!','오늘도 좋은 하루','행복한 하루 되세요','하루의 시작!','오늘도 건강하게','씩씩하게 출석'];
  var msgInput = document.getElementById('att-msg-input');
  if (msgInput && !msgInput.value) {
    msgInput.value = NB_MSGS[Math.floor(Math.random() * NB_MSGS.length)];
  }

  function doAttend(todayCell) {
    if (!NB_IS_LOGGED_IN) {
      document.getElementById('att-join-popup').classList.add('is-show');
      return;
    }
    if (todayCell) todayCell.classList.add('att-cell--stamped', 'att-cell--just-stamped');
    var mainBtn = document.getElementById('att-main-btn');
    if (mainBtn) { mainBtn.disabled = true; mainBtn.style.opacity = '.6'; }

    var msgVal = msgInput ? msgInput.value.trim() : '';
    var fd = new FormData();
    fd.append('_token', NB_CSRF);
    fd.append('date', today);
    if (msgVal) fd.append('message', msgVal);

    fetch(NB_CHECK_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (!json.ok) {
        if (todayCell && !json.already) todayCell.classList.remove('att-cell--stamped', 'att-cell--just-stamped');
        if (mainBtn) { mainBtn.disabled = false; mainBtn.style.opacity = ''; }
        if (json.needLogin) {
          showToast('로그인이 필요합니다');
          setTimeout(function () { location.href = NB_LOGIN_URL; }, 1200);
          return;
        }
        showToast(json.msg || '출석 실패');
        return;
      }
      var counter = document.querySelector('.att-streak strong');
      if (counter && json.count) counter.textContent = json.count;
      showPointPopup(json.point || 0, json.count || 0);
    })
    .catch(function () {
      if (todayCell) todayCell.classList.remove('att-cell--stamped', 'att-cell--just-stamped');
      if (mainBtn) { mainBtn.disabled = false; mainBtn.style.opacity = ''; }
      showToast('네트워크 오류');
    });
  }

  // 달력 셀 클릭
  grid.addEventListener('click', function (e) {
    var cell = e.target.closest('.att-cell');
    if (!cell || cell.classList.contains('att-cell--empty')) return;
    if (cell.classList.contains('att-cell--future')) { showToast('미래 날짜는 출석할 수 없어요'); return; }
    var date = cell.dataset.date;
    if (date !== today) {
      showToast(cell.classList.contains('att-cell--stamped') ? '출석 완료된 날짜입니다' : '오늘 날짜만 출석 가능합니다');
      return;
    }
    if (cell.classList.contains('att-cell--stamped')) { showToast('오늘 이미 출석했어요'); return; }
    doAttend(cell);
  });

  // 버튼 클릭
  var mainBtn = document.getElementById('att-main-btn');
  if (mainBtn) {
    mainBtn.addEventListener('click', function () {
      var todayCell = grid.querySelector('[data-date="' + today + '"]');
      doAttend(todayCell);
    });
  }
})();
</script>

<!-- 회원가입 유도 팝업 -->
<div class="att-popup-bg" id="att-join-popup">
  <div class="att-popup">
    <div class="att-popup-icon">
      <svg width="52" height="52" viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="26" cy="26" r="26" fill="#f0fdf4"/>
        <circle cx="26" cy="20" r="7" stroke="#22c55e" stroke-width="2.5" fill="none"/>
        <path d="M13 42c0-7.18 5.82-13 13-13s13 5.82 13 13" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" fill="none"/>
      </svg>
    </div>
    <div class="att-popup-title">출석체크에 참여해보세요!</div>
    <div class="att-popup-desc">매일 출석하면 포인트가 적립돼요.<br>지금 바로 가입하고 혜택을 받아보세요.</div>
    <div class="att-popup-actions">
      <a href="<?= nb_url('register') ?>" class="att-popup-close">회원가입</a>
      <a href="<?= nb_url('login') ?>?redirect=<?= urlencode(nb_url('attendance')) ?>" class="att-popup-login">로그인</a>
    </div>
  </div>
</div>

<div class="att-popup-bg" id="att-point-popup">
  <div class="att-popup">
    <div class="att-popup-icon">
      <svg width="52" height="52" viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="26" cy="26" r="26" fill="#f0fdf4"/>
        <path d="M15 27l8 8 14-16" stroke="#22c55e" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="att-popup-title">출석 완료!</div>
    <div class="att-popup-point"></div>
    <div class="att-popup-sub"></div>
    <button class="att-popup-close" id="att-popup-close-btn">확인</button>
  </div>
</div>
<script>
(function(){
  // 회원가입 유도 팝업 닫기 (배경 클릭)
  var joinBg = document.getElementById('att-join-popup');
  if (joinBg) {
    joinBg.addEventListener('click', function(e){ if (e.target === joinBg) joinBg.classList.remove('is-show'); });
  }
  // 포인트 팝업 닫기
  var bg = document.getElementById('att-point-popup');
  var btn = document.getElementById('att-popup-close-btn');
  if (!btn) return;
  btn.addEventListener('click', function(){ bg.classList.remove('is-show'); location.reload(); });
  bg.addEventListener('click', function(e){ if (e.target === bg) { bg.classList.remove('is-show'); location.reload(); } });
})();
</script>

<?php require dirname(__DIR__) . '/footer.php'; ?>

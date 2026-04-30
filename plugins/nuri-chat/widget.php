<?php
/**
 * 누리챗 프론트 위젯 (채널톡 스타일 리디자인)
 */
$cfg = $_ncConfig;
$accent  = htmlspecialchars($cfg['accent_color'] ?: '#22c55e');
$botName = htmlspecialchars($cfg['bot_name']);
$botSub  = htmlspecialchars($cfg['bot_subtitle']);
$pos     = ($cfg['position'] === 'left') ? 'left' : 'right';
$bottom  = (int)$cfg['bottom'];
$offset  = (int)$cfg['offset'];
$apiBase = '/?nc_api=';

// 로그인 정보 자동 채워넣기 (설정 탭 연락처에 사용)
$me = ['name' => '', 'email' => '', 'phone' => '', 'logged_in' => false];
if (class_exists('Auth') && method_exists('Auth', 'user')) {
    $u = Auth::user();
    if (!empty($u['id'])) {
        $me['logged_in'] = true;
        $me['name']  = $u['nickname'] ?? $u['name'] ?? '';
        $me['email'] = $u['email'] ?? '';
        $me['phone'] = $u['phone'] ?? $u['hp'] ?? '';
    }
}
?>
<style>
.nc-root{position:fixed;<?= $pos ?>:<?= $offset ?>px;bottom:<?= $bottom ?>px;z-index:99990;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans KR',sans-serif;color:#111827;line-height:1.5}
.nc-root *{box-sizing:border-box}
.nc-root button{font-family:inherit}

/* === 플로팅 버튼 === */
.nc-fab{position:relative;width:60px;height:60px;border-radius:50%;background:<?= $accent ?>;border:none;box-shadow:0 6px 20px rgba(0,0,0,.18);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .15s;padding:0}
.nc-fab:hover{transform:scale(1.08)}
.nc-fab svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.nc-fab .nc-badge{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;background:#ef4444;color:#fff;font-size:11px;font-weight:700;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 5px;border:2px solid #fff}

/* === 패널 === */
.nc-panel{position:absolute;bottom:76px;<?= $pos ?>:0;width:380px;max-width:calc(100vw - <?= $offset * 2 ?>px);height:620px;max-height:calc(100vh - 120px);background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.22);display:none;flex-direction:column;overflow:hidden;animation:ncSlide .25s ease-out}
.nc-panel.open{display:flex}
@keyframes ncSlide{from{opacity:0;transform:translateY(16px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}

/* 패널 우상단 닫기 버튼 */
.nc-panel-close{position:absolute;top:12px;right:12px;width:32px;height:32px;border:none;background:rgba(0,0,0,.05);border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10;transition:background .15s;padding:0}
.nc-panel-close:hover{background:rgba(0,0,0,.1)}
.nc-panel-close svg{width:16px;height:16px;stroke:#6b7280;fill:none;stroke-width:2.2}

/* 모바일: 풀스크린 + 하단 FAB 숨김 */
@media (max-width:480px){
    .nc-panel{position:fixed;top:0;left:0;right:0;bottom:0;width:100vw;height:100vh;max-width:100vw;max-height:100vh;border-radius:0;animation:ncSlideMobile .22s ease-out}
    .nc-panel.open ~ .nc-fab{display:none}
    .nc-panel-close{top:max(12px, env(safe-area-inset-top));right:max(12px, env(safe-area-inset-right));width:36px;height:36px;background:rgba(255,255,255,.9);box-shadow:0 2px 6px rgba(0,0,0,.1)}
}
@keyframes ncSlideMobile{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}

/* === 탭 공통 === */
.nc-screen{flex:1;display:none;flex-direction:column;overflow:hidden}
.nc-screen.active{display:flex}
.nc-title{padding:20px 22px 14px;font-size:22px;font-weight:800;color:#111827}

/* === 홈 탭 === */
.nc-home-wrap{flex:1;overflow-y:auto;padding:20px 16px}
.nc-home-top{display:flex;align-items:center;gap:12px;padding:8px 6px 18px}
.nc-home-top .nc-avatar-big{width:52px;height:52px;border-radius:50%;background:<?= $accent ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(34,197,94,.25)}
.nc-home-top .nc-avatar-big svg{width:28px;height:28px;stroke:#fff;fill:none;stroke-width:2}
.nc-home-top .nc-bot-title{font-size:20px;font-weight:800;color:#111827;margin:0}
.nc-home-top .nc-bot-sub{font-size:12px;color:#6b7280;margin-top:4px;display:flex;align-items:center;gap:5px}
.nc-home-top .nc-bot-sub svg{width:12px;height:12px;stroke:<?= $accent ?>;fill:none;stroke-width:2.4}

.nc-home-card{background:#fff;border:1px solid #eef0f3;border-radius:16px;padding:16px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.nc-home-greet{display:flex;align-items:flex-start;gap:10px;margin-bottom:14px}
.nc-home-greet .nc-avatar-s{width:32px;height:32px;border-radius:50%;background:<?= $accent ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.nc-home-greet .nc-avatar-s svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2}
.nc-home-greet .nc-greet-text{font-size:13px;color:#374151;line-height:1.55}
.nc-home-greet .nc-greet-text strong{display:block;font-size:12px;color:#9ca3af;font-weight:600;margin-bottom:2px}

.nc-home-cta{width:100%;background:<?= $accent ?>;color:#fff;border:none;padding:14px;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s}
.nc-home-cta:hover{opacity:.9}
.nc-home-cta svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.2}
.nc-home-status{text-align:center;font-size:12px;color:#6b7280;margin-top:10px;display:flex;align-items:center;justify-content:center;gap:6px}
.nc-home-status::before{content:"";width:7px;height:7px;border-radius:50%;background:#22c55e}
.nc-home-powered{text-align:center;margin-top:16px;font-size:11px;color:#9ca3af;display:flex;align-items:center;justify-content:center;gap:4px}
.nc-home-powered svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2}

/* === 대화 탭: 리스트 상태 === */
.nc-chat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;text-align:center;position:relative}
.nc-chat-empty svg{width:80px;height:80px;color:#e5e7eb;stroke:currentColor;fill:none;stroke-width:1.5;margin-bottom:16px}
.nc-chat-empty p{color:#9ca3af;font-size:14px;margin:0}
.nc-chat-empty .nc-new-chat{margin-top:auto;background:<?= $accent ?>;color:#fff;border:none;padding:12px 26px;border-radius:24px;font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(34,197,94,.3);position:absolute;bottom:24px;left:50%;transform:translateX(-50%)}
.nc-chat-empty .nc-new-chat:hover{opacity:.92}
.nc-chat-empty .nc-new-chat svg{width:14px;height:14px;stroke:#fff;stroke-width:2.2;color:inherit;margin:0}

/* === 대화 탭: 활성 상태 === */
.nc-chat-active{flex:1;display:flex;flex-direction:column;overflow:hidden}
.nc-chat-header{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid #f1f3f5;background:#fff}
.nc-chat-back{width:28px;height:28px;border:none;background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;color:#6b7280;border-radius:6px}
.nc-chat-back:hover{background:#f3f4f6}
.nc-chat-back svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2.4}
.nc-chat-header .nc-avatar-mini{width:34px;height:34px;border-radius:50%;background:<?= $accent ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.nc-chat-header .nc-avatar-mini svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2}
.nc-chat-header .nc-bot-name{font-size:15px;font-weight:700;color:#111827;margin:0}
.nc-chat-header .nc-bot-hint{font-size:11px;color:#9ca3af;margin-top:2px}

.nc-body{flex:1;overflow-y:auto;padding:16px 14px;background:#f7f8fa}
.nc-body::-webkit-scrollbar{width:6px}
.nc-body::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:3px}

/* === 메시지 === */
.nc-row{display:flex;margin-bottom:14px;gap:8px}
.nc-row.me{justify-content:flex-end}
.nc-row.them{justify-content:flex-start}
.nc-avatar{width:30px;height:30px;border-radius:50%;background:<?= $accent ?>;flex-shrink:0;display:flex;align-items:center;justify-content:center;align-self:flex-end}
.nc-avatar.admin{background:#3b82f6}
.nc-avatar svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2}
.nc-msg-col{display:flex;flex-direction:column;max-width:72%;min-width:0}
.nc-row.me .nc-msg-col{align-items:flex-end}
.nc-row.them .nc-msg-col{align-items:flex-start}
.nc-sender{font-size:11px;color:#6b7280;margin:0 4px 4px;font-weight:600}
.nc-bubble{padding:10px 14px;border-radius:18px;font-size:14px;line-height:1.55;word-break:break-word;white-space:pre-wrap;max-width:100%}
.nc-row.me .nc-bubble{background:<?= $accent ?>;color:#fff;border-bottom-right-radius:4px}
.nc-row.them.bot .nc-bubble{background:#fff;color:#111827;border-bottom-left-radius:4px;box-shadow:0 1px 2px rgba(0,0,0,.05);border:1px solid #edeff2}
.nc-row.them.admin .nc-bubble{background:#eff6ff;color:#1e3a8a;border-bottom-left-radius:4px;border:1px solid #dbeafe}
.nc-time{font-size:10px;color:#9ca3af;margin:4px 4px 0}
.nc-refs{margin-top:8px;padding-top:8px;border-top:1px solid rgba(0,0,0,.08);display:flex;flex-direction:column;gap:4px}
.nc-refs a{font-size:12px;color:#2563eb;text-decoration:none}
.nc-refs a:hover{text-decoration:underline}

/* === FAQ 칩 === */
.nc-chips{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 4px;padding:0 4px;justify-content:flex-end}
.nc-chip{background:#fff;border:1px solid #e5e7eb;padding:9px 14px;border-radius:22px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .12s;color:#374151;font-weight:500}
.nc-chip:hover{border-color:<?= $accent ?>;color:<?= $accent ?>;background:#f0fdf4}
.nc-chip svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2}

/* === 입력창 === */
.nc-input-wrap{display:flex;padding:10px 12px;background:#fff;border-top:1px solid #f1f3f5;align-items:flex-end;gap:8px}
.nc-input{flex:1;border:none;outline:none;font-size:14px;padding:10px 14px;background:#f3f4f6;border-radius:22px;resize:none;max-height:80px;font-family:inherit;line-height:1.4}
.nc-send{width:38px;height:38px;border-radius:50%;background:<?= $accent ?>;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0}
.nc-send:disabled{background:#d1d5db;cursor:not-allowed}
.nc-send svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.4}

/* === 설정 탭 === */
.nc-settings-wrap{flex:1;overflow-y:auto;padding:0}
.nc-contact-card{padding:20px 20px 22px;text-align:center;border-bottom:1px solid #f1f3f5}
.nc-contact-avatar{width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,<?= $accent ?> 0%,#a7f3d0 100%);display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.nc-contact-avatar svg{width:36px;height:36px;stroke:#fff;fill:none;stroke-width:1.8}
.nc-contact-info{font-size:13px;color:#374151;margin-bottom:12px;font-weight:500;min-height:18px}
.nc-contact-info.empty{color:#9ca3af}
.nc-contact-edit{background:#f3f4f6;border:none;padding:7px 16px;border-radius:18px;font-size:12px;color:#4b5563;cursor:pointer;display:inline-flex;align-items:center;gap:5px;font-weight:500}
.nc-contact-edit:hover{background:#e5e7eb}
.nc-contact-edit svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2}

.nc-settings-group{padding:14px 20px 4px}
.nc-settings-label{font-size:12px;color:#9ca3af;font-weight:600;margin-bottom:6px}
.nc-setting-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #f3f4f6;font-size:14px;color:#1f2937}
.nc-setting-row:last-child{border-bottom:none}
.nc-setting-row .nc-setting-label{display:flex;align-items:center;gap:10px;flex:1}
.nc-setting-row .nc-setting-label svg{width:18px;height:18px;stroke:#6b7280;fill:none;stroke-width:1.8}
.nc-setting-row .nc-setting-value{color:#9ca3af;font-size:13px;display:flex;align-items:center;gap:4px}
.nc-setting-row .nc-setting-value svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2}

/* 토글 스위치 */
.nc-switch{position:relative;width:40px;height:22px;background:#d1d5db;border-radius:11px;cursor:pointer;transition:background .15s;border:none;padding:0;flex-shrink:0}
.nc-switch.on{background:<?= $accent ?>}
.nc-switch::after{content:"";position:absolute;top:2px;left:2px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform .18s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.nc-switch.on::after{transform:translateX(18px)}

.nc-version{text-align:right;padding:8px 20px 20px;font-size:11px;color:#d1d5db}
.nc-danger-row{padding:18px 20px 24px;border-top:1px solid #f1f3f5;margin-top:8px}
.nc-danger-btn{width:100%;background:transparent;border:1px solid #fecaca;color:#ef4444;padding:10px;border-radius:10px;font-size:13px;font-weight:500;cursor:pointer}
.nc-danger-btn:hover{background:#fef2f2}

/* === 하단 탭 === */
.nc-tabs{display:flex;border-top:1px solid #f1f3f5;background:#fff}
.nc-tab{flex:1;padding:10px 0 10px;background:transparent;border:none;cursor:pointer;color:#9ca3af;font-size:11px;display:flex;flex-direction:column;align-items:center;gap:3px;font-weight:500;position:relative}
.nc-tab.active{color:<?= $accent ?>}
.nc-tab svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.nc-tab-dot{position:absolute;top:6px;right:calc(50% - 16px);width:7px;height:7px;background:#ef4444;border-radius:50%;display:none}
.nc-tab.has-unread .nc-tab-dot{display:block}

/* === 타이핑 === */
.nc-typing{display:inline-flex;gap:3px;padding:12px 16px;background:#fff;border-radius:18px;border:1px solid #edeff2;border-bottom-left-radius:4px}
.nc-typing span{width:6px;height:6px;border-radius:50%;background:#9ca3af;animation:ncTyping 1.2s infinite}
.nc-typing span:nth-child(2){animation-delay:.2s}
.nc-typing span:nth-child(3){animation-delay:.4s}
@keyframes ncTyping{0%,60%,100%{opacity:.3;transform:translateY(0)}30%{opacity:1;transform:translateY(-3px)}}
</style>

<div class="nc-root" id="ncRoot">
  <div class="nc-panel" id="ncPanel">
    <button class="nc-panel-close" id="ncPanelClose" aria-label="닫기">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <!-- 홈 탭 -->
    <div class="nc-screen active" data-screen="home">
      <div class="nc-home-wrap">
        <div class="nc-home-top">
          <div class="nc-avatar-big">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </div>
          <div>
            <div class="nc-bot-title"><?= $botName ?></div>
            <div class="nc-bot-sub">
              <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              <span><?= $botSub ?></span>
            </div>
          </div>
        </div>
        <div class="nc-home-card">
          <div class="nc-home-greet">
            <div class="nc-avatar-s"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
            <div class="nc-greet-text"><strong><?= $botName ?></strong><?= htmlspecialchars($cfg['greeting']) ?></div>
          </div>
          <button class="nc-home-cta" id="ncHomeStart">
            문의하기
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
          <div class="nc-home-status">몇 분 내 답변 받으실 수 있어요</div>
        </div>
        <div class="nc-home-powered">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          누리챗 이용 중
        </div>
      </div>
    </div>

    <!-- 대화 탭 -->
    <div class="nc-screen" data-screen="chat">
      <!-- 리스트 상태 -->
      <div class="nc-chat-empty" id="ncChatEmpty">
        <div class="nc-title" style="position:absolute;top:0;left:0;padding:20px 22px 14px">대화</div>
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <p>대화를 시작해보세요</p>
        <button class="nc-new-chat" id="ncNewChat">
          새 문의하기
          <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </div>
      <!-- 활성 대화 -->
      <div class="nc-chat-active" id="ncChatActive" style="display:none">
        <div class="nc-chat-header">
          <button class="nc-chat-back" id="ncChatBack" aria-label="뒤로">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <div class="nc-avatar-mini"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
          <div>
            <div class="nc-bot-name"><?= $botName ?></div>
            <div class="nc-bot-hint">몇 분 내 답변 받으실 수 있어요</div>
          </div>
        </div>
        <div class="nc-body" id="ncBody"></div>
        <div class="nc-input-wrap">
          <textarea class="nc-input" id="ncInput" rows="1" placeholder="메시지를 입력해주세요..."></textarea>
          <button class="nc-send" id="ncSend" aria-label="보내기">
            <svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- 설정 탭 -->
    <div class="nc-screen" data-screen="settings">
      <div class="nc-title">설정</div>
      <div class="nc-settings-wrap">
        <div class="nc-contact-card">
          <div class="nc-contact-avatar">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="nc-contact-info <?= $me['logged_in'] ? '' : 'empty' ?>">
            <?php
              if ($me['logged_in']) {
                  $parts = array_filter([$me['phone'], $me['email']]);
                  echo htmlspecialchars(implode(' · ', $parts) ?: ($me['name'] ?: '회원'));
              } else {
                  echo '연락처 정보';
              }
            ?>
          </div>
          <button class="nc-contact-edit" onclick="window.location.href='<?= $me['logged_in'] ? '/mypage' : '/login' ?>'">
            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <?= $me['logged_in'] ? '정보 수정하기' : '로그인하기' ?>
          </button>
        </div>

        <div class="nc-settings-group">
          <div class="nc-settings-label">상담 환경</div>
          <div class="nc-setting-row">
            <div class="nc-setting-label">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
              <span>언어</span>
            </div>
            <div class="nc-setting-value">한국어 <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
          </div>
          <div class="nc-setting-row">
            <div class="nc-setting-label">
              <svg viewBox="0 0 24 24"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
              <span>알림음</span>
            </div>
            <button class="nc-switch on" data-setting="sound" id="ncSoundSwitch"></button>
          </div>
        </div>

        <div class="nc-danger-row">
          <button class="nc-danger-btn" id="ncResetBtn">대화 내용 전체 삭제</button>
        </div>

        <div class="nc-version">누리챗 v1.0</div>
      </div>
    </div>

    <!-- 하단 탭 바 -->
    <div class="nc-tabs">
      <button class="nc-tab active" data-tab="home">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <span>홈</span>
      </button>
      <button class="nc-tab" data-tab="chat">
        <span class="nc-tab-dot"></span>
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>대화</span>
      </button>
      <button class="nc-tab" data-tab="settings">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        <span>설정</span>
      </button>
    </div>
  </div>
  <button class="nc-fab" id="ncFab" aria-label="상담 열기">
    <svg id="ncFabOpen" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <svg id="ncFabClose" viewBox="0 0 24 24" style="display:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    <span class="nc-badge" id="ncBadge" style="display:none">0</span>
  </button>
</div>

<script>
(function(){
  var API = <?= json_encode($apiBase) ?>;
  var panel = document.getElementById('ncPanel');
  var fab = document.getElementById('ncFab');
  var body = document.getElementById('ncBody');
  var tabs = document.querySelectorAll('.nc-tab');
  var screens = document.querySelectorAll('.nc-screen');
  var input = document.getElementById('ncInput');
  var sendBtn = document.getElementById('ncSend');
  var badge = document.getElementById('ncBadge');
  var fabOpen = document.getElementById('ncFabOpen');
  var fabClose = document.getElementById('ncFabClose');
  var chatEmpty = document.getElementById('ncChatEmpty');
  var chatActive = document.getElementById('ncChatActive');
  var chatTabBtn = document.querySelector('.nc-tab[data-tab="chat"]');

  var state = {
    opened: false,
    tab: 'home',
    chatOpen: false,
    sessionId: null,
    lastMsgId: 0,
    faqs: [],
    bot: {},
    messages: [],
    pollTimer: null,
    soundOn: localStorage.getItem('nc_sound') !== '0',
  };

  // 알림음 (짧은 beep, base64)
  var notifyAudio = new Audio('data:audio/wav;base64,UklGRiQFAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAFAAB/f39/f39/f3+AgICAgICAgICAgICAgIB/f39/f39/f38AAA==');

  function apiCall(action, data, method) {
    method = method || 'POST';
    var url = API + encodeURIComponent(action);
    var opts = { method: method, credentials: 'same-origin' };
    if (method === 'POST') {
      var fd = new FormData();
      if (data) Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
      opts.body = fd;
    } else if (data) {
      Object.keys(data).forEach(function(k){ url += '&' + k + '=' + encodeURIComponent(data[k]); });
    }
    return fetch(url, opts).then(function(r){
      return r.text().then(function(txt){
        try { return JSON.parse(txt); }
        catch(e){
          console.error('[nuri-chat] 서버 응답 파싱 실패:', txt.slice(0,500));
          return { ok:false, error:'서버 응답 오류 (콘솔 확인)' };
        }
      });
    });
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function linkify(s){
    var out = escapeHtml(s);
    var placeholders = [];
    // 1. 마크다운 스타일 [text](url) → 임시 플레이스홀더로 치환
    out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function(_, text, url){
      var idx = placeholders.length;
      placeholders.push('<a href="' + url + '" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">' + text + '</a>');
      return '\u0001MD' + idx + '\u0001';
    });
    // 2. 일반 URL 자동 링크 (꼬리에 붙은 구두점 분리)
    out = out.replace(/(https?:\/\/[^\s<]+)/g, function(url){
      var trailing = '';
      while (url.length > 0 && /[)\]\.,!?:;'"]/.test(url.slice(-1))) {
        trailing = url.slice(-1) + trailing;
        url = url.slice(0, -1);
      }
      return '<a href="' + url + '" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">' + url + '</a>' + trailing;
    });
    // 3. 플레이스홀더 복원
    out = out.replace(/\u0001MD(\d+)\u0001/g, function(_, idx){ return placeholders[idx]; });
    return out;
  }
  function fmtTime(iso){
    if(!iso) return '';
    var d = new Date(iso.replace(' ','T'));
    if (isNaN(d.getTime())) return '';
    var h = d.getHours(), m = d.getMinutes();
    var ap = h < 12 ? '오전' : '오후';
    h = h % 12; if (h === 0) h = 12;
    return ap + ' ' + h + ':' + (m<10?'0':'') + m;
  }

  function openPanel(){
    state.opened = true;
    panel.classList.add('open');
    fabOpen.style.display = 'none';
    fabClose.style.display = 'block';
    if (!state.sessionId) init();
  }
  function closePanel(){
    state.opened = false;
    panel.classList.remove('open');
    fabOpen.style.display = 'block';
    fabClose.style.display = 'none';
  }
  fab.addEventListener('click', function(){ state.opened ? closePanel() : openPanel(); });
  document.getElementById('ncPanelClose').addEventListener('click', closePanel);

  function init(){
    apiCall('init', null, 'POST').then(function(res){
      if(!res.ok){ console.error('init 실패:', res.error); return; }
      state.sessionId = res.session_id;
      state.faqs = res.faqs || [];
      state.bot = res.bot || {};
      state.messages = res.messages || [];
      if (state.messages.length > 0) state.lastMsgId = state.messages[state.messages.length - 1].id;
      // 인사말만 있는 상태면 빈 상태 유지, 실제 대화가 있으면 자동으로 open
      if (state.messages.filter(function(m){ return m.sender === 'user'; }).length > 0) {
        state.chatOpen = true;
      }
      renderChatTab();
      startPoll();
    });
  }

  function startPoll(){
    if (state.pollTimer) clearInterval(state.pollTimer);
    state.pollTimer = setInterval(function(){
      apiCall('poll', { after_id: state.lastMsgId }, 'GET').then(function(res){
        if (!res.ok || !res.messages || !res.messages.length) return;
        var hasAdmin = false;
        res.messages.forEach(function(m){
          state.messages.push(m);
          state.lastMsgId = m.id;
          if (m.sender === 'admin') hasAdmin = true;
        });
        if (state.tab === 'chat' && state.chatOpen) renderMessages();
        else if (hasAdmin) {
          chatTabBtn.classList.add('has-unread');
          updateBadge(1);
          if (state.soundOn) { try { notifyAudio.play(); } catch(e){} }
        }
      });
    }, 8000);
  }

  function updateBadge(n){
    if (!n) return;
    var cur = parseInt(badge.textContent || '0', 10);
    badge.textContent = cur + n;
    badge.style.display = 'flex';
  }
  function clearBadge(){ badge.style.display = 'none'; badge.textContent = '0'; chatTabBtn.classList.remove('has-unread'); }

  function switchTab(name){
    state.tab = name;
    tabs.forEach(function(t){ t.classList.toggle('active', t.dataset.tab === name); });
    screens.forEach(function(s){ s.classList.toggle('active', s.dataset.screen === name); });
    if (name === 'chat') {
      clearBadge();
      renderChatTab();
    }
  }
  tabs.forEach(function(t){ t.addEventListener('click', function(){ switchTab(t.dataset.tab); }); });

  // 홈에서 "문의하기" 클릭
  document.getElementById('ncHomeStart').addEventListener('click', function(){
    state.chatOpen = true;
    switchTab('chat');
  });
  // 대화 탭 빈상태에서 "새 문의하기"
  document.getElementById('ncNewChat').addEventListener('click', function(){
    state.chatOpen = true;
    renderChatTab();
  });
  // 대화에서 뒤로가기
  document.getElementById('ncChatBack').addEventListener('click', function(){
    state.chatOpen = false;
    renderChatTab();
  });

  function renderChatTab(){
    if (state.chatOpen) {
      chatEmpty.style.display = 'none';
      chatActive.style.display = 'flex';
      renderMessages();
    } else {
      chatEmpty.style.display = 'flex';
      chatActive.style.display = 'none';
    }
  }

  function renderMessages(){
    var html = '';
    state.messages.forEach(function(m){ html += renderMessage(m); });
    // FAQ 칩: 사용자가 아직 아무것도 안 보낸 첫 방문 상태에서만 노출
    var hasUserAction = state.messages.some(function(m){ return m.sender === 'user'; });
    var showChips = state.faqs.length > 0 && !hasUserAction;
    if (showChips) {
      html += '<div class="nc-chips">';
      state.faqs.forEach(function(f){
        html += '<button class="nc-chip" data-faq-id="' + escapeHtml(f.id) + '" data-faq-label="' + escapeHtml(f.label) + '">' +
                (f.icon ? f.icon : '') + '<span>' + escapeHtml(f.label) + '</span></button>';
      });
      html += '</div>';
    }
    body.innerHTML = html;
    body.scrollTop = body.scrollHeight;
    body.querySelectorAll('.nc-chip').forEach(function(el){
      el.addEventListener('click', function(){ onFaqClick(el.dataset.faqId, el.dataset.faqLabel); });
    });
  }

  function renderMessage(m){
    return m.sender === 'user' ? renderMe(m) : renderThem(m);
  }
  function renderMe(m){
    return '<div class="nc-row me"><div class="nc-msg-col">' +
           '<div class="nc-sender">나</div>' +
           '<div class="nc-bubble">' + linkify(m.content) + renderRefs(m) + '</div>' +
           '<div class="nc-time">' + fmtTime(m.created_at) + '</div></div></div>';
  }
  function renderThem(m){
    var isAdmin = m.sender === 'admin';
    var senderName = isAdmin ? '관리자' : (state.bot.name || '봇');
    var avatarSvg = isAdmin
      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
      : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    return '<div class="nc-row them ' + (isAdmin ? 'admin' : 'bot') + '">' +
           '<div class="nc-avatar ' + (isAdmin ? 'admin' : '') + '">' + avatarSvg + '</div>' +
           '<div class="nc-msg-col">' +
             '<div class="nc-sender">' + escapeHtml(senderName) + '</div>' +
             '<div class="nc-bubble">' + linkify(m.content) + renderRefs(m) + '</div>' +
             '<div class="nc-time">' + fmtTime(m.created_at) + '</div>' +
           '</div></div>';
  }
  function renderRefs(m){
    if (!m.meta || !m.meta.refs || !m.meta.refs.length) return '';
    var r = '<div class="nc-refs">';
    m.meta.refs.forEach(function(x){
      r += '<a href="' + escapeHtml(x.url) + '" target="_blank">→ ' + escapeHtml(x.title) + '</a>';
    });
    return r + '</div>';
  }

  function pushOptimistic(sender, content){
    var now = new Date();
    var iso = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0') +
              ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':00';
    state.messages.push({ id: 0, sender: sender, content: content, created_at: iso });
  }

  function onFaqClick(faqId, label){
    pushOptimistic('user', label);
    renderMessages();
    apiCall('faq', { faq_id: faqId }, 'POST').then(function(res){
      if (res.ok && res.bot_message) {
        state.messages.push(res.bot_message);
        state.lastMsgId = res.bot_message.id;
      } else {
        pushOptimistic('bot', '⚠ ' + (res.error || '답변을 불러올 수 없습니다.'));
      }
      renderMessages();
    });
  }

  function onSend(){
    var text = input.value.trim();
    if (!text) return;
    input.value = ''; input.style.height = 'auto';
    sendBtn.disabled = true;
    pushOptimistic('user', text);
    renderMessages();
    var typingHtml = '<div class="nc-row them bot" id="ncTypingRow">' +
                     '<div class="nc-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>' +
                     '<div class="nc-msg-col"><div class="nc-sender">' + escapeHtml(state.bot.name || '봇') + '</div><div class="nc-typing"><span></span><span></span><span></span></div></div></div>';
    body.insertAdjacentHTML('beforeend', typingHtml);
    body.scrollTop = body.scrollHeight;
    apiCall('send', { message: text }, 'POST').then(function(res){
      var t = document.getElementById('ncTypingRow'); if (t) t.remove();
      if (res.ok && res.bot_message) {
        state.messages.push(res.bot_message);
        state.lastMsgId = res.bot_message.id;
      } else {
        pushOptimistic('bot', '⚠ ' + (res.error || '답변 생성에 실패했습니다.'));
      }
      renderMessages();
      sendBtn.disabled = false;
    }).catch(function(){
      var t = document.getElementById('ncTypingRow'); if (t) t.remove();
      pushOptimistic('bot', '⚠ 네트워크 오류. 잠시 후 다시 시도해주세요.');
      renderMessages();
      sendBtn.disabled = false;
    });
  }
  sendBtn.addEventListener('click', onSend);
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onSend(); }
  });
  input.addEventListener('input', function(){
    input.style.height = 'auto';
    input.style.height = Math.min(80, input.scrollHeight) + 'px';
  });

  // 설정 탭 토글
  var soundSwitch = document.getElementById('ncSoundSwitch');
  soundSwitch.classList.toggle('on', state.soundOn);
  soundSwitch.addEventListener('click', function(){
    state.soundOn = !state.soundOn;
    localStorage.setItem('nc_sound', state.soundOn ? '1' : '0');
    soundSwitch.classList.toggle('on', state.soundOn);
  });

  // 대화 초기화
  document.getElementById('ncResetBtn').addEventListener('click', function(){
    if (!confirm('대화 내용을 전부 삭제하시겠어요?')) return;
    apiCall('reset', null, 'POST').then(function(){
      state.messages = []; state.lastMsgId = 0; state.sessionId = null;
      state.chatOpen = false;
      switchTab('home');
      init();
    });
  });
})();
</script>

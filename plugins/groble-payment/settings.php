<?php
/**
 * 그로블 소개 랜딩 페이지 (화이트 + 그린)
 */
?>
<style>
.gb-hero { background:#fff; border:1px solid #e5e7eb; border-radius:20px; padding:48px 32px; text-align:center; margin-bottom:22px; position:relative; overflow:hidden; }
.gb-hero::before { content:""; position:absolute; top:-100px; right:-100px; width:300px; height:300px; background:radial-gradient(circle,rgba(34,197,94,.08) 0%,transparent 70%); pointer-events:none; }
.gb-hero::after { content:""; position:absolute; left:-100px; bottom:-100px; width:300px; height:300px; background:radial-gradient(circle,rgba(34,197,94,.06) 0%,transparent 70%); pointer-events:none; }
.gb-badge { display:inline-flex; align-items:center; gap:6px; background:#f0fdf4; border:1px solid #bbf7d0; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; margin-bottom:18px; position:relative; color:#15803d; }
.gb-badge::before { content:""; width:7px; height:7px; background:#22c55e; border-radius:50%; box-shadow:0 0 8px #22c55e; }
.gb-hero h1 { font-size:32px; font-weight:800; margin:0 0 14px; letter-spacing:-.8px; line-height:1.3; position:relative; color:#111827; }
.gb-hero h1 span { color:#22c55e; }
.gb-hero p { font-size:15px; color:#4b5563; margin:0 0 28px; line-height:1.7; position:relative; }
.gb-cta { display:inline-flex; align-items:center; gap:8px; background:#22c55e; color:#fff; padding:14px 32px; border-radius:30px; font-size:15px; font-weight:700; text-decoration:none; box-shadow:0 8px 24px rgba(34,197,94,.3); transition:transform .15s, box-shadow .15s; position:relative; }
.gb-cta:hover { transform:translateY(-2px); box-shadow:0 12px 30px rgba(34,197,94,.4); color:#fff; text-decoration:none; }
.gb-cta svg { width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:2.5; }

.gb-url-box { display:inline-flex; align-items:center; gap:8px; background:#f9fafb; border:1px solid #e5e7eb; color:#4b5563; padding:8px 14px; border-radius:8px; font-size:13px; font-family:monospace; margin-top:16px; position:relative; }
.gb-url-box svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2; opacity:.7; }

.gb-features { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; margin-bottom:22px; }
.gb-feature { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px 20px; text-align:center; transition:transform .15s, border-color .15s, box-shadow .15s; }
.gb-feature:hover { transform:translateY(-2px); border-color:#86efac; box-shadow:0 6px 16px rgba(34,197,94,.1); }
.gb-feature .icon { width:48px; height:48px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:14px; }
.gb-feature .icon svg { width:22px; height:22px; stroke:#22c55e; fill:none; stroke-width:2.2; }
.gb-feature h3 { margin:0 0 6px; font-size:14px; font-weight:700; color:#111827; }
.gb-feature p { margin:0; font-size:12px; color:#6b7280; line-height:1.55; }

.gb-compare { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:24px 26px; margin-bottom:22px; }
.gb-compare h2 { font-size:18px; font-weight:700; color:#111827; margin:0 0 18px; }
.gb-compare-row { display:flex; justify-content:space-between; align-items:center; padding:13px 0; border-bottom:1px dashed #e5e7eb; font-size:14px; }
.gb-compare-row:last-child { border-bottom:none; }
.gb-compare-row .k { color:#374151; display:flex; align-items:center; gap:8px; font-weight:500; }
.gb-compare-row .k svg { width:16px; height:16px; stroke:#22c55e; fill:none; stroke-width:2.5; flex-shrink:0; }
.gb-compare-row .v { color:#6b7280; font-size:13px; }

.gb-quotes { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:14px; padding:22px 24px; margin-bottom:22px; }
.gb-quotes h2 { font-size:14px; font-weight:700; color:#15803d; margin:0 0 14px; display:flex; align-items:center; gap:6px; }
.gb-quotes h2 svg { width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:2; }
.gb-quote { font-size:13px; color:#065f46; line-height:1.75; margin-bottom:12px; padding-left:16px; border-left:3px solid #86efac; }
.gb-quote:last-child { margin-bottom:0; }
.gb-quote strong { color:#064e3b; }

.gb-final { background:#fff; border:2px solid #22c55e; border-radius:14px; padding:32px 28px; text-align:center; }
.gb-final h2 { font-size:22px; font-weight:800; margin:0 0 10px; color:#111827; }
.gb-final p { font-size:13px; color:#6b7280; margin:0 0 20px; line-height:1.6; }
.gb-final .btn-alt { display:inline-flex; align-items:center; gap:8px; background:#22c55e; color:#fff; padding:13px 28px; border-radius:24px; font-size:14px; font-weight:700; text-decoration:none; box-shadow:0 6px 20px rgba(34,197,94,.3); }
.gb-final .btn-alt:hover { opacity:.92; color:#fff; text-decoration:none; transform:translateY(-1px); }
.gb-final .note { margin-top:22px; padding-top:18px; border-top:1px solid #f1f3f5; font-size:11px; color:#9ca3af; }

@media (max-width:640px) {
    .gb-hero { padding:32px 20px; }
    .gb-hero h1 { font-size:24px; }
    .gb-hero p { font-size:14px; }
}
</style>

<div class="gb-hero">
    <div class="gb-badge">사업자 없이 OK</div>
    <h1>사업자 없이도<br><span>신용카드 결제</span>를 받을 수 있어요</h1>
    <p>
        사업자등록 · 통신판매업신고 없이도 가능<br>
        개인 · 프리랜서 · 크리에이터 모두 가입 즉시 사용 가능
    </p>
    <a href="https://www.groble.im" target="_blank" rel="noopener" class="gb-cta">
        무료로 시작하기
        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
    <div>
        <div class="gb-url-box">
            <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            https://www.groble.im
        </div>
    </div>
</div>

<div class="gb-features">
    <div class="gb-feature">
        <div class="icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
        <h3>사업자등록 불필요</h3>
        <p>복잡한 서류 없이<br>개인도 바로 가입</p>
    </div>
    <div class="gb-feature">
        <div class="icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div>
        <h3>모든 카드 결제</h3>
        <p>국내 주요 카드사<br>전부 지원</p>
    </div>
    <div class="gb-feature">
        <div class="icon"><svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
        <h3>가입 즉시 사용</h3>
        <p>5분이면 결제 링크<br>바로 발급 가능</p>
    </div>
    <div class="gb-feature">
        <div class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <h3>익일 정산</h3>
        <p>결제 받은 금액을<br>다음날 계좌로</p>
    </div>
</div>

<div class="gb-compare">
    <h2>누가 쓰면 좋나요?</h2>
    <div class="gb-compare-row">
        <div class="k"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> 사업자등록 없는 개인</div>
        <div class="v">블로그·SNS·커뮤니티 운영자</div>
    </div>
    <div class="gb-compare-row">
        <div class="k"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> 프리랜서 / 1인 크리에이터</div>
        <div class="v">작가·강사·디자이너·개발자</div>
    </div>
    <div class="gb-compare-row">
        <div class="k"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> 소규모 전자책·강의·디지털 판매</div>
        <div class="v">PDF·영상·템플릿·코칭권</div>
    </div>
    <div class="gb-compare-row">
        <div class="k"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> 모임비·후원·멤버십 받기</div>
        <div class="v">동호회·팬클럽·구독서비스</div>
    </div>
</div>

<div class="gb-quotes">
    <h2>
        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        이런 점이 좋아요
    </h2>
    <div class="gb-quote">
        <strong>사업자 없이도</strong> 신용카드로 돈 받을 수 있어서 블로그 운영하며 디지털 제품 팔기 딱 좋아요.
    </div>
    <div class="gb-quote">
        수수료는 낮고, <strong>정산은 익일</strong>이라 현금 흐름 관리가 편해요.
    </div>
    <div class="gb-quote">
        계정만 만들면 바로 <strong>결제 링크</strong> 만들어서 카톡으로 보낼 수 있어요. 코딩 지식 0이어도 가능.
    </div>
</div>

<div class="gb-final">
    <h2>5분 만에 시작해보세요</h2>
    <p>
        가입비 · 월정액 없음<br>
        결제 발생할 때만 수수료
    </p>
    <a href="https://www.groble.im" target="_blank" rel="noopener" class="btn-alt">
        그로블 알아보기
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
    <div class="note">
        ※ 본 플러그인은 사이트에 아무런 영향을 주지 않습니다. 확인 후 비활성화하셔도 좋아요.
    </div>
</div>

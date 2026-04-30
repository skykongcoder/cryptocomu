<?php
/**
 * 사이트 분석 - 관리자 대시보드
 */
if (!Auth::check() || !Auth::isAdmin()) { echo '권한이 없습니다.'; return; }
$apiUrl = nb_url('admin/plugin/site-analytics/api');
$csrfToken = Auth::csrfToken();
?>
<style>
.sa{max-width:1400px;margin:0 auto;font-family:-apple-system,'Malgun Gothic',sans-serif}

/* 상단 바 */
.sa-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid #1a1a1a}
.sa-topbar h2{font-size:20px;font-weight:700;color:#1a1a1a;margin:0}
.sa-topbar-right{display:flex;align-items:center;gap:8px}
.sa-tbtn{padding:6px 14px;border:1px solid #d5d5d5;border-radius:4px;background:#fff;font-size:12px;color:#555;cursor:pointer;font-weight:500}
.sa-tbtn:hover{border-color:#333;color:#1a1a1a}
.sa-tbtn-red{color:#c0392b}
.sa-tbtn-red:hover{border-color:#c0392b;background:#fdf2f2}
.sa-period{display:flex;gap:0}
.sa-period button{padding:6px 16px;border:1px solid #d5d5d5;background:#fff;font-size:12px;color:#777;cursor:pointer;font-weight:500;margin-left:-1px}
.sa-period button:first-child{border-radius:4px 0 0 4px}
.sa-period button:last-child{border-radius:0 4px 4px 0}
.sa-period button.active{background:#1a1a1a;color:#fff;border-color:#1a1a1a;z-index:1;position:relative}

/* 실시간 배너 */
.sa-live{background:#1a1a1a;border-radius:6px;padding:16px 24px;margin-bottom:20px;display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.sa-live-dot{width:10px;height:10px;border-radius:50%;background:#2ecc71;animation:saPulse 2s infinite}
@keyframes saPulse{0%,100%{box-shadow:0 0 0 0 rgba(46,204,113,.5)}50%{box-shadow:0 0 0 8px rgba(46,204,113,0)}}
.sa-live-num{font-size:28px;font-weight:800;color:#fff;line-height:1}
.sa-live-lbl{font-size:12px;color:#999;margin-top:2px}
.sa-live-sep{width:1px;height:36px;background:#333}
.sa-live-stat{text-align:center}
.sa-live-stat-v{font-size:18px;font-weight:700;color:#fff}
.sa-live-stat-l{font-size:11px;color:#888}

/* 핵심 지표 */
.sa-kpi{display:grid;grid-template-columns:repeat(5,1fr);gap:0;margin-bottom:20px;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden}
@media(max-width:1024px){.sa-kpi{grid-template-columns:repeat(3,1fr)}}
@media(max-width:600px){.sa-kpi{grid-template-columns:repeat(2,1fr)}}
.sa-kpi-item{padding:20px 24px;background:#fff;border-right:1px solid #e5e5e5;position:relative}
.sa-kpi-item:last-child{border-right:0}
.sa-kpi-label{font-size:12px;color:#888;font-weight:500;margin-bottom:8px}
.sa-kpi-val{font-size:32px;font-weight:800;color:#1a1a1a;line-height:1}
.sa-kpi-sub{font-size:11px;color:#aaa;margin-top:6px}
.sa-kpi-up{color:#2ecc71}
.sa-kpi-down{color:#e74c3c}

/* 그리드 */
.sa-grid{display:grid;gap:16px;margin-bottom:16px}
.sa-g2{grid-template-columns:1fr 1fr}
.sa-g3{grid-template-columns:2fr 1fr}
.sa-g33{grid-template-columns:1fr 1fr 1fr}

/* 패널 */
.sa-box{background:#fff;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden}
.sa-box-head{padding:14px 18px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
.sa-box-title{font-size:13px;font-weight:700;color:#1a1a1a}
.sa-box-sub{font-size:11px;color:#aaa}
.sa-box-body{padding:16px 18px}

/* 차트 */
.sa-chart{position:relative;height:260px}
.sa-chart-sm{height:200px}

/* 테이블 */
.sa-tbl{width:100%;border-collapse:collapse}
.sa-tbl th{text-align:left;padding:8px 12px;font-size:11px;font-weight:600;color:#888;border-bottom:1px solid #eee;background:#fafafa}
.sa-tbl td{padding:9px 12px;font-size:13px;color:#333;border-bottom:1px solid #f5f5f5}
.sa-tbl tr:hover td{background:#fafbff}
.sa-tbl .num{font-weight:700;color:#1a1a1a;text-align:center;width:32px}
.sa-tbl .cnt{font-weight:700;color:#2c3e50;text-align:right}
.sa-tbl .kw{font-weight:600;color:#1a1a1a}
.sa-tbl .eng{color:#666;font-size:12px}
.sa-tbl .bar-cell{width:120px}

/* 순위 번호 */
.sa-n{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:3px;font-size:11px;font-weight:700}
.sa-n1{background:#1a1a1a;color:#fff}
.sa-n2{background:#555;color:#fff}
.sa-n3{background:#999;color:#fff}
.sa-nn{background:#eee;color:#888}

/* 바 */
.sa-bar{height:6px;border-radius:3px;background:#f0f0f0;overflow:hidden}
.sa-bar-in{height:100%;border-radius:3px;background:#3498db}

/* 뱃지 */
.sa-tag{display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600}
.sa-tag-search{background:#e8f4fd;color:#2980b9}
.sa-tag-social{background:#fce4ec;color:#c0392b}
.sa-tag-direct{background:#e8f5e9;color:#27ae60}
.sa-tag-link{background:#fff8e1;color:#f39c12}

/* 유입검색어 좌측 패널 */
.sa-kw-panel{display:flex;flex-direction:column;gap:0}
.sa-kw-row{display:flex;align-items:center;padding:8px 0;border-bottom:1px solid #f5f5f5;gap:10px}
.sa-kw-row:last-child{border:0}
.sa-kw-rank{width:22px;text-align:center;font-size:12px;font-weight:700;color:#2980b9}
.sa-kw-text{flex:1;font-size:13px;font-weight:500;color:#1a1a1a}
.sa-kw-cnt{font-size:13px;font-weight:700;color:#333}

/* 실시간 방문자 목록 */
.sa-vlist{max-height:280px;overflow-y:auto}
.sa-vrow{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #f5f5f5;font-size:12px}
.sa-vrow:last-child{border:0}
.sa-vdot{width:6px;height:6px;border-radius:50%;background:#2ecc71;flex-shrink:0}
.sa-vpage{flex:1;color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sa-vdev{padding:1px 6px;border-radius:3px;font-size:10px;font-weight:600;background:#f0f0f0;color:#777}
.sa-vtime{font-size:11px;color:#aaa}

/* 로딩 */
.sa-load{display:flex;align-items:center;justify-content:center;height:120px;color:#aaa;font-size:12px}

/* 반응형 */
@media(max-width:1024px){.sa-g2,.sa-g3,.sa-g33{grid-template-columns:1fr}}
@media(max-width:768px){.sa-kpi{grid-template-columns:1fr}.sa-kpi-item{border-right:0;border-bottom:1px solid #e5e5e5}.sa-kpi-item:last-child{border-bottom:0}}
</style>

<div class="sa">
    <!-- 상단 바 -->
    <div class="sa-topbar">
        <h2>사이트 분석</h2>
        <div class="sa-topbar-right">
            <button class="sa-tbtn" onclick="saRefresh()">새로고침</button>
            <button class="sa-tbtn sa-tbtn-red" onclick="saCleanup()">90일 이전 데이터 정리</button>
        </div>
    </div>

    <!-- 실시간 접속자 -->
    <div class="sa-live">
        <div class="sa-live-dot"></div>
        <div>
            <div class="sa-live-num" id="saLiveCount">-</div>
            <div class="sa-live-lbl">실시간 접속자</div>
        </div>
        <div class="sa-live-sep"></div>
        <div id="saLiveDetail" style="display:flex;gap:20px"></div>
    </div>

    <!-- 핵심 지표 -->
    <div class="sa-kpi">
        <div class="sa-kpi-item">
            <div class="sa-kpi-label">방문자수</div>
            <div class="sa-kpi-val" id="saKpiUV">-</div>
            <div class="sa-kpi-sub" id="saKpiUVSub"></div>
        </div>
        <div class="sa-kpi-item">
            <div class="sa-kpi-label">페이지뷰</div>
            <div class="sa-kpi-val" id="saKpiPV">-</div>
            <div class="sa-kpi-sub" id="saKpiPVSub"></div>
        </div>
        <div class="sa-kpi-item">
            <div class="sa-kpi-label">검색유입</div>
            <div class="sa-kpi-val" id="saKpiSearch">-</div>
            <div class="sa-kpi-sub" id="saKpiSearchSub"></div>
        </div>
        <div class="sa-kpi-item">
            <div class="sa-kpi-label">이탈률</div>
            <div class="sa-kpi-val" id="saKpiBounce">-</div>
            <div class="sa-kpi-sub">1페이지만 보고 이탈한 비율</div>
        </div>
        <div class="sa-kpi-item">
            <div class="sa-kpi-label">평균 체류시간</div>
            <div class="sa-kpi-val" id="saKpiDuration">-</div>
            <div class="sa-kpi-sub">2페이지 이상 방문 기준</div>
        </div>
    </div>

    <!-- 일별 추이 + 실시간 방문자 -->
    <div class="sa-grid sa-g3">
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">방문 추이</div>
                <div class="sa-period" id="saPeriodBtns">
                    <button class="active" onclick="saChangePeriod(this,7)">7일</button>
                    <button onclick="saChangePeriod(this,14)">14일</button>
                    <button onclick="saChangePeriod(this,30)">30일</button>
                </div>
            </div>
            <div class="sa-box-body"><div class="sa-chart"><canvas id="saChartDaily"></canvas></div></div>
        </div>
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">실시간 방문자</div>
                <div class="sa-box-sub" id="saLiveTime"></div>
            </div>
            <div class="sa-box-body">
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:5px;padding:9px 12px;font-size:11px;color:#92400e;margin-bottom:10px;line-height:1.6">
                    실제 방문자만 표시됩니다. /wp-includes/ 등 해킹 스캔 봇은 자동으로 제외됩니다.
                </div>
                <div class="sa-vlist" id="saVisitorList"><div class="sa-load">불러오는 중...</div></div>
            </div>
        </div>
    </div>

    <!-- 유입검색어 + 검색채널별 + 접속기기별 -->
    <div class="sa-grid sa-g33">
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">유입검색어</div>
                <div class="sa-box-sub">최근 7일</div>
            </div>
            <div class="sa-box-body">
                <div class="sa-kw-panel" id="saKwList"><div class="sa-load">불러오는 중...</div></div>
                <div id="saKwHidden" style="padding-top:8px;font-size:11px;color:#aaa;display:none"></div>
            </div>
        </div>
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">검색채널별 검색유입</div>
                <div class="sa-box-sub">최근 7일</div>
            </div>
            <div class="sa-box-body"><div class="sa-chart sa-chart-sm"><canvas id="saChartEngine"></canvas></div></div>
        </div>
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">접속기기별</div>
                <div class="sa-box-sub">오늘</div>
            </div>
            <div class="sa-box-body"><div class="sa-chart sa-chart-sm"><canvas id="saChartDevice"></canvas></div></div>
        </div>
    </div>

    <!-- 시간대별 + 유입경로 -->
    <div class="sa-grid sa-g2">
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">시간대별 방문</div>
                <div class="sa-box-sub">오늘</div>
            </div>
            <div class="sa-box-body"><div class="sa-chart"><canvas id="saChartHourly"></canvas></div></div>
        </div>
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">유입 경로 비율</div>
                <div class="sa-box-sub">오늘</div>
            </div>
            <div class="sa-box-body"><div class="sa-chart"><canvas id="saChartReferer"></canvas></div></div>
        </div>
    </div>

    <!-- 인기 페이지 + 유입 도메인 -->
    <div class="sa-grid sa-g2">
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">인기 페이지</div>
                <div class="sa-box-sub">최근 7일</div>
            </div>
            <div class="sa-box-body" style="padding:0">
                <table class="sa-tbl" id="saPageTbl">
                    <thead><tr><th style="width:32px">#</th><th>페이지</th><th style="width:80px;text-align:right">조회수</th><th style="width:80px;text-align:right">방문자</th></tr></thead>
                    <tbody><tr><td colspan="4"><div class="sa-load">불러오는 중...</div></td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">유입 도메인</div>
                <div class="sa-box-sub">최근 7일</div>
            </div>
            <div class="sa-box-body" style="padding:0">
                <table class="sa-tbl" id="saRefTbl">
                    <thead><tr><th style="width:32px">#</th><th>도메인</th><th style="width:60px">유형</th><th style="width:60px;text-align:right">횟수</th><th class="bar-cell" style="width:100px">비율</th></tr></thead>
                    <tbody><tr><td colspan="5"><div class="sa-load">불러오는 중...</div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 브라우저 -->
    <div class="sa-grid sa-g2">
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">브라우저</div>
                <div class="sa-box-sub">최근 7일</div>
            </div>
            <div class="sa-box-body"><div class="sa-chart sa-chart-sm"><canvas id="saChartBrowser"></canvas></div></div>
        </div>
        <div class="sa-box">
            <div class="sa-box-head">
                <div class="sa-box-title">OS</div>
                <div class="sa-box-sub">최근 7일</div>
            </div>
            <div class="sa-box-body"><div class="sa-chart sa-chart-sm"><canvas id="saChartOS"></canvas></div></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const SA='<?=$apiUrl?>',TK='<?=$csrfToken?>';
function saF(a,x){return fetch(SA,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({_token:TK,action:a},x||{}))}).then(r=>r.json())}

const EN={google:'Google',naver:'Naver',daum:'Daum',bing:'Bing',yahoo:'Yahoo',zum:'ZUM',nate:'Nate',duckduckgo:'DuckDuckGo'};
const BR={chrome:'Chrome',safari:'Safari',firefox:'Firefox',edge:'Edge',whale:'Whale',samsung:'Samsung',opera:'Opera',ie:'IE',other:'기타'};
const OS={windows:'Windows',mac:'macOS',android:'Android',ios:'iOS',linux:'Linux',other:'기타'};
const DV={pc:'PC',mobile:'모바일',tablet:'태블릿'};
const TY={search:'검색',social:'SNS',direct:'직접',link:'외부'};
const TC={search:'sa-tag-search',social:'sa-tag-social',direct:'sa-tag-direct',link:'sa-tag-link'};

// 색상 팔레트 (네이버 스타일)
const C1='#3498db',C2='#2ecc71',C3='#9b59b6',C4='#e67e22',C5='#e74c3c',C6='#1abc9c',C7='#34495e',C8='#95a5a6';
const PIE=['#3498db','#e74c3c','#f39c12','#2ecc71','#9b59b6','#1abc9c','#e67e22','#95a5a6'];

let charts={};
let currentPeriod=7;

function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}

// 실시간
function loadLive(){
    saF('realtime').then(r=>{
        if(!r.success)return;
        document.getElementById('saLiveCount').textContent=r.count.toLocaleString();
        var v=r.visitors||[],dc={pc:0,mobile:0,tablet:0};
        v.forEach(x=>{dc[x.device]=(dc[x.device]||0)+1});
        var h='';
        ['pc','mobile','tablet'].forEach(k=>{
            if(dc[k]>0)h+='<div class="sa-live-stat"><div class="sa-live-stat-v">'+dc[k]+'</div><div class="sa-live-stat-l">'+DV[k]+'</div></div>';
        });
        document.getElementById('saLiveDetail').innerHTML=h;
        document.getElementById('saLiveTime').textContent=new Date().toLocaleTimeString('ko-KR');

        var lh='';
        v.slice(0,30).forEach(x=>{
            var t=x.created_at?x.created_at.substring(11,16):'';
            lh+='<div class="sa-vrow"><div class="sa-vdot"></div><div class="sa-vpage">'+esc(x.page_url)+'</div><div class="sa-vdev">'+DV[x.device]+'</div><div class="sa-vtime">'+t+'</div></div>';
        });
        document.getElementById('saVisitorList').innerHTML=lh||'<div style="padding:20px;text-align:center;color:#aaa;font-size:12px">접속자가 없습니다</div>';
    });
}

// 오늘 요약
function loadKPI(){
    saF('today').then(r=>{
        if(!r.success)return;
        var d=r.data;
        document.getElementById('saKpiUV').textContent=Number(d.unique_visitors||0).toLocaleString();
        document.getElementById('saKpiPV').textContent=Number(d.page_views||0).toLocaleString();
        document.getElementById('saKpiSearch').textContent=Number(d.from_search||0).toLocaleString();

        var newV=Number(d.new_visitors||0),retV=Number(d.unique_visitors||0)-newV;
        document.getElementById('saKpiUVSub').innerHTML='신규 '+newV+' / 재방문 '+Math.max(retV,0);

        var mob=Number(d.mobile||0),pc=Number(d.pc||0),tab=Number(d.tablet||0);
        document.getElementById('saKpiPVSub').innerHTML='PC '+pc+' / 모바일 '+mob+' / 태블릿 '+tab;

        var social=Number(d.from_social||0),direct=Number(d.from_direct||0);
        document.getElementById('saKpiSearchSub').innerHTML='SNS '+social+' / 직접 '+direct;

        // 이탈률
        var bounce=parseFloat(d.bounce_rate||0);
        var bounceEl=document.getElementById('saKpiBounce');
        bounceEl.textContent=bounce+'%';
        bounceEl.style.color=bounce>=70?'#e74c3c':bounce>=50?'#e67e22':'#2ecc71';

        // 평균 체류시간
        var sec=parseInt(d.avg_duration||0);
        var durStr=sec===0?'측정중':sec<60?sec+'초':Math.floor(sec/60)+'분 '+(sec%60)+'초';
        document.getElementById('saKpiDuration').textContent=durStr;
    });
}

// 일별 차트
function loadDaily(days){
    saF('daily_trend',{days:days||currentPeriod}).then(r=>{
        if(!r.success)return;
        var labels=r.data.map(x=>x.stat_date.substring(5));
        var uv=r.data.map(x=>+x.unique_visitors);
        var pv=r.data.map(x=>+x.page_views);
        var sc=r.data.map(x=>+(x.search_count||0));

        if(charts.daily)charts.daily.destroy();
        charts.daily=new Chart(document.getElementById('saChartDaily'),{
            type:'line',
            data:{labels:labels,datasets:[
                {label:'방문자수',data:uv,borderColor:C2,backgroundColor:'rgba(46,204,113,.06)',fill:true,tension:.3,borderWidth:2,pointRadius:0,pointHoverRadius:4},
                {label:'페이지뷰',data:pv,borderColor:C3,backgroundColor:'transparent',tension:.3,borderWidth:2,pointRadius:0,pointHoverRadius:4},
                {label:'검색유입',data:sc,borderColor:C1,backgroundColor:'transparent',tension:.3,borderWidth:1.5,pointRadius:0,pointHoverRadius:4,borderDash:[4,3]}
            ]},
            options:{responsive:true,maintainAspectRatio:false,
                plugins:{legend:{position:'top',align:'end',labels:{usePointStyle:true,pointStyle:'line',padding:14,font:{size:11,weight:'600'},boxWidth:20}}},
                scales:{x:{grid:{display:false},ticks:{font:{size:10},maxRotation:0}},y:{beginAtZero:true,grid:{color:'#f5f5f5'},ticks:{font:{size:10}}}},
                interaction:{intersect:false,mode:'index'}
            }
        });
    });
}

function saChangePeriod(btn,days){
    document.querySelectorAll('#saPeriodBtns button').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    currentPeriod=days;
    loadDaily(days);
}

// 시간대별
function loadHourly(){
    saF('hourly').then(r=>{
        if(!r.success)return;
        var map={};r.data.forEach(x=>{map[x.h]=x});
        var hrs=Array.from({length:24},(_,i)=>i);
        var labels=hrs.map(h=>h+'시');
        var pv=hrs.map(h=>+((map[h]||{}).cnt||0));
        var uv=hrs.map(h=>+((map[h]||{}).uv||0));

        if(charts.hourly)charts.hourly.destroy();
        charts.hourly=new Chart(document.getElementById('saChartHourly'),{
            type:'bar',
            data:{labels:labels,datasets:[
                {label:'페이지뷰',data:pv,backgroundColor:'rgba(52,152,219,.6)',borderRadius:3,borderSkipped:false,barPercentage:.7},
                {label:'방문자',data:uv,backgroundColor:'rgba(46,204,113,.6)',borderRadius:3,borderSkipped:false,barPercentage:.7}
            ]},
            options:{responsive:true,maintainAspectRatio:false,
                plugins:{legend:{position:'top',align:'end',labels:{usePointStyle:true,padding:12,font:{size:11,weight:'600'}}}},
                scales:{x:{grid:{display:false},ticks:{font:{size:9},maxRotation:0}},y:{beginAtZero:true,grid:{color:'#f5f5f5'},ticks:{font:{size:10}}}}
            }
        });
    });
}

// 유입경로 도넛
function loadRefPie(){
    saF('today').then(r=>{
        if(!r.success)return;
        var d=r.data;
        var data=[+d.from_search||0,+d.from_social||0,+d.from_direct||0,+d.from_link||0];
        var total=data.reduce((a,b)=>a+b,0);
        if(!total){data=[0,0,1,0];total=1}

        if(charts.refPie)charts.refPie.destroy();
        charts.refPie=new Chart(document.getElementById('saChartReferer'),{
            type:'doughnut',
            data:{labels:['검색','SNS','직접접속','외부링크'],datasets:[{data:data,backgroundColor:[C1,C5,C2,C4],borderWidth:0,hoverOffset:4}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'60%',
                plugins:{
                    legend:{position:'right',labels:{usePointStyle:true,pointStyle:'circle',padding:10,font:{size:11,weight:'600'}}},
                    tooltip:{callbacks:{label:function(ctx){var pct=(ctx.parsed/total*100).toFixed(1);return ctx.label+': '+ctx.parsed+'건 ('+pct+'%)'}}}
                }
            }
        });
    });
}

// 기기별
function loadDevice(){
    saF('today').then(r=>{
        if(!r.success)return;
        var d=r.data;
        var data=[+d.pc||0,+d.mobile||0,+d.tablet||0];
        var total=data.reduce((a,b)=>a+b,0)||1;

        if(charts.device)charts.device.destroy();
        charts.device=new Chart(document.getElementById('saChartDevice'),{
            type:'doughnut',
            data:{labels:['PC','모바일','태블릿'],datasets:[{data:data.every(v=>!v)?[1,0,0]:data,backgroundColor:[C1,C5,C4],borderWidth:0,hoverOffset:4}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'60%',
                plugins:{
                    legend:{position:'right',labels:{usePointStyle:true,pointStyle:'circle',padding:10,font:{size:11,weight:'600'}}},
                    tooltip:{callbacks:{label:function(ctx){var pct=(ctx.parsed/total*100).toFixed(1);return ctx.label+': '+ctx.parsed+'건 ('+pct+'%)'}}}
                }
            }
        });
    });
}

// 검색엔진
function loadEngine(){
    saF('engines').then(r=>{
        if(!r.success)return;
        var labels=r.data.map(x=>EN[x.search_engine]||x.search_engine);
        var data=r.data.map(x=>+x.cnt);
        var total=data.reduce((a,b)=>a+b,0)||1;

        if(charts.engine)charts.engine.destroy();
        charts.engine=new Chart(document.getElementById('saChartEngine'),{
            type:'doughnut',
            data:{labels:labels.length?labels:['데이터 없음'],datasets:[{data:data.length?data:[1],backgroundColor:data.length?PIE.slice(0,data.length):['#eee'],borderWidth:0,hoverOffset:4}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'60%',
                plugins:{
                    legend:{position:'right',labels:{usePointStyle:true,pointStyle:'circle',padding:10,font:{size:11,weight:'600'}}},
                    tooltip:{callbacks:{label:function(ctx){var pct=(ctx.parsed/total*100).toFixed(1);return ctx.label+': '+ctx.parsed+'건 ('+pct+'%)'}}}
                }
            }
        });
    });
}

// 브라우저
function loadBrowser(){
    saF('browsers').then(r=>{
        if(!r.success)return;
        var labels=r.data.map(x=>BR[x.browser]||x.browser);
        var data=r.data.map(x=>+x.cnt);
        if(charts.browser)charts.browser.destroy();
        charts.browser=new Chart(document.getElementById('saChartBrowser'),{
            type:'doughnut',
            data:{labels:labels.length?labels:['데이터 없음'],datasets:[{data:data.length?data:[1],backgroundColor:data.length?PIE.slice(0,data.length):['#eee'],borderWidth:0,hoverOffset:4}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'60%',
                plugins:{legend:{position:'right',labels:{usePointStyle:true,pointStyle:'circle',padding:10,font:{size:11,weight:'600'}}}}}
        });
    });
}

// OS
function loadOS(){
    saF('os_stats').then(r=>{
        if(!r.success)return;
        var labels=r.data.map(x=>OS[x.os]||x.os);
        var data=r.data.map(x=>+x.cnt);
        if(charts.os)charts.os.destroy();
        charts.os=new Chart(document.getElementById('saChartOS'),{
            type:'doughnut',
            data:{labels:labels.length?labels:['데이터 없음'],datasets:[{data:data.length?data:[1],backgroundColor:data.length?PIE.slice(0,data.length):['#eee'],borderWidth:0,hoverOffset:4}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'60%',
                plugins:{legend:{position:'right',labels:{usePointStyle:true,pointStyle:'circle',padding:10,font:{size:11,weight:'600'}}}}}
        });
    });
}

// 키워드
function loadKW(){
    saF('keywords').then(r=>{
        if(!r.success)return;
        var el=document.getElementById('saKwList');
        if(!r.data.length){el.innerHTML='<div style="padding:20px;text-align:center;color:#aaa;font-size:12px">검색 키워드 데이터가 없습니다</div>';return}
        var h='';
        r.data.slice(0,15).forEach((x,i)=>{
            h+='<div class="sa-kw-row"><div class="sa-kw-rank">'+(i+1)+'</div><div class="sa-kw-text">'+esc(x.search_keyword)+'</div><div class="sa-kw-cnt">'+Number(x.cnt).toLocaleString()+'</div></div>';
        });
        el.innerHTML=h;
        if(r.hidden>0){var hel=document.getElementById('saKwHidden');hel.style.display='block';hel.textContent='* 키워드 비공개: '+Number(r.hidden).toLocaleString()+'건'}
    });
}

// 인기 페이지
function loadPages(){
    saF('pages').then(r=>{
        if(!r.success)return;
        var tb=document.querySelector('#saPageTbl tbody');
        if(!r.data.length){tb.innerHTML='<tr><td colspan="4" style="text-align:center;padding:24px;color:#aaa;font-size:12px">데이터가 없습니다</td></tr>';return}
        var h='';
        r.data.forEach((x,i)=>{
            var nc=i<1?'sa-n1':i<2?'sa-n2':i<3?'sa-n3':'sa-nn';
            h+='<tr><td class="num"><span class="sa-n '+nc+'">'+(i+1)+'</span></td><td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(x.page_url)+'">'+esc(x.page_url)+'</td><td class="cnt">'+Number(x.views).toLocaleString()+'</td><td class="cnt">'+Number(x.visitors).toLocaleString()+'</td></tr>';
        });
        tb.innerHTML=h;
    });
}

// 유입 도메인
function loadRefs(){
    saF('referers').then(r=>{
        if(!r.success)return;
        var tb=document.querySelector('#saRefTbl tbody');
        if(!r.data.length){tb.innerHTML='<tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa;font-size:12px">데이터가 없습니다</td></tr>';return}
        var total=r.data.reduce((s,x)=>s+(+x.cnt),0);
        var h='';
        r.data.forEach((x,i)=>{
            var nc=i<1?'sa-n1':i<2?'sa-n2':i<3?'sa-n3':'sa-nn';
            var pct=total?(+x.cnt/total*100).toFixed(1):0;
            var tc=TC[x.referer_type]||'sa-tag-link';
            var tl=TY[x.referer_type]||x.referer_type;
            h+='<tr><td class="num"><span class="sa-n '+nc+'">'+(i+1)+'</span></td><td style="font-weight:600">'+esc(x.referer_domain)+'</td><td><span class="sa-tag '+tc+'">'+tl+'</span></td><td class="cnt">'+Number(x.cnt).toLocaleString()+'</td><td><div class="sa-bar"><div class="sa-bar-in" style="width:'+pct+'%"></div></div><span style="font-size:11px;color:#888;margin-left:6px">'+pct+'%</span></td></tr>';
        });
        tb.innerHTML=h;
    });
}

function saRefresh(){loadLive();loadKPI();loadDaily();loadHourly();loadRefPie();loadDevice();loadEngine();loadBrowser();loadOS();loadKW();loadPages();loadRefs()}
function saCleanup(){if(!confirm('90일 이전 상세 데이터를 삭제합니다.\n일별 집계는 유지됩니다.\n\n계속하시겠습니까?'))return;saF('cleanup').then(r=>{alert(r.message||'완료')})}

document.addEventListener('DOMContentLoaded',function(){saRefresh();setInterval(loadLive,30000)});
</script>

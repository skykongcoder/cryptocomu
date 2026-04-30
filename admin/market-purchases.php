<?php
/**
 * 배포버전 관리자 - 내 마켓 구매 내역
 *
 * nuribd.com 에서 내가 구매한 플러그인 목록을 site_token 으로 조회합니다.
 * - 체크박스 일괄 삭제 (nuribd.com 서버의 내 기록을 삭제)
 * - 페이지네이션
 * - 취소 버튼 없음 (환불은 배포사이트에서 할 수 없음 — 운영사 문의)
 */

$prefix = DB::getPrefix();
$siteToken = nb_setting('market_site_token', '');

adminHeader('market-purchases');
?>

<div class="page-header">
    <h1>내 마켓 구매 내역</h1>
    <span style="font-size:13px;color:#94a3b8">nuribd.com 에서 구매한 플러그인 기록</span>
</div>

<?php if (empty($siteToken)): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#94a3b8">
    사이트 토큰이 없습니다. 설치가 정상적으로 완료되지 않았을 수 있습니다.
</div></div>
<?php adminFooter(); return; endif; ?>

<!-- 일괄 작업 툴바 -->
<div style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
        <input type="checkbox" id="mpCheckAll"> 전체선택
    </label>
    <button type="button" class="btn btn-sm btn-danger" id="mpBulkDelete">선택 삭제</button>
    <span id="mpSelectedCount" style="font-size:12px;color:#94a3b8"></span>
    <span style="font-size:11px;color:#94a3b8;margin-left:auto">※ 삭제는 기록 정리용입니다. 환불이 필요하면 운영사에 문의하세요.</span>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width:36px"></th>
                <th style="width:50px">번호</th>
                <th>플러그인</th>
                <th style="width:80px">버전</th>
                <th style="width:160px">주문번호</th>
                <th style="width:100px;text-align:right">금액</th>
                <th style="width:130px">결제일</th>
                <th style="width:80px">설치</th>
            </tr>
        </thead>
        <tbody id="mpTbody">
            <tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8">구매 내역을 불러오는 중...</td></tr>
        </tbody>
    </table>
</div>

<div id="mpPagination" style="display:flex;justify-content:center;gap:4px;margin-top:16px;flex-wrap:wrap"></div>

<script>
var NB_MARKET = {
    marketBase: 'https://nuribd.com',
    siteToken: <?= json_encode($siteToken) ?>,
    currentPage: 1,
    perPage: 20,
    total: 0
};

function mpLoad(page){
    NB_MARKET.currentPage = page || 1;
    var url = NB_MARKET.marketBase + '/api/v1/market/my-purchases'
            + '?site_token=' + encodeURIComponent(NB_MARKET.siteToken)
            + '&page=' + NB_MARKET.currentPage
            + '&per=' + NB_MARKET.perPage;

    document.getElementById('mpTbody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8">불러오는 중...</td></tr>';

    fetch(url)
    .then(function(r){return r.json()})
    .then(function(res){
        if (!res.success) {
            document.getElementById('mpTbody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#dc2626">'+(res.message||'조회 실패')+'</td></tr>';
            return;
        }
        NB_MARKET.total = res.total || 0;
        var list = res.purchases || [];

        if (list.length === 0) {
            document.getElementById('mpTbody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8">구매 내역이 없습니다.</td></tr>';
            document.getElementById('mpPagination').innerHTML = '';
            return;
        }

        var html = '';
        list.forEach(function(p){
            var paidDate = p.paid_at ? new Date(p.paid_at.replace(' ','T')).toLocaleDateString('ko-KR') + ' ' + new Date(p.paid_at.replace(' ','T')).toLocaleTimeString('ko-KR',{hour:'2-digit',minute:'2-digit'}) : '-';
            html += '<tr>';
            html += '<td><input type="checkbox" class="mpRowCheck" value="'+p.purchase_id+'"></td>';
            html += '<td>'+p.purchase_id+'</td>';
            html += '<td>';
            if (p.thumbnail) html += '<img src="'+p.thumbnail+'" style="width:32px;height:32px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px">';
            html += (p.plugin_name || '(삭제됨)');
            if (p.author) html += '<br><small style="color:#94a3b8">by '+p.author+'</small>';
            html += '</td>';
            html += '<td>'+(p.version||'-')+'</td>';
            html += '<td style="font-family:monospace;font-size:11px;color:#64748b">'+p.mbr_ref_no+'</td>';
            html += '<td style="text-align:right;font-weight:600">'+Number(p.amount).toLocaleString()+'원</td>';
            html += '<td style="font-size:11px;color:#64748b">'+paidDate+'</td>';
            var dlUrl = NB_MARKET.marketBase + '/api/v1/market/download-licensed/' + p.plugin_id
                      + '?site_token=' + encodeURIComponent(NB_MARKET.siteToken);
            var pluginName = (p.plugin_name||'').replace(/'/g,"\\'");
            html += '<td><button class="btn btn-sm btn-primary" onclick="mpInstall(\''+dlUrl+'\',\''+pluginName+'\')">설치</button></td>';
            html += '</tr>';
        });
        document.getElementById('mpTbody').innerHTML = html;

        // 이벤트 바인딩
        document.querySelectorAll('.mpRowCheck').forEach(function(cb){
            cb.addEventListener('change', mpUpdateCount);
        });
        mpUpdateCount();
        mpRenderPagination();
    })
    .catch(function(e){
        document.getElementById('mpTbody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#dc2626">서버 연결 실패: '+e.message+'</td></tr>';
    });
}

function mpRenderPagination(){
    var pages = Math.ceil(NB_MARKET.total / NB_MARKET.perPage);
    var cur = NB_MARKET.currentPage;
    if (pages <= 1) { document.getElementById('mpPagination').innerHTML = ''; return; }
    var html = '';
    if (cur > 1) {
        html += '<button class="btn btn-sm" onclick="mpLoad(1)">«</button>';
        html += '<button class="btn btn-sm" onclick="mpLoad('+(cur-1)+')">‹</button>';
    }
    var start = Math.max(1, cur - 4);
    var end = Math.min(pages, cur + 4);
    for (var i = start; i <= end; i++) {
        html += '<button class="btn btn-sm '+(i===cur?'btn-primary':'')+'" onclick="mpLoad('+i+')">'+i+'</button>';
    }
    if (cur < pages) {
        html += '<button class="btn btn-sm" onclick="mpLoad('+(cur+1)+')">›</button>';
        html += '<button class="btn btn-sm" onclick="mpLoad('+pages+')">»</button>';
    }
    html += '<span style="margin-left:8px;font-size:12px;color:#94a3b8;align-self:center">'+cur+' / '+pages+' 페이지 (총 '+NB_MARKET.total+'건)</span>';
    document.getElementById('mpPagination').innerHTML = html;
}

// 전체 선택
document.getElementById('mpCheckAll').addEventListener('change', function(){
    var checked = this.checked;
    document.querySelectorAll('.mpRowCheck').forEach(function(cb){ cb.checked = checked; });
    mpUpdateCount();
});
function mpUpdateCount(){
    var n = document.querySelectorAll('.mpRowCheck:checked').length;
    document.getElementById('mpSelectedCount').textContent = n > 0 ? (n + '개 선택됨') : '';
}

// 일괄 삭제 (nuribd.com API 호출)
document.getElementById('mpBulkDelete').addEventListener('click', function(){
    var ids = Array.from(document.querySelectorAll('.mpRowCheck:checked')).map(function(cb){return cb.value;});
    if (ids.length === 0) { alert('삭제할 항목을 선택하세요.'); return; }
    if (!confirm(ids.length + '개 구매 기록을 삭제할까요?\n\n⚠ 주의: 이 작업은 기록만 삭제하며 환불되지 않습니다.\n삭제하면 마켓 탭에서 [설치] 버튼이 사라지고, 다시 설치하려면 재구매가 필요합니다.')) return;

    var fd = new FormData();
    fd.append('site_token', NB_MARKET.siteToken);
    ids.forEach(function(id){ fd.append('ids[]', id); });

    fetch(NB_MARKET.marketBase + '/api/v1/market/my-purchases/delete', {
        method: 'POST',
        body: fd
    })
    .then(function(r){return r.json()})
    .then(function(res){
        if (res.success) {
            alert(res.deleted + '건 삭제 완료');
            mpLoad(NB_MARKET.currentPage);
        } else {
            alert(res.message || '삭제 실패');
        }
    })
    .catch(function(e){ alert('서버 연결 실패: ' + e.message); });
});

// 구매한 플러그인 설치 (서버사이드 다운로드 → plugin_install 액션)
function mpInstall(downloadUrl, name){
    if(!confirm('"'+name+'" 플러그인을 설치할까요?')) return;
    var fd = new FormData();
    fd.append('action','plugin_install');
    fd.append('url', downloadUrl);
    fd.append('market_name', name || '');
    fetch('?page=plugins', {
        method:'POST',
        body: fd,
        credentials:'same-origin',
        headers:{'X-Requested-With':'XMLHttpRequest'}
    })
    .then(function(r){return r.json()})
    .then(function(res){
        alert(res.message || '설치 완료!');
        if(res.success) location.href='?page=plugins&tab=installed';
    })
    .catch(function(e){ alert('설치 실패:\n\n'+(e.message||e)); });
}

// 최초 로드
mpLoad(1);
</script>

<?php adminFooter(); ?>

<?php
/**
 * 자동 색인 요청 - 관리자 설정
 */
if (!Auth::check() || !Auth::isAdmin()) { echo '권한이 없습니다.'; return; }
$_aiCfg = file_exists(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
$apiUrl = nb_url('admin/plugin/auto-indexing/api');
$csrfToken = Auth::csrfToken();
?>
<style>
.ai-wrap{max-width:960px;margin:0 auto;font-family:-apple-system,'Malgun Gothic',sans-serif}
.ai-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid #1a1a1a}
.ai-topbar h2{font-size:20px;font-weight:700;color:#1a1a1a;margin:0}

/* 오늘 현황 */
.ai-status{display:flex;gap:16px;margin-bottom:20px}
.ai-status-card{flex:1;background:#fff;border:1px solid #e5e5e5;border-radius:6px;padding:18px 20px}
.ai-status-label{font-size:12px;color:#888;font-weight:500;margin-bottom:6px}
.ai-status-val{font-size:26px;font-weight:800;color:#1a1a1a}
.ai-status-sub{font-size:11px;color:#aaa;margin-top:4px}

/* 박스 */
.ai-box{background:#fff;border:1px solid #e5e5e5;border-radius:6px;margin-bottom:16px}
.ai-box-head{padding:14px 18px;border-bottom:1px solid #eee;font-size:14px;font-weight:700;color:#1a1a1a}
.ai-box-body{padding:18px}

/* 폼 */
.ai-row{display:flex;align-items:center;margin-bottom:14px;gap:12px}
.ai-row:last-child{margin-bottom:0}
.ai-label{width:160px;font-size:13px;font-weight:600;color:#333;flex-shrink:0}
.ai-label small{display:block;font-weight:400;color:#999;font-size:11px;margin-top:2px}
.ai-input{flex:1;padding:8px 12px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;color:#333;background:#fff}
.ai-input:focus{outline:none;border-color:#333}
.ai-input-file{font-size:12px}
.ai-textarea{width:100%;min-height:80px;resize:vertical}
.ai-toggle{position:relative;width:42px;height:22px;cursor:pointer}
.ai-toggle input{display:none}
.ai-toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:11px;transition:.2s}
.ai-toggle-slider::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:2px;top:2px;transition:.2s}
.ai-toggle input:checked+.ai-toggle-slider{background:#1a1a1a}
.ai-toggle input:checked+.ai-toggle-slider::before{left:22px}

/* 버튼 */
.ai-btn{padding:8px 20px;border-radius:4px;border:1px solid #d5d5d5;background:#fff;font-size:13px;font-weight:600;color:#555;cursor:pointer}
.ai-btn:hover{border-color:#333;color:#1a1a1a}
.ai-btn-primary{background:#1a1a1a;color:#fff;border-color:#1a1a1a}
.ai-btn-primary:hover{background:#333}
.ai-btn-sm{padding:5px 12px;font-size:12px}

/* 수동 요청 */
.ai-manual{display:flex;gap:8px}
.ai-manual input{flex:1}

/* 로그 테이블 */
.ai-tbl{width:100%;border-collapse:collapse}
.ai-tbl th{text-align:left;padding:8px 12px;font-size:11px;font-weight:600;color:#888;border-bottom:1px solid #eee;background:#fafafa}
.ai-tbl td{padding:8px 12px;font-size:12px;color:#333;border-bottom:1px solid #f5f5f5}
.ai-tbl tr:hover td{background:#fafbff}
.ai-tag{display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:600}
.ai-tag-success{background:#e8f5e9;color:#2e7d32}
.ai-tag-fail{background:#ffebee;color:#c62828}
.ai-tag-google{background:#e8f0fe;color:#1967d2}
.ai-tag-indexnow{background:#e0f2f1;color:#00695c}

/* 페이지네이션 */
.ai-pager{display:flex;justify-content:center;gap:4px;margin-top:12px}
.ai-pager button{padding:4px 10px;border:1px solid #ddd;border-radius:3px;background:#fff;font-size:12px;cursor:pointer}
.ai-pager button.active{background:#1a1a1a;color:#fff;border-color:#1a1a1a}

/* 가이드 */
.ai-guide{background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:14px 16px;font-size:12px;line-height:1.8;color:#555}
.ai-guide strong{color:#1a1a1a}
.ai-guide code{background:#eee;padding:1px 6px;border-radius:3px;font-size:11px}

@media(max-width:768px){
    .ai-row{flex-direction:column;align-items:flex-start}
    .ai-label{width:100%}
    .ai-status{flex-direction:column}
}
</style>

<div class="ai-wrap">
    <div class="ai-topbar">
        <h2>자동 색인 요청</h2>
        <button class="ai-btn ai-btn-primary" onclick="aiSave()">설정 저장</button>
    </div>

    <!-- 오늘 현황 -->
    <div class="ai-status">
        <div class="ai-status-card">
            <div class="ai-status-label">오늘 색인 요청</div>
            <div class="ai-status-val" id="aiTodayCount">-</div>
            <div class="ai-status-sub">일일 한도: <span id="aiDailyLimit">-</span></div>
        </div>
        <div class="ai-status-card">
            <div class="ai-status-label">IndexNow 상태</div>
            <div class="ai-status-val" id="aiInStatus" style="font-size:16px">-</div>
        </div>
        <div class="ai-status-card">
            <div class="ai-status-label">Google Indexing API</div>
            <div class="ai-status-val" id="aiGoogleStatus" style="font-size:16px">-</div>
        </div>
    </div>

    <!-- 기본 설정 -->
    <div class="ai-box">
        <div class="ai-box-head">기본 설정</div>
        <div class="ai-box-body">
            <div class="ai-row">
                <div class="ai-label">플러그인 활성화</div>
                <label class="ai-toggle"><input type="checkbox" id="aiEnabled" <?= !empty($_aiCfg['enabled']) ? 'checked' : '' ?>><span class="ai-toggle-slider"></span></label>
            </div>
            <div class="ai-row">
                <div class="ai-label">글 작성 시 자동 요청</div>
                <label class="ai-toggle"><input type="checkbox" id="aiOnCreate" <?= ($_aiCfg['auto_on_create'] ?? true) ? 'checked' : '' ?>><span class="ai-toggle-slider"></span></label>
            </div>
            <div class="ai-row">
                <div class="ai-label">글 수정 시 자동 요청</div>
                <label class="ai-toggle"><input type="checkbox" id="aiOnUpdate" <?= ($_aiCfg['auto_on_update'] ?? true) ? 'checked' : '' ?>><span class="ai-toggle-slider"></span></label>
            </div>
            <div class="ai-row">
                <div class="ai-label">일일 요청 한도</div>
                <input type="number" class="ai-input" id="aiDailyLimitInput" value="<?= (int)($_aiCfg['daily_limit'] ?? 200) ?>" min="1" max="10000" style="max-width:120px">
            </div>
            <div class="ai-row">
                <div class="ai-label">제외 게시판<small>게시판 코드, 쉼표 구분</small></div>
                <input type="text" class="ai-input" id="aiExclude" value="<?= htmlspecialchars($_aiCfg['exclude_boards'] ?? '') ?>" placeholder="예: test, draft">
            </div>
        </div>
    </div>

    <!-- IndexNow 설정 -->
    <div class="ai-box">
        <div class="ai-box-head">IndexNow (네이버, Bing, Yandex)</div>
        <div class="ai-box-body">
            <div class="ai-row">
                <div class="ai-label">IndexNow 활성화</div>
                <label class="ai-toggle"><input type="checkbox" id="aiIndexNow" <?= ($_aiCfg['indexnow_enabled'] ?? true) ? 'checked' : '' ?>><span class="ai-toggle-slider"></span></label>
            </div>
            <div class="ai-row">
                <div class="ai-label">IndexNow API 키<small>영숫자 8자 이상</small></div>
                <input type="text" class="ai-input" id="aiIndexNowKey" value="<?= htmlspecialchars($_aiCfg['indexnow_key'] ?? '') ?>" placeholder="예: a1b2c3d4e5f6g7h8">
                <button class="ai-btn ai-btn-sm" onclick="aiGenKey()">자동 생성</button>
            </div>
            <div class="ai-guide" style="margin-top:8px">
                <strong>IndexNow 설정 방법:</strong><br>
                1. 위에서 API 키를 입력하거나 자동 생성 버튼 클릭<br>
                2. 설정 저장 후 <code>https://사이트주소/{키값}.txt</code> 파일이 자동 서빙됨<br>
                3. 별도 설정 없이 네이버, Bing, Yandex에 동시 전송됨<br>
                4. 네이버 서치어드바이저에서 IndexNow 사용 설정 권장
            </div>
        </div>
    </div>

    <!-- Google Indexing API 설정 -->
    <div class="ai-box">
        <div class="ai-box-head">Google Indexing API</div>
        <div class="ai-box-body">
            <div class="ai-row">
                <div class="ai-label">Google API 활성화</div>
                <label class="ai-toggle"><input type="checkbox" id="aiGoogle" <?= !empty($_aiCfg['google_enabled']) ? 'checked' : '' ?>><span class="ai-toggle-slider"></span></label>
            </div>
            <div class="ai-row">
                <div class="ai-label">서비스 계정 JSON 키</div>
                <div style="flex:1">
                    <?php
                    $currentKeyFile = $_aiCfg['google_json_key'] ?? '';
                    $keyExists = $currentKeyFile && file_exists(__DIR__ . '/' . $currentKeyFile);
                    if ($keyExists):
                        // JSON에서 이메일 추출
                        $keyData = json_decode(file_get_contents(__DIR__ . '/' . $currentKeyFile), true);
                        $clientEmail = $keyData['client_email'] ?? '';
                    ?>
                        <div style="padding:8px 12px;background:#f0f7f0;border:1px solid #c8e6c9;border-radius:4px;margin-bottom:8px">
                            <div style="font-size:13px;font-weight:600;color:#2e7d32">JSON 키 등록됨</div>
                            <div style="font-size:11px;color:#555;margin-top:4px">파일: <?= htmlspecialchars($currentKeyFile) ?></div>
                            <?php if ($clientEmail): ?>
                            <div style="font-size:11px;color:#555;margin-top:2px">서비스 계정: <code style="background:#e8f5e9;padding:1px 6px;border-radius:3px;user-select:all"><?= htmlspecialchars($clientEmail) ?></code></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding:8px 12px;background:#fff8e1;border:1px solid #ffe082;border-radius:4px;margin-bottom:8px;font-size:12px;color:#f57f17">JSON 키가 등록되지 않았습니다</div>
                    <?php endif; ?>
                    <input type="file" id="aiGoogleFile" accept=".json" class="ai-input" style="padding:6px">
                    <button class="ai-btn ai-btn-sm" onclick="aiUploadKey()" style="margin-top:6px">업로드</button>
                    <?php if ($keyExists): ?>
                    <button class="ai-btn ai-btn-sm" onclick="aiDeleteKey()" style="margin-top:6px;color:#c62828">삭제</button>
                    <?php endif; ?>
                </div>
            </div>
            <input type="hidden" id="aiGoogleKey" value="<?= htmlspecialchars($currentKeyFile) ?>">
            <div class="ai-guide" style="margin-top:8px">
                <strong>Google Indexing API 설정 방법:</strong><br>
                1. <a href="https://btg1.net/bbs/board.php?bo_table=tip1&wr_id=255" target="_blank" style="color:#1967d2">JSON 키 발급 방법 보기 (상세 설명서)</a><br>
                2. 위에서 다운받은 JSON 파일을 업로드<br>
                3. <a href="https://search.google.com/search-console" target="_blank" style="color:#1967d2">Google Search Console</a> > 설정 > 사용자 및 권한 > 위 서비스 계정 이메일을 <strong>소유자</strong>로 추가
            </div>
        </div>
    </div>

    <!-- 수동 색인 요청 -->
    <div class="ai-box">
        <div class="ai-box-head">수동 색인 요청</div>
        <div class="ai-box-body">
            <div class="ai-manual">
                <input type="text" class="ai-input" id="aiManualUrl" placeholder="https://사이트주소/board/notice/1">
                <button class="ai-btn ai-btn-primary" onclick="aiManual()">색인 요청</button>
            </div>
            <div id="aiManualResult" style="margin-top:10px;font-size:12px;color:#555"></div>
        </div>
    </div>

    <!-- 요청 이력 -->
    <div class="ai-box">
        <div class="ai-box-head">요청 이력</div>
        <div class="ai-box-body" style="padding:0">
            <table class="ai-tbl">
                <thead><tr><th style="width:50px">#</th><th>URL</th><th style="width:80px">서비스</th><th style="width:60px">결과</th><th style="width:130px">일시</th></tr></thead>
                <tbody id="aiLogBody"><tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa;font-size:12px">불러오는 중...</td></tr></tbody>
            </table>
            <div class="ai-pager" id="aiPager"></div>
        </div>
    </div>
</div>

<script>
var API='<?=$apiUrl?>',TK='<?=$csrfToken?>';
function aiF(a,x){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({_token:TK,action:a},x||{}))}).then(r=>r.json())}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}

function aiSave(){
    aiF('save',{
        enabled:document.getElementById('aiEnabled').checked,
        google_enabled:document.getElementById('aiGoogle').checked,
        google_json_key:document.getElementById('aiGoogleKey').value,
        indexnow_enabled:document.getElementById('aiIndexNow').checked,
        indexnow_key:document.getElementById('aiIndexNowKey').value,
        auto_on_create:document.getElementById('aiOnCreate').checked,
        auto_on_update:document.getElementById('aiOnUpdate').checked,
        daily_limit:document.getElementById('aiDailyLimitInput').value,
        exclude_boards:document.getElementById('aiExclude').value
    }).then(r=>{
        if(r.success)alert('설정이 저장되었습니다.');
        else alert(r.message||'오류');
    });
}

function aiGenKey(){
    var chars='abcdefghijklmnopqrstuvwxyz0123456789';
    var key='';for(var i=0;i<32;i++)key+=chars[Math.floor(Math.random()*chars.length)];
    document.getElementById('aiIndexNowKey').value=key;
}

function aiManual(){
    var url=document.getElementById('aiManualUrl').value.trim();
    if(!url){alert('URL을 입력하세요');return}
    var el=document.getElementById('aiManualResult');
    el.textContent='요청 중...';
    aiF('manual',{url:url}).then(r=>{
        if(!r.success){el.textContent='오류: '+(r.message||'실패');return}
        var msg=[];
        if(r.results.indexnow)msg.push('IndexNow: '+(r.results.indexnow.success?'성공':'실패')+' ('+esc(r.results.indexnow.response)+')');
        if(r.results.google)msg.push('Google: '+(r.results.google.success?'성공':'실패')+' ('+esc(r.results.google.response)+')');
        el.innerHTML=msg.join('<br>')||'활성화된 서비스가 없습니다';
        loadLogs(1);
        loadToday();
    });
}

function loadToday(){
    aiF('today_count').then(r=>{
        if(!r.success)return;
        document.getElementById('aiTodayCount').textContent=r.count.toLocaleString();
        document.getElementById('aiDailyLimit').textContent=r.limit.toLocaleString();
    });
    // 상태 표시
    var inEnabled=document.getElementById('aiIndexNow').checked&&document.getElementById('aiIndexNowKey').value;
    var gEnabled=document.getElementById('aiGoogle').checked&&document.getElementById('aiGoogleKey').value;
    document.getElementById('aiInStatus').textContent=inEnabled?'활성':'비활성';
    document.getElementById('aiInStatus').style.color=inEnabled?'#2e7d32':'#999';
    document.getElementById('aiGoogleStatus').textContent=gEnabled?'활성':'비활성';
    document.getElementById('aiGoogleStatus').style.color=gEnabled?'#1967d2':'#999';
}

function loadLogs(page){
    aiF('logs',{page:page||1}).then(r=>{
        if(!r.success)return;
        var tb=document.getElementById('aiLogBody');
        if(!r.data.length){tb.innerHTML='<tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa;font-size:12px">요청 이력이 없습니다</td></tr>';return}
        var h='';
        r.data.forEach(function(x){
            var svcCls=x.service==='google'?'ai-tag-google':'ai-tag-indexnow';
            var svcName=x.service==='google'?'Google':'IndexNow';
            var stCls=x.status==='success'?'ai-tag-success':'ai-tag-fail';
            var stName=x.status==='success'?'성공':'실패';
            h+='<tr><td style="color:#aaa;font-size:11px">'+x.id+'</td><td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(x.url)+'">'+esc(x.url)+'</td><td><span class="ai-tag '+svcCls+'">'+svcName+'</span></td><td><span class="ai-tag '+stCls+'">'+stName+'</span></td><td style="font-size:11px;color:#888">'+x.created_at+'</td></tr>';
        });
        tb.innerHTML=h;

        // 페이지네이션
        var pg=document.getElementById('aiPager');
        if(r.pages<=1){pg.innerHTML='';return}
        var ph='';
        for(var i=1;i<=r.pages;i++){
            ph+='<button'+(i===r.page?' class="active"':'')+' onclick="loadLogs('+i+')">'+i+'</button>';
        }
        pg.innerHTML=ph;
    });
}

function aiUploadKey(){
    var fileInput=document.getElementById('aiGoogleFile');
    if(!fileInput.files.length){alert('JSON 파일을 선택하세요');return}
    var fd=new FormData();
    fd.append('google_key_file',fileInput.files[0]);
    fd.append('action','upload_key');
    fd.append('_token',TK);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(r=>{
        if(r.success){
            alert('업로드 완료\n서비스 계정: '+r.email+'\n\nGoogle Search Console에서 이 이메일을 소유자로 추가하세요.');
            document.getElementById('aiGoogleKey').value=r.filename;
            location.reload();
        } else {
            alert(r.message||'업로드 실패');
        }
    });
}

function aiDeleteKey(){
    if(!confirm('구글 JSON 키 파일을 삭제하시겠습니까?'))return;
    aiF('delete_key').then(r=>{
        if(r.success){alert('삭제되었습니다');location.reload()}
        else alert(r.message||'오류');
    });
}

document.addEventListener('DOMContentLoaded',function(){loadToday();loadLogs(1)});
</script>

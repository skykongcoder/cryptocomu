<?php
/**
 * NuriBoard - 글쓰기/수정
 */
$editing = $editing ?? false;
$pageTitle = $editing ? '글 수정' : '글쓰기';
SEO::setTitle($pageTitle . ' - ' . $board['title']);
$categories = array_filter(array_map('trim', explode(',', $board['categories'] ?? '')));
require dirname(__DIR__) . '/header.php';
?>

<div class="board-wrap"><article class="write-page">
    <div class="board-header">
        <h1><?= nb_e($board['title']) ?> - <?= $pageTitle ?></h1>
    </div>

    <form method="post" enctype="multipart/form-data" class="write-form"
          action="<?= $editing ? nb_url("board/{$board['board_id']}/{$post['id']}/edit") : nb_url("board/{$board['board_id']}/write") ?>">
        <?= Auth::csrfField() ?>

        <!-- 옵션 -->
        <div class="write-options">
            <?php if (Auth::isAdmin()): ?>
                <label><input type="checkbox" name="is_notice" value="1" <?= ($post['is_notice'] ?? 0) ? 'checked' : '' ?>> 공지</label>
            <?php endif; ?>
            <label><input type="checkbox" name="is_secret" value="1" <?= ($post['is_secret'] ?? 0) ? 'checked' : '' ?>> 비밀글</label>
        </div>

        <!-- 말머리 -->
        <?php if (!empty($categories)): ?>
        <div class="form-group">
            <label for="category">말머리</label>
            <select id="category" name="category" class="write-select">
                <option value="">선택 안함</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= nb_e($cat) ?>" <?= ($post['category'] ?? '') === $cat ? 'selected' : '' ?>><?= nb_e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- 제목 -->
        <div class="form-group">
            <label>제목</label>
            <input type="text" id="title" name="title" value="<?= nb_e($post['title'] ?? '') ?>" required placeholder="제목을 입력하세요" maxlength="255">
            <input type="hidden" name="title_color" id="titleColor" value="<?= nb_e($post['title_color'] ?? '') ?>">
            <input type="hidden" name="title_bg" id="titleBg" value="<?= nb_e($post['title_bg'] ?? '') ?>">
            <div class="title-toolbar">
                <span class="tb-label">제목 서식</span>
                <select id="titleSize" onchange="document.getElementById('title').style.fontSize=this.value">
                    <option value="">크기</option>
                    <option value="12px">12</option>
                    <option value="14px">14</option>
                    <option value="15px" selected>15</option>
                    <option value="18px">18</option>
                    <option value="20px">20</option>
                    <option value="24px">24</option>
                </select>
                <div class="color-picker-wrap">
                    <button type="button" class="tbtn" onclick="togglePicker('fg')" title="글자색">
                        A <span class="cbar" id="fgBar" style="background:<?= nb_e($post['title_color'] ?? '#333') ?>"></span>
                    </button>
                    <div class="cpanel" id="fgPanel"></div>
                </div>
                <div class="color-picker-wrap">
                    <button type="button" class="tbtn" onclick="togglePicker('bg')" title="배경색">
                        BG <span class="cbar" id="bgBar" style="background:<?= nb_e($post['title_bg'] ?? '#fff') ?>"></span>
                    </button>
                    <div class="cpanel" id="bgPanel"></div>
                </div>
                <div class="color-picker-wrap">
                    <button type="button" class="tbtn" id="titleBoldBtn" onclick="var cb=document.getElementById('titleBold');cb.value=cb.value==='1'?'0':'1';document.getElementById('title').style.fontWeight=cb.value==='1'?'bold':'';this.style.background=cb.value==='1'?'#eff6ff':'#fff';document.getElementById('boldBar').style.background=cb.value==='1'?'#2563eb':'#fff'" title="굵게" style="<?= ($post['title_bold'] ?? 0) ? 'background:#eff6ff' : '' ?>">B <span class="cbar" id="boldBar" style="background:<?= ($post['title_bold'] ?? 0) ? '#2563eb' : '#fff' ?>"></span></button>
                    <input type="hidden" name="title_bold" id="titleBold" value="<?= ($post['title_bold'] ?? 0) ? '1' : '0' ?>">
                </div>
            </div>
        </div>

        <!-- 내용 (Summernote) -->
        <div class="form-group">
            <label>내용</label>
            <textarea id="content" name="content"><?= nb_e($post['content'] ?? '') ?></textarea>
        </div>

        <!-- 파일 첨부 -->
        <div class="form-group">
            <label>파일 첨부 <small style="font-weight:normal;color:#999">(최대 <?= nb_setting('upload_max_size', '10') ?>MB, 0/5개)</small></label>
            <input type="file" name="files[]" multiple class="file-input">
            <?php if (($board['allow_paid_file'] ?? 0)): ?>
            <div style="margin-top:8px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px">
                <label style="font-size:13px;font-weight:600;color:#92400e">💰 다운로드 포인트 설정</label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
                    <input type="number" name="download_point" value="<?= nb_e($post['_download_point'] ?? '0') ?>" min="0" style="width:100px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">
                    <span style="font-size:12px;color:#92400e">P (0이면 무료)</span>
                </div>
                <small style="color:#94a3b8;display:block;margin-top:4px">설정한 포인트만큼 다운로드 시 차감됩니다. 판매 수익은 작성자에게 지급됩니다.</small>
            </div>
            <?php endif; ?>
            <?php if ($editing && !empty($attachments)): ?>
            <div class="attached-files">
                <?php foreach ($attachments as $att): ?>
                <div class="attached-file" id="att-<?= $att['id'] ?>">
                    <span><?= nb_e($att['orig_name']) ?> (<?= Upload::formatSize($att['file_size']) ?>)</span>
                    <?php if (($board['allow_paid_file'] ?? 0)): ?>
                    <input type="number" name="att_point[<?= $att['id'] ?>]" value="<?= (int)($att['download_point'] ?? 0) ?>" min="0" style="width:70px;padding:3px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px" title="다운로드 포인트">
                    <span style="font-size:11px;color:#94a3b8">P</span>
                    <?php elseif (($att['download_point'] ?? 0) > 0): ?>
                    <span style="color:#f59e0b;font-size:12px;font-weight:600"><?= $att['download_point'] ?>P</span>
                    <?php endif; ?>
                    <button type="button" class="btn-link delete" onclick="deleteAttachment(<?= $att['id'] ?>)">삭제</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 태그 -->
        <div class="form-group">
            <label>태그 <small style="font-weight:normal;color:#999">(쉼표로 구분)</small></label>
            <input type="text" name="tags" value="<?= nb_e($post['tags'] ?? '') ?>" placeholder="예: 맛집, 서울, 추천">
        </div>

        <!-- 링크 -->
        <div class="form-row" style="gap:12px">
            <div class="form-group">
                <label>링크 #1</label>
                <input type="text" name="link1" value="<?= nb_e($post['link1'] ?? '') ?>" placeholder="https://...">
                <?php if (($board['board_type'] ?? 'normal') === 'gallery'): ?>
                <small style="color:var(--primary)">📷 이미지 게시판: 여기에 URL을 입력하면 메인 갤러리에서 이미지 클릭 시 해당 사이트로 이동합니다. (광고/배너 링크용) 비워두면 게시글로 이동합니다.</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>링크 #2</label>
                <input type="text" name="link2" value="<?= nb_e($post['link2'] ?? '') ?>" placeholder="https://...">
            </div>
        </div>

        <!-- 버튼 -->
        <div class="form-actions">
            <a href="<?= nb_url("board/{$board['board_id']}") ?>" class="btn">취소</a>
            <button type="submit" class="btn btn-primary btn-lg"><?= $editing ? '수정하기' : '작성하기' ?></button>
        </div>
    </form>
</article></div>

<!-- Summernote CDN -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-ko-KR.min.js"></script>

<style>
/* 에디터 */
.note-editor{border:1px solid var(--border)!important;border-radius:8px!important;overflow:visible!important}
.note-toolbar{background:#f8fafc!important;border-bottom:1px solid var(--border)!important}
.note-editable{direction:ltr!important;text-align:left;unicode-bidi:embed!important;min-height:300px}
.note-editable p,.note-editable div{direction:ltr!important}
.note-editable iframe{display:block;max-width:100%;margin:10px 0}

/* 에디터 모달 (링크삽입 등) */
.note-modal-backdrop{z-index:9998!important}
.note-modal{z-index:9999!important}
.note-modal .note-modal-content{margin:12% auto!important;max-width:480px!important;width:90%!important;border-radius:12px!important;box-shadow:0 20px 60px rgba(0,0,0,.15)!important;background:#fff!important;position:relative!important}
.note-modal-footer{text-align:right;padding:0 35px 20px 20px}
.note-modal .checkbox:last-child{display:none!important}

/* 글쓰기 옵션 */
.write-options{display:flex;gap:16px;margin-bottom:12px}
.write-options label{display:inline-flex;align-items:center;gap:4px;font-size:13px;cursor:pointer}
.write-select{width:200px;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:14px}

/* 제목 서식 툴바 */
.title-toolbar{display:flex;align-items:center;gap:6px;margin-top:6px;padding:6px 10px;background:#f8fafc;border:1px solid var(--border);border-radius:6px;flex-wrap:wrap}
.tb-label{font-size:11px;font-weight:600;color:#94a3b8;margin-right:4px}
.title-toolbar select{padding:3px 6px;border:1px solid #ddd;border-radius:4px;font-size:12px;background:#fff}
.tbtn{display:inline-flex;flex-direction:column;align-items:center;padding:3px 8px;border:1px solid var(--border);border-radius:4px;background:#fff;cursor:pointer;font-size:11px;font-weight:700;line-height:1;gap:2px}
.tbtn:hover{border-color:var(--primary);background:#f0f4ff}
.cbar{display:block;width:20px;height:4px;border-radius:1px}
.tbtn-check{display:inline-flex;align-items:center;gap:2px;font-size:11px;cursor:pointer;padding:2px 6px;border:1px solid var(--border);border-radius:4px;background:#fff}
.tbtn-check input{margin:0}

/* 색상 팔레트 */
.color-picker-wrap{position:relative}
.cpanel{display:none;position:absolute;top:36px;left:0;background:#fff;border:1px solid var(--border);border-radius:10px;padding:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:200;width:256px}
.cpanel.open{display:block}
.cp-title{font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;text-align:center}
.cp-grid{display:grid;grid-template-columns:repeat(13,1fr);gap:1px;margin-bottom:8px}
.cp-cell{width:16px;height:16px;border-radius:2px;cursor:pointer;border:1px solid transparent}
.cp-cell:hover{border-color:#333;transform:scale(1.3);z-index:1;position:relative}
.cp-row{display:flex;gap:6px;align-items:center;font-size:12px;border-top:1px solid #f0f0f0;padding-top:8px}
.cp-row input{width:70px;padding:3px 6px;border:1px solid #ddd;border-radius:4px;font-size:12px;font-family:monospace}
.cp-row button{padding:3px 8px;border:none;border-radius:4px;cursor:pointer;font-size:11px}
.cp-apply{background:var(--primary);color:#fff}
.cp-reset{background:#f1f5f9;color:#475569}

/* 에디터 색상 팝업 (body에 직접 붙임) */
#editorColorPopup{display:none;position:fixed;z-index:99999;width:340px;background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px;box-shadow:0 8px 30px rgba(0,0,0,.15);pointer-events:auto}
#editorColorPopup.open{display:block}
#editorColorPopup *{pointer-events:auto}
#editorColorPopup .cp-grid{display:grid;grid-template-columns:repeat(13,1fr);gap:2px;margin-bottom:8px}
#editorColorPopup .cp-cell{width:20px;height:20px;border-radius:2px;cursor:pointer;border:1px solid transparent}
#editorColorPopup .cp-cell:hover{border-color:#333;transform:scale(1.3);z-index:1;position:relative}
#editorColorPopup .cp-title{font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;text-align:center}
#editorColorPopup .cp-row{display:flex;gap:6px;align-items:center;font-size:12px;border-top:1px solid #f0f0f0;padding-top:8px}
#editorColorPopup .cp-row input{width:70px;padding:3px 6px;border:1px solid #ddd;border-radius:4px;font-size:12px;font-family:monospace}
#editorColorPopup .cp-row button{padding:3px 8px;border:none;border-radius:4px;cursor:pointer;font-size:11px}

/* 파일 */
.file-input{padding:8px 0}
.attached-files{margin-top:8px;display:flex;flex-direction:column;gap:4px}
.attached-file{display:flex;align-items:center;justify-content:space-between;padding:6px 12px;background:#f8fafc;border-radius:6px;font-size:13px}
</style>

<script>
// ===== 색상 팔레트 =====
var COLORS=[
    '#000000','#333333','#555555','#808080','#aaaaaa','#cccccc','#e0e0e0','#f0f0f0','#ffffff','#ff0000','#ff6600','#ff9900','#ffcc00',
    '#ffff00','#ccff00','#99ff00','#00ff00','#00ff66','#00ffcc','#00ffff','#00ccff','#0099ff','#0066ff','#0000ff','#6600ff',
    '#9900ff','#cc00ff','#ff00ff','#ff0099','#ff0066','#cc0000','#993300','#996600','#999900','#339900','#009933','#009999',
    '#003399','#000099','#330099','#660066','#990066','#ff3333','#ff6633','#ff9933','#ffcc33','#ffff33','#ccff33','#66ff33',
    '#33ff99','#33ffcc','#33ffff','#33ccff','#3399ff','#3366ff','#3333ff','#6633ff','#9933ff','#cc33ff','#ff33ff','#ff33cc',
    '#ff3399','#cc3333','#ff6666','#ff9966','#ffcc66','#ffff66','#ccff66','#99ff66','#66ff99','#66ffcc','#66ffff','#66ccff',
    '#6699ff','#6666ff','#9966ff','#cc66ff','#ff66ff','#ff66cc','#ff6699','#ff9999','#ffcc99','#ffcccc','#ffffcc','#ccffcc',
    '#ccffff','#ccccff','#ffccff','#660000','#663300','#666600','#336600','#006633','#006666','#003366','#000066','#330066',
    '#660033','#330000','#333300','#003300','#003333','#000033','#330033'
];

function buildPalette(id, label) {
    var h='<div class="cp-title">'+label+'</div><div class="cp-grid">';
    for(var i=0;i<COLORS.length;i++) h+='<div class="cp-cell" style="background:'+COLORS[i]+'" onclick="pickColor(\''+id+'\',\''+COLORS[i]+'\')"></div>';
    h+='</div><div class="cp-row"><input id="'+id+'Hex" value="#000000" maxlength="7"><button class="cp-apply" onclick="pickColor(\''+id+'\',document.getElementById(\''+id+'Hex\').value)">적용</button><button class="cp-reset" onclick="resetColor(\''+id+'\')">초기화</button></div>';
    document.getElementById(id+'Panel').innerHTML=h;
}
buildPalette('fg','글자색');
buildPalette('bg','배경색');

function togglePicker(id) {
    document.querySelectorAll('.cpanel').forEach(function(p){if(p.id!==id+'Panel')p.classList.remove('open')});
    document.getElementById(id+'Panel').classList.toggle('open');
}
function pickColor(id,c) {
    if(!c||c.charAt(0)!=='#') return;
    var t=document.getElementById('title');
    if(id==='fg'){ document.getElementById('titleColor').value=c; document.getElementById('fgBar').style.background=c; t.style.color=c; }
    else { document.getElementById('titleBg').value=c; document.getElementById('bgBar').style.background=c; t.style.backgroundColor=c; }
    document.getElementById(id+'Panel').classList.remove('open');
}
function resetColor(id) {
    var t=document.getElementById('title');
    if(id==='fg'){ document.getElementById('titleColor').value=''; document.getElementById('fgBar').style.background='#333'; t.style.color=''; }
    else { document.getElementById('titleBg').value=''; document.getElementById('bgBar').style.background='#fff'; t.style.backgroundColor=''; }
    document.getElementById(id+'Panel').classList.remove('open');
}

// 초기 제목 스타일 적용
(function(){
    var t=document.getElementById('title');
    var fc=document.getElementById('titleColor').value;
    var bc=document.getElementById('titleBg').value;
    if(fc) t.style.color=fc;
    if(bc) t.style.backgroundColor=bc;
    if(document.getElementById('titleBold').checked) t.style.fontWeight='bold';
})();

// 외부 클릭 시 팔레트 닫기
document.addEventListener('click',function(e){
    if(!e.target.closest('.color-picker-wrap')) document.querySelectorAll('.cpanel').forEach(function(p){p.classList.remove('open')});
});

// ===== Summernote 에디터 =====
$(function(){
    $('#content').summernote({
        lang:'ko-KR',
        height:280,
        placeholder:'내용을 입력하세요',
        codeviewFilter:false,
        codeviewFilterRegex:'',
        codeviewIframeFilter:false,
        popover:{image:[['custom',[]]]},
        callbacks:{
            onPaste:function(e){
                var buf=((e.originalEvent||e).clipboardData||window.clipboardData);
                if(!buf)return;
                // 이미지가 있으면 이미지 붙여넣기 허용
                var hasImage=false;
                if(buf.items){for(var i=0;i<buf.items.length;i++){if(buf.items[i].type.indexOf('image')!==-1){hasImage=true;break}}}
                if(hasImage)return;
                e.preventDefault();
                var text=buf.getData('text/plain')||'';
                // 줄바꿈을 <br>로 변환
                text=text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                text=text.replace(/\n/g,'<br>');
                document.execCommand('insertHTML',false,text);
            }
        },
        toolbar:[
            ['font',['bold']],
            ['fontsize',['fontsize']],
            ['color',['nbForecolor']],
            ['para',['paragraph']],
            ['insert',['link','picture','hr']],
            ['view',['codeview','nbCodeBlock']]
        ],
        buttons:{
            nbForecolor:function(context){
                var ui=$.summernote.ui;
                var button=ui.button({
                    contents:'<span style="font-size:14px;font-weight:700">A</span><span class="cbar" id="editorColorBar" style="display:block;width:20px;height:4px;border-radius:1px;background:#000;margin:1px auto 0"></span>',
                    tooltip:'글자색',
                    click:function(){
                        var popup=document.getElementById('editorColorPopup');
                        if(popup.classList.contains('open')){popup.classList.remove('open');return;}
                        var btnEl=document.querySelector('.note-btn[data-tooltip="글자색"]');
                        if(!btnEl) btnEl=document.getElementById('editorColorBar').parentElement;
                        var rect=btnEl.getBoundingClientRect();
                        popup.style.top=(rect.bottom+4)+'px';
                        popup.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-350))+'px';
                        popup.classList.add('open');
                        popup._ctx=context;
                    }
                });
                return button.render();
            },
            nbCodeBlock:function(context){
                var ui=$.summernote.ui;
                var button=ui.button({
                    contents:'<span style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;font-weight:700">&lt;/&gt;</span>',
                    tooltip:'코드 블록',
                    click:function(){
                        var block='<pre class="nb-code-block"><code>여기에 코드를 입력하세요</code></pre><p><br></p>';
                        $('.note-editable').focus();
                        document.execCommand('insertHTML',false,block);
                    }
                });
                return button.render();
            }
        },
        callbacks:{
            onInit:function(){
                $('.note-editable').attr('dir','ltr').css({'text-align':'left','direction':'ltr'});
            },
            onImageUpload:function(files){
                for(var i=0;i<files.length;i++){
                    var data=new FormData();
                    data.append('file',files[i]);
                    $.ajax({
                        url:'<?= nb_url("upload/editor") ?>',
                        method:'POST',
                        data:data,
                        processData:false,
                        contentType:false,
                        success:function(res){
                            if(res.url) $('#content').summernote('insertImage','<?= nb_url("") ?>'+res.url);
                        }
                    });
                }
            }
        }
    });

    // 폼 전송 시 에디터 내용 동기화 (iframe 보존)
    $('form.write-form').on('submit',function(){
        $('#content').val($('.note-editable').html());
    });
});

// ===== 첨부파일 삭제 =====
<?php if ($editing): ?>
function deleteAttachment(id){
    if(!confirm('첨부파일을 삭제하시겠습니까?'))return;
    fetch('<?= nb_url("upload/delete") ?>',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id='+id+'&_token=<?= Auth::csrfToken() ?>'
    }).then(function(r){return r.json()}).then(function(res){
        if(res.success) document.getElementById('att-'+id).remove();
    });
}
<?php endif; ?>
</script>

<!-- 이미지 편집 패널 (Summernote 독립) -->
<div id="nbImgPanel" style="display:none;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 16px;background:#f8fafc;border:1px solid #2563eb;border-radius:10px;margin:8px 0;box-shadow:0 4px 12px rgba(37,99,235,.15)">
    <div style="display:flex;gap:4px;align-items:center">
        <span style="font-size:12px;color:#64748b;margin-right:4px">크기</span>
        <button type="button" onclick="nbImgSize('100%')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:12px;cursor:pointer">100%</button>
        <button type="button" onclick="nbImgSize('50%')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:12px;cursor:pointer">50%</button>
        <button type="button" onclick="nbImgSize('25%')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:12px;cursor:pointer">25%</button>
    </div>
    <div style="display:flex;gap:4px;align-items:center">
        <span style="font-size:12px;color:#64748b;margin-right:4px">정렬</span>
        <button type="button" onclick="nbImgAlign('left')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:12px;cursor:pointer">왼쪽</button>
        <button type="button" onclick="nbImgAlign('center')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:12px;cursor:pointer">가운데</button>
        <button type="button" onclick="nbImgAlign('right')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:12px;cursor:pointer">오른쪽</button>
    </div>
    <div style="display:flex;gap:6px;align-items:center;flex:1;min-width:200px">
        <span style="font-size:12px;color:#64748b;white-space:nowrap">이미지 설명</span>
        <input type="text" id="nbImgAlt" oninput="nbImgAltSet(this.value)" placeholder="검색노출에 도움이 됩니다" style="flex:1;padding:5px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;outline:none">
    </div>
    <button type="button" onclick="nbImgDel()" style="padding:4px 10px;border:1px solid #fecaca;border-radius:6px;background:#fff;color:#dc2626;font-size:12px;cursor:pointer">삭제</button>
</div>
<script>
var _nbImg=null;
var _nbPanel=document.getElementById('nbImgPanel');

// 에디터 영역에 캡처 단계 mousedown
document.addEventListener('mousedown',function(e){
    var img=e.target;
    if(img.tagName==='IMG'&&img.closest('.note-editable')){
        e.preventDefault();e.stopImmediatePropagation();
        _nbImg=img;
        // 에디터 내 다른 이미지 outline 제거
        document.querySelectorAll('.note-editable img').forEach(function(i){i.style.outline='';});
        img.style.outline='3px solid #2563eb';
        // 패널 표시
        _nbPanel.style.display='flex';
        // 에디터 바로 아래에 이동
        var editor=document.querySelector('.note-editor');
        if(editor) editor.parentNode.insertBefore(_nbPanel,editor.nextSibling);
        document.getElementById('nbImgAlt').value=img.getAttribute('alt')||'';
        // 패널로 스크롤
        _nbPanel.scrollIntoView({behavior:'smooth',block:'nearest'});
    }
},true);

// 패널 밖 클릭 시 숨김
document.addEventListener('click',function(e){
    if(!_nbPanel.contains(e.target)&&!(e.target.tagName==='IMG'&&e.target.closest('.note-editable'))){
        _nbPanel.style.display='none';
        if(_nbImg){_nbImg.style.outline='';_nbImg=null;}
    }
});
// 패널 클릭 시 닫히지 않게
_nbPanel.addEventListener('mousedown',function(e){e.stopPropagation();});
_nbPanel.addEventListener('click',function(e){e.stopPropagation();});

function nbImgSize(s){if(_nbImg){_nbImg.style.width=s;_nbImg.style.height='auto';}}
function nbImgAlign(a){
    if(!_nbImg)return;
    _nbImg.style.float='';_nbImg.style.margin='';_nbImg.style.display='';
    var p=_nbImg.parentElement;if(p)p.style.textAlign='';
    if(a==='left'){_nbImg.style.float='left';_nbImg.style.margin='0 16px 10px 0';}
    else if(a==='right'){_nbImg.style.float='right';_nbImg.style.margin='0 0 10px 16px';}
    else{_nbImg.style.display='block';_nbImg.style.margin='0 auto';if(p)p.style.textAlign='center';}
}
function nbImgAltSet(v){if(_nbImg)_nbImg.setAttribute('alt',v);}
function nbImgDel(){
    if(_nbImg&&confirm('이미지를 삭제하시겠습니까?')){
        _nbImg.remove();_nbPanel.style.display='none';_nbImg=null;
    }
}
</script>

<!-- 에디터 색상 팝업 (body에 직접) -->
<div id="editorColorPopup">
    <div class="cp-title">글자색</div>
    <div class="cp-grid" id="editorColorGrid"></div>
    <div class="cp-row">
        <input type="text" id="editorHex" value="#000000" maxlength="7">
        <button class="cp-apply" onclick="applyEditorColor()">적용</button>
        <button class="cp-reset" onclick="resetEditorColor()">초기화</button>
    </div>
</div>
<script>
(function(){
    var grid=document.getElementById('editorColorGrid');
    var h='';
    for(var i=0;i<COLORS.length;i++){
        h+='<div class="cp-cell" style="background:'+COLORS[i]+'" data-color="'+COLORS[i]+'"></div>';
    }
    grid.innerHTML=h;
    // mousedown에서 포커스 뺏김 방지
    document.getElementById('editorColorPopup').addEventListener('mousedown',function(e){
        e.preventDefault();
    });
    grid.addEventListener('click',function(e){
        var t=e.target;
        if(t.classList.contains('cp-cell')){
            var c=t.dataset.color;
            var popup=document.getElementById('editorColorPopup');
            if(popup._ctx) _nbApplyColor(popup._ctx, c);
            document.getElementById('editorColorBar').style.background=c;
            document.getElementById('editorHex').value=c;
            popup.classList.remove('open');
        }
    });
    document.addEventListener('click',function(e){
        var popup=document.getElementById('editorColorPopup');
        if(!popup.contains(e.target)&&!e.target.closest('#editorColorBar')&&!e.target.closest('.note-btn')){
            popup.classList.remove('open');
        }
    });
})();

// styleWithCSS=true → <font> 대신 <span style="color:..."> 생성 (저장 후에도 색상 유지)
function _nbApplyColor(ctx, color){
    if(!ctx||!color) return;
    ctx.invoke('editor.restoreRange');
    ctx.invoke('editor.focus');
    document.execCommand('styleWithCSS', false, true);
    document.execCommand('foreColor', false, color);
}

function applyEditorColor(){
    var c=document.getElementById('editorHex').value;
    var popup=document.getElementById('editorColorPopup');
    if(c&&c.charAt(0)==='#'&&popup._ctx){
        _nbApplyColor(popup._ctx, c);
        document.getElementById('editorColorBar').style.background=c;
    }
    popup.classList.remove('open');
}
function resetEditorColor(){
    var popup=document.getElementById('editorColorPopup');
    if(popup._ctx) _nbApplyColor(popup._ctx,'#000000');
    document.getElementById('editorColorBar').style.background='#000';
    document.getElementById('editorHex').value='#000000';
    popup.classList.remove('open');
}


<?php require dirname(__DIR__) . '/footer.php'; ?>

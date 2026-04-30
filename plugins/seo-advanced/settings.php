<?php
/**
 * SEO 자동 최적화 - 관리자 설정 페이지
 */

$_seoFile = __DIR__ . '/config.json';
$_seoCfg = file_exists($_seoFile) ? json_decode(file_get_contents($_seoFile), true) : [];
$_seoCfg = array_merge([
    'enabled'=>'1','title_suffix'=>'1','auto_description'=>'1','description_length'=>'150',
    'default_description'=>'','default_keywords'=>'','auto_og'=>'1','auto_og_image'=>'1',
    'og_image'=>'','twitter_card'=>'1','auto_canonical'=>'1','schema_article'=>'1',
    'schema_breadcrumb'=>'1','schema_comment'=>'1','schema_website'=>'1','auto_sitemap'=>'1',
    'sitemap_ping_google'=>'0','robots_txt'=>'','auto_image_alt'=>'1','lazy_loading'=>'1',
    'related_posts'=>'1','related_posts_count'=>'5','noindex_login'=>'1','noindex_register'=>'1',
    'noindex_search'=>'1','noindex_mypage'=>'1','noindex_boards'=>'','google_verification'=>'',
    'naver_verification'=>'',
], $_seoCfg);

$_seoUpDir = NB_ROOT . '/uploads/seo';
if (!is_dir($_seoUpDir)) @mkdir($_seoUpDir, 0755, true);

// 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_save'])) {
    $checkboxes = ['enabled','title_suffix','auto_description','auto_og','auto_og_image','twitter_card',
        'auto_canonical','schema_article','schema_breadcrumb','schema_comment','schema_website',
        'auto_sitemap','sitemap_ping_google','auto_image_alt','lazy_loading','related_posts',
        'noindex_login','noindex_register','noindex_search','noindex_mypage'];

    foreach ($checkboxes as $key) {
        $_seoCfg[$key] = isset($_POST[$key]) ? '1' : '0';
    }

    $texts = ['description_length','default_description','default_keywords','related_posts_count',
        'noindex_boards','google_verification','naver_verification','robots_txt'];
    foreach ($texts as $key) {
        $_seoCfg[$key] = trim($_POST[$key] ?? '');
    }

    // OG 이미지 업로드
    if (!empty($_FILES['og_image_file']) && $_FILES['og_image_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['og_image_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            if ($_seoCfg['og_image'] && file_exists(NB_ROOT.'/'.$_seoCfg['og_image'])) @unlink(NB_ROOT.'/'.$_seoCfg['og_image']);
            $fn = 'seo_og_'.time().'.'.$ext;
            move_uploaded_file($_FILES['og_image_file']['tmp_name'], $_seoUpDir.'/'.$fn);
            $_seoCfg['og_image'] = 'uploads/seo/'.$fn;
        }
    }
    if (isset($_POST['og_image_delete']) && $_POST['og_image_delete']==='1') {
        if ($_seoCfg['og_image'] && file_exists(NB_ROOT.'/'.$_seoCfg['og_image'])) @unlink(NB_ROOT.'/'.$_seoCfg['og_image']);
        $_seoCfg['og_image'] = '';
    }

    // robots.txt 저장
    if ($_seoCfg['robots_txt']) {
        @file_put_contents(NB_ROOT.'/robots.txt', $_seoCfg['robots_txt']);
    }

    file_put_contents($_seoFile, json_encode($_seoCfg, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    echo '<div class="seo-msg seo-msg-ok">SEO 설정이 저장되었습니다.</div>';
}

// robots.txt 불러오기
if (!$_seoCfg['robots_txt'] && file_exists(NB_ROOT.'/robots.txt')) {
    $_seoCfg['robots_txt'] = file_get_contents(NB_ROOT.'/robots.txt');
}

$c = $_seoCfg;
?>

<form method="post" enctype="multipart/form-data" style="max-width:720px">

<!-- ON/OFF -->
<div class="seo-s">
    <label class="seo-sw"><input type="checkbox" name="enabled" <?=$c['enabled']==='1'?'checked':''?>>
    <span>SEO 자동 최적화 <strong style="color:<?=$c['enabled']==='1'?'#059669':'#dc2626'?>"><?=$c['enabled']==='1'?'ON':'OFF'?></strong></span></label>
</div>

<!-- ==================== 1. 메타태그 ==================== -->
<div class="seo-s">
    <h3 class="seo-h">1. 메타태그 자동생성</h3>
    <p class="seo-sub">게시글 제목/본문에서 자동으로 title, description, keywords를 생성합니다.</p>

    <label class="seo-ck"><input type="checkbox" name="title_suffix" <?=$c['title_suffix']==='1'?'checked':''?>> 페이지 제목에 사이트명 자동 추가 <span class="seo-eg">예: 게시글제목 - 사이트명</span></label>
    <label class="seo-ck"><input type="checkbox" name="auto_description" <?=$c['auto_description']==='1'?'checked':''?>> 게시글 본문에서 meta description 자동 추출</label>

    <div class="seo-field" style="margin-top:10px">
        <label>추출 글자수</label>
        <input type="number" name="description_length" value="<?=htmlspecialchars($c['description_length'])?>" style="width:100px" min="50" max="300"> <span class="seo-eg">자 (권장: 150)</span>
    </div>

    <div class="seo-field">
        <label>사이트 기본 설명</label>
        <textarea name="default_description" rows="2" class="seo-input" placeholder="게시글이 없는 페이지에 적용되는 기본 설명"><?=htmlspecialchars($c['default_description'])?></textarea>
    </div>

    <div class="seo-field">
        <label>기본 키워드</label>
        <input type="text" name="default_keywords" value="<?=htmlspecialchars($c['default_keywords'])?>" class="seo-input" placeholder="커뮤니티, 게시판, 정보공유">
        <p class="seo-tip">모든 페이지에 기본 포함 + 게시글에서는 제목 키워드를 자동 추가합니다.</p>
    </div>
</div>

<!-- ==================== 2. OG / 소셜 ==================== -->
<div class="seo-s">
    <h3 class="seo-h">2. 소셜 공유 (Open Graph / Twitter)</h3>
    <p class="seo-sub">카카오톡, 페이스북, 트위터 공유 시 제목·설명·이미지를 자동 설정합니다.</p>

    <label class="seo-ck"><input type="checkbox" name="auto_og" <?=$c['auto_og']==='1'?'checked':''?>> OG 태그 자동 생성 (og:title, og:description, og:url, og:image)</label>
    <label class="seo-ck"><input type="checkbox" name="auto_og_image" <?=$c['auto_og_image']==='1'?'checked':''?>> 게시글 첫 이미지를 og:image로 자동 지정</label>
    <label class="seo-ck"><input type="checkbox" name="twitter_card" <?=$c['twitter_card']==='1'?'checked':''?>> Twitter Card 자동 생성 (summary_large_image)</label>

    <div class="seo-field" style="margin-top:12px">
        <label>기본 대표 이미지 (이미지 없는 페이지에 사용)</label>
        <?php if ($c['og_image'] && file_exists(NB_ROOT.'/'.$c['og_image'])): ?>
        <div class="seo-og-pv">
            <img src="<?=nb_url($c['og_image'])?>" alt="OG">
            <div class="seo-og-act">
                <label class="seo-btn-sm">변경 <input type="file" name="og_image_file" accept="image/*" style="display:none" onchange="this.form.submit()"></label>
                <button type="submit" name="og_image_delete" value="1" class="seo-btn-sm seo-btn-del" onclick="return confirm('삭제할까요?')">삭제</button>
            </div>
        </div>
        <?php else: ?>
        <label class="seo-upload">
            <input type="file" name="og_image_file" accept="image/*" style="display:none" onchange="this.form.submit()">
            <span style="font-size:28px">+</span>
            <span>이미지 업로드</span>
            <span class="seo-tip">1200 x 630px 권장</span>
        </label>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== 3. 구조화 데이터 ==================== -->
<div class="seo-s">
    <h3 class="seo-h">3. 구조화 데이터 (JSON-LD)</h3>
    <p class="seo-sub">구글 리치 스니펫 노출 가능성을 높이는 Schema.org 데이터를 자동 삽입합니다.</p>

    <label class="seo-ck"><input type="checkbox" name="schema_article" <?=$c['schema_article']==='1'?'checked':''?>> Article 스키마 <span class="seo-eg">게시글: 제목, 작성자, 날짜, 이미지</span></label>
    <label class="seo-ck"><input type="checkbox" name="schema_breadcrumb" <?=$c['schema_breadcrumb']==='1'?'checked':''?>> BreadcrumbList 스키마 <span class="seo-eg">홈 > 게시판 > 글제목</span></label>
    <label class="seo-ck"><input type="checkbox" name="schema_comment" <?=$c['schema_comment']==='1'?'checked':''?>> Comment 스키마 <span class="seo-eg">댓글 구조화 (리치 스니펫)</span></label>
    <label class="seo-ck"><input type="checkbox" name="schema_website" <?=$c['schema_website']==='1'?'checked':''?>> WebSite 스키마 <span class="seo-eg">구글 사이트링크 검색창 노출</span></label>
</div>

<!-- ==================== 4. Canonical / 사이트맵 ==================== -->
<div class="seo-s">
    <h3 class="seo-h">4. Canonical URL / 사이트맵</h3>

    <label class="seo-ck"><input type="checkbox" name="auto_canonical" <?=$c['auto_canonical']==='1'?'checked':''?>> Canonical URL 자동 삽입 <span class="seo-eg">중복 URL 방지 (?page=1 등)</span></label>
    <label class="seo-ck"><input type="checkbox" name="auto_sitemap" <?=$c['auto_sitemap']==='1'?'checked':''?>> XML 사이트맵 자동 갱신</label>
    <label class="seo-ck"><input type="checkbox" name="sitemap_ping_google" <?=$c['sitemap_ping_google']==='1'?'checked':''?>> 게시글 작성 시 구글에 사이트맵 핑 전송</label>
</div>

<!-- ==================== 5. 이미지 / 속도 / 내부링크 ==================== -->
<div class="seo-s">
    <h3 class="seo-h">5. 이미지 최적화 / 페이지 속도 / 내부링크</h3>

    <label class="seo-ck"><input type="checkbox" name="auto_image_alt" <?=$c['auto_image_alt']==='1'?'checked':''?>> 이미지 alt 자동 삽입 <span class="seo-eg">alt 없는 이미지에 게시글 제목 자동 적용</span></label>
    <label class="seo-ck"><input type="checkbox" name="lazy_loading" <?=$c['lazy_loading']==='1'?'checked':''?>> 이미지 Lazy Loading <span class="seo-eg">Core Web Vitals LCP 개선</span></label>
    <label class="seo-ck"><input type="checkbox" name="related_posts" <?=$c['related_posts']==='1'?'checked':''?>> 관련글 자동 추천 <span class="seo-eg">내부링크 강화, 크롤러 깊이 유도</span></label>

    <div class="seo-field" style="margin-top:8px">
        <label>관련글 표시 개수</label>
        <input type="number" name="related_posts_count" value="<?=htmlspecialchars($c['related_posts_count'])?>" style="width:80px" min="1" max="20"> <span class="seo-eg">개</span>
    </div>
</div>

<!-- ==================== 6. noindex ==================== -->
<div class="seo-s">
    <h3 class="seo-h">6. 검색 제외 (noindex)</h3>
    <p class="seo-sub">검색엔진에 노출되지 않아야 할 페이지를 자동 처리합니다.</p>

    <label class="seo-ck"><input type="checkbox" name="noindex_login" <?=$c['noindex_login']==='1'?'checked':''?>> 로그인 / 비밀번호찾기 페이지</label>
    <label class="seo-ck"><input type="checkbox" name="noindex_register" <?=$c['noindex_register']==='1'?'checked':''?>> 회원가입 페이지</label>
    <label class="seo-ck"><input type="checkbox" name="noindex_search" <?=$c['noindex_search']==='1'?'checked':''?>> 검색결과 페이지</label>
    <label class="seo-ck"><input type="checkbox" name="noindex_mypage" <?=$c['noindex_mypage']==='1'?'checked':''?>> 마이페이지 / 프로필</label>

    <div class="seo-field" style="margin-top:10px">
        <label>특정 게시판 검색 제외</label>
        <input type="text" name="noindex_boards" value="<?=htmlspecialchars($c['noindex_boards'])?>" class="seo-input" placeholder="예: test, sandbox">
        <p class="seo-tip">게시판 ID를 쉼표(,)로 구분해서 입력하면 해당 게시판이 검색에서 제외됩니다.</p>
    </div>
</div>

<!-- ==================== 7. 검색엔진 인증 ==================== -->
<div class="seo-s">
    <h3 class="seo-h">7. 검색엔진 인증</h3>

    <div class="seo-field">
        <label>Google Search Console 인증코드</label>
        <input type="text" name="google_verification" value="<?=htmlspecialchars($c['google_verification'])?>" class="seo-input" placeholder="google-site-verification 값">
    </div>
    <div class="seo-field">
        <label>Naver Search Advisor 인증코드</label>
        <input type="text" name="naver_verification" value="<?=htmlspecialchars($c['naver_verification'])?>" class="seo-input" placeholder="naver-site-verification 값">
    </div>
</div>

<!-- ==================== 8. robots.txt ==================== -->
<div class="seo-s">
    <h3 class="seo-h">8. robots.txt 편집</h3>
    <p class="seo-sub">검색엔진 크롤러의 접근 정책을 직접 설정합니다.</p>
    <textarea name="robots_txt" rows="10" class="seo-input" style="font-family:monospace;font-size:13px"><?=htmlspecialchars($c['robots_txt'])?></textarea>
</div>

<button type="submit" name="seo_save" value="1" class="btn btn-primary" style="padding:12px 40px;font-size:15px;margin-top:4px">설정 저장</button>

</form>

<style>
.seo-msg{padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:16px}
.seo-msg-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.seo-s{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:18px 22px;margin-bottom:14px}
.seo-h{font-size:16px;font-weight:700;color:#1e293b;margin-bottom:4px}
.seo-sub{font-size:12px;color:#94a3b8;margin-bottom:14px;line-height:1.5}
.seo-field{margin-bottom:12px}
.seo-field>label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px}
.seo-input{width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;color:#334155;box-sizing:border-box}
.seo-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
textarea.seo-input{resize:vertical;font-family:inherit}
.seo-tip{font-size:12px;color:#94a3b8;margin-top:4px}
.seo-eg{font-size:11px;color:#94a3b8;margin-left:4px}
.seo-sw{display:flex;align-items:center;gap:10px;cursor:pointer;font-size:15px;color:#334155}
.seo-sw input{width:18px;height:18px;accent-color:#3b82f6}
.seo-ck{display:flex;align-items:center;gap:8px;font-size:14px;color:#334155;cursor:pointer;padding:5px 0}
.seo-ck input{width:16px;height:16px;accent-color:#3b82f6;flex-shrink:0}
.seo-upload{display:flex;flex-direction:column;align-items:center;gap:4px;border:2px dashed #cbd5e1;border-radius:10px;padding:24px;cursor:pointer;color:#94a3b8;transition:all .15s;text-align:center}
.seo-upload:hover{border-color:#3b82f6;background:#f8fafc}
.seo-og-pv{border:1px solid #e2e8f0;border-radius:8px;overflow:hidden}
.seo-og-pv img{width:100%;max-height:180px;object-fit:cover;display:block}
.seo-og-act{display:flex;gap:8px;padding:8px 12px;background:#f8fafc}
.seo-btn-sm{padding:5px 14px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#334155;cursor:pointer;background:#fff}
.seo-btn-sm:hover{border-color:#3b82f6;color:#3b82f6}
.seo-btn-del{border-color:#fecaca;color:#dc2626}
.seo-btn-del:hover{background:#fef2f2}
</style>

<?php
/**
 * 페이지 속도 최적화 - 설정
 */
if (!Auth::check() || !Auth::isAdmin()) { echo '권한이 없습니다.'; return; }
$_soCfg = file_exists(__DIR__ . '/config.json') ? json_decode(file_get_contents(__DIR__ . '/config.json'), true) : [];
$_soCfg = array_merge([
    'html_minify' => true, 'lazy_loading' => true, 'lazy_iframe' => true,
    'dns_prefetch' => true, 'js_defer' => true, 'gzip' => true,
    'browser_cache' => true, 'remove_query_strings' => false,
    'preload_fonts' => false, 'custom_prefetch' => '',
], $_soCfg);

// 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_so_save'] ?? '') === '1') {
    Auth::verifyCsrf($_POST['_token'] ?? '');
    $_soCfg['html_minify'] = !empty($_POST['html_minify']);
    $_soCfg['lazy_loading'] = !empty($_POST['lazy_loading']);
    $_soCfg['lazy_iframe'] = !empty($_POST['lazy_iframe']);
    $_soCfg['dns_prefetch'] = !empty($_POST['dns_prefetch']);
    $_soCfg['js_defer'] = !empty($_POST['js_defer']);
    $_soCfg['gzip'] = !empty($_POST['gzip']);
    $_soCfg['browser_cache'] = !empty($_POST['browser_cache']);
    $_soCfg['remove_query_strings'] = !empty($_POST['remove_query_strings']);
    $_soCfg['preload_fonts'] = !empty($_POST['preload_fonts']);
    $_soCfg['custom_prefetch'] = trim($_POST['custom_prefetch'] ?? '');
    file_put_contents(__DIR__ . '/config.json', json_encode($_soCfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo '<div style="background:#e8f5e9;border:1px solid #c8e6c9;padding:10px 16px;border-radius:4px;margin-bottom:16px;font-size:13px;color:#2e7d32">설정이 저장되었습니다.</div>';
}

// GD, zlib 체크
$hasGzip = extension_loaded('zlib');
$hasGd = extension_loaded('gd');
$hasDeflate = function_exists('apache_get_modules') ? in_array('mod_deflate', apache_get_modules()) : null;
?>
<style>
.so-wrap{max-width:800px;margin:0 auto;font-family:-apple-system,'Malgun Gothic',sans-serif}
.so-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid #1a1a1a}
.so-topbar h2{font-size:20px;font-weight:700;color:#1a1a1a;margin:0}

.so-status{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.so-status-item{padding:10px 16px;border-radius:4px;font-size:12px;font-weight:600}
.so-on{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9}
.so-off{background:#ffebee;color:#c62828;border:1px solid #ffcdd2}
.so-unknown{background:#f5f5f5;color:#888;border:1px solid #e0e0e0}

.so-box{background:#fff;border:1px solid #e5e5e5;border-radius:6px;margin-bottom:16px}
.so-box-head{padding:14px 18px;border-bottom:1px solid #eee;font-size:14px;font-weight:700;color:#1a1a1a}
.so-box-body{padding:18px}

.so-row{display:flex;align-items:flex-start;margin-bottom:16px;gap:12px}
.so-row:last-child{margin-bottom:0}
.so-label{width:200px;flex-shrink:0}
.so-label-main{font-size:13px;font-weight:600;color:#333}
.so-label-sub{font-size:11px;color:#999;margin-top:2px}
.so-control{flex:1}

.so-toggle{position:relative;width:42px;height:22px;cursor:pointer;display:inline-block}
.so-toggle input{display:none}
.so-toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:11px;transition:.2s}
.so-toggle-slider::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:2px;top:2px;transition:.2s}
.so-toggle input:checked+.so-toggle-slider{background:#1a1a1a}
.so-toggle input:checked+.so-toggle-slider::before{left:22px}

.so-textarea{width:100%;min-height:80px;padding:8px 12px;border:1px solid #d5d5d5;border-radius:4px;font-size:12px;color:#333;resize:vertical;font-family:monospace}
.so-textarea:focus{outline:none;border-color:#333}

.so-btn{padding:8px 24px;border-radius:4px;border:none;background:#1a1a1a;color:#fff;font-size:13px;font-weight:600;cursor:pointer}
.so-btn:hover{background:#333}

.so-info{background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:12px 16px;font-size:12px;line-height:1.8;color:#555;margin-top:16px}
.so-info strong{color:#1a1a1a}

@media(max-width:768px){.so-row{flex-direction:column}.so-label{width:100%}}
</style>

<form method="post" class="so-wrap">
    <input type="hidden" name="_so_save" value="1">
    <input type="hidden" name="_token" value="<?= Auth::csrfToken() ?>">

    <div class="so-topbar">
        <h2>페이지 속도 최적화</h2>
        <button type="submit" class="so-btn">설정 저장</button>
    </div>

    <!-- 서버 환경 -->
    <div class="so-status">
        <div class="so-status-item <?= $hasGzip ? 'so-on' : 'so-off' ?>">Gzip: <?= $hasGzip ? '지원' : '미지원' ?></div>
        <div class="so-status-item <?= $hasGd ? 'so-on' : 'so-off' ?>">GD: <?= $hasGd ? '지원' : '미지원' ?></div>
        <div class="so-status-item <?= $hasDeflate === null ? 'so-unknown' : ($hasDeflate ? 'so-on' : 'so-off') ?>">mod_deflate: <?= $hasDeflate === null ? '확인불가' : ($hasDeflate ? '지원' : '미지원') ?></div>
    </div>

    <!-- HTML 최적화 -->
    <div class="so-box">
        <div class="so-box-head">HTML 최적화</div>
        <div class="so-box-body">
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">HTML 압축</div>
                    <div class="so-label-sub">불필요한 공백, 줄바꿈, 주석 제거</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="html_minify" value="1" <?= $_soCfg['html_minify'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">Gzip 압축</div>
                    <div class="so-label-sub">전송 데이터 크기 60~80% 감소</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="gzip" value="1" <?= $_soCfg['gzip'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
        </div>
    </div>

    <!-- 이미지/미디어 -->
    <div class="so-box">
        <div class="so-box-head">이미지 / 미디어</div>
        <div class="so-box-body">
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">이미지 Lazy Loading</div>
                    <div class="so-label-sub">화면에 보이는 이미지만 먼저 로드 (상위 3개 제외)</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="lazy_loading" value="1" <?= $_soCfg['lazy_loading'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">iframe Lazy Loading</div>
                    <div class="so-label-sub">유튜브 등 임베드 영상 지연 로드</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="lazy_iframe" value="1" <?= $_soCfg['lazy_iframe'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
        </div>
    </div>

    <!-- JS/CSS -->
    <div class="so-box">
        <div class="so-box-head">JavaScript / CSS</div>
        <div class="so-box-body">
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">JS defer 로딩</div>
                    <div class="so-label-sub">외부 JS를 페이지 로드 후 실행 (jQuery/Summernote 제외)</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="js_defer" value="1" <?= $_soCfg['js_defer'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">쿼리스트링 제거</div>
                    <div class="so-label-sub">정적 파일 URL에서 ?ver= 등 제거 (CDN 캐싱 개선)</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="remove_query_strings" value="1" <?= $_soCfg['remove_query_strings'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
        </div>
    </div>

    <!-- 네트워크 -->
    <div class="so-box">
        <div class="so-box-head">네트워크</div>
        <div class="so-box-body">
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">DNS Prefetch</div>
                    <div class="so-label-sub">외부 도메인 DNS를 미리 조회하여 연결 시간 단축</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="dns_prefetch" value="1" <?= $_soCfg['dns_prefetch'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">브라우저 캐싱</div>
                    <div class="so-label-sub">.htaccess에 만료 헤더 자동 추가</div>
                </div>
                <div class="so-control">
                    <label class="so-toggle"><input type="checkbox" name="browser_cache" value="1" <?= $_soCfg['browser_cache'] ? 'checked' : '' ?>><span class="so-toggle-slider"></span></label>
                </div>
            </div>
            <div class="so-row">
                <div class="so-label">
                    <div class="so-label-main">추가 Prefetch 도메인</div>
                    <div class="so-label-sub">한 줄에 하나씩 입력</div>
                </div>
                <div class="so-control">
                    <textarea name="custom_prefetch" class="so-textarea" placeholder="https://example.com&#10;https://cdn.example.com"><?= htmlspecialchars($_soCfg['custom_prefetch']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align:right;margin-bottom:20px">
        <button type="submit" class="so-btn">설정 저장</button>
    </div>

    <div class="so-info">
        <strong>적용되는 최적화 항목:</strong><br>
        - HTML 압축: 출력 HTML에서 불필요한 공백, 줄바꿈, 주석을 제거하여 전송 크기 감소<br>
        - Gzip: 서버에서 압축 전송하여 데이터 크기 60~80% 절감<br>
        - Lazy Loading: 화면 밖의 이미지/iframe을 스크롤 시 로드 (LCP 상위 3개 이미지는 제외)<br>
        - JS defer: 렌더링 차단 없이 JS를 비동기 로드 (jQuery, Summernote 등 필수 라이브러리는 자동 제외)<br>
        - DNS Prefetch: Google Fonts, CDN 등 외부 도메인을 미리 연결<br>
        - 브라우저 캐싱: 이미지 1년, CSS/JS 1개월 캐시 설정 (.htaccess)<br>
        - 관리자 페이지에는 최적화가 적용되지 않습니다
    </div>
</form>

<?php
/**
 * NuriBoard 관리자 - 사이트 설정
 */

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $settings = $_POST['settings'] ?? [];
        foreach ($settings as $key => $value) {
            $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                DB::update("{$prefix}settings", ['setting_value' => $value], 'setting_key = ?', [$key]);
            } else {
                DB::insert("{$prefix}settings", ['setting_key' => $key, 'setting_value' => $value]);
            }
        }
        AdminLog::write('settings_save', '', 0, '사이트 설정 변경');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'upload_site_image') {
        $type = $_POST['type'] ?? '';
        if (!in_array($type, ['site_logo', 'site_favicon'])) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청']);
            exit;
        }
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => '파일을 선택하세요.']);
            exit;
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','ico','svg','webp'])) {
            echo json_encode(['success' => false, 'message' => '이미지 파일만 업로드 가능합니다.']);
            exit;
        }
        $dir = NB_ROOT . '/uploads/site';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $newName = $type . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $newName);
        $path = 'uploads/site/' . $newName;

        // 파비콘은 루트에 favicon.ico로도 복사 (구글봇/크롤러 직접 접근 대응)
        if ($type === 'site_favicon') {
            @copy($dir . '/' . $newName, NB_ROOT . '/favicon.ico');
        }

        $exists = DB::fetch("SELECT setting_key FROM {$prefix}settings WHERE setting_key = ?", [$type]);
        if ($exists) {
            DB::update("{$prefix}settings", ['setting_value' => $path], "setting_key = ?", [$type]);
        } else {
            DB::insert("{$prefix}settings", ['setting_key' => $type, 'setting_value' => $path]);
        }
        echo json_encode(['success' => true, 'path' => $path]);
        exit;
    }

    if ($action === 'delete_site_image') {
        $type = $_POST['type'] ?? '';
        if (!in_array($type, ['site_logo', 'site_favicon'])) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청']);
            exit;
        }
        $current = nb_setting($type);
        if ($current && file_exists(NB_ROOT . '/' . $current)) {
            unlink(NB_ROOT . '/' . $current);
        }
        // 파비콘 삭제 시 루트 favicon.ico도 제거
        if ($type === 'site_favicon' && file_exists(NB_ROOT . '/favicon.ico')) {
            @unlink(NB_ROOT . '/favicon.ico');
        }
        DB::update("{$prefix}settings", ['setting_value' => ''], "setting_key = ?", [$type]);
        AdminLog::write('settings_save', '', 0, $type . ' 삭제');
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$s = $siteSettings;

adminHeader('settings');
?>

<!-- 저장 토스트 -->
<div id="saveToast" style="display:none;position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;z-index:99999;box-shadow:0 4px 16px rgba(0,0,0,.25);pointer-events:none">
    전체 설정이 저장되었습니다.
</div>

<div class="page-header"><h1>사이트 설정</h1></div>
<form id="settingsForm" onsubmit="return saveSettings(event)">
    <div class="card">
        <div class="card-header"><h2>기본 설정</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>사이트 제목</label>
                <input type="text" name="site_title" value="<?= nb_e($s['site_title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>사이트 제목 색상</label>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <span class="nb-color-preview" id="titleColorPreview" style="background:<?= nb_e($s['site_title_color'] ?? '#2563eb') ?>"></span>
                    <span style="font-size:13px;color:#64748b" id="titleColorLabel"><?= nb_e($s['site_title_color'] ?? '#2563eb') ?></span>
                </div>
                <input type="hidden" id="titleColor" name="site_title_color" value="<?= nb_e($s['site_title_color'] ?? '#2563eb') ?>">
                <div id="titleColor_palette"></div>
            </div>
            <div class="form-group">
                <label>사이트 설명</label>
                <input type="text" name="site_description" value="<?= nb_e($s['site_description'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>사이트 URL</label>
                <input type="text" name="site_url" value="<?= nb_e($s['site_url'] ?? '') ?>" placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label>SEO 키워드 (쉼표 구분)</label>
                <input type="text" name="site_keywords" value="<?= nb_e($s['site_keywords'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>헤더 배경색</label>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <span class="nb-color-preview" id="headerBgPreview" style="background:<?= nb_e($s['header_bg_color'] ?? '#ffffff') ?>"></span>
                    <span style="font-size:13px;color:#64748b" id="headerBgLabel"><?= nb_e($s['header_bg_color'] ?? '#ffffff') ?></span>
                </div>
                <input type="hidden" id="headerBgColor" name="header_bg_color" value="<?= nb_e($s['header_bg_color'] ?? '#ffffff') ?>">
                <div id="headerBgColor_palette"></div>
            </div>
            <div class="form-group">
                <label>메뉴 배경색</label>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <span class="nb-color-preview" id="navBgPreview" style="background:<?= nb_e($s['nav_bg_color'] ?? '#2d2d2d') ?>"></span>
                    <span style="font-size:13px;color:#64748b" id="navBgLabel"><?= nb_e($s['nav_bg_color'] ?? '#2d2d2d') ?></span>
                </div>
                <input type="hidden" id="navBgColor" name="nav_bg_color" value="<?= nb_e($s['nav_bg_color'] ?? '#2d2d2d') ?>">
                <div id="navBgColor_palette"></div>
            </div>
            <div class="form-group">
                <label>메뉴 글자색</label>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <span class="nb-color-preview" id="navTextPreview" style="background:<?= nb_e($s['nav_text_color'] ?? '#e0e0e0') ?>"></span>
                    <span style="font-size:13px;color:#64748b" id="navTextLabel"><?= nb_e($s['nav_text_color'] ?? '#e0e0e0') ?></span>
                </div>
                <input type="hidden" id="navTextColor" name="nav_text_color" value="<?= nb_e($s['nav_text_color'] ?? '#e0e0e0') ?>">
                <div id="navTextColor_palette"></div>
            </div>
        </div>
    </div>
    <div style="margin-bottom:20px"><button type="submit" class="btn btn-primary btn-lg">전체 저장</button></div>

    <div class="card">
        <div class="card-header"><h2>운영 설정</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>한 페이지에 보여줄 게시글 수</label>
                    <input type="number" name="posts_per_page" value="<?= nb_e($s['posts_per_page'] ?? '20') ?>">
                </div>
                <div class="form-group">
                    <label>한 페이지에 보여줄 댓글 수</label>
                    <input type="number" name="comments_per_page" value="<?= nb_e($s['comments_per_page'] ?? '50') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="signup_enabled" value="1" <?= ($s['signup_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    회원가입 허용
                </label>
            </div>
        </div>
    </div>
    <div style="margin-bottom:20px"><button type="submit" class="btn btn-primary btn-lg">전체 저장</button></div>

    <div class="card">
        <div class="card-header"><h2>로고 / 파비콘</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>사이트 로고</label>
                    <?php if (!empty($s['site_logo'])): ?>
                        <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px">
                            <img src="../<?= nb_e($s['site_logo']) ?>" style="height:50px;border:1px solid #e2e8f0;border-radius:6px;padding:4px">
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteSiteImage('site_logo')">삭제</button>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="logo_file" accept="image/*" onchange="uploadSiteImage('site_logo','logo_file')">
                    <small>권장 사이즈: 가로 200~300px, 세로 50~60px (PNG 투명 배경 권장)</small>
                </div>
                <div class="form-group">
                    <label>파비콘</label>
                    <?php if (!empty($s['site_favicon'])): ?>
                        <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px">
                            <img src="../<?= nb_e($s['site_favicon']) ?>" style="height:32px;border:1px solid #e2e8f0;border-radius:4px;padding:2px">
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteSiteImage('site_favicon')">삭제</button>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="favicon_file" accept="image/*,.ico" onchange="uploadSiteImage('site_favicon','favicon_file')">
                    <small>권장 사이즈: 32x32px 또는 64x64px (ICO, PNG)</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>포인트 설정</h2></div>
        <div class="card-body">
            <p style="font-size:13px;color:#059669;font-weight:600;margin-bottom:12px">적립 포인트 (활동 시 지급)</p>
            <div class="form-row">
                <div class="form-group">
                    <label>글 작성 시 적립</label>
                    <input type="number" name="point_write" value="<?= nb_e($s['point_write'] ?? '10') ?>" min="0">
                    <small>글 작성하면 이 포인트가 지급됩니다</small>
                </div>
                <div class="form-group">
                    <label>댓글 작성 시 적립</label>
                    <input type="number" name="point_comment" value="<?= nb_e($s['point_comment'] ?? '5') ?>" min="0">
                    <small>댓글 작성하면 이 포인트가 지급됩니다</small>
                </div>
                <div class="form-group">
                    <label>로그인 시 적립 (하루 1회)</label>
                    <input type="number" name="point_login" value="<?= nb_e($s['point_login'] ?? '3') ?>" min="0">
                    <small>하루 한번 로그인 시 지급됩니다</small>
                </div>
                <div class="form-group">
                    <label>댓글 채택 시 적립</label>
                    <input type="number" name="point_adoption" value="<?= nb_e($s['point_adoption'] ?? '50') ?>" min="0">
                    <small>댓글이 채택되면 작성자에게 지급됩니다</small>
                </div>
            </div>
            <div class="form-row" style="margin-top:12px">
                <div class="form-group">
                    <label>출석체크 포인트</label>
                    <input type="number" name="point_attendance" value="<?= nb_e($s['point_attendance'] ?? '5') ?>" min="0">
                    <small>매일 출석 시 지급</small>
                </div>
            </div>
            <p style="font-size:13px;color:#dc2626;font-weight:600;margin:16px 0 12px;padding-top:16px;border-top:1px solid #e2e8f0">소모 포인트 (활동 시 차감)</p>
            <div class="form-row">
                <div class="form-group">
                    <label>쪽지 보내기 시 소모</label>
                    <input type="number" name="point_message" value="<?= nb_e($s['point_message'] ?? '0') ?>" min="0">
                    <small>0이면 무료, 포인트 부족 시 발송 불가</small>
                </div>
            </div>
            <small style="color:#94a3b8;display:block;margin-top:8px">게시판별 글쓰기 소모 포인트는 [게시판 관리]에서 각 게시판마다 설정합니다.</small>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>소셜 로그인 설정</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="social_login_enabled" value="1" <?= ($s['social_login_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                    소셜 로그인 사용
                </label>
            </div>
            <p style="font-size:13px;color:#64748b;margin-bottom:16px">API 키를 입력한 항목만 로그인/회원가입 페이지에 버튼이 표시됩니다.</p>
            <?php $siteBaseUrl = rtrim($s['site_url'] ?? '', '/'); ?>
            <p style="font-size:13px;font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                카카오
                <button type="button" class="social-help-btn" onclick="openKakaoHelp()" title="설정 방법 보기">?</button>
            </p>
            <div class="form-group">
                <label>REST API 키</label>
                <input type="text" name="kakao_client_id" value="<?= nb_e($s['kakao_client_id'] ?? '') ?>" placeholder="카카오 REST API 키">
            </div>
            <p style="font-size:13px;font-weight:600;margin:16px 0 8px;display:flex;align-items:center;gap:6px">
                네이버
                <button type="button" class="social-help-btn" onclick="openNaverHelp()" title="설정 방법 보기">?</button>
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="naver_client_id" value="<?= nb_e($s['naver_client_id'] ?? '') ?>" placeholder="네이버 Client ID">
                </div>
                <div class="form-group">
                    <label>Client Secret</label>
                    <input type="text" name="naver_client_secret" value="<?= nb_e($s['naver_client_secret'] ?? '') ?>" placeholder="네이버 Client Secret">
                </div>
            </div>
            <p style="font-size:13px;font-weight:600;margin:16px 0 8px;display:flex;align-items:center;gap:6px">
                구글
                <button type="button" class="social-help-btn" onclick="openGoogleHelp()" title="설정 방법 보기">?</button>
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="google_client_id" value="<?= nb_e($s['google_client_id'] ?? '') ?>" placeholder="구글 Client ID">
                </div>
                <div class="form-group">
                    <label>Client Secret</label>
                    <input type="text" name="google_client_secret" value="<?= nb_e($s['google_client_secret'] ?? '') ?>" placeholder="구글 Client Secret">
                </div>
            </div>
        </div>
    </div>

    <!-- 카카오 설명서 모달 -->
    <div id="kakaoHelpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:flex-start;justify-content:center;padding:40px 16px;overflow-y:auto">
        <div style="background:#fff;border-radius:12px;width:min(640px,100%);box-shadow:0 8px 32px rgba(0,0,0,.18);margin:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #e2e8f0">
                <strong style="font-size:16px">카카오 로그인 설정 방법</strong>
                <button onclick="document.getElementById('kakaoHelpModal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;line-height:1">&times;</button>
            </div>
            <div style="padding:24px;font-size:14px;line-height:1.8;color:#334155">
<p style="margin:0 0 16px;padding:10px 14px;background:#fef9c3;border-left:3px solid #eab308;border-radius:4px;font-size:13px">
사전 준비: 카카오 계정이 있어야 합니다. 사업자 인증 없이도 사용할 수 있습니다.
</p>
<ol style="padding-left:20px;margin:0;display:flex;flex-direction:column;gap:14px">
<li>
  <strong>카카오 개발자 사이트 접속</strong><br>
  <a href="https://developers.kakao.com" target="_blank" style="color:#2563eb">https://developers.kakao.com</a> 에 접속한 뒤 카카오 계정으로 로그인합니다.
</li>
<li>
  <strong>애플리케이션 추가</strong><br>
  상단 메뉴에서 "내 애플리케이션" 클릭 &rarr; "애플리케이션 추가하기" 버튼 클릭<br>
  앱 이름(서비스명)과 회사명을 입력하고 저장합니다.
</li>
<li>
  <strong>REST API 키 확인</strong><br>
  생성된 앱을 클릭하면 요약 정보 화면이 나옵니다.<br>
  "앱 키" 항목에서 <strong>REST API 키</strong>를 복사해서 위의 입력란에 붙여넣습니다.
</li>
<li>
  <strong>카카오 로그인 활성화</strong><br>
  왼쪽 메뉴에서 "제품 설정 &rarr; 카카오 로그인" 클릭<br>
  "활성화 설정"을 <strong>ON</strong>으로 변경합니다.
</li>
<li>
  <strong>Redirect URI 등록</strong><br>
  "카카오 로그인" 메뉴 하단의 "Redirect URI" 항목에서 "등록하기" 클릭<br>
  아래 주소를 그대로 복사해서 붙여넣습니다.<br>
  <code style="display:block;margin-top:6px;background:#f1f5f9;padding:6px 10px;border-radius:4px;font-size:12px;word-break:break-all"><?= nb_e($siteBaseUrl) ?>/oauth/kakao/callback</code>
</li>
<li>
  <strong>동의항목 설정</strong><br>
  왼쪽 메뉴에서 "제품 설정 &rarr; 카카오 로그인 &rarr; 동의항목" 클릭<br>
  "닉네임"과 "카카오계정(이메일)"을 <strong>필수 동의</strong> 또는 <strong>선택 동의</strong>로 설정합니다.<br>
  <span style="font-size:12px;color:#64748b">이메일이 선택 동의이면 회원 중 일부가 이메일을 제공하지 않을 수 있습니다.</span>
</li>
<li>
  <strong>플랫폼 등록 (선택)</strong><br>
  왼쪽 메뉴 "내 애플리케이션 &rarr; 앱 설정 &rarr; 플랫폼"에서 Web 플랫폼을 추가하고<br>
  사이트 도메인(<code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:12px"><?= nb_e($siteBaseUrl) ?></code>)을 등록합니다.<br>
  <span style="font-size:12px;color:#64748b">등록하지 않아도 로그인은 작동하지만, Redirect URI 오류가 발생할 경우 등록하세요.</span>
</li>
<li>
  <strong>설정 저장</strong><br>
  복사한 REST API 키를 위의 입력란에 붙여넣고 화면 하단 "저장" 버튼을 누릅니다.
</li>
</ol>
            </div>
        </div>
    </div>
    <!-- 구글 설명서 모달 -->
    <div id="googleHelpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:flex-start;justify-content:center;padding:40px 16px;overflow-y:auto">
        <div style="background:#fff;border-radius:12px;width:min(640px,100%);box-shadow:0 8px 32px rgba(0,0,0,.18);margin:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #e2e8f0">
                <strong style="font-size:16px">구글 로그인 설정 방법</strong>
                <button onclick="document.getElementById('googleHelpModal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;line-height:1">&times;</button>
            </div>
            <div style="padding:24px;font-size:14px;line-height:1.8;color:#334155">
<p style="margin:0 0 16px;padding:10px 14px;background:#e8f0fe;border-left:3px solid #4285F4;border-radius:4px;font-size:13px">
사전 준비: Google 계정이 있어야 합니다. Google Cloud Console에서 프로젝트를 생성해야 합니다.
</p>
<ol style="padding-left:20px;margin:0;display:flex;flex-direction:column;gap:14px">
<li>
  <strong>Google Cloud Console 접속</strong><br>
  <a href="https://console.cloud.google.com" target="_blank" style="color:#2563eb">https://console.cloud.google.com</a> 에 접속한 뒤 Google 계정으로 로그인합니다.
</li>
<li>
  <strong>프로젝트 생성</strong><br>
  상단의 프로젝트 선택 드롭다운 클릭 &rarr; "새 프로젝트" 클릭<br>
  프로젝트 이름을 입력하고 "만들기"를 클릭합니다.
</li>
<li>
  <strong>OAuth 동의 화면 설정</strong><br>
  왼쪽 메뉴에서 "API 및 서비스 &rarr; OAuth 동의 화면" 클릭<br>
  User Type을 <strong>외부</strong>로 선택하고 "만들기" 클릭<br>
  앱 이름, 사용자 지원 이메일, 개발자 연락처 이메일을 입력하고 저장합니다.
</li>
<li>
  <strong>사용자 인증 정보 생성</strong><br>
  왼쪽 메뉴에서 "API 및 서비스 &rarr; 사용자 인증 정보" 클릭<br>
  상단 "+ 사용자 인증 정보 만들기" &rarr; <strong>OAuth 클라이언트 ID</strong> 선택<br>
  애플리케이션 유형을 <strong>웹 애플리케이션</strong>으로 선택합니다.
</li>
<li>
  <strong>승인된 리디렉션 URI 등록</strong><br>
  "승인된 리디렉션 URI" 항목에서 "+ URI 추가" 클릭<br>
  아래 주소를 그대로 붙여넣습니다.<br>
  <code style="display:block;margin-top:6px;background:#f1f5f9;padding:6px 10px;border-radius:4px;font-size:12px;word-break:break-all"><?= nb_e($siteBaseUrl) ?>/oauth/google/callback</code>
</li>
<li>
  <strong>승인된 자바스크립트 원본 등록 (선택)</strong><br>
  "승인된 자바스크립트 원본" 항목에 사이트 주소를 추가합니다.<br>
  <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:12px"><?= nb_e($siteBaseUrl) ?></code><br>
  <span style="font-size:12px;color:#64748b">등록하지 않아도 로그인은 작동하지만 오류 발생 시 추가하세요.</span>
</li>
<li>
  <strong>Client ID / Client Secret 확인</strong><br>
  "만들기" 버튼을 클릭하면 팝업에 <strong>클라이언트 ID</strong>와 <strong>클라이언트 보안 비밀</strong>이 표시됩니다.<br>
  두 값을 복사해서 위의 입력란에 각각 붙여넣습니다.<br>
  <span style="font-size:12px;color:#64748b">팝업을 닫은 후에는 "사용자 인증 정보" 목록에서 해당 항목을 클릭해 다시 확인할 수 있습니다.</span>
</li>
<li>
  <strong>설정 저장</strong><br>
  입력을 마친 뒤 화면 하단 "저장" 버튼을 누릅니다.
</li>
</ol>
            </div>
        </div>
    </div>

    <!-- 네이버 설명서 모달 -->
    <div id="naverHelpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:flex-start;justify-content:center;padding:40px 16px;overflow-y:auto">
        <div style="background:#fff;border-radius:12px;width:min(640px,100%);box-shadow:0 8px 32px rgba(0,0,0,.18);margin:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #e2e8f0">
                <strong style="font-size:16px">네이버 로그인 설정 방법</strong>
                <button onclick="document.getElementById('naverHelpModal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;line-height:1">&times;</button>
            </div>
            <div style="padding:24px;font-size:14px;line-height:1.8;color:#334155">
<p style="margin:0 0 16px;padding:10px 14px;background:#e6f5ec;border-left:3px solid #03C75A;border-radius:4px;font-size:13px">
사전 준비: 네이버 계정이 있어야 합니다. 사업자 인증 없이도 사용할 수 있습니다.
</p>
<ol style="padding-left:20px;margin:0;display:flex;flex-direction:column;gap:14px">
<li>
  <strong>네이버 개발자 센터 접속</strong><br>
  <a href="https://developers.naver.com" target="_blank" style="color:#2563eb">https://developers.naver.com</a> 에 접속한 뒤 네이버 계정으로 로그인합니다.
</li>
<li>
  <strong>애플리케이션 등록</strong><br>
  상단 메뉴에서 "Application &rarr; 애플리케이션 등록" 클릭<br>
  애플리케이션 이름(서비스명)을 입력합니다.
</li>
<li>
  <strong>사용 API 선택</strong><br>
  "사용 API" 항목에서 <strong>네이버 로그인</strong>을 선택합니다.<br>
  권한 항목에서 <strong>이메일 주소</strong>와 <strong>별명(닉네임)</strong>을 체크합니다.
</li>
<li>
  <strong>로그인 오픈 API 서비스 환경 추가</strong><br>
  "로그인 오픈 API 서비스 환경" 항목에서 <strong>PC 웹</strong>을 선택합니다.<br>
  서비스 URL에 사이트 주소(<code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:12px"><?= nb_e($siteBaseUrl) ?></code>)를 입력합니다.
</li>
<li>
  <strong>Callback URL 등록</strong><br>
  Callback URL 입력란에 아래 주소를 그대로 붙여넣습니다.<br>
  <code style="display:block;margin-top:6px;background:#f1f5f9;padding:6px 10px;border-radius:4px;font-size:12px;word-break:break-all"><?= nb_e($siteBaseUrl) ?>/oauth/naver/callback</code>
</li>
<li>
  <strong>애플리케이션 등록 완료</strong><br>
  "등록하기" 버튼을 클릭하면 애플리케이션이 생성됩니다.
</li>
<li>
  <strong>Client ID / Client Secret 확인</strong><br>
  생성된 애플리케이션을 클릭하면 <strong>Client ID</strong>와 <strong>Client Secret</strong>이 표시됩니다.<br>
  두 값을 복사해서 위의 입력란에 각각 붙여넣습니다.<br>
  <span style="font-size:12px;color:#64748b">Client Secret은 "보기" 버튼을 클릭해야 확인할 수 있습니다.</span>
</li>
<li>
  <strong>설정 저장</strong><br>
  입력을 마친 뒤 화면 하단 "저장" 버튼을 누릅니다.
</li>
</ol>
            </div>
        </div>
    </div>
    <style>
    .social-help-btn{width:22px;height:22px;border-radius:50%;border:1.5px solid #cbd5e1;background:#fff;cursor:pointer;font-size:12px;font-weight:700;color:#64748b;line-height:1;padding:0;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;vertical-align:middle}
    .social-help-btn:hover{border-color:#6366f1;color:#6366f1;background:#eef2ff}
    </style>
    <script>
    function openKakaoHelp() {
        document.getElementById('kakaoHelpModal').style.display = 'flex';
    }
    document.getElementById('kakaoHelpModal').addEventListener('click', function(e){
        if (e.target === this) this.style.display = 'none';
    });
    function openNaverHelp() {
        document.getElementById('naverHelpModal').style.display = 'flex';
    }
    document.getElementById('naverHelpModal').addEventListener('click', function(e){
        if (e.target === this) this.style.display = 'none';
    });
    function openGoogleHelp() {
        document.getElementById('googleHelpModal').style.display = 'flex';
    }
    document.getElementById('googleHelpModal').addEventListener('click', function(e){
        if (e.target === this) this.style.display = 'none';
    });
    </script>
    <div style="margin-bottom:20px"><button type="submit" class="btn btn-primary btn-lg">전체 저장</button></div>

    <div class="card">
        <div class="card-header"><h2>날개 배너 설정</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>좌측 날개 스크롤 고정</label>
                    <select name="wing_left_sticky">
                        <option value="0" <?= ($s['wing_left_sticky'] ?? '1') === '0' ? 'selected' : '' ?>>고정 안함</option>
                        <option value="1" <?= ($s['wing_left_sticky'] ?? '1') === '1' ? 'selected' : '' ?>>고정 (스크롤 시 따라다님)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>우측 날개 스크롤 고정</label>
                    <select name="wing_right_sticky">
                        <option value="0" <?= ($s['wing_right_sticky'] ?? '1') === '0' ? 'selected' : '' ?>>고정 안함</option>
                        <option value="1" <?= ($s['wing_right_sticky'] ?? '1') === '1' ? 'selected' : '' ?>>고정 (스크롤 시 따라다님)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>파일 업로드 설정</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>첨부파일 최대 크기 (MB)</label>
                <input type="number" name="upload_max_size" value="<?= nb_e($s['upload_max_size'] ?? '10') ?>">
            </div>
            <div class="form-group">
                <label>허용 파일 확장자 (쉼표 구분)</label>
                <input type="text" name="upload_extensions" value="<?= nb_e($s['upload_extensions'] ?? 'jpg,jpeg,png,gif,pdf,zip') ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>푸터 설정</h2></div>
        <div class="card-body">
            <?php
            $_ftTypeVal = $s['footer_type'] ?? 'biz';
            $_ftOpts = [
                'biz' => [
                    'title' => '사업자 정보',
                    'desc' => '전자상거래법 대응 (유료 서비스 필수)',
                    'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="1"/><line x1="9" y1="8" x2="9" y2="8"/><line x1="15" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="9" y2="12"/><line x1="15" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="9" y2="16"/><line x1="15" y1="16" x2="15" y2="16"/><path d="M10 21v-4h4v4"/></svg>',
                ],
                'custom' => [
                    'title' => '커스텀 HTML',
                    'desc' => '직접 작성한 HTML 표시',
                    'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
                ],
                'minimal' => [
                    'title' => '최소형',
                    'desc' => '저작권 한 줄만 표시',
                    'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
                ],
            ];
            ?>
            <div class="form-group">
                <label style="margin-bottom:10px">푸터 유형</label>
                <div class="ft-type-grid">
                    <?php foreach ($_ftOpts as $_k => $_o): $_on = $_ftTypeVal === $_k; ?>
                    <label class="ft-type-opt<?= $_on ? ' on' : '' ?>">
                        <input type="radio" name="footer_type" value="<?= $_k ?>" <?= $_on?'checked':'' ?> onchange="toggleFooterType(this.value)">
                        <span class="ft-type-icon"><?= $_o['svg'] ?></span>
                        <strong class="ft-type-title"><?= $_o['title'] ?></strong>
                        <small class="ft-type-desc"><?= $_o['desc'] ?></small>
                    </label>
                    <?php endforeach; ?>
                </div>
                <small style="margin-top:8px;display:block">저작권 문구는 아래에서 설정하며, 어떤 유형이든 항상 맨 아래에 표시됩니다.</small>
            </div>
            <style>
            .ft-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
            .ft-type-opt{display:flex;flex-direction:column;gap:6px;padding:16px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;background:#fff;transition:all .15s;position:relative}
            .ft-type-opt:hover{border-color:#f9a8d4;background:#fef7fb}
            .ft-type-opt input[type="radio"]{position:absolute;opacity:0;pointer-events:none;width:0;height:0}
            .ft-type-opt.on{border-color:#ec4899;background:#fdf2f8}
            .ft-type-icon{color:#94a3b8;display:flex;align-items:center}
            .ft-type-opt.on .ft-type-icon{color:#ec4899}
            .ft-type-title{font-size:14px;color:#0f172a}
            .ft-type-opt.on .ft-type-title{color:#be185d}
            .ft-type-desc{color:#64748b;font-size:12px;line-height:1.5}
            @media(max-width:640px){.ft-type-grid{grid-template-columns:1fr}}
            </style>
        </div>
    </div>

    <div class="card" id="ftCardBiz" style="<?= $_ftTypeVal!=='biz'?'display:none':'' ?>">
        <div class="card-header"><h2>사업자 정보 (푸터 표시)</h2></div>
        <div class="card-body">
            <p style="font-size:13px;color:#64748b;margin-bottom:16px;padding:10px 14px;background:#f0f9ff;border-left:3px solid #0ea5e9;border-radius:4px">
                아래 정보는 사이트 하단(푸터)에 자동으로 표시됩니다. <strong>본인의 정보로 변경해 주세요.</strong><br>
                비워두면 푸터에서 해당 항목이 숨겨집니다. (전자상거래법 제13조 기준 — 유료 서비스 운영 시 필수 기재)
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>상호 / 회사명</label>
                    <input type="text" name="biz_company" value="<?= nb_e($s['biz_company'] ?? '(주)누리보드') ?>" placeholder="(주)회사명">
                </div>
                <div class="form-group">
                    <label>대표자 성명</label>
                    <input type="text" name="biz_ceo" value="<?= nb_e($s['biz_ceo'] ?? '홍길동') ?>" placeholder="홍길동">
                </div>
            </div>
            <div class="form-group">
                <label>사업장 주소</label>
                <input type="text" name="biz_address" value="<?= nb_e($s['biz_address'] ?? '서울특별시 강남구 테헤란로 123, 4층') ?>" placeholder="도로명 주소">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>사업자등록번호</label>
                    <input type="text" name="biz_reg_number" value="<?= nb_e($s['biz_reg_number'] ?? '123-45-67890') ?>" placeholder="000-00-00000">
                    <small>국세청 사업자정보 확인 링크가 자동 생성됩니다</small>
                </div>
                <div class="form-group">
                    <label>통신판매업 신고번호</label>
                    <input type="text" name="biz_online_number" value="<?= nb_e($s['biz_online_number'] ?? '제2026-서울강남-0000호') ?>" placeholder="제0000-지역-0000호">
                    <small>유료 서비스 미운영 시 비워두면 숨겨집니다</small>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>대표 전화</label>
                    <input type="text" name="biz_phone" value="<?= nb_e($s['biz_phone'] ?? '02-1234-5678') ?>" placeholder="02-0000-0000">
                </div>
                <div class="form-group">
                    <label>대표 이메일</label>
                    <input type="email" name="biz_email" value="<?= nb_e($s['biz_email'] ?? 'contact@example.com') ?>" placeholder="contact@example.com">
                </div>
            </div>
            <div class="form-group">
                <label>개인정보보호책임자</label>
                <input type="text" name="biz_privacy_officer" value="<?= nb_e($s['biz_privacy_officer'] ?? '홍길동 (privacy@example.com)') ?>" placeholder="홍길동 (privacy@example.com)">
                <small>개인정보보호법상 회원가입을 받는 사이트는 필수 표기</small>
            </div>
        </div>
    </div>

    <div class="card" id="ftCardCustom" style="<?= $_ftTypeVal!=='custom'?'display:none':'' ?>">
        <div class="card-header"><h2>커스텀 푸터 HTML</h2></div>
        <div class="card-body">
            <p style="font-size:13px;color:#64748b;margin-bottom:16px;padding:10px 14px;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px">
                자유롭게 HTML 로 작성하세요. <code>&lt;a&gt;</code>, <code>&lt;p&gt;</code>, <code>&lt;div&gt;</code>, <code>&lt;img&gt;</code> 등 안전한 태그만 허용됩니다.<br>
                <strong>주의:</strong> 회원가입을 받는 사이트는 개인정보처리방침 링크가 반드시 있어야 합니다.
            </p>
            <div class="form-group">
                <label>푸터 HTML</label>
                <textarea name="footer_custom_html" rows="10" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical;font-family:'JetBrains Mono',Consolas,monospace;line-height:1.6" placeholder="<div style='text-align:center;padding:20px'>
  <p>문의: contact@example.com</p>
  <p><a href='/terms'>이용약관</a> · <a href='/privacy'>개인정보처리방침</a></p>
</div>"><?= nb_e($s['footer_custom_html'] ?? '') ?></textarea>
                <small>기본 컨테이너(<code>.container</code>) 안에 렌더링됩니다. 스타일은 인라인 또는 태마 CSS 활용</small>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>저작권 문구 (공통)</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>저작권 문구</label>
                <input type="text" name="biz_copyright" value="<?= nb_e($s['biz_copyright'] ?? '') ?>" placeholder="비워두면 © 2026 사이트제목. All rights reserved. 자동 생성">
                <small>푸터 맨 아래 © 문구. 어떤 푸터 유형이든 항상 표시됩니다. (비워두면 자동 생성)</small>
            </div>
        </div>
    </div>

    <div style="margin-bottom:20px"><button type="submit" class="btn btn-primary btn-lg">전체 저장</button></div>

    <div class="card">
        <div class="card-header"><h2>약관 페이지 내용</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>이용약관 본문</label>
                <textarea name="footer_terms" rows="8" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical;font-family:monospace"><?= nb_e($s['footer_terms'] ?? '') ?></textarea>
                <small>비워두면 <code>/terms</code> 페이지에 기본 약관 템플릿이 자동 표시됩니다. HTML 사용 가능</small>
            </div>
            <div class="form-group">
                <label>개인정보처리방침 본문</label>
                <textarea name="footer_privacy" rows="8" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical;font-family:monospace"><?= nb_e($s['footer_privacy'] ?? '') ?></textarea>
                <small>비워두면 <code>/privacy</code> 페이지에 기본 방침 템플릿이 자동 표시됩니다. HTML 사용 가능</small>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">전체 저장</button>
</form>

<script>
function toggleFooterType(v){
    document.getElementById('ftCardBiz').style.display = (v==='biz') ? '' : 'none';
    document.getElementById('ftCardCustom').style.display = (v==='custom') ? '' : 'none';
    document.querySelectorAll('.ft-type-opt').forEach(function(l){
        var r = l.querySelector('input[name="footer_type"]');
        l.classList.toggle('on', r && r.value === v);
    });
}
function uploadSiteImage(type,inputId){var file=document.getElementById(inputId).files[0];if(!file)return;var data=new FormData();data.append('action','upload_site_image');data.append('type',type);data.append('file',file);ajaxPost(data).then(function(res){if(res.success){alert('업로드 완료!');location.reload()}else{alert(res.message||'업로드 실패')}})}
function deleteSiteImage(type){if(!confirm('정말 삭제하시겠습니까?'))return;var data=new FormData();data.append('action','delete_site_image');data.append('type',type);ajaxPost(data).then(function(res){if(res.success){alert('삭제되었습니다.');location.reload()}else{alert(res.message||'삭제 실패')}})}
function showSaveToast(){var t=document.getElementById('saveToast');t.style.display='block';t.style.opacity='1';clearTimeout(window._toastTimer);window._toastTimer=setTimeout(function(){t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(function(){t.style.display='none';t.style.transition=''},500)},2500)}
function saveSettings(e){e.preventDefault();var form=document.getElementById('settingsForm');var data=new FormData();data.append('action','save_settings');form.querySelectorAll('input[name],select[name],textarea[name]').forEach(function(el){if(el.type==='checkbox'){data.append('settings['+el.name+']',el.checked?'1':'0')}else if(el.type==='radio'){if(el.checked){data.append('settings['+el.name+']',el.value)}}else{data.append('settings['+el.name+']',el.value)}});ajaxPost(data).then(function(res){if(res.success){showSaveToast()}});return false}

</script>

<?php adminFooter(); ?>

<script>
nbColorPalette('titleColor', 'titleColorPreview', function(c){ document.getElementById('titleColorLabel').textContent=c; });
nbColorPalette('headerBgColor', 'headerBgPreview', function(c){ document.getElementById('headerBgLabel').textContent=c; });
nbColorPalette('navBgColor', 'navBgPreview', function(c){ document.getElementById('navBgLabel').textContent=c; });
nbColorPalette('navTextColor', 'navTextPreview', function(c){ document.getElementById('navTextLabel').textContent=c; });
</script>

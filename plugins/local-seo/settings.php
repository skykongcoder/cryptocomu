<?php
/**
 * 지역 비즈니스 SEO 부스터 — 설정 페이지
 */

$cfg = _lseo_load_cfg();
$msg = '';
$msg_type = 'ok';

// ── 설정 저장 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_save'])) {
    $cfg['enabled']         = isset($_POST['enabled']);
    $cfg['business_type']   = trim($_POST['business_type']    ?? 'LocalBusiness');
    $cfg['business_name']   = trim($_POST['business_name']    ?? '');
    $cfg['description']     = trim($_POST['description']      ?? '');
    $cfg['phone']           = trim($_POST['phone']            ?? '');
    $cfg['email']           = trim($_POST['email']            ?? '');
    $cfg['address']         = trim($_POST['address']          ?? '');
    $cfg['address_region']  = trim($_POST['address_region']   ?? '');
    $cfg['address_locality']= trim($_POST['address_locality'] ?? '');
    $cfg['postal_code']     = trim($_POST['postal_code']      ?? '');
    $cfg['country']         = trim($_POST['country']          ?? 'KR');
    $cfg['latitude']        = trim($_POST['latitude']         ?? '');
    $cfg['longitude']       = trim($_POST['longitude']        ?? '');
    $cfg['logo_url']        = trim($_POST['logo_url']         ?? '');
    $cfg['image_url']       = trim($_POST['image_url']        ?? '');
    $cfg['price_range']     = trim($_POST['price_range']      ?? '$$');
    $cfg['social_facebook'] = trim($_POST['social_facebook']  ?? '');
    $cfg['social_instagram']= trim($_POST['social_instagram'] ?? '');
    $cfg['social_youtube']  = trim($_POST['social_youtube']   ?? '');
    $cfg['show_footer_nap'] = isset($_POST['show_footer_nap']);
    $cfg['show_open_status']= isset($_POST['show_open_status']);
    $cfg['inject_schema']   = isset($_POST['inject_schema']);
    $cfg['inject_map']      = isset($_POST['inject_map']);
    $cfg['map_zoom']        = max(10, min(20, (int)($_POST['map_zoom'] ?? 16)));

    $day_keys = ['Mo','Tu','We','Th','Fr','Sa','Su'];
    foreach ($day_keys as $dk) {
        $cfg['hours'][$dk] = [
            'open'   => trim($_POST["hours_{$dk}_open"]  ?? '09:00'),
            'close'  => trim($_POST["hours_{$dk}_close"] ?? '18:00'),
            'closed' => isset($_POST["hours_{$dk}_closed"]),
        ];
    }

    _lseo_save_cfg($cfg);
    $cfg = _lseo_load_cfg();
    $msg = '설정이 저장되었습니다. 아래 "구글 Rich Results 테스트"에서 정상 인식되는지 검증해보세요.';
}

$types = _lseo_business_types();
$preview_jsonld = _lseo_build_jsonld($cfg);
$site_url = function_exists('nb_setting') ? nb_setting('site_url', '') : '';
?>

<style>
.ls-wrap { max-width: 760px; font-family: -apple-system, sans-serif; }
.ls-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.ls-card h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; letter-spacing: .5px; }
.ls-row { margin-bottom: 18px; }
.ls-row > label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; }
.ls-row .req { color: #dc2626; margin-left: 3px; font-weight: 700; }
.ls-input, .ls-select, .ls-textarea {
    width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px;
    font-size: 14px; box-sizing: border-box; font-family: inherit;
}
.ls-input:focus, .ls-select:focus, .ls-textarea:focus {
    outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.12);
}
.ls-textarea { min-height: 70px; resize: vertical }
.ls-help {
    font-size: 12px; color: #475569; margin: 6px 0 0; line-height: 1.7;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 10px 12px;
}
.ls-help strong { color: #16a34a; }
.ls-help-warn {
    font-size: 12px; color: #92400e; margin: 6px 0 0; line-height: 1.7;
    background: #fefce8; border: 1px solid #fde68a; border-radius: 6px;
    padding: 10px 12px;
}
.ls-help-step { margin: 6px 0 0 18px; padding: 0; }
.ls-help-step li { margin-bottom: 3px; }
.ls-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.ls-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
.ls-btn { padding: 10px 28px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.ls-btn:hover { background: #15803d; }
.ls-msg-ok  { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.ls-msg-err { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 500; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.ls-check { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px; }
.ls-check:hover { background: #f8fafc; }
.ls-check input { margin-top: 2px; accent-color: #22c55e; width: 15px; height: 15px; flex-shrink: 0; }
.ls-check-title { font-size: 13px; font-weight: 600; color: #1e293b; }
.ls-check-sub { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.6; }
.ls-hours-row { display: grid; grid-template-columns: 50px 1fr 1fr 100px; gap: 10px; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.ls-hours-row:last-child { border-bottom: none; }
.ls-hours-day { font-size: 13px; font-weight: 700; color: #475569; }
.ls-hours-row input[type=time] { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 13px; width: 100%; }
.ls-hours-closed { font-size: 12px; display: flex; align-items: center; gap: 5px; color: #64748b; }
.ls-jsonld-preview {
    background: #0f172a; color: #94a3b8; padding: 16px; border-radius: 8px;
    font-family: 'Consolas', 'Monaco', monospace; font-size: 11px;
    max-height: 320px; overflow: auto; white-space: pre-wrap; word-break: break-all;
}
.ls-link { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 13px; font-weight: 600; margin-right: 8px; margin-top: 8px; }
.ls-link:hover { background: #f8fafc; }
.ls-section-intro {
    font-size: 13px; color: #475569; line-height: 1.7;
    background: #f8fafc; border-left: 3px solid #16a34a;
    padding: 10px 14px; margin-bottom: 18px; border-radius: 0 6px 6px 0;
}
</style>

<div class="ls-wrap">

<?php if ($msg): ?>
<div class="ls-msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- 동작 방식 안내 -->
<div class="ls-card" style="background:#f0fdf4;border-color:#bbf7d0">
    <h2 style="border-color:#bbf7d0;color:#15803d">이 플러그인은 무엇을 하나요?</h2>
    <div style="font-size:13px;color:#374151;line-height:1.9">
        <strong>구글에서 "강남 치과", "홍대 카페" 같은 지역 검색을 하면</strong>, 검색결과 상단에 지도와 함께 사업자 3곳이 카드 형태로 나옵니다. 이걸 <strong>"로컬 팩"</strong>이라고 합니다.
        <br><br>
        이 플러그인은 사업자 정보를 구글이 정확히 인식할 수 있게 만들어 <strong>로컬 팩에 노출되도록 도와줍니다.</strong>
        <br><br>
        <strong style="color:#15803d">기대 효과</strong>
        <ol style="margin:6px 0 0;padding-left:20px;line-height:1.8">
            <li>구글 지도와 함께 사업자 정보가 검색결과에 노출</li>
            <li>"지역명 + 업종" 키워드 클릭률 2~3배 상승</li>
            <li>모든 페이지에 일관된 사업자 정보 표시 (구글 신뢰도 향상)</li>
            <li>방문자가 사이트에서 영업시간, 위치, 전화번호를 즉시 확인</li>
        </ol>
        <br>
        <strong style="color:#dc2626">중요:</strong> 이 플러그인만으로는 부족합니다. <strong>구글 비즈니스 프로필</strong>(google.com/business)에도 같은 정보로 등록해야 효과가 극대화됩니다.
    </div>
</div>

<form method="POST">
<input type="hidden" name="plugin_save" value="1">

<!-- 활성화 -->
<div class="ls-card">
    <h2>1단계 — 플러그인 켜기</h2>
    <label class="ls-check">
        <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
        <div>
            <div class="ls-check-title">플러그인 활성화</div>
            <div class="ls-check-sub">
                체크 표시를 한 후 아래 사업자 정보를 입력하고 저장하면 동작합니다.<br>
                사업자명이 비어 있으면 활성화해도 동작하지 않습니다.
            </div>
        </div>
    </label>
</div>

<!-- 사업자 기본 정보 -->
<div class="ls-card">
    <h2>2단계 — 사업자 기본 정보 입력</h2>
    <div class="ls-section-intro">
        구글에 알려줄 가장 기본적인 정보입니다. 정확하게 입력할수록 검색결과에 잘 노출됩니다.
    </div>

    <div class="ls-row">
        <label>사업 종류 (카테고리)<span class="req">필수</span></label>
        <select name="business_type" class="ls-select">
            <?php foreach ($types as $val => $label): ?>
                <option value="<?= $val ?>" <?= $cfg['business_type'] === $val ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?> (<?= $val ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="ls-help">
            <strong>왜 중요한가요?</strong> 구글은 "치과"와 "카페"를 다른 종류로 분류합니다. 정확한 카테고리를 선택하면 해당 업종 검색에 노출됩니다.<br>
            <strong>예시</strong> 김밥집 → "식당", 학원 → "학원/교육기관", 미용실 → "미용실/뷰티샵"<br>
            정확히 맞는 항목이 없으면 가장 가까운 것을 선택하세요.
        </div>
    </div>

    <div class="ls-row">
        <label>사업자명 / 상호<span class="req">필수</span></label>
        <input type="text" name="business_name" class="ls-input"
               value="<?= htmlspecialchars($cfg['business_name'] ?? '') ?>"
               placeholder="예: 강남 누리치과">
        <div class="ls-help">
            <strong>주의</strong> 간판, 사업자등록증, 네이버 플레이스, 구글 비즈니스 프로필에 표시된 이름과 <strong>완전히 동일하게</strong> 입력하세요.<br>
            구글은 여러 곳의 사업자명이 같아야 "동일한 사업장"으로 인식합니다 (NAP 일관성).<br>
            잘못된 예: "누리치과" / "강남누리치과" / "(주)누리치과" 처럼 표기 다르게 쓰면 다른 사업장으로 인식됩니다.
        </div>
    </div>

    <div class="ls-row">
        <label>한 줄 소개</label>
        <textarea name="description" class="ls-textarea"
                  placeholder="예: 강남역 1번 출구 도보 3분 거리의 임플란트 전문 치과입니다. 30년 경력 원장이 직접 진료합니다."><?= htmlspecialchars($cfg['description'] ?? '') ?></textarea>
        <div class="ls-help">
            <strong>작성 팁</strong> 어떤 곳인지 + 위치 특징 + 차별점을 50~150자 정도로.<br>
            이 문장이 구글 검색결과의 사업자 카드 설명으로 표시될 수 있습니다.
        </div>
    </div>

    <div class="ls-grid-2">
        <div class="ls-row">
            <label>대표 전화번호</label>
            <input type="tel" name="phone" class="ls-input"
                   value="<?= htmlspecialchars($cfg['phone'] ?? '') ?>"
                   placeholder="02-1234-5678">
            <div class="ls-help">
                <strong>형식</strong> 02-1234-5678 또는 010-1234-5678 처럼 하이픈(-) 포함해서 입력.<br>
                방문자가 클릭하면 바로 전화 걸기가 됩니다 (모바일).
            </div>
        </div>
        <div class="ls-row">
            <label>이메일</label>
            <input type="email" name="email" class="ls-input"
                   value="<?= htmlspecialchars($cfg['email'] ?? '') ?>"
                   placeholder="info@example.com">
            <div class="ls-help">
                선택 사항. 비워두면 표시되지 않습니다.
            </div>
        </div>
    </div>

    <div class="ls-row">
        <label>가격대</label>
        <select name="price_range" class="ls-select" style="max-width:240px">
            <option value="$"    <?= $cfg['price_range']==='$'    ? 'selected':'' ?>>$ — 저렴 (1만원 미만)</option>
            <option value="$$"   <?= $cfg['price_range']==='$$'   ? 'selected':'' ?>>$$ — 보통 (1~3만원)</option>
            <option value="$$$"  <?= $cfg['price_range']==='$$$'  ? 'selected':'' ?>>$$$ — 고급 (3~10만원)</option>
            <option value="$$$$" <?= $cfg['price_range']==='$$$$' ? 'selected':'' ?>>$$$$ — 프리미엄 (10만원 이상)</option>
        </select>
        <div class="ls-help">
            구글이 가격대를 표시할 때 사용합니다. 식당이라면 1인 평균 식사 가격 기준으로 선택하세요.
        </div>
    </div>
</div>

<!-- 주소 -->
<div class="ls-card">
    <h2>3단계 — 주소 정보</h2>
    <div class="ls-section-intro">
        지역 검색의 핵심입니다. 정확한 주소가 있어야 "근처 + 업종" 검색에 노출됩니다.
    </div>

    <div class="ls-grid-3">
        <div class="ls-row">
            <label>시 / 도</label>
            <input type="text" name="address_region" class="ls-input"
                   value="<?= htmlspecialchars($cfg['address_region'] ?? '') ?>"
                   placeholder="서울특별시">
            <div class="ls-help">
                예) 서울특별시, 경기도, 부산광역시
            </div>
        </div>
        <div class="ls-row">
            <label>시 / 군 / 구</label>
            <input type="text" name="address_locality" class="ls-input"
                   value="<?= htmlspecialchars($cfg['address_locality'] ?? '') ?>"
                   placeholder="강남구">
            <div class="ls-help">
                예) 강남구, 수원시, 해운대구
            </div>
        </div>
        <div class="ls-row">
            <label>우편번호</label>
            <input type="text" name="postal_code" class="ls-input"
                   value="<?= htmlspecialchars($cfg['postal_code'] ?? '') ?>"
                   placeholder="06234">
            <div class="ls-help">
                5자리 새 우편번호. <a href="https://www.epost.go.kr/search/zipcode/areacdAddressList.jsp" target="_blank" style="color:#16a34a">우편번호 찾기</a>
            </div>
        </div>
    </div>

    <div class="ls-row">
        <label>도로명 주소 (상세)</label>
        <input type="text" name="address" class="ls-input"
               value="<?= htmlspecialchars($cfg['address'] ?? '') ?>"
               placeholder="테헤란로 123, 5층 501호">
        <div class="ls-help">
            <strong>형식</strong> "도로명 + 건물번호 + 상세" (시/구는 위에서 이미 입력했으니 빼고)<br>
            예) 테헤란로 123, 5층 / 강남대로 456, 101호 / 봉은사로 789
        </div>
    </div>

    <div class="ls-grid-2">
        <div class="ls-row">
            <label>위도 (Latitude)</label>
            <input type="text" name="latitude" class="ls-input"
                   value="<?= htmlspecialchars($cfg['latitude'] ?? '') ?>"
                   placeholder="37.498095">
        </div>
        <div class="ls-row">
            <label>경도 (Longitude)</label>
            <input type="text" name="longitude" class="ls-input"
                   value="<?= htmlspecialchars($cfg['longitude'] ?? '') ?>"
                   placeholder="127.027610">
        </div>
    </div>

    <div class="ls-help-warn">
        <strong>위도/경도가 뭔가요?</strong> 지구상의 정확한 위치를 표시하는 좌표입니다. 입력하면 구글 지도에서 정확히 핀이 꽂힙니다.<br><br>
        <strong>찾는 방법 (1분 소요)</strong>
        <ol class="ls-help-step">
            <li>구글 지도(<a href="https://www.google.com/maps" target="_blank" style="color:#92400e;font-weight:700">maps.google.com</a>) 접속</li>
            <li>주소 입력해서 사업장 위치 찾기</li>
            <li>사업장 위치를 마우스 우클릭</li>
            <li>맨 위에 나오는 숫자 두 개 (예: 37.498095, 127.027610) 클릭하면 자동 복사됨</li>
            <li>위 두 칸에 각각 첫 번째 숫자(위도)와 두 번째 숫자(경도) 붙여넣기</li>
        </ol>
        <strong>입력하지 않으면?</strong> 주소 텍스트로 대략 위치만 표시됩니다. 정확도를 위해 꼭 입력하는 것을 권장합니다.
    </div>
</div>

<!-- 영업시간 -->
<div class="ls-card">
    <h2>4단계 — 영업시간</h2>
    <div class="ls-section-intro">
        요일별 영업시간을 입력하세요. 구글이 검색결과에 "영업중", "마감", "곧 마감" 같은 표시를 자동으로 보여줍니다.
    </div>

    <?php
    $day_kr = ['Mo'=>'월요일','Tu'=>'화요일','We'=>'수요일','Th'=>'목요일','Fr'=>'금요일','Sa'=>'토요일','Su'=>'일요일'];
    foreach ($day_kr as $dk => $label):
        $h = $cfg['hours'][$dk] ?? ['open'=>'09:00','close'=>'18:00','closed'=>false];
    ?>
    <div class="ls-hours-row">
        <div class="ls-hours-day"><?= $label ?></div>
        <input type="time" name="hours_<?= $dk ?>_open"  value="<?= htmlspecialchars($h['open']  ?? '09:00') ?>" <?= !empty($h['closed']) ? 'disabled' : '' ?>>
        <input type="time" name="hours_<?= $dk ?>_close" value="<?= htmlspecialchars($h['close'] ?? '18:00') ?>" <?= !empty($h['closed']) ? 'disabled' : '' ?>>
        <label class="ls-hours-closed">
            <input type="checkbox" name="hours_<?= $dk ?>_closed" value="1" <?= !empty($h['closed']) ? 'checked' : '' ?>
                   onchange="this.closest('.ls-hours-row').querySelectorAll('input[type=time]').forEach(i=>i.disabled=this.checked)">
            휴무
        </label>
    </div>
    <?php endforeach; ?>

    <div class="ls-help" style="margin-top:14px">
        <strong>입력 방법</strong> 시간 칸을 클릭하면 시간 선택기가 나옵니다. 24시간제로 선택하세요.<br>
        <strong>휴무일</strong> 영업하지 않는 날은 "휴무" 체크 (예: 일요일 휴무라면 일요일 휴무 체크)<br>
        <strong>점심시간이 있는 경우</strong> 현재 버전은 단일 영업시간만 지원합니다. 점심시간(예: 12-13시 휴게)은 한 줄 소개에 별도 안내해주세요.
    </div>
</div>

<!-- 이미지/소셜 -->
<div class="ls-card">
    <h2>5단계 — 이미지 & 소셜 링크 (선택)</h2>
    <div class="ls-section-intro">
        선택 사항이지만 입력하면 구글이 사업자를 더 신뢰하고, 검색결과에 이미지가 함께 노출될 수 있습니다.
    </div>

    <div class="ls-grid-2">
        <div class="ls-row">
            <label>로고 이미지 URL</label>
            <input type="url" name="logo_url" class="ls-input"
                   value="<?= htmlspecialchars($cfg['logo_url'] ?? '') ?>"
                   placeholder="https://nuribd.com/uploads/logo.png">
            <div class="ls-help">
                <strong>크기</strong> 정사각형 권장, 최소 112×112px<br>
                <strong>입력 방법</strong> 사이트에 이미지 업로드한 후 그 URL을 복사해서 붙여넣기
            </div>
        </div>
        <div class="ls-row">
            <label>대표 이미지 URL</label>
            <input type="url" name="image_url" class="ls-input"
                   value="<?= htmlspecialchars($cfg['image_url'] ?? '') ?>"
                   placeholder="https://nuribd.com/uploads/store.jpg">
            <div class="ls-help">
                <strong>크기</strong> 가로:세로 = 16:9 권장 (1200×675px)<br>
                매장 외관, 내부 인테리어, 대표 메뉴 등의 사진
            </div>
        </div>
    </div>

    <div class="ls-row">
        <label>페이스북 페이지 URL</label>
        <input type="url" name="social_facebook" class="ls-input"
               value="<?= htmlspecialchars($cfg['social_facebook'] ?? '') ?>"
               placeholder="https://facebook.com/yourpage">
        <div class="ls-help">
            페이스북에 사업자 페이지가 있다면 전체 URL 입력. 비어있어도 됨.
        </div>
    </div>
    <div class="ls-row">
        <label>인스타그램 URL</label>
        <input type="url" name="social_instagram" class="ls-input"
               value="<?= htmlspecialchars($cfg['social_instagram'] ?? '') ?>"
               placeholder="https://instagram.com/youraccount">
        <div class="ls-help">
            인스타그램 계정 URL. 형식: https://instagram.com/계정아이디
        </div>
    </div>
    <div class="ls-row">
        <label>유튜브 채널 URL</label>
        <input type="url" name="social_youtube" class="ls-input"
               value="<?= htmlspecialchars($cfg['social_youtube'] ?? '') ?>"
               placeholder="https://youtube.com/@yourchannel">
        <div class="ls-help">
            유튜브 채널이 있다면 입력. 없으면 비워두기.
        </div>
    </div>

    <div class="ls-help" style="margin-top:6px">
        <strong>왜 소셜 링크를 입력하나요?</strong> 구글은 같은 사업자가 여러 플랫폼(페북, 인스타, 유튜브)에 일관되게 존재하면 "진짜 사업자"로 신뢰합니다. 이를 sameAs 시그널이라고 합니다.
    </div>
</div>

<!-- 화면 표시 옵션 -->
<div class="ls-card">
    <h2>6단계 — 화면 표시 옵션</h2>
    <div class="ls-section-intro">
        사이트의 어떤 위치에 사업자 정보를 자동으로 보여줄지 선택합니다.
    </div>

    <div class="ls-row">
        <label class="ls-check">
            <input type="checkbox" name="inject_schema" value="1" <?= !empty($cfg['inject_schema']) ? 'checked' : '' ?>>
            <div>
                <div class="ls-check-title">구조화 데이터(JSON-LD) 자동 삽입 <span style="color:#dc2626;font-size:11px;font-weight:700;margin-left:4px">필수 — 반드시 켜세요</span></div>
                <div class="ls-check-sub">
                    모든 페이지의 HTML &lt;head&gt; 영역에 사업자 정보가 구글이 읽을 수 있는 형식으로 자동 삽입됩니다.<br>
                    <strong style="color:#dc2626">이 옵션이 꺼지면 구글이 사업자 정보를 인식하지 못해 로컬 팩 노출이 안 됩니다.</strong> 화면에 보이지는 않지만 SEO 효과의 핵심입니다.
                </div>
            </div>
        </label>
    </div>

    <div class="ls-row">
        <label class="ls-check">
            <input type="checkbox" name="show_footer_nap" value="1" <?= !empty($cfg['show_footer_nap']) ? 'checked' : '' ?>>
            <div>
                <div class="ls-check-title">사이트 하단(푸터)에 사업자 정보 자동 표시</div>
                <div class="ls-check-sub">
                    모든 페이지 맨 아래에 "상호 / 주소 / 전화번호"가 자동으로 표시됩니다.<br>
                    이를 NAP(Name, Address, Phone)이라고 하며, 모든 페이지에 동일하게 노출되어야 구글이 신뢰합니다.<br>
                    이미 푸터에 정보가 있다면 중복 표시될 수 있으니 확인 후 켜세요.
                </div>
            </div>
        </label>
    </div>

    <div class="ls-row">
        <label class="ls-check">
            <input type="checkbox" name="show_open_status" value="1" <?= !empty($cfg['show_open_status']) ? 'checked' : '' ?>>
            <div>
                <div class="ls-check-title">현재 영업중/마감 자동 표시</div>
                <div class="ls-check-sub">
                    푸터 사업자명 옆에 현재 시각 기준으로 "영업중 · 22:00 마감", "영업 종료 · 화 09:00 영업 시작" 같은 실시간 상태가 표시됩니다.<br>
                    위 "푸터 NAP 표시"가 켜져 있을 때만 작동합니다.
                </div>
            </div>
        </label>
    </div>

    <div class="ls-row">
        <label class="ls-check">
            <input type="checkbox" name="inject_map" value="1" <?= !empty($cfg['inject_map']) ? 'checked' : '' ?>>
            <div>
                <div class="ls-check-title">사이트 하단에 구글 지도 자동 삽입</div>
                <div class="ls-check-sub">
                    모든 페이지 푸터에 사업장 위치가 표시된 구글 지도가 표시됩니다.<br>
                    구글 지도 API 키나 결제 카드 등록이 필요 없습니다 (iframe 임베드 방식, 완전 무료).<br>
                    위에서 입력한 위도/경도(또는 주소)를 기준으로 자동으로 지도가 표시됩니다.
                </div>
            </div>
        </label>
    </div>

    <div class="ls-row">
        <label>지도 줌 레벨</label>
        <input type="number" name="map_zoom" class="ls-input" style="max-width:120px"
               value="<?= (int)($cfg['map_zoom'] ?? 16) ?>" min="10" max="20">
        <div class="ls-help">
            10(매우 넓게, 도시 전체) ~ 20(매우 좁게, 건물 단위)<br>
            <strong>권장값 16</strong> — 주변 도로와 건물이 잘 보이는 적당한 줌
        </div>
    </div>
</div>

<!-- 저장 버튼 -->
<div class="ls-card" style="padding:18px 24px">
    <button type="submit" class="ls-btn">설정 저장</button>
</div>

</form>

<!-- 미리보기 -->
<?php if ($preview_jsonld): ?>
<div class="ls-card">
    <h2>구글에 전달되는 정보 (미리보기)</h2>
    <p style="font-size:13px;color:#475569;margin-bottom:12px;line-height:1.7">
        아래 코드는 사이트의 모든 페이지 &lt;head&gt;에 자동으로 삽입됩니다. <strong>구글이 이 코드를 읽고 사업자를 인식합니다.</strong> 직접 수정할 필요는 없으며, 위에서 정보를 입력하면 자동으로 생성됩니다.
    </p>
    <div class="ls-jsonld-preview"><?= htmlspecialchars($preview_jsonld) ?></div>

    <div style="margin-top:16px">
        <a class="ls-link" href="https://search.google.com/test/rich-results?url=<?= urlencode($site_url) ?>" target="_blank">
            구글 Rich Results 테스트 — 정상 인식 확인
        </a>
        <a class="ls-link" href="https://validator.schema.org/?url=<?= urlencode($site_url) ?>" target="_blank">
            Schema.org 검증
        </a>
        <a class="ls-link" href="https://www.google.com/business/" target="_blank">
            구글 비즈니스 프로필 등록 (필수)
        </a>
    </div>
    <div class="ls-help" style="margin-top:14px">
        <strong>다음 할 일 (반드시 진행)</strong>
        <ol class="ls-help-step">
            <li><strong>위 "구글 Rich Results 테스트"</strong> 클릭 → 사업자 정보가 정상 인식되는지 확인 (오류 없으면 OK)</li>
            <li><strong>"구글 비즈니스 프로필 등록"</strong> 클릭 → 같은 정보로 등록 (이게 진짜 핵심. 등록 없이는 로컬 팩 노출 안 됨)</li>
            <li>등록 후 <strong>1~2주 기다리기</strong> → 구글이 정보를 검토하고 검색결과에 반영</li>
            <li>"지역명 + 업종" 키워드로 검색해서 노출 확인</li>
        </ol>
    </div>
</div>
<?php else: ?>
<div class="ls-card" style="background:#fefce8;border-color:#fde68a">
    <p style="font-size:13px;color:#92400e;margin:0;line-height:1.7">
        <strong>아직 사업자명이 입력되지 않았습니다.</strong> 위 "2단계 — 사업자 기본 정보"에서 사업자명을 입력하고 저장하면, 구글에 전달될 코드가 자동 생성되어 여기 표시됩니다.
    </p>
</div>
<?php endif; ?>

</div>

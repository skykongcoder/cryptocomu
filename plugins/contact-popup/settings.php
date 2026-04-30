<?php
/**
 * 카카오/텔레그램 상담 플로팅 버튼 - 설정 페이지
 */

$_cpConfigFile = __DIR__ . '/config.json';
$_cpConfigRaw = file_exists($_cpConfigFile) ? json_decode(file_get_contents($_cpConfigFile), true) : [];
if (!is_array($_cpConfigRaw)) $_cpConfigRaw = [];

$_cpConfig = array_merge([
    'kakao_enabled' => '0',
    'kakao_url' => '',
    'kakao_image' => '',
    'telegram_enabled' => '0',
    'telegram_url' => '',
    'telegram_image' => '',
    'position' => 'bottom-right',
    'size' => 'medium',
    'show_on_admin' => '0',
], $_cpConfigRaw);

// 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cp_save'])) {
    $_cpConfig['kakao_enabled'] = isset($_POST['kakao_enabled']) ? '1' : '0';
    $_cpConfig['kakao_url'] = trim($_POST['kakao_url'] ?? '');
    $_cpConfig['kakao_image'] = trim($_POST['kakao_image'] ?? '');
    $_cpConfig['telegram_enabled'] = isset($_POST['telegram_enabled']) ? '1' : '0';
    $_cpConfig['telegram_url'] = trim($_POST['telegram_url'] ?? '');
    $_cpConfig['telegram_image'] = trim($_POST['telegram_image'] ?? '');
    $_cpConfig['position'] = in_array($_POST['position'] ?? '', ['bottom-right', 'bottom-left', 'middle-right', 'middle-left']) ? $_POST['position'] : 'bottom-right';
    $_cpConfig['size'] = in_array($_POST['size'] ?? '', ['small', 'medium', 'large', 'xlarge']) ? $_POST['size'] : 'medium';
    $_cpConfig['show_on_admin'] = isset($_POST['show_on_admin']) ? '1' : '0';
    file_put_contents($_cpConfigFile, json_encode($_cpConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다. 사이트에서 확인해보세요.</div>';
}

// 플러그인 URL 계산 (프리뷰용)
function _cp_plugin_url() {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $pluginDir = __DIR__;
    if ($docRoot && strpos($pluginDir, $docRoot) === 0) {
        return str_replace('\\', '/', substr($pluginDir, strlen($docRoot)));
    }
    if (preg_match('~(/plugins/[^/]+)$~', str_replace('\\', '/', $pluginDir), $m)) {
        return $m[1];
    }
    return '/plugins/' . basename($pluginDir);
}
$pluginUrl = _cp_plugin_url();
$defaultKakaoImg = $pluginUrl . '/assets/kakao.png';
$defaultTelegramImg = $pluginUrl . '/assets/telegram.png';
?>

<style>
.cp-form-row { display:grid; grid-template-columns:140px 1fr; gap:16px; align-items:center; margin-bottom:16px }
.cp-form-row label.label-title { font-weight:600; color:#334155; font-size:14px }
.cp-form-row input[type="text"], .cp-form-row select { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px }
.cp-form-row small { color:#94a3b8; font-size:12px; grid-column:2 }

.cp-card { background:white; border:1px solid #e2e8f0; border-radius:10px; padding:20px; margin-bottom:16px }
.cp-card.kakao { border-left:4px solid #fee500 }
.cp-card.telegram { border-left:4px solid #0088cc }

.cp-preview-img { width:80px; height:80px; border-radius:12px; object-fit:cover; box-shadow:0 2px 8px rgba(0,0,0,.12) }

.cp-position-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px }
.cp-position-option { border:2px solid #e2e8f0; border-radius:8px; padding:12px; cursor:pointer; display:flex; align-items:center; gap:8px; font-weight:normal; font-size:13px }
.cp-position-option.selected, .cp-position-option:has(input:checked) { border-color:#3b82f6; background:#eff6ff }

.cp-size-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:8px }
.cp-size-option { border:2px solid #e2e8f0; border-radius:8px; padding:10px; cursor:pointer; text-align:center; font-size:13px; font-weight:normal }
.cp-size-option.selected, .cp-size-option:has(input:checked) { border-color:#3b82f6; background:#eff6ff }
</style>

<form method="post">
    <input type="hidden" name="cp_save" value="1">

    <!-- 카카오 설정 -->
    <div class="cp-card kakao">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <img src="<?= htmlspecialchars($defaultKakaoImg) ?>" class="cp-preview-img" alt="카카오">
            <div style="flex:1">
                <h3 style="margin:0;font-size:16px;color:#854d0e">🟡 카카오톡 상담 버튼</h3>
                <label style="display:flex;align-items:center;gap:8px;margin-top:6px;cursor:pointer;font-size:13px">
                    <input type="checkbox" name="kakao_enabled" value="1" <?= $_cpConfig['kakao_enabled'] === '1' ? 'checked' : '' ?>>
                    이 버튼 사이트에 표시
                </label>
            </div>
        </div>

        <div class="cp-form-row">
            <label class="label-title">연결 URL</label>
            <input type="text" name="kakao_url" value="<?= htmlspecialchars($_cpConfig['kakao_url']) ?>" placeholder="https://pf.kakao.com/_xxxx 또는 open.kakao.com/... ">
            <small>카카오톡 채널 URL 또는 오픈채팅 링크</small>
        </div>

        <div class="cp-form-row">
            <label class="label-title">이미지 URL (선택)</label>
            <input type="text" name="kakao_image" value="<?= htmlspecialchars($_cpConfig['kakao_image']) ?>" placeholder="비워두면 기본 이미지 사용">
            <small>직접 만든 이미지 쓰고 싶으면 이미지 URL 입력. 비워두면 기본 제공 이미지 사용.</small>
        </div>
    </div>

    <!-- 텔레그램 설정 -->
    <div class="cp-card telegram">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <img src="<?= htmlspecialchars($defaultTelegramImg) ?>" class="cp-preview-img" alt="텔레그램">
            <div style="flex:1">
                <h3 style="margin:0;font-size:16px;color:#1e40af">🔵 텔레그램 상담 버튼</h3>
                <label style="display:flex;align-items:center;gap:8px;margin-top:6px;cursor:pointer;font-size:13px">
                    <input type="checkbox" name="telegram_enabled" value="1" <?= $_cpConfig['telegram_enabled'] === '1' ? 'checked' : '' ?>>
                    이 버튼 사이트에 표시
                </label>
            </div>
        </div>

        <div class="cp-form-row">
            <label class="label-title">연결 URL</label>
            <input type="text" name="telegram_url" value="<?= htmlspecialchars($_cpConfig['telegram_url']) ?>" placeholder="https://t.me/아이디">
            <small>텔레그램 채널/계정 링크</small>
        </div>

        <div class="cp-form-row">
            <label class="label-title">이미지 URL (선택)</label>
            <input type="text" name="telegram_image" value="<?= htmlspecialchars($_cpConfig['telegram_image']) ?>" placeholder="비워두면 기본 이미지 사용">
            <small>직접 만든 이미지 쓰고 싶으면 이미지 URL 입력.</small>
        </div>
    </div>

    <!-- 공통 옵션: 위치 -->
    <div class="cp-card">
        <h3 style="margin:0 0 12px;font-size:15px">📍 버튼 위치</h3>
        <div class="cp-position-grid">
            <label class="cp-position-option">
                <input type="radio" name="position" value="bottom-right" <?= $_cpConfig['position'] === 'bottom-right' ? 'checked' : '' ?>>
                ↘ 우측 하단 (추천)
            </label>
            <label class="cp-position-option">
                <input type="radio" name="position" value="bottom-left" <?= $_cpConfig['position'] === 'bottom-left' ? 'checked' : '' ?>>
                ↙ 좌측 하단
            </label>
            <label class="cp-position-option">
                <input type="radio" name="position" value="middle-right" <?= $_cpConfig['position'] === 'middle-right' ? 'checked' : '' ?>>
                → 우측 중간
            </label>
            <label class="cp-position-option">
                <input type="radio" name="position" value="middle-left" <?= $_cpConfig['position'] === 'middle-left' ? 'checked' : '' ?>>
                ← 좌측 중간
            </label>
        </div>
    </div>

    <!-- 공통 옵션: 크기 -->
    <div class="cp-card">
        <h3 style="margin:0 0 12px;font-size:15px">📏 버튼 크기</h3>
        <div class="cp-size-grid">
            <label class="cp-size-option">
                <input type="radio" name="size" value="small" <?= $_cpConfig['size'] === 'small' ? 'checked' : '' ?>>
                작게<br><small style="color:#94a3b8">60px</small>
            </label>
            <label class="cp-size-option">
                <input type="radio" name="size" value="medium" <?= $_cpConfig['size'] === 'medium' ? 'checked' : '' ?>>
                보통 (추천)<br><small style="color:#94a3b8">80px</small>
            </label>
            <label class="cp-size-option">
                <input type="radio" name="size" value="large" <?= $_cpConfig['size'] === 'large' ? 'checked' : '' ?>>
                크게<br><small style="color:#94a3b8">100px</small>
            </label>
            <label class="cp-size-option">
                <input type="radio" name="size" value="xlarge" <?= $_cpConfig['size'] === 'xlarge' ? 'checked' : '' ?>>
                아주크게<br><small style="color:#94a3b8">120px</small>
            </label>
        </div>
        <small style="color:#94a3b8;font-size:12px;display:block;margin-top:6px">모바일에서는 자동으로 15px 작게 표시됩니다.</small>
    </div>

    <!-- 기타 옵션 -->
    <div class="cp-card">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
            <input type="checkbox" name="show_on_admin" value="1" <?= $_cpConfig['show_on_admin'] === '1' ? 'checked' : '' ?>>
            관리자 페이지에도 버튼 표시
        </label>
        <small style="color:#94a3b8;font-size:12px;margin-left:24px">기본값: 꺼짐 (관리자 페이지에는 안 보임)</small>
    </div>

    <div style="text-align:right;margin-top:16px">
        <button type="submit" class="btn btn-primary" style="padding:10px 28px;font-size:14px">💾 저장하기</button>
    </div>
</form>

<div style="margin-top:24px;padding:16px;background:#f8fafc;border-radius:8px">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">💡 사용 팁</h4>
    <ul style="font-size:13px;color:#64748b;line-height:2;padding-left:20px">
        <li>카카오톡 채널은 <a href="https://center-pf.kakao.com/login" target="_blank" style="color:#2563eb">카카오톡 채널 관리자센터</a>에서 만들 수 있습니다.</li>
        <li>텔레그램은 <code>https://t.me/아이디</code> 형태로 링크 입력하세요.</li>
        <li>둘 다 켜면 세로로 나란히 표시됩니다 (카카오 위, 텔레그램 아래).</li>
        <li>이미지 URL은 비워두면 기본 제공 상담채널 이미지가 사용됩니다.</li>
        <li>저장 후 사이트 메인 페이지에서 실제 표시를 확인하세요.</li>
    </ul>
</div>

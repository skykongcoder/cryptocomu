<?php
/**
 * 카카오톡 채팅 버튼 - 설정 페이지
 */
$_kcConfigFile = __DIR__ . '/config.json';
$_kcConfigRaw = file_exists($_kcConfigFile) ? json_decode(file_get_contents($_kcConfigFile), true) : [];
if (!is_array($_kcConfigRaw)) $_kcConfigRaw = [];
$_kcConfig = array_merge([
    'kakao_chat_url' => 'https://pf.kakao.com/_example/chat',
    'message' => '카카오톡 상담',
    'bubble_text' => '',
    'bubble_show' => '0',
    'size' => '56',
    'bottom' => '24',
    'right' => '24',
    'hide_admin' => '1',
], $_kcConfigRaw);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kc_save'])) {
    $_kcConfig['kakao_chat_url'] = trim($_POST['kakao_chat_url'] ?? '');
    $_kcConfig['message'] = trim($_POST['message'] ?? '카카오톡 상담');
    $_kcConfig['bubble_text'] = trim($_POST['bubble_text'] ?? '');
    $_kcConfig['bubble_show'] = isset($_POST['bubble_show']) ? '1' : '0';
    $_kcConfig['size'] = max(40, min(80, (int)($_POST['size'] ?? 56)));
    $_kcConfig['bottom'] = max(0, (int)($_POST['bottom'] ?? 24));
    $_kcConfig['right'] = max(0, (int)($_POST['right'] ?? 24));
    $_kcConfig['hide_admin'] = isset($_POST['hide_admin']) ? '1' : '0';
    file_put_contents($_kcConfigFile, json_encode($_kcConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다.</div>';
}
?>
<form method="post">
    <input type="hidden" name="kc_save" value="1">

    <div class="form-group">
        <label>카카오톡 채팅 URL *</label>
        <input type="url" name="kakao_chat_url" value="<?= htmlspecialchars($_kcConfig['kakao_chat_url']) ?>" placeholder="https://pf.kakao.com/_yourpage/chat" required>
        <small>카카오톡 채널의 채팅 URL을 입력하세요. 비워두면 버튼이 표시되지 않습니다.</small>
    </div>

    <div class="form-group">
        <label>버튼 툴팁 텍스트</label>
        <input type="text" name="message" value="<?= htmlspecialchars($_kcConfig['message']) ?>" placeholder="카카오톡 상담">
        <small>버튼에 마우스를 올렸을 때 표시되는 텍스트</small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>말풍선 텍스트</label>
            <input type="text" name="bubble_text" value="<?= htmlspecialchars($_kcConfig['bubble_text']) ?>" placeholder="궁금한 점을 물어보세요!">
            <small>버튼 위에 표시되는 말풍선 (5초 후 자동 숨김)</small>
        </div>
        <div class="form-group" style="flex:0 0 auto;padding-top:28px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="bubble_show" <?= $_kcConfig['bubble_show'] === '1' ? 'checked' : '' ?>>
                말풍선 표시
            </label>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>버튼 크기 (px)</label>
            <input type="number" name="size" value="<?= (int)$_kcConfig['size'] ?>" min="40" max="80" style="width:100px">
        </div>
        <div class="form-group">
            <label>하단 여백 (px)</label>
            <input type="number" name="bottom" value="<?= (int)$_kcConfig['bottom'] ?>" min="0" style="width:100px">
        </div>
        <div class="form-group">
            <label>우측 여백 (px)</label>
            <input type="number" name="right" value="<?= (int)$_kcConfig['right'] ?>" min="0" style="width:100px">
        </div>
    </div>

    <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="hide_admin" <?= $_kcConfig['hide_admin'] === '1' ? 'checked' : '' ?>>
            관리자 페이지에서 숨기기
        </label>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<div style="margin-top:20px;padding:16px;background:#f8fafc;border-radius:8px">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">미리보기</h4>
    <div style="position:relative;background:#1e293b;border-radius:8px;height:180px;overflow:hidden">
        <div style="position:absolute;bottom:<?= (int)$_kcConfig['bottom'] ?>px;right:<?= (int)$_kcConfig['right'] ?>px;display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <?php if ($_kcConfig['bubble_show'] === '1' && $_kcConfig['bubble_text']): ?>
            <div style="background:#fff;color:#333;padding:8px 12px;border-radius:16px;font-size:12px;box-shadow:0 2px 8px rgba(0,0,0,.2)"><?= htmlspecialchars($_kcConfig['bubble_text']) ?></div>
            <?php endif; ?>
            <div style="width:<?= (int)$_kcConfig['size'] ?>px;height:<?= (int)$_kcConfig['size'] ?>px;border-radius:50%;background:#FEE500;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.3)">
                <svg width="<?= round((int)$_kcConfig['size'] * 0.5) ?>" height="<?= round((int)$_kcConfig['size'] * 0.5) ?>" viewBox="0 0 24 24" fill="#3C1E1E">
                    <path d="M12 3C6.48 3 2 6.58 2 10.94c0 2.8 1.86 5.27 4.68 6.67l-1.19 4.44c-.04.16.12.3.27.22l5.14-3.39c.36.03.73.06 1.1.06 5.52 0 10-3.58 10-7.94S17.52 3 12 3z"/>
                </svg>
            </div>
        </div>
        <div style="position:absolute;top:12px;left:16px;color:#94a3b8;font-size:12px">사이트 우측 하단에 이렇게 표시됩니다</div>
    </div>
</div>

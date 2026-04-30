<?php
/**
 * 텔레그램 채팅 버튼 - 설정 페이지
 */
$_tgConfigFile = __DIR__ . '/config.json';
$_tgConfigRaw = file_exists($_tgConfigFile) ? json_decode(file_get_contents($_tgConfigFile), true) : [];
if (!is_array($_tgConfigRaw)) $_tgConfigRaw = [];
$_tgConfig = array_merge([
    'username' => '',
    'message' => '문의하기',
    'bubble_text' => '무엇이든 물어보세요!',
    'bubble_show' => '1',
    'color' => '#0088cc',
    'size' => '56',
    'bottom' => '24',
    'right' => '24',
    'hide_admin' => '1',
], $_tgConfigRaw);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tg_save'])) {
    $_tgConfig['username'] = trim($_POST['username'] ?? '');
    $_tgConfig['message'] = trim($_POST['message'] ?? '문의하기');
    $_tgConfig['bubble_text'] = trim($_POST['bubble_text'] ?? '');
    $_tgConfig['bubble_show'] = isset($_POST['bubble_show']) ? '1' : '0';
    $_tgConfig['color'] = $_POST['color'] ?? '#0088cc';
    $_tgConfig['size'] = max(40, min(80, (int)($_POST['size'] ?? 56)));
    $_tgConfig['bottom'] = max(0, (int)($_POST['bottom'] ?? 24));
    $_tgConfig['right'] = max(0, (int)($_POST['right'] ?? 24));
    $_tgConfig['hide_admin'] = isset($_POST['hide_admin']) ? '1' : '0';
    file_put_contents($_tgConfigFile, json_encode($_tgConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다.</div>';
}
?>
<form method="post">
    <input type="hidden" name="tg_save" value="1">

    <div class="form-group">
        <label>텔레그램 아이디 *</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_tgConfig['username']) ?>" placeholder="@ 없이 입력 (예: myshop_support)">
        <small>텔레그램 사용자 아이디를 입력하세요. 비워두면 버튼이 표시되지 않습니다.</small>
    </div>

    <div class="form-group">
        <label>버튼 툴팁 텍스트</label>
        <input type="text" name="message" value="<?= htmlspecialchars($_tgConfig['message']) ?>" placeholder="문의하기">
        <small>버튼에 마우스를 올렸을 때 표시되는 텍스트</small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>말풍선 텍스트</label>
            <input type="text" name="bubble_text" value="<?= htmlspecialchars($_tgConfig['bubble_text']) ?>" placeholder="무엇이든 물어보세요!">
            <small>버튼 위에 표시되는 말풍선 (5초 후 자동 숨김)</small>
        </div>
        <div class="form-group" style="flex:0 0 auto;padding-top:28px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="bubble_show" <?= $_tgConfig['bubble_show'] === '1' ? 'checked' : '' ?>>
                말풍선 표시
            </label>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>버튼 색상</label>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="color" name="color" value="<?= htmlspecialchars($_tgConfig['color']) ?>" style="width:50px;height:36px;padding:2px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer">
                <span style="font-size:12px;color:#64748b"><?= htmlspecialchars($_tgConfig['color']) ?></span>
            </div>
        </div>
        <div class="form-group">
            <label>버튼 크기 (px)</label>
            <input type="number" name="size" value="<?= (int)$_tgConfig['size'] ?>" min="40" max="80" style="width:100px">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>하단 여백 (px)</label>
            <input type="number" name="bottom" value="<?= (int)$_tgConfig['bottom'] ?>" min="0" style="width:100px">
        </div>
        <div class="form-group">
            <label>우측 여백 (px)</label>
            <input type="number" name="right" value="<?= (int)$_tgConfig['right'] ?>" min="0" style="width:100px">
        </div>
    </div>

    <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="hide_admin" <?= $_tgConfig['hide_admin'] === '1' ? 'checked' : '' ?>>
            관리자 페이지에서 숨기기
        </label>
    </div>

    <button type="submit" class="btn btn-primary">저장</button>
</form>

<div style="margin-top:20px;padding:16px;background:#f8fafc;border-radius:8px">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">미리보기</h4>
    <div style="position:relative;background:#1e293b;border-radius:8px;height:180px;overflow:hidden">
        <div style="position:absolute;bottom:16px;right:16px;display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <?php if ($_tgConfig['bubble_show'] === '1' && $_tgConfig['bubble_text']): ?>
            <div style="background:#fff;color:#333;padding:8px 12px;border-radius:16px;font-size:12px;box-shadow:0 2px 8px rgba(0,0,0,.2)"><?= htmlspecialchars($_tgConfig['bubble_text']) ?></div>
            <?php endif; ?>
            <div style="width:48px;height:48px;border-radius:50%;background:<?= htmlspecialchars($_tgConfig['color']) ?>;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.3)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
            </div>
        </div>
        <div style="position:absolute;top:12px;left:16px;color:#94a3b8;font-size:12px">사이트 우측 하단에 이렇게 표시됩니다</div>
    </div>
</div>

<?php
/**
 * 301 리다이렉트 관리 - 설정 페이지
 */
$_configFile = __DIR__ . '/config.json';
$_configRaw = file_exists($_configFile) ? json_decode(file_get_contents($_configFile), true) : [];
if (!is_array($_configRaw)) $_configRaw = [];

$_config = array_merge([
    'enabled' => '1',
], $_configRaw);

// 활성화 토글
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rd_toggle'])) {
    $_config['enabled'] = $_config['enabled'] === '1' ? '0' : '1';
    file_put_contents($_configFile, json_encode($_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">설정이 저장되었습니다.</div>';
}
?>

<style>
.rd-badge-on { display:inline-block; padding:2px 10px; background:#dcfce7; color:#166534; border-radius:12px; font-size:12px; font-weight:600 }
.rd-badge-off { display:inline-block; padding:2px 10px; background:#fee2e2; color:#991b1b; border-radius:12px; font-size:12px; font-weight:600 }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div>
        <span style="font-size:14px;font-weight:600">자동 리다이렉트 상태</span>
        <?php if ($_config['enabled'] === '1'): ?>
            <span class="rd-badge-on">활성</span>
        <?php else: ?>
            <span class="rd-badge-off">비활성</span>
        <?php endif; ?>
    </div>
    <form method="post" style="margin:0">
        <input type="hidden" name="rd_toggle" value="1">
        <button type="submit" class="btn btn-sm" style="font-size:13px">
            <?= $_config['enabled'] === '1' ? '비활성화' : '활성화' ?>
        </button>
    </form>
</div>

<div style="padding:16px;background:#f8fafc;border-radius:8px">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">동작 방식</h4>
    <ul style="font-size:13px;color:#64748b;line-height:2;padding-left:20px">
        <li>존재하지 않는 페이지(404)에 접속하면 자동으로 메인 페이지로 이동합니다</li>
        <li>301 영구 리다이렉트로 검색엔진에 URL 변경을 알립니다</li>
        <li>관리자 페이지(/admin)는 리다이렉트 대상에서 제외됩니다</li>
    </ul>
</div>

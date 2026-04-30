<?php
/**
 * 광고 삽입 - 설정 페이지
 */
$_adConfigFile = __DIR__ . '/config.json';
$_adConfigRaw = file_exists($_adConfigFile) ? json_decode(file_get_contents($_adConfigFile), true) : [];
if (!is_array($_adConfigRaw)) $_adConfigRaw = [];
$_adConfig = array_merge([
    'ad_top' => '',
    'ad_bottom' => '',
    'ad_sidebar' => '',
], $_adConfigRaw);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ad_save'])) {
    $_adConfig['ad_top'] = $_POST['ad_top'] ?? '';
    $_adConfig['ad_bottom'] = $_POST['ad_bottom'] ?? '';
    $_adConfig['ad_sidebar'] = $_POST['ad_sidebar'] ?? '';
    file_put_contents($_adConfigFile, json_encode($_adConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다.</div>';
}
?>
<form method="post">
    <input type="hidden" name="ad_save" value="1">
    <div class="form-group">
        <label>게시글 상단 광고 코드</label>
        <textarea name="ad_top" rows="5" style="resize:vertical;font-family:monospace;font-size:12px" placeholder="<script>...</script> 또는 HTML 코드"><?= htmlspecialchars($_adConfig['ad_top']) ?></textarea>
        <small>게시글 본문 위에 표시됩니다 (구글 애드센스, 카카오 애드핏 등)</small>
    </div>
    <div class="form-group">
        <label>게시글 하단 광고 코드</label>
        <textarea name="ad_bottom" rows="5" style="resize:vertical;font-family:monospace;font-size:12px" placeholder="<script>...</script> 또는 HTML 코드"><?= htmlspecialchars($_adConfig['ad_bottom']) ?></textarea>
        <small>게시글 본문 아래에 표시됩니다</small>
    </div>
    <div class="form-group">
        <label>사이드바 광고 코드</label>
        <textarea name="ad_sidebar" rows="5" style="resize:vertical;font-family:monospace;font-size:12px" placeholder="<script>...</script> 또는 HTML 코드"><?= htmlspecialchars($_adConfig['ad_sidebar']) ?></textarea>
        <small>사이드바 위젯 영역에 표시됩니다</small>
    </div>
    <button type="submit" class="btn btn-primary">저장</button>
</form>

<div style="margin-top:20px;padding:16px;background:#f8fafc;border-radius:8px">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">사용법</h4>
    <ul style="font-size:13px;color:#64748b;line-height:2;padding-left:20px">
        <li>구글 애드센스: 애드센스 코드를 그대로 붙여넣으세요</li>
        <li>카카오 애드핏: 애드핏 스크립트 코드를 붙여넣으세요</li>
        <li>HTML 배너: <code>&lt;a href="..."&gt;&lt;img src="..."&gt;&lt;/a&gt;</code> 형식도 가능</li>
        <li>비워두면 해당 위치에 광고가 표시되지 않습니다</li>
    </ul>
</div>

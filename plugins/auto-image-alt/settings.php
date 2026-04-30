<?php
/**
 * 이미지 ALT 자동 삽입 - 설정 페이지
 */
$_altConfigFile = __DIR__ . '/config.json';
$_altConfigRaw = file_exists($_altConfigFile) ? json_decode(file_get_contents($_altConfigFile), true) : [];
if (!is_array($_altConfigRaw)) $_altConfigRaw = [];
$_altConfig = array_merge([
    'boards' => [],
    'keyword' => '',
], $_altConfigRaw);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alt_save'])) {
    $_altConfig['boards'] = $_POST['alt_boards'] ?? [];
    $_altConfig['keyword'] = trim($_POST['alt_keyword'] ?? '');
    file_put_contents($_altConfigFile, json_encode($_altConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다.</div>';
}

$_allBoards = Board::listAll(true);
?>
<form method="post">
    <input type="hidden" name="alt_save" value="1">
    <div class="form-group">
        <label>적용할 게시판 선택</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
            <?php foreach ($_allBoards as $b): ?>
            <label style="display:flex;align-items:center;gap:4px;padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="alt_boards[]" value="<?= htmlspecialchars($b['board_id']) ?>"
                    <?= in_array($b['board_id'], $_altConfig['boards']) ? 'checked' : '' ?>>
                <?= htmlspecialchars($b['title']) ?> (<?= htmlspecialchars($b['board_id']) ?>)
            </label>
            <?php endforeach; ?>
        </div>
        <small>선택하지 않으면 모든 게시판에 적용됩니다.</small>
    </div>
    <div class="form-group">
        <label>ALT 뒤에 붙일 키워드</label>
        <input type="text" name="alt_keyword" value="<?= htmlspecialchars($_altConfig['keyword']) ?>" placeholder="예: 누리보드">
        <small>입력하면 alt가 "글 제목 - 키워드" 형식으로 생성됩니다. 비워두면 글 제목만 사용합니다.</small>
    </div>
    <button type="submit" class="btn btn-primary">저장</button>
</form>

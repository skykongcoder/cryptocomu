<?php
/**
 * 금칙어 필터 - 설정 페이지
 */
$_bwWordsFile = __DIR__ . '/words.txt';
$_bwWords = file_exists($_bwWordsFile) ? trim(file_get_contents($_bwWordsFile)) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bw_save'])) {
    $words = trim($_POST['bw_words'] ?? '');
    file_put_contents($_bwWordsFile, $words);
    $_bwWords = $words;
    echo '<div class="alert success">저장되었습니다.</div>';
}
?>
<form method="post">
    <input type="hidden" name="bw_save" value="1">
    <div class="form-group">
        <label>금칙어 목록 (한 줄에 하나씩)</label>
        <textarea name="bw_words" rows="15" style="resize:vertical;font-family:monospace;font-size:13px" placeholder="욕설1&#10;욕설2&#10;광고단어"><?= htmlspecialchars($_bwWords) ?></textarea>
        <small>한 줄에 금칙어 하나씩 입력하세요. 대소문자 구분 없이 자동 필터링됩니다.</small>
    </div>
    <button type="submit" class="btn btn-primary">저장</button>
</form>

<div style="margin-top:20px;padding:16px;background:#f8fafc;border-radius:8px">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">사용법</h4>
    <ul style="font-size:13px;color:#64748b;line-height:2;padding-left:20px">
        <li>등록된 금칙어는 게시글 제목, 본문, 댓글에서 <code>***</code>로 자동 치환됩니다</li>
        <li>예: "나쁜말" → "***"</li>
        <li>대소문자 구분 없이 필터링됩니다</li>
    </ul>
</div>

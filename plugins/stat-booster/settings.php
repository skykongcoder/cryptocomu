<?php
/**
 * 통계 부스터 - 설정 페이지
 */
$_sbConfigFile = __DIR__ . '/config.json';
$_sbConfigRaw = file_exists($_sbConfigFile) ? json_decode(file_get_contents($_sbConfigFile), true) : [];
if (!is_array($_sbConfigRaw)) $_sbConfigRaw = [];
$_sbConfig = array_merge([
    'online' => 1,
    'today_posts' => 1,
    'today_comments' => 1,
    'today_members' => 1,
    'total_posts' => 1,
    'total_comments' => 1,
    'total_members' => 1,
], $_sbConfigRaw);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sb_save'])) {
    $_sbConfig['online'] = max(1, (int)($_POST['online'] ?? 1));
    $_sbConfig['today_posts'] = max(1, (int)($_POST['today_posts'] ?? 1));
    $_sbConfig['today_comments'] = max(1, (int)($_POST['today_comments'] ?? 1));
    $_sbConfig['today_members'] = max(1, (int)($_POST['today_members'] ?? 1));
    $_sbConfig['total_posts'] = max(1, (int)($_POST['total_posts'] ?? 1));
    $_sbConfig['total_comments'] = max(1, (int)($_POST['total_comments'] ?? 1));
    $_sbConfig['total_members'] = max(1, (int)($_POST['total_members'] ?? 1));
    file_put_contents($_sbConfigFile, json_encode($_sbConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo '<div class="alert success">저장되었습니다.</div>';
}

$_sbItems = [
    ['key' => 'online', 'label' => '현재 접속자'],
    ['key' => 'today_posts', 'label' => '오늘 새 글'],
    ['key' => 'today_comments', 'label' => '오늘 새 댓글'],
    ['key' => 'today_members', 'label' => '오늘 새 회원'],
    ['key' => 'total_posts', 'label' => '전체 게시물'],
    ['key' => 'total_comments', 'label' => '전체 댓글'],
    ['key' => 'total_members', 'label' => '전체 회원'],
];
?>
<form method="post">
    <input type="hidden" name="sb_save" value="1">
    <table class="table">
        <thead>
            <tr>
                <th>항목</th>
                <th style="width:120px">배수</th>
                <th style="width:200px">예시</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($_sbItems as $item): ?>
            <tr>
                <td><?= $item['label'] ?></td>
                <td><input type="number" name="<?= $item['key'] ?>" value="<?= $_sbConfig[$item['key']] ?>" min="1" max="100" style="width:80px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:14px"></td>
                <td style="font-size:13px;color:#64748b">
                    실제 0 &rarr; <strong style="color:#2563eb"><?= $_sbConfig[$item['key']] ?></strong>
                    <span style="color:#cbd5e1">/</span>
                    실제 10 &rarr; <strong style="color:#2563eb"><?= 10 * $_sbConfig[$item['key']] ?></strong>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary">저장</button>
    </div>
</form>
<div style="margin-top:16px;padding:16px;background:#f8fafc;border-radius:8px">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:8px">사용 안내</h4>
    <ul style="font-size:13px;color:#64748b;line-height:2;padding-left:20px">
        <li>배수를 1로 설정하면 실제 수치 그대로 표시됩니다.</li>
        <li>실제 수치가 0일 때는 배수 값이 그대로 표시됩니다. (예: 실제 오늘 새 글 0개, 배수 20 → 화면에 20개)</li>
        <li>실제 수치가 1 이상일 때는 실제값 × 배수로 표시됩니다. (예: 실제 3개, 배수 20 → 60개)</li>
        <li>메인 페이지 우측 "커뮤니티 현황" 영역에 적용됩니다.</li>
        <li>소스 보기에는 원본 수치가 보이고, 화면에만 배수가 적용됩니다.</li>
    </ul>
</div>

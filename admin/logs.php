<?php
/**
 * NuriBoard 관리자 - 활동 로그
 */

$page = max(1, (int)($_GET['p'] ?? 1));
$data = AdminLog::list($page, 40);

adminHeader('logs');
?>

<div class="page-header">
    <h1>관리자 활동 로그</h1>
    <span style="font-size:13px;color:#94a3b8">총 <?= number_format($data['total']) ?>건</span>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px">번호</th>
                <th style="width:90px">관리자</th>
                <th style="width:120px">액션</th>
                <th style="width:80px">대상</th>
                <th>상세</th>
                <th style="width:90px">IP</th>
                <th style="width:120px">일시</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data['items'])): ?>
            <tr><td colspan="7" class="text-center" style="padding:40px;color:#94a3b8">활동 로그가 없습니다.</td></tr>
        <?php endif; ?>
        <?php foreach ($data['items'] as $log): ?>
        <?php
            $label  = AdminLog::ACTION_LABELS[$log['action']] ?? $log['action'];
            $colors = [
                'member_warn'    => ['#fef3c7', '#92400e'],
                'member_ban'     => ['#fef2f2', '#dc2626'],
                'member_unban'   => ['#ecfdf5', '#059669'],
                'member_delete'  => ['#fef2f2', '#dc2626'],
                'post_delete'    => ['#fef2f2', '#dc2626'],
                'post_hide'      => ['#fef3c7', '#92400e'],
                'report_approve' => ['#eff6ff', '#2563eb'],
                'report_reject'  => ['#f8fafc', '#64748b'],
                'board_create'   => ['#ecfdf5', '#059669'],
                'board_delete'   => ['#fef2f2', '#dc2626'],
                'settings_save'  => ['#f5f3ff', '#7c3aed'],
            ];
            [$bg, $fg] = $colors[$log['action']] ?? ['#f1f5f9', '#475569'];
        ?>
        <tr>
            <td class="text-center" style="color:#94a3b8;font-size:12px"><?= $log['id'] ?></td>
            <td style="font-size:13px;font-weight:500"><?= nb_e($log['admin_name'] ?? '관리자') ?></td>
            <td>
                <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?= $bg ?>;color:<?= $fg ?>">
                    <?= nb_e($label) ?>
                </span>
            </td>
            <td style="font-size:12px;color:#64748b">
                <?php if ($log['target_type'] && $log['target_id']): ?>
                    <?= nb_e($log['target_type']) ?> #<?= $log['target_id'] ?>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#64748b"><?= nb_e($log['detail']) ?></td>
            <td style="font-size:12px;color:#94a3b8"><?= nb_e($log['ip']) ?></td>
            <td style="font-size:12px;color:#94a3b8"><?= date('m.d H:i:s', strtotime($log['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($data['total_pages'] > 1): ?>
    <?php
        $cp = $data['page'];
        $tp = $data['total_pages'];
        $range = 2;
        $start = max(1, $cp - $range);
        $end = min($tp, $cp + $range);
    ?>
    <div class="pagination">
        <?php if ($cp > 1): ?><a href="?page=logs&p=<?= $cp - 1 ?>">&laquo;</a><?php endif; ?>
        <?php if ($start > 1): ?><a href="?page=logs&p=1">1</a><?php if ($start > 2): ?><span style="padding:0 4px;color:#94a3b8">...</span><?php endif; endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=logs&p=<?= $i ?>" class="<?= $i === $cp ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end < $tp): ?><?php if ($end < $tp - 1): ?><span style="padding:0 4px;color:#94a3b8">...</span><?php endif; ?><a href="?page=logs&p=<?= $tp ?>"><?= $tp ?></a><?php endif; ?>
        <?php if ($cp < $tp): ?><a href="?page=logs&p=<?= $cp + 1 ?>">&raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php adminFooter(); ?>

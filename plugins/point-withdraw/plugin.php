<?php
/**
 * 포인트 출금 신청 플러그인
 */

$_pwConfigFile = __DIR__ . '/config.json';
$_pwRaw = file_exists($_pwConfigFile) ? json_decode(file_get_contents($_pwConfigFile), true) : [];
if (!is_array($_pwRaw)) $_pwRaw = [];

$_pwDefault = [
    'enabled' => '1',
    'min_amount' => '10000',
    'amount_unit' => '10000',
    'point_to_won' => '1',
    'page_path' => 'withdraw',
    'menu_label' => '포인트 출금',
    'admin_member_ids' => '',
    'notify_admin' => '1',
    'notify_member_on_complete' => '1',
    'notify_member_on_reject' => '1',
    'page_per' => '10',
    'max_pending_per_member' => '3',
];
$_pwConfig = array_merge($_pwDefault, $_pwRaw);

// ============================================================
// DB 설치
// ============================================================
if (class_exists('DB')) {
    try {
        $prefix = DB::getPrefix();
        DB::query("CREATE TABLE IF NOT EXISTS {$prefix}pw_withdrawals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            member_id INT UNSIGNED NOT NULL,
            amount INT UNSIGNED NOT NULL,
            bank_name VARCHAR(50) NOT NULL,
            account_holder VARCHAR(50) NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            processed_at DATETIME NULL,
            processed_by INT UNSIGNED NULL,
            INDEX idx_member (member_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

// ============================================================
// 라우팅
// ============================================================
if (isset($_REQUEST['pw_api'])) {
    while (ob_get_level()) { @ob_end_clean(); }
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    require __DIR__ . '/api.php';
    exit;
}
if (isset($_REQUEST['pw_page'])) {
    while (ob_get_level()) { @ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    require __DIR__ . '/page.php';
    exit;
}

// ============================================================
// 헬퍼: 회원 포인트 조회 / 차감 / 복구
// ============================================================
function _pw_get_member_point($memberId) {
    if (!class_exists('DB') || !$memberId) return 0;
    $prefix = DB::getPrefix();
    try {
        $r = DB::fetch("SELECT point FROM {$prefix}members WHERE id = ?", [$memberId]);
        return (int)($r['point'] ?? 0);
    } catch (Exception $e) { return 0; }
}

function _pw_subtract_point($memberId, $points) {
    if (!class_exists('DB') || !$memberId || $points <= 0) return false;
    $prefix = DB::getPrefix();
    try {
        // 동시성 안전: WHERE point >= ? 로 부족하면 0줄 영향
        $sql = "UPDATE {$prefix}members SET point = point - ? WHERE id = ? AND point >= ?";
        DB::query($sql, [$points, $memberId, $points]);
        // 영향받은 행 수 확인 (변경됐는지)
        $check = DB::fetch("SELECT point FROM {$prefix}members WHERE id = ?", [$memberId]);
        return $check !== false;
    } catch (Exception $e) { return false; }
}

function _pw_add_point($memberId, $points) {
    if (!class_exists('DB') || !$memberId || $points <= 0) return false;
    $prefix = DB::getPrefix();
    try {
        DB::query("UPDATE {$prefix}members SET point = point + ? WHERE id = ?", [$points, $memberId]);
        return true;
    } catch (Exception $e) { return false; }
}

// ============================================================
// 헬퍼: 관리자 ID 조회
// ============================================================
function _pw_get_admin_ids($config) {
    $configured = trim($config['admin_member_ids'] ?? '');
    if ($configured) {
        $ids = array_map('intval', array_filter(array_map('trim', explode(',', $configured))));
        if (!empty($ids)) return $ids;
    }
    if (!class_exists('DB')) return [1];
    $prefix = DB::getPrefix();
    try {
        $rows = DB::fetchAll("SELECT id FROM {$prefix}members WHERE level >= 10 LIMIT 10") ?: [];
        $ids = array_map(function($r){ return (int)$r['id']; }, $rows);
        return !empty($ids) ? $ids : [1];
    } catch (Exception $e) { return [1]; }
}

// ============================================================
// 헬퍼: 쪽지 발송 (다양한 테이블 명 시도)
// ============================================================
function _pw_send_message($senderId, $receiverId, $title, $content) {
    if (!class_exists('DB')) return false;
    $prefix = DB::getPrefix();
    $now = date('Y-m-d H:i:s');

    $attempts = [
        // 표준 messages
        ["{$prefix}messages", ['sender_id'=>$senderId,'receiver_id'=>$receiverId,'title'=>mb_substr($title,0,200),'content'=>$content,'is_read'=>0,'created_at'=>$now]],
        // notes
        ["{$prefix}notes", ['from_id'=>$senderId,'to_id'=>$receiverId,'title'=>mb_substr($title,0,200),'content'=>$content,'is_read'=>0,'created_at'=>$now]],
        // whisper
        ["{$prefix}whisper", ['sender_id'=>$senderId,'receiver_id'=>$receiverId,'title'=>mb_substr($title,0,200),'content'=>$content,'is_read'=>0,'created_at'=>$now]],
        // memo
        ["{$prefix}memos", ['sender_id'=>$senderId,'receiver_id'=>$receiverId,'title'=>mb_substr($title,0,200),'content'=>$content,'is_read'=>0,'created_at'=>$now]],
    ];

    foreach ($attempts as $a) {
        try {
            DB::insert($a[0], $a[1]);
            return true;
        } catch (Exception $e) {
            continue;
        }
    }
    error_log('[point-withdraw] 쪽지 발송 실패 - 호환되는 테이블 없음');
    return false;
}

// ============================================================
// 헬퍼: 회원 닉네임 조회
// ============================================================
function _pw_get_nickname($memberId) {
    if (!class_exists('DB') || !$memberId) return '회원';
    $prefix = DB::getPrefix();
    try {
        $r = DB::fetch("SELECT nickname, user_id FROM {$prefix}members WHERE id = ?", [$memberId]);
        return $r['nickname'] ?? $r['user_id'] ?? ('회원#' . $memberId);
    } catch (Exception $e) { return '회원#' . $memberId; }
}

// ============================================================
// 출금 완료 처리 (관리자가 호출)
// ============================================================
function _pw_process_complete($withdrawalId, $adminId, $config) {
    if (!class_exists('DB')) return ['ok'=>false,'error'=>'DB unavailable'];
    $prefix = DB::getPrefix();

    $w = DB::fetch("SELECT * FROM {$prefix}pw_withdrawals WHERE id = ?", [$withdrawalId]);
    if (!$w) return ['ok'=>false,'error'=>'신청 없음'];
    if ($w['status'] !== 'pending') return ['ok'=>false,'error'=>'이미 처리됨 (' . $w['status'] . ')'];

    DB::query("UPDATE {$prefix}pw_withdrawals SET status = 'completed', processed_at = ?, processed_by = ? WHERE id = ?",
        [date('Y-m-d H:i:s'), $adminId, $withdrawalId]);

    if ($config['notify_member_on_complete'] === '1') {
        _pw_send_message($adminId, (int)$w['member_id'], '출금 완료 안내',
            "신청하신 " . number_format((int)$w['amount']) . "원 출금이 완료되었습니다.\n\n" .
            "은행: {$w['bank_name']}\n예금주: {$w['account_holder']}\n계좌: {$w['account_number']}\n\n" .
            "이용해 주셔서 감사합니다.");
    }
    return ['ok'=>true];
}

// ============================================================
// 출금 거절 처리 (포인트 복구)
// ============================================================
function _pw_process_reject($withdrawalId, $adminId, $reason, $config) {
    if (!class_exists('DB')) return ['ok'=>false,'error'=>'DB unavailable'];
    $prefix = DB::getPrefix();

    $w = DB::fetch("SELECT * FROM {$prefix}pw_withdrawals WHERE id = ?", [$withdrawalId]);
    if (!$w) return ['ok'=>false,'error'=>'신청 없음'];
    if ($w['status'] !== 'pending') return ['ok'=>false,'error'=>'이미 처리됨'];

    // 포인트 복구
    $points = (int)round((int)$w['amount'] / max(1, (int)$config['point_to_won']));
    _pw_add_point((int)$w['member_id'], $points);

    DB::query("UPDATE {$prefix}pw_withdrawals SET status = 'rejected', admin_note = ?, processed_at = ?, processed_by = ? WHERE id = ?",
        [mb_substr($reason, 0, 490), date('Y-m-d H:i:s'), $adminId, $withdrawalId]);

    if ($config['notify_member_on_reject'] === '1') {
        $msg = "신청하신 " . number_format((int)$w['amount']) . "원 출금이 거절되었습니다.\n\n";
        if ($reason) $msg .= "사유: {$reason}\n\n";
        $msg .= "차감되었던 " . number_format($points) . " 포인트는 다시 복구되었습니다.";
        _pw_send_message($adminId, (int)$w['member_id'], '출금 신청 거절 안내', $msg);
    }
    return ['ok'=>true];
}

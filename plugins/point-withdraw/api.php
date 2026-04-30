<?php
/**
 * 포인트 출금 AJAX API
 */

if (!class_exists('DB')) { echo json_encode(['ok'=>false,'error'=>'DB unavailable']); exit; }

$action = $_REQUEST['pw_api'] ?? '';
$prefix = DB::getPrefix();
$config = $_pwConfig;

// 로그인 확인
$memberId = null;
if (class_exists('Auth') && method_exists('Auth', 'user')) {
    $u = Auth::user();
    if (!empty($u['id'])) $memberId = (int)$u['id'];
}

$isAdmin = false;
if (class_exists('Auth') && method_exists('Auth', 'isAdmin') && method_exists('Auth', 'check')) {
    $isAdmin = Auth::check() && Auth::isAdmin();
}

switch ($action) {

    // === 출금 신청 ===
    case 'submit': {
        if (!$memberId) { echo json_encode(['ok'=>false,'error'=>'로그인이 필요합니다.']); exit; }

        $amount = (int)($_POST['amount'] ?? 0);
        $bankName = trim($_POST['bank_name'] ?? '');
        $accountHolder = trim($_POST['account_holder'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');

        $minAmount = max(1000, (int)$config['min_amount']);
        $unit = max(1000, (int)$config['amount_unit']);
        $ratio = max(1, (int)$config['point_to_won']);

        // 검증
        if ($amount < $minAmount) { echo json_encode(['ok'=>false,'error'=>'최소 ' . number_format($minAmount) . '원 이상 신청 가능합니다.']); exit; }
        if ($amount % $unit !== 0) { echo json_encode(['ok'=>false,'error'=>number_format($unit) . '원 단위로만 신청 가능합니다.']); exit; }
        if ($bankName === '') { echo json_encode(['ok'=>false,'error'=>'은행명을 입력하세요.']); exit; }
        if ($accountHolder === '') { echo json_encode(['ok'=>false,'error'=>'예금주를 입력하세요.']); exit; }
        if ($accountNumber === '') { echo json_encode(['ok'=>false,'error'=>'계좌번호를 입력하세요.']); exit; }

        // 동시 진행 가능 신청 수 체크
        $maxPending = max(1, (int)$config['max_pending_per_member']);
        $pending = DB::fetch("SELECT COUNT(*) AS c FROM {$prefix}pw_withdrawals WHERE member_id = ? AND status = 'pending'", [$memberId]);
        if ((int)($pending['c'] ?? 0) >= $maxPending) {
            echo json_encode(['ok'=>false,'error'=>'대기 중인 신청이 ' . $maxPending . '건 이상 있어 신청이 불가합니다.']); exit;
        }

        // 포인트 차감 시도 (동시성 안전)
        $pointsRequired = (int)round($amount / $ratio);
        $current = _pw_get_member_point($memberId);
        if ($current < $pointsRequired) {
            echo json_encode(['ok'=>false,'error'=>'보유 포인트가 부족합니다. (보유 ' . number_format($current) . ' / 필요 ' . number_format($pointsRequired) . ')']); exit;
        }
        if (!_pw_subtract_point($memberId, $pointsRequired)) {
            echo json_encode(['ok'=>false,'error'=>'포인트 차감 실패']); exit;
        }

        // 신청 저장
        $newId = DB::insert("{$prefix}pw_withdrawals", [
            'member_id' => $memberId,
            'amount' => $amount,
            'bank_name' => mb_substr($bankName, 0, 50),
            'account_holder' => mb_substr($accountHolder, 0, 50),
            'account_number' => mb_substr($accountNumber, 0, 50),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 관리자에게 쪽지
        if ($config['notify_admin'] === '1') {
            $nick = _pw_get_nickname($memberId);
            $title = '[출금신청] ' . $nick . ' / ' . number_format($amount) . '원';
            $body = $nick . " 회원님이 출금을 신청했습니다.\n\n" .
                    "금액: " . number_format($amount) . "원 (" . number_format($pointsRequired) . " P)\n" .
                    "은행: {$bankName}\n예금주: {$accountHolder}\n계좌: {$accountNumber}\n" .
                    "신청일시: " . date('Y-m-d H:i:s') . "\n\n" .
                    "관리자 페이지에서 처리해주세요.";
            foreach (_pw_get_admin_ids($config) as $adminId) {
                _pw_send_message($memberId, $adminId, $title, $body);
            }
        }

        echo json_encode(['ok'=>true,'id'=>(int)$newId,'remaining_point'=>_pw_get_member_point($memberId)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === 내 신청 이력 조회 ===
    case 'list': {
        if (!$memberId) { echo json_encode(['ok'=>false,'error'=>'로그인 필요']); exit; }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = max(5, (int)$config['page_per']);
        $offset = ($page - 1) * $per;

        $totalRow = DB::fetch("SELECT COUNT(*) AS c FROM {$prefix}pw_withdrawals WHERE member_id = ?", [$memberId]);
        $total = (int)($totalRow['c'] ?? 0);
        $rows = DB::fetchAll("SELECT * FROM {$prefix}pw_withdrawals WHERE member_id = ? ORDER BY id DESC LIMIT {$per} OFFSET {$offset}", [$memberId]) ?: [];

        echo json_encode([
            'ok' => true,
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'page_per' => $per,
            'total_pages' => max(1, ceil($total / $per)),
            'point' => _pw_get_member_point($memberId),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === 사용자 측 신청 삭제 (본인 것만, completed/rejected만) ===
    case 'delete': {
        if (!$memberId) { echo json_encode(['ok'=>false,'error'=>'로그인 필요']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $ids = $body['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) { echo json_encode(['ok'=>false,'error'=>'ids 필요']); exit; }
        $ids = array_map('intval', array_filter($ids));
        if (empty($ids)) { echo json_encode(['ok'=>false,'error'=>'유효 id 없음']); exit; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 본인 + pending 아닌 것만 삭제
        $params = array_merge($ids, [$memberId]);
        DB::query("DELETE FROM {$prefix}pw_withdrawals WHERE id IN ({$placeholders}) AND member_id = ? AND status != 'pending'", $params);
        echo json_encode(['ok'=>true]); exit;
    }

    // === 관리자: 완료 처리 ===
    case 'admin_complete': {
        if (!$isAdmin) { echo json_encode(['ok'=>false,'error'=>'관리자 권한 필요']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'error'=>'id 필요']); exit; }
        $r = _pw_process_complete($id, $memberId ?: 0, $config);
        echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
    }

    // === 관리자: 거절 처리 ===
    case 'admin_reject': {
        if (!$isAdmin) { echo json_encode(['ok'=>false,'error'=>'관리자 권한 필요']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if (!$id) { echo json_encode(['ok'=>false,'error'=>'id 필요']); exit; }
        $r = _pw_process_reject($id, $memberId ?: 0, $reason, $config);
        echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
    }

    // === 관리자: 신청 영구 삭제 ===
    case 'admin_delete': {
        if (!$isAdmin) { echo json_encode(['ok'=>false,'error'=>'관리자 권한 필요']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $ids = $body['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) { echo json_encode(['ok'=>false,'error'=>'ids 필요']); exit; }
        $ids = array_map('intval', array_filter($ids));
        if (empty($ids)) { echo json_encode(['ok'=>false,'error'=>'유효 id 없음']); exit; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::query("DELETE FROM {$prefix}pw_withdrawals WHERE id IN ({$placeholders})", $ids);
        echo json_encode(['ok'=>true]); exit;
    }

    default:
        echo json_encode(['ok'=>false,'error'=>'unknown action']);
        exit;
}

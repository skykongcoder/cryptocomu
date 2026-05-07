<?php
/**
 * POST /api/wallet/amount/deduction
 * Detrade 가 베팅 시 호출 — 우리 포인트에서 amount 만큼 차감
 *
 * 멱등성: bizId 가 같으면 캐시된 응답 반환
 */

$v = dt_webhook_verify();
if (!$v['ok']) dt_json(['code' => 401, 'msg' => $v['error']], $v['http_code']);

$body = $v['body'];
$userId   = (int)($body['userId'] ?? 0);
$amount   = (float)($body['amount'] ?? 0);
$currency = (string)($body['currency'] ?? 'PT');
$bizId    = trim((string)($body['bizid'] ?? ''));

if (!$userId || $amount <= 0 || !$bizId) {
    dt_json(['code' => 400, 'msg' => 'invalid params'], 400);
}

// 멱등성 체크
$cached = dt_idempotency_get($bizId, 'deduction');
if ($cached !== null) dt_json($cached);

// 차감 실행
$r = dt_deduct_point($userId, $amount);
if (!$r['ok']) {
    $resp = [
        'code'     => 4001,
        'msg'      => $r['error'] ?? 'deduction failed',
        'currency' => $currency,
        'balance'  => $r['balance'] ?? dt_get_point($userId),
    ];
    dt_log_order($userId, $bizId, 'deduction', $amount, $body, [], 'fail');
    dt_idempotency_save($bizId, 'deduction', $resp);
    dt_json($resp, 200); // Detrade 가 200 으로 비즈니스 에러를 받음
}

$resp = [
    'code'     => 200,
    'msg'      => 'ok',
    'currency' => $currency,
    'balance'  => $r['after'],
];
dt_log_order($userId, $bizId, 'deduction', $amount, $body, $r, 'ok');
dt_idempotency_save($bizId, 'deduction', $resp);
dt_json($resp);

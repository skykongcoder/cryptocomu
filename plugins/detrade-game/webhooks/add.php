<?php
/**
 * POST /api/wallet/amount/add
 * Detrade 가 정산(승리 시) 호출 — 우리 포인트에 amount 추가
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

$cached = dt_idempotency_get($bizId, 'add');
if ($cached !== null) dt_json($cached);

$r = dt_add_point($userId, $amount);
if (!$r['ok']) {
    $resp = [
        'code'     => 4002,
        'msg'      => $r['error'] ?? 'add failed',
        'currency' => $currency,
        'balance'  => dt_get_point($userId),
    ];
    dt_log_order($userId, $bizId, 'add', $amount, $body, [], 'fail');
    dt_idempotency_save($bizId, 'add', $resp);
    dt_json($resp, 200);
}

$resp = [
    'code'     => 200,
    'msg'      => 'ok',
    'currency' => $currency,
    'balance'  => $r['after'],
];
dt_log_order($userId, $bizId, 'add', $amount, $body, $r, 'ok');
dt_idempotency_save($bizId, 'add', $resp);
dt_json($resp);

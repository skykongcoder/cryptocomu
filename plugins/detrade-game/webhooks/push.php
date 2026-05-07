<?php
/**
 * POST /api/order/push
 * Detrade 가 게임 종료 후 주문 데이터 푸시 — 기록만 남김
 */

$v = dt_webhook_verify();
if (!$v['ok']) dt_json(['code' => 401, 'msg' => $v['error']], $v['http_code']);

$body = $v['body'];
$userId = (int)($body['userId'] ?? 0);
$bizId  = trim((string)($body['bizid'] ?? $body['orderId'] ?? ''));
$amount = (float)($body['amount'] ?? 0);

// 멱등성
if ($bizId) {
    $cached = dt_idempotency_get($bizId, 'order_push');
    if ($cached !== null) dt_json($cached);
}

dt_log_order($userId ?: 0, $bizId ?: ('push_' . uniqid()), 'order_push', $amount, $body, [], 'ok');

$resp = ['code' => 200, 'msg' => 'received'];
if ($bizId) dt_idempotency_save($bizId, 'order_push', $resp);
dt_json($resp);

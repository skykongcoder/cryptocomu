<?php
/**
 * POST /api/wallet/balance/{currency}
 * Detrade 가 우리 유저의 잔액 조회 시 호출
 */

$v = dt_webhook_verify();
if (!$v['ok']) dt_json(['code' => 401, 'msg' => $v['error']], $v['http_code']);

$body = $v['body'];
$userId   = (int)($body['userId'] ?? 0);
$currency = $GLOBALS['DT_PARAM_CURRENCY'] ?? ($body['currency'] ?? 'PT');

if (!$userId) {
    dt_json(['code' => 400, 'msg' => 'invalid userId'], 400);
}

$balance = dt_get_point($userId);
dt_json([
    'code'     => 200,
    'msg'      => 'ok',
    'currency' => $currency,
    'balance'  => $balance,
]);

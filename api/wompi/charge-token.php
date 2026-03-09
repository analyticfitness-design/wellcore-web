<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * ============================================================
 * WELLCORE FITNESS — CHARGE TOKEN ENDPOINT (M16)
 * ============================================================
 * POST /api/wompi/charge-token.php
 *
 * Cobra al cliente usando un token de tarjeta guardado.
 * Uso interno: llamado por el cron de auto-renovacion.
 *
 * Requiere autenticacion admin O header X-Cron-Secret.
 *
 * Body JSON:
 * {
 *   "client_id":    3,
 *   "amount_cents": 39900000,
 *   "currency":     "COP",
 *   "reference":    "wc-renewal-3-20260309"
 * }
 * ============================================================
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

requireMethod('POST');

// -------------------------------------------------------
// AUTENTICACION: admin JWT o token interno de cron
// -------------------------------------------------------
$authorized = false;

// 1. Token interno del cron (X-Cron-Secret header)
$cronSecret        = env('CRON_SECRET', '');
$requestCronSecret = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
if (!empty($cronSecret) && !empty($requestCronSecret) && hash_equals($cronSecret, $requestCronSecret)) {
    $authorized = true;
}

// 2. Admin JWT si no hay cron secret valido
if (!$authorized) {
    $token = getTokenFromHeader();
    if ($token) {
        try {
            authenticateAdmin();
            $authorized = true;
        } catch (\Throwable $e) {
            $authorized = false;
        }
    }
}

if (!$authorized) {
    respondError('Authentication required', 401);
}

// -------------------------------------------------------
// BODY
// -------------------------------------------------------
$body = getJsonBody();

$clientId    = (int)      ($body['client_id']    ?? 0);
$amountCents = (int)      ($body['amount_cents']  ?? 0);
$currency    = strtoupper(trim($body['currency']  ?? 'COP'));
$reference   = trim($body['reference']  ?? '');

if (!$clientId || !$amountCents || !$reference) {
    respondError('client_id, amount_cents y reference son requeridos', 400);
}

if ($amountCents < 100) {
    respondError('amount_cents debe ser al menos 100', 400);
}

// -------------------------------------------------------
// OBTENER TOKEN ACTIVO DEL CLIENTE
// -------------------------------------------------------
$db = getDB();

$pmStmt = $db->prepare("
    SELECT pm.id, pm.token_id, pm.card_brand, pm.last_four, pm.card_holder,
           c.email, c.name, c.plan
    FROM payment_methods pm
    JOIN clients c ON c.id = pm.client_id
    WHERE pm.client_id = ?
      AND pm.is_active = 1
    ORDER BY pm.created_at DESC
    LIMIT 1
");
$pmStmt->execute([$clientId]);
$pm = $pmStmt->fetch();

if (!$pm) {
    respondError('No se encontro metodo de pago activo para este cliente', 404);
}

// -------------------------------------------------------
// LLAMAR A WOMPI API — COBRAR CON TOKEN
// -------------------------------------------------------
$apiUrl = wompi_api_url() . '/transactions';

$wompiBody = [
    'amount_in_cents' => $amountCents,
    'currency'        => $currency,
    'customer_email'  => $pm['email'],
    'reference'       => $reference,
    'payment_method'  => [
        'type'         => 'CARD',
        'token'        => $pm['token_id'],
        'installments' => 1,
    ],
    'customer_data'   => [
        'full_name'    => $pm['name'] ?? $pm['card_holder'] ?? '',
        'phone_number' => '',
    ],
    'metadata'        => [
        'client_id'   => (string) $clientId,
        'auto_charge' => 'true',
        'plan'        => $pm['plan'] ?? '',
    ],
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($wompiBody),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . WOMPI_PRIVATE_KEY,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$rawResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr     = curl_error($ch);
curl_close($ch);

// -------------------------------------------------------
// PROCESAR RESPUESTA Y REGISTRAR EN auto_charge_log
// -------------------------------------------------------
$logStatus    = 'failed';
$wompiTxId    = null;
$errorMessage = null;

if ($curlErr) {
    $errorMessage = 'cURL error: ' . $curlErr;
} else {
    $responseData = json_decode($rawResponse, true);
    $wompiTx      = $responseData['data'] ?? null;
    $wompiTxId    = $wompiTx['id'] ?? null;
    $wompiStatus  = strtoupper($wompiTx['status'] ?? '');

    if ($httpCode >= 200 && $httpCode < 300 && in_array($wompiStatus, ['APPROVED', 'PENDING'], true)) {
        $logStatus = ($wompiStatus === 'APPROVED') ? 'success' : 'pending';
    } else {
        $errorReason  = $wompiTx['error']['reason'] ?? '';
        $errorMessage = 'Wompi HTTP ' . $httpCode . ($errorReason ? ': ' . $errorReason : '');
    }
}

// Registrar en auto_charge_log
try {
    $db->prepare("
        INSERT INTO auto_charge_log
            (client_id, payment_method_id, amount_cents, status, wompi_transaction_id, error_message)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $clientId,
        (int) $pm['id'],
        $amountCents,
        $logStatus,
        $wompiTxId,
        $errorMessage,
    ]);
} catch (\Throwable $logErr) {
    error_log('[WellCore][charge-token] Error inserting auto_charge_log: ' . $logErr->getMessage());
}

// -------------------------------------------------------
// RESPUESTA
// -------------------------------------------------------
if ($logStatus === 'success') {
    respond([
        'ok'                   => true,
        'status'               => 'success',
        'wompi_transaction_id' => $wompiTxId,
        'client_id'            => $clientId,
        'amount_cents'         => $amountCents,
        'reference'            => $reference,
    ]);
} elseif ($logStatus === 'pending') {
    respond([
        'ok'                   => false,
        'status'               => 'pending',
        'wompi_transaction_id' => $wompiTxId,
        'client_id'            => $clientId,
        'message'              => 'Transaccion pendiente — se confirmara por webhook',
    ], 202);
} else {
    respondError('Cobro fallido: ' . ($errorMessage ?? 'Error desconocido'), 422, [
        'client_id'  => $clientId,
        'reference'  => $reference,
        'wompi_code' => $httpCode,
    ]);
}

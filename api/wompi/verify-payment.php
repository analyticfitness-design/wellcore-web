<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * ============================================================
 * WELLCORE FITNESS — WOMPI VERIFY PAYMENT
 * ============================================================
 * GET /api/wompi/verify-payment.php?reference=WC-...
 *
 * Consulta el estado de una transaccion por su referenceCode.
 * Usado por pago-exitoso.html para mostrar el resultado.
 *
 * RESPONSE (JSON):
 *   ok        bool
 *   found     bool
 *   reference string
 *   status    string  pending|approved|declined|voided|error
 *   plan      string
 *   plan_name string
 *   amount    string  "$399.000 COP"
 *   buyer     string
 *   date      string  ISO 8601
 * ============================================================
 */

$allowedOrigins = [
    'https://wellcorefitness.com',
    'https://www.wellcorefitness.com',
    'http://172.17.216.45:8080',
    'http://172.17.216.45:8082',
    'http://localhost:8080',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/transactions.php';
require_once __DIR__ . '/rate-limit.php';

// Rate limit: 30 consultas por IP por hora
$ip = get_client_ip();
if (!rate_limit_check($ip, 'verify_payment', 30, 3600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Demasiadas solicitudes.']);
    exit;
}

$reference = trim($_GET['reference'] ?? '');

if (empty($reference)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Referencia requerida.']);
    exit;
}

// Sanitizar: solo alfanumericos y guiones
if (!preg_match('/^WC-[a-z]+-\d+$/', $reference)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Formato de referencia invalido.']);
    exit;
}

$tx = transactions_find_by_reference($reference);

if (!$tx) {
    echo json_encode(['ok' => true, 'found' => false]);
    exit;
}

$plans    = WELLCORE_PLANS;
$plan     = $tx['plan'] ?? '';
$planData = $plans[$plan] ?? null;
$planName = $planData ? $planData['display'] : strtoupper($plan);
$amountCop = isset($tx['amount_cop'])
    ? '$' . number_format((float)$tx['amount_cop'], 0, '.', '.') . ' COP'
    : '$' . number_format((float)($tx['amount_in_cents'] / 100), 0, '.', '.') . ' COP';

echo json_encode([
    'ok'        => true,
    'found'     => true,
    'reference' => $tx['reference_code'],
    'status'    => $tx['status'],
    'plan'      => $plan,
    'plan_name' => $planName,
    'amount'    => $amountCop,
    'buyer'     => $tx['buyer_name'] ?? '',
    'date'      => $tx['date_created'] ?? '',
]);

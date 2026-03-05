<?php
/**
 * WellCore Fitness — RISE Session Exchange
 * ============================================================
 * GET /api/wompi/rise-session.php?reference=WC-rise-{timestamp}
 *
 * Devuelve el auth_token de un cliente RISE cuyo pago fue
 * aprobado por Wompi. Solo funciona para plan='rise' y
 * status='approved'.
 *
 * RESPONSE (JSON):
 *   ok     bool
 *   token  string   (solo si approved)
 *   status string   pending|approved|declined|not_found
 * ============================================================
 */

$allowedOrigins = [
    'https://wellcorefitness.com',
    'https://www.wellcorefitness.com',
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

// Rate limit: 20 consultas por IP por hora
$ip = get_client_ip();
if (!rate_limit_check($ip, 'rise_session', 20, 3600)) {
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

// Validar que es una referencia RISE
if (!preg_match('/^WC-rise-\d+$/', $reference)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Referencia RISE inválida.']);
    exit;
}

$tx = transactions_find_by_reference($reference);

if (!$tx) {
    echo json_encode(['ok' => true, 'status' => 'not_found']);
    exit;
}

$status = $tx['status'] ?? 'pending';

if ($status !== 'approved') {
    echo json_encode(['ok' => true, 'status' => $status]);
    exit;
}

$riseToken = $tx['rise_token'] ?? null;

if (!$riseToken) {
    // Token aún no generado (webhook puede estar demorando). Intentar obtenerlo de DB.
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = getDB();
        $email = strtolower(trim($tx['buyer_email'] ?? ''));

        if ($email) {
            $stmt = $db->prepare("
                SELECT at.token
                FROM auth_tokens at
                JOIN clients c ON c.id = at.user_id
                WHERE c.email = ? AND at.user_type = 'client'
                  AND at.expires_at > NOW()
                ORDER BY at.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $riseToken = $stmt->fetchColumn() ?: null;
        }
    } catch (\Throwable $ignore) {}
}

if (!$riseToken) {
    // Token aún no disponible (webhook en camino)
    echo json_encode(['ok' => true, 'status' => 'approved', 'token' => null]);
    exit;
}

echo json_encode([
    'ok'     => true,
    'status' => 'approved',
    'token'  => $riseToken,
    'buyer'  => $tx['buyer_name'] ?? '',
]);

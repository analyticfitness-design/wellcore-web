<?php
/**
 * ============================================================
 * WELLCORE FITNESS — WOMPI CREATE ORDER
 * ============================================================
 * POST /api/wompi/create-order.php
 *
 * Genera la referencia, calcula el hash de integridad y
 * guarda la transaccion pendiente. El frontend usa estos datos
 * para renderizar el widget de Wompi.
 *
 * REQUEST (JSON):
 *   plan         string  esencial|metodo|elite
 *   buyer_name   string  Nombre completo
 *   buyer_email  string  Email del comprador
 *   buyer_phone  string  Telefono (opcional)
 *
 * RESPONSE (JSON):
 *   ok              bool
 *   reference       string  WC-{plan}-{timestamp}
 *   amount_in_cents int     Monto en centavos COP
 *   currency        string  COP
 *   integrity_hash  string  SHA256 para el widget
 *   widget_url      string  URL del script del widget
 *   public_key      string  Llave publica Wompi
 *   redirect_url    string  URL de redirect post-pago
 *   plan_display    string  Nombre del plan
 *   plan_desc       string  Descripcion del plan
 * ============================================================
 */

// -------------------------------------------------------
// HEADERS CORS
// -------------------------------------------------------
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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// -------------------------------------------------------
// DEPENDENCIAS
// -------------------------------------------------------
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/transactions.php';
require_once __DIR__ . '/rate-limit.php';

// -------------------------------------------------------
// HELPERS
// -------------------------------------------------------
function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function respondError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// -------------------------------------------------------
// RATE LIMITING: 10 ordenes por IP por hora
// -------------------------------------------------------
$ip = get_client_ip();
if (!rate_limit_check($ip, 'create_order', 10, 3600)) {
    respondError('Demasiadas solicitudes. Intenta en unos minutos.', 429);
}

// -------------------------------------------------------
// LEER INPUT
// -------------------------------------------------------
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$plan       = sanitize($body['plan']        ?? '');
$buyerName  = sanitize($body['buyer_name']  ?? '');
$buyerEmail = strtolower(trim($body['buyer_email'] ?? ''));
$buyerPhone = sanitize($body['buyer_phone'] ?? '');

// -------------------------------------------------------
// VALIDACIONES
// -------------------------------------------------------
$validPlans = array_keys(WELLCORE_PLANS);
if (!in_array($plan, $validPlans, true)) {
    respondError('Plan no valido. Opciones: ' . implode(', ', $validPlans));
}

if (empty($buyerName) || strlen($buyerName) < 3) {
    respondError('Nombre requerido (minimo 3 caracteres).');
}

if (empty($buyerEmail) || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    respondError('Email invalido.');
}

// -------------------------------------------------------
// GENERAR REFERENCIA UNICA
// -------------------------------------------------------
$referenceCode = 'WC-' . $plan . '-' . time();

// -------------------------------------------------------
// DATOS DEL PLAN
// -------------------------------------------------------
$planData      = WELLCORE_PLANS[$plan];
$amountInCents = $planData['amount_in_cents'];
$currency      = $planData['currency'];

// -------------------------------------------------------
// CALCULAR HASH DE INTEGRIDAD WOMPI
// Formula: SHA256(reference + amountInCents + currency + integritySecret)
// -------------------------------------------------------
$integrityHash = wompi_integrity_hash($referenceCode, $amountInCents, $currency);

// -------------------------------------------------------
// GUARDAR TRANSACCION PENDIENTE
// -------------------------------------------------------
$transaction = [
    'id'                    => generate_uuid(),
    'reference_code'        => $referenceCode,
    'plan'                  => $plan,
    'amount_in_cents'       => $amountInCents,
    'amount_cop'            => $planData['amount_cop'],
    'currency'              => $currency,
    'buyer_name'            => $buyerName,
    'buyer_email'           => $buyerEmail,
    'buyer_phone'           => $buyerPhone,
    'status'                => 'pending',
    'wompi_transaction_id'  => null,
    'wompi_payment_method'  => null,
    'date_created'          => date('c'),
    'date_updated'          => date('c'),
];

transactions_append($transaction);

// -------------------------------------------------------
// RESPUESTA AL FRONTEND
// -------------------------------------------------------
http_response_code(200);
echo json_encode([
    'ok'             => true,
    'reference'      => $referenceCode,
    'amount_in_cents'=> $amountInCents,
    'currency'       => $currency,
    'integrity_hash' => $integrityHash,
    'widget_url'     => wompi_widget_url(),
    'public_key'     => WOMPI_PUBLIC_KEY,
    'redirect_url'   => WOMPI_REDIRECT_URL,
    'plan_display'   => $planData['display'],
    'plan_desc'      => $planData['description'],
    'sandbox'        => WOMPI_SANDBOX,
]);

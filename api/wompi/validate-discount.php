<?php
/**
 * WellCore Fitness — Validar Código de Descuento
 * ================================================
 * POST /api/wompi/validate-discount.php
 * Body: { code: "FUNDADOR15", plan: "elite" }
 *
 * Retorna: { valid, discount_type, discount_value, original_amount,
 *            discount_amount, final_amount, message }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/rate-limit.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Rate limit: max 10 validaciones por IP cada 5 minutos
if (!rate_limit_check('validate_discount', 10, 300)) {
    http_response_code(429);
    echo json_encode(['valid' => false, 'error' => 'Demasiados intentos. Espera unos minutos.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($body['code'] ?? ''));
$plan = strtolower(trim($body['plan'] ?? ''));

if (empty($code)) {
    echo json_encode(['valid' => false, 'error' => 'Ingresa un código de descuento']);
    exit;
}

if (!isset(WELLCORE_PLANS[$plan])) {
    echo json_encode(['valid' => false, 'error' => 'Plan no válido']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT * FROM discount_codes
    WHERE code = ? AND is_active = 1
");
$stmt->execute([$code]);
$discount = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$discount) {
    echo json_encode(['valid' => false, 'error' => 'Código no válido o expirado']);
    exit;
}

// Verificar fechas
$now = new DateTime();
if ($discount['starts_at'] && new DateTime($discount['starts_at']) > $now) {
    echo json_encode(['valid' => false, 'error' => 'Este código aún no está activo']);
    exit;
}
if ($discount['expires_at'] && new DateTime($discount['expires_at']) < $now) {
    echo json_encode(['valid' => false, 'error' => 'Este código ha expirado']);
    exit;
}

// Verificar usos
if ($discount['max_uses'] > 0 && $discount['times_used'] >= $discount['max_uses']) {
    echo json_encode(['valid' => false, 'error' => 'Este código ya fue utilizado']);
    exit;
}

// Verificar que aplica a este plan
if ($discount['applies_to']) {
    $validPlans = array_map('trim', explode(',', $discount['applies_to']));
    if (!in_array($plan, $validPlans, true)) {
        echo json_encode(['valid' => false, 'error' => 'Este código no aplica para el plan ' . strtoupper($plan)]);
        exit;
    }
}

// Calcular descuento
$planData = WELLCORE_PLANS[$plan];
$originalCents = $planData['amount_in_cents'];

if ($discount['discount_type'] === 'percent') {
    $discountCents = (int) round($originalCents * ($discount['discount_value'] / 100));
} else {
    // fixed: discount_value en COP, convertir a centavos
    $discountCents = (int) ($discount['discount_value'] * 100);
}

// No puede ser mayor que el monto original
$discountCents = min($discountCents, $originalCents);
$finalCents = $originalCents - $discountCents;

// Monto mínimo
if ($discount['min_amount_cop'] > 0 && ($finalCents / 100) < $discount['min_amount_cop']) {
    echo json_encode(['valid' => false, 'error' => 'El descuento no puede reducir el monto por debajo del mínimo']);
    exit;
}

echo json_encode([
    'valid'           => true,
    'code'            => $discount['code'],
    'discount_id'     => (int) $discount['id'],
    'discount_type'   => $discount['discount_type'],
    'discount_value'  => (float) $discount['discount_value'],
    'description'     => $discount['description'],
    'original_amount' => $originalCents,
    'discount_amount' => $discountCents,
    'final_amount'    => $finalCents,
    'original_cop'    => number_format($originalCents / 100, 0, ',', '.'),
    'discount_cop'    => number_format($discountCents / 100, 0, ',', '.'),
    'final_cop'       => number_format($finalCents / 100, 0, ',', '.'),
    'message'         => $discount['discount_type'] === 'percent'
        ? "¡{$discount['discount_value']}% de descuento aplicado!"
        : "¡$" . number_format($discount['discount_value'], 0, ',', '.') . " de descuento aplicado!",
]);

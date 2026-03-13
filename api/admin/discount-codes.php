<?php
/**
 * WellCore Fitness — Admin: Códigos de Descuento
 * ================================================
 * GET  /api/admin/discount-codes  → lista todos los códigos
 * POST /api/admin/discount-codes  → crear nuevo código
 *
 * POST Body:
 *   code           string   Código (ej: FUNDADOR15)
 *   description    string   Descripción
 *   discount_type  string   percent|fixed
 *   discount_value number   15 (para 15%) o 50000 (para $50.000 fijo)
 *   applies_to     string   null (todos) o "esencial,metodo,elite"
 *   max_uses       int      1 para un solo uso, 0 para ilimitado
 *   expires_at     string   "2026-12-31 23:59:59" o null
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = authenticateAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("
        SELECT dc.*,
               (SELECT COUNT(*) FROM discount_code_usage dcu WHERE dcu.discount_code_id = dc.id AND dcu.payment_status = 'approved') as approved_uses
        FROM discount_codes dc
        ORDER BY dc.created_at DESC
    ");
    respond(['ok' => true, 'codes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

requireMethod('POST');
$body = getJsonBody();

$code = strtoupper(trim($body['code'] ?? ''));
$description = trim($body['description'] ?? '');
$discountType = $body['discount_type'] ?? 'percent';
$discountValue = (float) ($body['discount_value'] ?? 0);
$appliesTo = !empty($body['applies_to']) ? trim($body['applies_to']) : null;
$maxUses = (int) ($body['max_uses'] ?? 1);
$expiresAt = !empty($body['expires_at']) ? $body['expires_at'] : null;

// Validaciones
if (empty($code) || strlen($code) < 3) {
    respondError('Código requerido (mínimo 3 caracteres)', 400);
}
if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
    respondError('Código solo puede contener letras, números, guión y guión bajo', 400);
}
if (!in_array($discountType, ['percent', 'fixed'], true)) {
    respondError('Tipo debe ser percent o fixed', 400);
}
if ($discountValue <= 0) {
    respondError('Valor de descuento debe ser mayor a 0', 400);
}
if ($discountType === 'percent' && $discountValue > 100) {
    respondError('Porcentaje no puede ser mayor a 100%', 400);
}

// Verificar que no exista
$check = $db->prepare("SELECT id FROM discount_codes WHERE code = ?");
$check->execute([$code]);
if ($check->fetch()) {
    respondError("El código '$code' ya existe", 409);
}

$stmt = $db->prepare("
    INSERT INTO discount_codes (code, description, discount_type, discount_value, applies_to, max_uses, expires_at, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$code, $description, $discountType, $discountValue, $appliesTo, $maxUses, $expiresAt, $admin['id']]);

$newId = $db->lastInsertId();

respond([
    'ok' => true,
    'message' => "Código $code creado exitosamente",
    'discount_code' => [
        'id' => (int) $newId,
        'code' => $code,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'max_uses' => $maxUses,
        'expires_at' => $expiresAt,
    ]
]);

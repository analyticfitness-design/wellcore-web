<?php
// POST /api/rise/payment.php
// Procesar pago del reto RISE y crear auth token para el cliente
// Body: { client_id, program_id, payment_method, amount }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';

requireMethod('POST');

// Rate limit: 5 intentos por IP cada 30 minutos
if (!rate_limit_check('rise_payment', 5, 1800)) {
    respondError('Demasiadas solicitudes. Intenta en unos minutos.', 429);
}

$input = getJsonBody();

// Validar campos requeridos
$required = ['client_id', 'program_id', 'payment_method', 'amount'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        respondError("Campo requerido: $field", 400);
    }
}

$client_id = intval($input['client_id']);
$program_id = intval($input['program_id']);
$payment_method = htmlspecialchars($input['payment_method'], ENT_QUOTES, 'UTF-8');
$amount = floatval($input['amount']);

// Validar amount (RISE cuesta $33 USD o $99,900 COP)
if ($amount < 30 || $amount > 105000) {
    respondError('Monto inválido', 400);
}

$db = getDB();

// Verificar que el cliente existe
$stmt = $db->prepare("SELECT id, email, name, plan FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
if ($stmt->rowCount() === 0) {
    respondError('Cliente no encontrado', 404);
}

$client = $stmt->fetch(PDO::FETCH_ASSOC);
if ($client['plan'] !== 'rise') {
    respondError('Este cliente no tiene un plan RISE', 400);
}

// Verificar que el programa existe y pertenece al cliente
$stmt = $db->prepare("SELECT id FROM rise_programs WHERE id = ? AND client_id = ?");
$stmt->execute([$program_id, $client_id]);
if ($stmt->rowCount() === 0) {
    respondError('Programa RISE no encontrado para este cliente', 404);
}

try {
    $db->beginTransaction();

    // 1. Registrar pago en tabla payments (usar columnas que existen)
    $reference = 'RISE-' . strtoupper(bin2hex(random_bytes(6)));
    $stmt = $db->prepare("
        INSERT INTO payments
        (client_id, amount, currency, plan, payu_reference, status, created_at)
        VALUES (?, ?, 'USD', 'rise', ?, 'approved', NOW())
    ");
    $stmt->execute([$client_id, $amount, $reference]);
    $payment_id = $db->lastInsertId();

    // 2. Crear auth token para el cliente
    // Generar token seguro
    $token = bin2hex(random_bytes(32));
    $expiration = date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = $db->prepare("
        INSERT INTO auth_tokens
        (user_type, user_id, token, expires_at, created_at)
        VALUES ('client', ?, ?, ?, NOW())
    ");

    $stmt->execute([$client_id, $token, $expiration]);

    // 3. Actualizar estado del programa a 'active'
    $stmt = $db->prepare("
        UPDATE rise_programs
        SET status = 'active'
        WHERE id = ? AND client_id = ?
    ");
    $stmt->execute([$program_id, $client_id]);

    $db->commit();

    // Respuesta exitosa
    respond([
        'success' => true,
        'message' => 'Pago procesado exitosamente. Acceso activado.',
        'token' => $token, // Enviar token al cliente
        'client' => [
            'id' => $client_id,
            'name' => $client['name'],
            'email' => $client['email'],
            'plan' => 'rise'
        ],
        'payment' => [
            'id' => $payment_id,
            'reference' => $reference,
            'amount' => $amount,
            'status' => 'completed'
        ],
        'program' => [
            'id' => $program_id,
            'status' => 'active'
        ],
        'token_expires_at' => $expiration
    ], 201);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('rise/payment.php error: ' . $e->getMessage());
    respondError('Error interno al procesar pago', 500);
}
?>

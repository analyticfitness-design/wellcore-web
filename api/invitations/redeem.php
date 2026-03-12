<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * WellCore Fitness — Redeem Invitation Code (Public)
 * POST /api/invitations/redeem.php
 *
 * Body: {code, nombre, email, password, telefono}
 * Creates client account, marks invitation as used, returns auth token.
 */

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = ['https://wellcorefitness.com', 'https://www.wellcorefitness.com', 'http://172.17.216.45:8082', 'http://localhost:8082'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://wellcorefitness.com');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

function ok(array $data): void {
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Rate limiting (5 req/hour per IP) ---
$rateLimitFile = sys_get_temp_dir() . '/wc_invite_redeem_rate.json';
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = hash('sha256', $clientIp);
$now = time();
$window = 3600;
$maxReq = 5;

$rateData = [];
if (file_exists($rateLimitFile)) {
    $rateData = json_decode(@file_get_contents($rateLimitFile), true) ?: [];
}

foreach ($rateData as $hash => $entry) {
    if ($now - $entry['first_request'] > $window) {
        unset($rateData[$hash]);
    }
}

if (isset($rateData[$ipHash]) && $rateData[$ipHash]['count'] >= $maxReq) {
    err('Rate limit exceeded', 429);
}

if (isset($rateData[$ipHash])) {
    $rateData[$ipHash]['count']++;
} else {
    $rateData[$ipHash] = ['count' => 1, 'first_request' => $now];
}

@file_put_contents($rateLimitFile, json_encode($rateData, JSON_PRETTY_PRINT), LOCK_EX);

// --- Parse body ---
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    err('Cuerpo JSON invalido o vacio');
}

// --- Validate fields ---
$code     = trim($body['code'] ?? '');
$nombre   = htmlspecialchars(trim($body['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$email    = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';
$telefono = htmlspecialchars(trim($body['telefono'] ?? ''), ENT_QUOTES, 'UTF-8');

if (!$code)     err('Codigo de invitacion requerido');
if (!$nombre)   err('Nombre requerido');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) err('Email valido requerido');
if (strlen($password) < 6) err('La contrasena debe tener al menos 6 caracteres');

if (!preg_match('/^[a-f0-9]{32}$/i', $code)) {
    err('Codigo de invitacion invalido');
}

// --- Validate invitation ---
require_once __DIR__ . '/../config/database.php';
$db = getDB();

try {
    $db->beginTransaction();

    // Lock the invitation row
    $stmt = $db->prepare("
        SELECT id, plan, status, expires_at
        FROM invitations
        WHERE code = ?
        FOR UPDATE
    ");
    $stmt->execute([$code]);
    $inv = $stmt->fetch();

    if (!$inv) {
        $db->rollBack();
        err('Codigo de invitacion no encontrado');
    }

    if ($inv['status'] !== 'pending') {
        $db->rollBack();
        err('Esta invitacion ya fue utilizada o expiro');
    }

    if ($inv['expires_at'] && strtotime($inv['expires_at']) < $now) {
        $db->prepare("UPDATE invitations SET status = 'expired' WHERE id = ?")->execute([$inv['id']]);
        $db->commit();
        err('Esta invitacion ha expirado');
    }

    $plan = $inv['plan'];

    // Check email not already in use
    $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $db->rollBack();
        err('Ya existe una cuenta con este email', 409);
    }

    // Generate client code: WC-{PLAN}-{NNN} pattern (matches existing codes)
    $planUpper = strtoupper($plan);
    $prefix = "WC-{$planUpper}-";
    $stmt2 = $db->prepare("SELECT COUNT(*) FROM clients WHERE client_code LIKE ?");
    $stmt2->execute([$prefix . '%']);
    $count = (int) $stmt2->fetchColumn();
    $clientCode = $prefix . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Create client
    $stmt = $db->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, plan, status, fecha_inicio)
        VALUES (?, ?, ?, ?, ?, 'activo', CURDATE())
    ");
    $stmt->execute([$clientCode, $nombre, $email, $hash, $plan]);
    $clientId = $db->lastInsertId();

    // Create default profile with phone
    $stmt = $db->prepare("INSERT INTO client_profiles (client_id, whatsapp) VALUES (?, ?)");
    $stmt->execute([$clientId, $telefono ?: null]);

    // Mark invitation as used
    $stmt = $db->prepare("
        UPDATE invitations SET status = 'used', used_by = ?, used_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$clientId, $inv['id']]);

    // Create auth token (24h)
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', $now + (24 * 3600));
    $stmt = $db->prepare("
        INSERT INTO auth_tokens (user_type, user_id, token, expires_at)
        VALUES ('client', ?, ?, ?)
    ");
    $stmt->execute([$clientId, $token, $expiresAt]);

    $db->commit();

    // Notificar al admin
    require_once __DIR__ . '/../includes/notify-admin.php';
    notifyAdminNewClient([
        'name' => $nombre, 'email' => $email, 'plan' => $plan, 'code' => $clientCode,
        'phone' => $telefono,
    ], 'invitation');

    ok([
        'message'     => 'Cuenta creada exitosamente',
        'client_id'   => $clientId,
        'client_code' => $clientCode,
        'plan'        => $plan,
        'token'       => $token,
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[WellCore] redeem error: ' . $e->getMessage());
    err('Error al crear cuenta. Intenta de nuevo o contacta soporte.', 500);
}

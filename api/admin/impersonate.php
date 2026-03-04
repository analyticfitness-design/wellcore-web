<?php
// POST /api/admin/impersonate
// Genera un token temporal de cliente para que el admin pueda ver su portal
// Body: { client_id: int }
// Requiere token de admin válido (admin/jefe/superadmin)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

$admin = requireAdminRole('admin', 'jefe', 'superadmin');

$input    = getJsonBody();
$clientId = intval($input['client_id'] ?? 0);
if (!$clientId) respondError('client_id requerido', 400);

$db = getDB();

// Verificar que el cliente existe y está activo
$stmt = $db->prepare("SELECT id, name, email, plan, status FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) respondError('Cliente no encontrado', 404);
if ($client['status'] !== 'activo') respondError('Cliente inactivo', 403);

// Crear token temporal (1 hora), no elimina tokens existentes del cliente
$token   = generateToken();
$expires = date('Y-m-d H:i:s', time() + 3600);
$fp      = getClientFingerprint();
$ip      = getClientIp();

$stmt = $db->prepare(
    "INSERT INTO auth_tokens (user_type, user_id, token, fingerprint, ip_address, expires_at) VALUES ('client', ?, ?, ?, ?, ?)"
);
$stmt->execute([$clientId, $token, $fp, $ip, $expires]);

respond([
    'token'      => $token,
    'expires_in' => 3600,
    'client'     => [
        'id'    => $client['id'],
        'name'  => $client['name'],
        'email' => $client['email'],
        'plan'  => $client['plan'],
    ]
]);
?>

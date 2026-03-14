<?php
/**
 * WellCore Fitness — Cambiar Contraseña del Cliente
 * POST /api/client/change-password
 * Body: { current_password: string, new_password: string }
 * Auth: Cliente autenticado
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();
$body   = getJsonBody();

$currentPassword = $body['current_password'] ?? '';
$newPassword     = $body['new_password']     ?? '';

if (empty($newPassword) || strlen($newPassword) < 6) {
    respondError('La nueva contraseña debe tener al menos 6 caracteres', 422);
}

$db   = getDB();
$stmt = $db->prepare("SELECT password_hash, must_change_password FROM clients WHERE id = ?");
$stmt->execute([$client['user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    respondError('Cliente no encontrado', 404);
}

// Always require current password — even for must_change_password (user knows temp password from email)
if (empty($currentPassword)) {
    respondError('Debes ingresar tu contraseña actual', 422);
}
if (!password_verify($currentPassword, $row['password_hash'])) {
    respondError('La contraseña actual es incorrecta', 401);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);

$db->prepare("UPDATE clients SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?")
   ->execute([$newHash, $client['user_id']]);

respond([
    'ok'      => true,
    'message' => 'Contraseña actualizada correctamente',
]);

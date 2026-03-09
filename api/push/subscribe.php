<?php
// POST /api/push/subscribe  — Guarda o actualiza suscripción push del cliente
// DELETE /api/push/subscribe — Desactiva suscripción del cliente
//
// POST body: { "endpoint": "...", "keys": { "p256dh": "...", "auth": "..." }, "userAgent": "..." }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST', 'DELETE');

$client = authenticateClient();
$clientId = (int)$client['id'];
$db = getDB();

// ===== DELETE — desactivar suscripción =====
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE client_id = ?");
    $stmt->execute([$clientId]);
    respond(['success' => true, 'message' => 'Suscripcion desactivada']);
}

// ===== POST — guardar/actualizar suscripción =====
$body = getJsonBody();

$endpoint = trim($body['endpoint'] ?? '');
$p256dh   = trim($body['keys']['p256dh'] ?? '');
$authKey  = trim($body['keys']['auth'] ?? '');
$ua       = substr(trim($body['userAgent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 200);

if ($endpoint === '') respondError('endpoint requerido', 422);
if ($p256dh === '')   respondError('keys.p256dh requerido', 422);
if ($authKey === '')  respondError('keys.auth requerido', 422);

// Desactivar todas las suscripciones previas de este cliente
$stmt = $db->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE client_id = ?");
$stmt->execute([$clientId]);

// ¿Ya existe esta suscripción exacta (mismo endpoint)?
$stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE client_id = ? AND endpoint = ?");
$stmt->execute([$clientId, $endpoint]);
$existing = $stmt->fetch();

if ($existing) {
    // Reactivar y actualizar claves (pueden cambiar en re-subscribe)
    $stmt = $db->prepare("
        UPDATE push_subscriptions
        SET p256dh_key = ?, auth_key = ?, user_agent = ?, is_active = 1, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$p256dh, $authKey, $ua, (int)$existing['id']]);
    $subId = (int)$existing['id'];
} else {
    // Insertar nueva
    $stmt = $db->prepare("
        INSERT INTO push_subscriptions (client_id, endpoint, p256dh_key, auth_key, user_agent, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$clientId, $endpoint, $p256dh, $authKey, $ua]);
    $subId = (int)$db->lastInsertId();
}

respond(['success' => true, 'subscription_id' => $subId]);

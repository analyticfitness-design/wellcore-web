<?php
/**
 * POST /api/notifications/subscribe   — Registra suscripción push del browser
 * DELETE /api/notifications/subscribe — Desuscribe
 *
 * Body POST: { endpoint, p256dh, auth }
 * Auth: cliente
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$client = authenticateClient();
$cid    = $client['id'];

if ($method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $endpoint = trim($body['endpoint'] ?? '');
    $p256dh   = trim($body['p256dh'] ?? '');
    $auth     = trim($body['auth'] ?? '');

    if (!$endpoint || !$p256dh || !$auth) {
        respondError('endpoint, p256dh y auth son requeridos', 400);
    }

    $db->prepare("
        INSERT INTO push_subscriptions (client_id, endpoint, p256dh, auth, is_active)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            p256dh    = VALUES(p256dh),
            auth      = VALUES(auth),
            is_active = 1,
            updated_at = NOW()
    ")->execute([$cid, $endpoint, $p256dh, $auth]);

    respond(['success' => true, 'message' => 'Notificaciones activadas']);

} elseif ($method === 'DELETE') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $endpoint = trim($body['endpoint'] ?? '');

    if ($endpoint) {
        $db->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE client_id = ? AND endpoint = ?")
           ->execute([$cid, $endpoint]);
    } else {
        $db->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE client_id = ?")
           ->execute([$cid]);
    }

    respond(['success' => true, 'message' => 'Notificaciones desactivadas']);

} else {
    respondError('Método no permitido', 405);
}

<?php
// GET  /api/coach/messages?client_id=X  — thread de mensajes
// POST /api/coach/messages { client_id, message } — enviar mensaje

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$coach = authenticateCoach();
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    if (!$clientId) respondError('client_id requerido', 400);

    // Verificar que el cliente pertenece a este coach
    $check = $db->prepare("SELECT id FROM clients WHERE id = ? AND coach_id = ?");
    $check->execute([$clientId, $coach['id']]);
    if (!$check->fetch()) respondError('Cliente no encontrado', 404);

    // Marcar mensajes del cliente como leídos
    $db->prepare("UPDATE coach_messages SET read_at = NOW()
                  WHERE coach_id = ? AND client_id = ? AND direction = 'client_to_coach' AND read_at IS NULL")
       ->execute([$coach['id'], $clientId]);

    $stmt = $db->prepare("
        SELECT id, message, direction, read_at, created_at
        FROM coach_messages
        WHERE coach_id = ? AND client_id = ?
        ORDER BY created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$coach['id'], $clientId]);
    respond(['messages' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $clientId = isset($body['client_id']) ? (int)$body['client_id'] : 0;
    $message  = trim($body['message'] ?? '');

    if (!$clientId || !$message) respondError('client_id y message requeridos', 400);
    if (strlen($message) > 2000) respondError('Mensaje demasiado largo', 400);

    $check = $db->prepare("SELECT id FROM clients WHERE id = ? AND coach_id = ?");
    $check->execute([$clientId, $coach['id']]);
    if (!$check->fetch()) respondError('Cliente no encontrado', 404);

    $stmt = $db->prepare("
        INSERT INTO coach_messages (coach_id, client_id, message, direction)
        VALUES (?, ?, ?, 'coach_to_client')
    ");
    $stmt->execute([$coach['id'], $clientId, $message]);

    respond(['ok' => true, 'id' => $db->lastInsertId()]);
}

respondError('Método no permitido', 405);

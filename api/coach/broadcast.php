<?php
// POST /api/coach/broadcast { message } — enviar a todos los clientes activos del coach

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$coach = authenticateCoach();
$db    = getDB();

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim($body['message'] ?? '');
if (!$message) respondError('message requerido', 400);
if (strlen($message) > 2000) respondError('Mensaje demasiado largo', 400);

$clients = $db->prepare("SELECT id FROM clients WHERE coach_id = ? AND status = 'active'");
$clients->execute([$coach['id']]);
$rows = $clients->fetchAll();
if (!$rows) respond(['ok' => true, 'sent' => 0]);

$insert = $db->prepare("
    INSERT INTO coach_messages (coach_id, client_id, message, direction)
    VALUES (?, ?, ?, 'coach_to_client')
");
foreach ($rows as $row) {
    $insert->execute([$coach['id'], $row['id'], $message]);
}

respond(['ok' => true, 'sent' => count($rows)]);

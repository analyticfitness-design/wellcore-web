<?php
// POST /api/notifications/mark-read
// Body: { id: 5 } | { all: true }
// Auth: Bearer token (client)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();
$body = getJsonBody();
$db = getDB();

$now = date('Y-m-d H:i:s');

if (!empty($body['all'])) {
    $stmt = $db->prepare("
        UPDATE notifications SET read_at = ?
        WHERE user_type = 'client' AND user_id = ? AND read_at IS NULL
    ");
    $stmt->execute([$now, $client['id']]);
    respond(['ok' => true, 'updated' => $stmt->rowCount()]);
}

$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    respondError('Se requiere id o all:true', 400);
}

$stmt = $db->prepare("
    UPDATE notifications SET read_at = ?
    WHERE id = ? AND user_type = 'client' AND user_id = ? AND read_at IS NULL
");
$stmt->execute([$now, $id, $client['id']]);

if ($stmt->rowCount() === 0) {
    respondError('Notificacion no encontrada o ya leida', 404);
}

respond(['ok' => true]);

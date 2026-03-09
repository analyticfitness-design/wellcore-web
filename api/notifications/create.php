<?php
// POST /api/notifications/create  (solo admin)
// Body: { user_id: N|"all", type: "info", title: "...", body: "...", link: "..." }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
requireAdminRole('admin', 'superadmin');

$body = getJsonBody();
$db = getDB();

$type  = trim($body['type']  ?? 'info');
$title = trim($body['title'] ?? '');
$msg   = trim($body['body']  ?? '');
$link  = trim($body['link']  ?? '');
$userId = $body['user_id'] ?? null;

if ($title === '') {
    respondError('El titulo es requerido', 422);
}
if (strlen($title) > 160) {
    respondError('El titulo no puede superar 160 caracteres', 422);
}

$now = date('Y-m-d H:i:s');

if ($userId === 'all' || $userId === null) {
    // Enviar a todos los clientes activos
    $clients = $db->query("SELECT id FROM clients WHERE status = 'activo'")->fetchAll();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_type, user_id, type, title, body, link, created_at)
        VALUES ('client', ?, ?, ?, ?, ?, ?)
    ");
    foreach ($clients as $c) {
        $stmt->execute([(int)$c['id'], $type, $title, $msg ?: null, $link ?: null, $now]);
    }
    respond(['ok' => true, 'sent_to' => count($clients)]);
}

$uid = (int)$userId;
if ($uid <= 0) {
    respondError('user_id invalido', 422);
}

$stmt = $db->prepare("
    INSERT INTO notifications (user_type, user_id, type, title, body, link, created_at)
    VALUES ('client', ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$uid, $type, $title, $msg ?: null, $link ?: null, $now]);

respond(['ok' => true, 'notification_id' => (int)$db->lastInsertId()]);

<?php
/**
 * GET  /api/pods/messages?pod_id=&limit=30  — Lee mensajes del pod
 * POST /api/pods/messages                    — Envía mensaje al pod
 *
 * Auth: cliente (debe ser miembro del pod)
 * Responde: { messages[] } o { id, created_at }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('GET', 'POST');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$client = authenticateClient();
$cid    = $client['id'];

function assertMember(PDO $db, int $pod_id, string $client_id): void {
    $m = $db->prepare("SELECT 1 FROM pod_members WHERE pod_id = ? AND client_id = ?");
    $m->execute([$pod_id, $client_id]);
    if (!$m->fetchColumn()) {
        respondError('No eres miembro de este pod', 403);
    }
}

if ($method === 'GET') {
    $pod_id = (int)($_GET['pod_id'] ?? 0);
    $limit  = min(100, max(10, (int)($_GET['limit'] ?? 30)));

    if (!$pod_id) respondError('pod_id requerido', 400);
    assertMember($db, $pod_id, $cid);

    $stmt = $db->prepare("
        SELECT pm.id, pm.client_id, c.name AS client_name, pm.message, pm.created_at
        FROM pod_messages pm
        JOIN clients c ON c.id = pm.client_id
        WHERE pm.pod_id = ?
        ORDER BY pm.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$pod_id, $limit]);
    $msgs = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    respond(['messages' => $msgs]);

} elseif ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $pod_id  = (int)($body['pod_id'] ?? 0);
    $message = trim($body['message'] ?? '');

    if (!$pod_id || $message === '') respondError('pod_id y message son requeridos', 400);
    assertMember($db, $pod_id, $cid);

    if (mb_strlen($message) > 1000) respondError('Mensaje demasiado largo (máx 1000 chars)', 400);

    $db->prepare("INSERT INTO pod_messages (pod_id, client_id, message) VALUES (?, ?, ?)")
       ->execute([$pod_id, $cid, $message]);

    $new_id = (int)$db->lastInsertId();
    respond(['id' => $new_id, 'created_at' => date('Y-m-d H:i:s')]);

} else {
    respondError('Método no permitido', 405);
}

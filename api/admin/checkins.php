<?php
// GET /api/admin/checkins?pending=1  → pending checkins
// PUT /api/admin/checkins?id=X       → reply to checkin

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET','PUT');
$admin = authenticateAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pending  = isset($_GET['pending']) && $_GET['pending'] == '1';
    $clientId = (int)($_GET['client_id'] ?? 0);

    $sql = "SELECT ci.*, c.name as client_name, c.client_code, c.plan
            FROM checkins ci JOIN clients c ON c.id = ci.client_id
            WHERE 1=1";
    $params = [];

    if ($pending)  { $sql .= " AND ci.coach_reply IS NULL"; }
    if ($clientId) { $sql .= " AND ci.client_id = ?"; $params[] = $clientId; }
    $sql .= " ORDER BY ci.checkin_date DESC LIMIT 50";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respond(['checkins' => $stmt->fetchAll()]);
}

// PUT — reply
$id = (int)($_GET['id'] ?? 0);
if (!$id) respondError('ID requerido', 422);

$body  = getJsonBody();
$reply = htmlspecialchars(trim($body['reply'] ?? ''), ENT_QUOTES, 'UTF-8');
if (!$reply) respondError('Respuesta requerida', 422);

$stmt = $db->prepare("UPDATE checkins SET coach_reply = ?, replied_at = NOW() WHERE id = ?");
$stmt->execute([$reply, $id]);
respond(['message' => 'Respuesta enviada al cliente']);

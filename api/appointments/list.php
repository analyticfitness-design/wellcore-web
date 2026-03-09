<?php
/**
 * GET  /api/appointments/list  — Lista citas del cliente o coach
 * POST /api/appointments/list  — Coach actualiza status de una cita (confirm/cancel/complete)
 *
 * Query params: ?status=pending|confirmed|all&upcoming=1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $is_coach  = false;
    $filter_id = '';
    try {
        $client    = authenticateClient();
        $filter_id = $client['id'];
    } catch (\Exception $e) {
        $coach     = authenticateCoach();
        $is_coach  = true;
        $filter_id = $coach['id'];
    }

    $status   = $_GET['status'] ?? 'all';
    $upcoming = (bool)($_GET['upcoming'] ?? false);

    $where    = $is_coach ? "a.coach_id = ?" : "a.client_id = ?";
    $params   = [$filter_id];

    if ($status !== 'all') {
        $where    .= " AND a.status = ?";
        $params[]  = $status;
    }
    if ($upcoming) {
        $where    .= " AND a.scheduled_at >= NOW()";
    }

    $stmt = $db->prepare("
        SELECT a.id, a.scheduled_at, a.duration_min, a.title, a.notes,
               a.meet_link, a.status, a.created_at,
               c.name AS client_name, c.plan AS client_plan
        FROM appointments a
        JOIN clients c ON c.id = a.client_id
        WHERE {$where}
        ORDER BY a.scheduled_at ASC
        LIMIT 50
    ");
    $stmt->execute($params);

    respond(['appointments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} elseif ($method === 'POST') {
    $coach    = authenticateCoach();
    $coach_id = $coach['id'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

    $appt_id  = (int)($body['id'] ?? 0);
    $status   = $body['status'] ?? '';
    $meet_link= trim($body['meet_link'] ?? '');

    $valid = ['confirmed', 'cancelled', 'completed'];
    if (!$appt_id || !in_array($status, $valid, true)) {
        respondError('id y status válido requeridos', 400);
    }

    $updates = "status = ?";
    $params  = [$status];

    if ($meet_link) {
        $updates  .= ", meet_link = ?";
        $params[]  = $meet_link;
    }

    $params[] = $appt_id;
    $params[] = $coach_id;

    $db->prepare("UPDATE appointments SET {$updates}, updated_at = NOW() WHERE id = ? AND coach_id = ?")
       ->execute($params);

    respond(['success' => true, 'id' => $appt_id, 'status' => $status]);

} else {
    respondError('Método no permitido', 405);
}

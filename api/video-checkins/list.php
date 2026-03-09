<?php
/**
 * GET /api/video-checkins/list
 * Lista los video check-ins del cliente autenticado o (coach) de sus clientes.
 *
 * Query params: ?limit=20&offset=0&status=pending|coach_reviewed|ai_reviewed
 * Responde: { total, items[] }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$limit  = min(50, max(5, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$status = $_GET['status'] ?? '';

$valid_statuses = ['pending', 'coach_reviewed', 'ai_reviewed'];

// Autenticar por tipo de token — evita exit() prematuro del try-catch
$is_coach  = false;
$filter_id = '';

$tokenType = peekTokenUserType();
if (!$tokenType) respondError('Autenticación requerida', 401);

if ($tokenType === 'client') {
    $client    = authenticateClient();
    $filter_id = $client['id'];
} else {
    $coach     = authenticateCoach();
    $is_coach  = true;
    $filter_id = $coach['id'];
}

$where = $is_coach ? "vc.coach_id = ?" : "vc.client_id = ?";
$params = [$filter_id];

if ($status && in_array($status, $valid_statuses, true)) {
    $where .= " AND vc.status = ?";
    $params[] = $status;
}

$total_row = $db->prepare("SELECT COUNT(*) FROM video_checkins vc WHERE {$where}");
$total_row->execute($params);
$total = (int)$total_row->fetchColumn();

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare("
    SELECT
        vc.id, vc.client_id, vc.media_type, vc.media_url,
        vc.exercise_name, vc.notes,
        vc.coach_response, vc.ai_response, vc.ai_used,
        vc.status, vc.responded_at, vc.created_at,
        c.name AS client_name
    FROM video_checkins vc
    JOIN clients c ON c.id = vc.client_id
    WHERE {$where}
    ORDER BY vc.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);

respond([
    'total' => $total,
    'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
]);

<?php
/**
 * WellCore Fitness — Admin AI History
 * GET /api/admin/ai-history
 *
 * Query params opcionales:
 *   type    (entrenamiento|nutricion|habitos|ticket_response|analisis)
 *   status  (queued|pending|completed|failed|approved|rejected)
 *   client_id (int)
 *   limit   (default 50, max 200)
 *   offset  (default 0)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
$db    = getDB();

$type      = $_GET['type']      ?? '';
$status    = $_GET['status']    ?? '';
$clientId  = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
$limit     = min(200, max(1, (int) ($_GET['limit']  ?? 50)));
$offset    = max(0, (int) ($_GET['offset'] ?? 0));

$where  = [];
$params = [];

if ($type !== '') {
    $where[]  = 'g.type = ?';
    $params[] = $type;
}
if ($status !== '') {
    $where[]  = 'g.status = ?';
    $params[] = $status;
}
if ($clientId > 0) {
    $where[]  = 'g.client_id = ?';
    $params[] = $clientId;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = $db->prepare("
    SELECT
        g.id,
        g.client_id,
        c.name          AS client_name,
        g.type,
        g.ticket_id,
        g.status,
        g.model,
        g.prompt_tokens,
        g.completion_tokens,
        g.coach_notes,
        g.approved_by,
        g.approved_at,
        g.created_at,
        -- Costo estimado USD (opus-4-6: $15/M inp, $75/M out)
        ROUND(
            (g.prompt_tokens     / 1000000 * 15.0) +
            (g.completion_tokens / 1000000 * 75.0),
            6
        ) AS cost_usd,
        -- Extracto del raw_response (primeros 300 chars)
        LEFT(g.raw_response, 300) AS raw_preview
    FROM ai_generations g
    LEFT JOIN clients c ON c.id = g.client_id
    $whereClause
    ORDER BY g.created_at DESC
    LIMIT $limit OFFSET $offset
");
$rows->execute($params);
$generations = $rows->fetchAll(PDO::FETCH_ASSOC);

// Total count para paginación
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM ai_generations g $whereClause
");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

respond([
    'generations' => $generations,
    'pagination'  => [
        'total'  => $total,
        'limit'  => $limit,
        'offset' => $offset,
        'pages'  => (int) ceil($total / $limit),
    ],
]);

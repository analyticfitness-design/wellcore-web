<?php
/**
 * WellCore Fitness — Admin AI Generations List
 *
 * GET /api/admin/ai-generations
 *   ?status=pending|completed|approved|rejected|failed   (default: completed)
 *   ?type=rise|entrenamiento|nutricion|habitos           (opcional)
 *   ?client_id=X                                         (opcional)
 *   ?limit=20                                            (default: 50)
 *
 * Devuelve lista de generaciones IA con datos del cliente y del plan asignado.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
authenticateAdmin();
$db = getDB();

$status   = in_array($_GET['status'] ?? '', ['pending','completed','approved','rejected','failed'], true)
            ? $_GET['status'] : 'completed';
$type     = trim($_GET['type']      ?? '');
$clientId = (int) ($_GET['client_id'] ?? 0);
$limit    = min((int) ($_GET['limit'] ?? 50), 200);

$where  = ['g.status = ?'];
$params = [$status];

if ($type !== '') {
    $where[]  = 'g.type = ?';
    $params[] = $type;
}
if ($clientId > 0) {
    $where[]  = 'g.client_id = ?';
    $params[] = $clientId;
}

$whereSQL = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT
        g.id,
        g.client_id,
        g.type,
        g.status,
        g.model,
        g.prompt_tokens,
        g.completion_tokens,
        g.coach_notes,
        g.approved_at,
        g.approved_by,
        g.created_at,
        c.name        AS client_name,
        c.email       AS client_email,
        c.plan        AS client_plan,
        ap.id         AS assigned_plan_id,
        ap.active     AS plan_active,
        ap.version    AS plan_version
    FROM ai_generations g
    LEFT JOIN clients c  ON c.id  = g.client_id
    LEFT JOIN assigned_plans ap ON ap.ai_generation_id = g.id
    WHERE $whereSQL
    ORDER BY g.created_at DESC
    LIMIT $limit
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir tipos
foreach ($rows as &$r) {
    $r['id']               = (int) $r['id'];
    $r['client_id']        = (int) $r['client_id'];
    $r['prompt_tokens']    = (int) $r['prompt_tokens'];
    $r['completion_tokens']= (int) $r['completion_tokens'];
    $r['assigned_plan_id'] = $r['assigned_plan_id'] ? (int) $r['assigned_plan_id'] : null;
    $r['plan_active']      = $r['plan_active'] !== null ? (bool) $r['plan_active']  : null;
    $r['plan_version']     = $r['plan_version'] ? (int) $r['plan_version'] : null;
}
unset($r);

respond(['ok' => true, 'total' => count($rows), 'generations' => $rows]);

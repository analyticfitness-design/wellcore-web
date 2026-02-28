<?php
// POST /api/admin/plans              → assign plan to client
// GET  /api/admin/plans?client_id=X  → get client's plans

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET','POST');
$admin = authenticateAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $clientId = (int)($_GET['client_id'] ?? 0);
    if (!$clientId) respondError('client_id requerido', 422);

    $stmt = $db->prepare("
        SELECT ap.*, a.name as assigned_by_name
        FROM assigned_plans ap LEFT JOIN admins a ON a.id = ap.assigned_by
        WHERE ap.client_id = ? AND ap.active = 1
        ORDER BY ap.plan_type
    ");
    $stmt->execute([$clientId]);
    respond(['plans' => $stmt->fetchAll()]);
}

// POST — assign plan
$body      = getJsonBody();
$clientId  = (int)($body['client_id']  ?? 0);
$planType  = $body['plan_type']  ?? '';
$content   = $body['content']    ?? '';
$validFrom = $body['valid_from'] ?? date('Y-m-d');

if (!$clientId || !$planType || !$content) {
    respondError('client_id, plan_type y content son requeridos', 422);
}
if (!in_array($planType, ['entrenamiento', 'nutricion', 'habitos'])) {
    respondError('plan_type inválido', 422);
}

// Deactivate previous plan of same type
$db->prepare("UPDATE assigned_plans SET active = 0 WHERE client_id = ? AND plan_type = ?")->execute([$clientId, $planType]);

// Get next version
$ver = $db->prepare("SELECT MAX(version) FROM assigned_plans WHERE client_id = ? AND plan_type = ?");
$ver->execute([$clientId, $planType]);
$version = (int)$ver->fetchColumn() + 1;

$stmt = $db->prepare("
    INSERT INTO assigned_plans (client_id, plan_type, content, version, assigned_by, valid_from)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$clientId, $planType, $content, $version, $admin['id'], $validFrom]);

respond(['message' => "Plan $planType v$version asignado al cliente $clientId"], 201);

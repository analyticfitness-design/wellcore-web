<?php
// GET /api/plans?type=entrenamiento|nutricion|habitos

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db     = getDB();

$type = $_GET['type'] ?? null;

// Plan-level access control
// Los clientes RISE tienen acceso a todos los tipos de plan
$planAccess = [
    'entrenamiento' => 'esencial',
    'nutricion'     => 'metodo',
    'habitos'       => 'elite',
];

if ($type && isset($planAccess[$type]) && $client['plan'] !== 'rise') {
    requirePlan($client, $planAccess[$type]);
}

// rise-dashboard pide 'training' pero el plan RISE se guarda como plan_type='rise'
// Mapear para que clientes RISE vean su plan al pedir 'training'
$dbType = $type;
if ($client['plan'] === 'rise' && $type === 'training') {
    $dbType = 'rise';
}

$sql    = "SELECT ap.id, ap.plan_type, ap.content, ap.version, ap.valid_from, ap.created_at,
                  ap.ai_generation_id, ag.parsed_json AS plan_json
           FROM assigned_plans ap
           LEFT JOIN ai_generations ag ON ag.id = ap.ai_generation_id
           WHERE ap.client_id = ? AND ap.active = 1";
$params = [$client['id']];

if ($dbType) {
    $sql    .= " AND ap.plan_type = ?";
    $params[] = $dbType;
}
$sql .= " ORDER BY ap.plan_type, ap.version DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If content is HTML (from render-plan), prefer plan_json for structured data
foreach ($rows as &$row) {
    if (!empty($row['plan_json'])) {
        $row['plan_data'] = json_decode($row['plan_json'], true);
    } elseif (!empty($row['content'])) {
        $decoded = json_decode($row['content'], true);
        if ($decoded) $row['plan_data'] = $decoded;
    }
    // Don't send large HTML content when plan_data exists — except RISE plans
    // which need the rendered HTML for the interactive dashboard view
    if (isset($row['plan_data']) && ($row['plan_type'] ?? '') !== 'rise') {
        unset($row['content']);
    }
    unset($row['plan_json'], $row['ai_generation_id']);
}
unset($row);

respond(['plans' => $rows]);

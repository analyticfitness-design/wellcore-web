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

$sql    = "SELECT id, plan_type, content, version, valid_from, created_at
           FROM assigned_plans
           WHERE client_id = ? AND active = 1";
$params = [$client['id']];

if ($type) {
    $sql    .= " AND plan_type = ?";
    $params[] = $type;
}
$sql .= " ORDER BY plan_type, version DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
respond(['plans' => $stmt->fetchAll()]);

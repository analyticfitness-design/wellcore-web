<?php
/**
 * WellCore Fitness — Get Client Intake Data (Admin)
 * GET /api/admin/client-intake.php?client_id=5
 *
 * Returns the intake questionnaire data for a specific client.
 * Auth: authenticateAdmin()
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
$db = getDB();

$clientId = (int) ($_GET['client_id'] ?? 0);
if ($clientId <= 0) {
    respondError('client_id requerido', 400);
}

// Get client basic info
$stmt = $db->prepare("
    SELECT c.id, c.name, c.email, c.plan, c.status, c.client_code, c.fecha_inicio,
           p.intake_data, p.whatsapp, p.edad, p.peso, p.altura, p.objetivo,
           p.nivel, p.lugar_entreno, p.dias_disponibles, p.restricciones
    FROM clients c
    LEFT JOIN client_profiles p ON p.client_id = c.id
    WHERE c.id = ?
");
$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    respondError('Cliente no encontrado', 404);
}

$intake = json_decode($client['intake_data'] ?? 'null', true);

// Check existing assigned plans
$stmtPlans = $db->prepare("
    SELECT plan_type, active, version, created_at
    FROM assigned_plans
    WHERE client_id = ?
    ORDER BY plan_type, version DESC
");
$stmtPlans->execute([$clientId]);
$plans = $stmtPlans->fetchAll(PDO::FETCH_ASSOC);

// Group plans by type
$planStatus = [];
foreach ($plans as $p) {
    if (!isset($planStatus[$p['plan_type']])) {
        $planStatus[$p['plan_type']] = [
            'active' => (bool) $p['active'],
            'version' => $p['version'],
            'last_generated' => $p['created_at'],
        ];
    }
}

respond([
    'client' => [
        'id' => $client['id'],
        'name' => $client['name'],
        'email' => $client['email'],
        'plan' => $client['plan'],
        'status' => $client['status'],
        'client_code' => $client['client_code'],
        'fecha_inicio' => $client['fecha_inicio'],
        'whatsapp' => $client['whatsapp'],
    ],
    'profile' => [
        'edad' => $client['edad'],
        'peso' => $client['peso'],
        'altura' => $client['altura'],
        'objetivo' => $client['objetivo'],
        'nivel' => $client['nivel'],
        'lugar_entreno' => $client['lugar_entreno'],
        'dias_disponibles' => json_decode($client['dias_disponibles'] ?? '[]', true),
        'restricciones' => $client['restricciones'],
    ],
    'intake' => $intake,
    'assigned_plans' => $planStatus,
]);

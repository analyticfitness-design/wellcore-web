<?php
// GET /api/nutrition/usage — returns daily usage and limits for nutrition AI
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();

$planLimits = ['elite' => 10, 'metodo' => 3, 'esencial' => 0, 'rise' => 0];
$clientPlan = strtolower($client['plan'] ?? 'esencial');
$dailyLimit = $planLimits[$clientPlan] ?? 0;

$db = getDB();
$stmt = $db->prepare("
    SELECT COUNT(*) FROM nutrition_logs
    WHERE client_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$client['id']]);
$usedToday = (int) $stmt->fetchColumn();

respond([
    'plan'      => $clientPlan,
    'limit'     => $dailyLimit,
    'used'      => $usedToday,
    'remaining' => max(0, $dailyLimit - $usedToday),
    'blocked'   => $dailyLimit === 0,
]);

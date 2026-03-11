<?php
// GET /api/clients/compliance  — Returns weekly compliance stats for the logged-in client
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db = getDB();
$clientId = (int)$client['id'];

// Last 8 weeks of check-ins
$stmt = $db->prepare("
    SELECT week_label, checkin_date, dias_entrenados, bienestar, rpe,
           ROUND((dias_entrenados / 5.0) * 100) AS compliance_pct
    FROM checkins
    WHERE client_id = ?
    ORDER BY checkin_date DESC
    LIMIT 8
");
$stmt->execute([$clientId]);
$weeks = $stmt->fetchAll();

// Current week compliance
$currentWeek = date('o') . '-W' . date('W');
$thisWeek = null;
foreach ($weeks as $w) {
    if ($w['week_label'] === $currentWeek) {
        $thisWeek = $w;
        break;
    }
}

// Average compliance last 4 weeks
$last4 = array_slice($weeks, 0, 4);
$avgCompliance = count($last4) > 0
    ? round(array_sum(array_column($last4, 'compliance_pct')) / count($last4))
    : 0;

respond([
    'current_week' => $thisWeek,
    'avg_compliance_4w' => $avgCompliance,
    'weeks' => $weeks,
]);

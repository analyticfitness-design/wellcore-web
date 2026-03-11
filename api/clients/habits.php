<?php
/**
 * WellCore — Habit Tracking API (M06)
 * GET  /api/clients/habits          → today's habits + streak info
 * POST /api/clients/habits          → toggle habit for today
 * GET  /api/clients/habits?history=1 → last 30 days
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db = getDB();
$clientId = (int)$client['id'];
$today = date('Y-m-d');

$habitTypes = ['agua', 'sueno', 'nutricion', 'estres'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['history'])) {
        // Last 30 days
        $stmt = $db->prepare("
            SELECT log_date, habit_type, value
            FROM habit_logs
            WHERE client_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY log_date DESC, habit_type
        ");
        $stmt->execute([$clientId]);
        respond(['logs' => $stmt->fetchAll()]);
    }

    // Today's status
    $stmt = $db->prepare("
        SELECT habit_type, value FROM habit_logs
        WHERE client_id = ? AND log_date = ?
    ");
    $stmt->execute([$clientId, $today]);
    $todayRows = $stmt->fetchAll();
    $todayMap = array_column($todayRows, 'value', 'habit_type');

    $today_habits = [];
    foreach ($habitTypes as $h) {
        $today_habits[$h] = isset($todayMap[$h]) ? (bool)$todayMap[$h] : false;
    }

    // Streak: consecutive days with all 4 habits completed
    $streak = 0;
    $checkDate = date('Y-m-d', strtotime('yesterday'));
    for ($i = 0; $i < 60; $i++) {
        $stmt2 = $db->prepare("
            SELECT COUNT(*) FROM habit_logs
            WHERE client_id = ? AND log_date = ? AND value = 1
        ");
        $stmt2->execute([$clientId, $checkDate]);
        $cnt = (int)$stmt2->fetchColumn();
        if ($cnt >= 4) {
            $streak++;
            $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
        } else {
            break;
        }
    }

    // Check if today all habits done (add to streak)
    $todayComplete = count(array_filter($today_habits)) >= 4;
    if ($todayComplete) $streak++;

    // Milestone check
    $milestone = null;
    if (in_array($streak, [7, 14, 21, 30])) $milestone = $streak;

    respond([
        'today' => $today_habits,
        'streak' => $streak,
        'milestone' => $milestone,
        'today_complete' => $todayComplete,
    ]);
}

// POST — toggle habit
$body = getJsonBody();
$habitType = $body['habit_type'] ?? '';
$value = isset($body['value']) ? (int)(bool)$body['value'] : 1;

if (!in_array($habitType, $habitTypes)) {
    respondError('habit_type debe ser: ' . implode(', ', $habitTypes), 422);
}

$stmt = $db->prepare("
    INSERT INTO habit_logs (client_id, log_date, habit_type, value)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE value = VALUES(value)
");
$stmt->execute([$clientId, $today, $habitType, $value]);

// Recompute streak after toggle
$stmt2 = $db->prepare("
    SELECT COUNT(*) FROM habit_logs WHERE client_id = ? AND log_date = ? AND value = 1
");
$stmt2->execute([$clientId, $today]);
$todayCount = (int)$stmt2->fetchColumn();
$todayComplete = $todayCount >= 4;

respond([
    'success' => true,
    'habit_type' => $habitType,
    'value' => (bool)$value,
    'today_complete' => $todayComplete,
]);

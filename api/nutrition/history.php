<?php
declare(strict_types=1);
/**
 * WellCore Fitness — F1: Historial Nutricional
 * ============================================================
 * GET /api/nutrition/history?days=7
 * GET /api/nutrition/history?date=2026-02-23
 *
 * Auth: Bearer token de cliente
 * ============================================================
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');
$client = authenticateClient();
$db     = getDB();

$days = (int) ($_GET['days'] ?? 7);
$date = $_GET['date'] ?? null;

if ($days < 1) $days = 7;
if ($days > 90) $days = 90;

// Obtener logs
if ($date) {
    $stmt = $db->prepare("
        SELECT id, image_path, calories, protein, carbs, fat,
               foods, meal_type, confidence, coach_comment, created_at
        FROM nutrition_logs
        WHERE client_id = ? AND DATE(created_at) = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$client['id'], $date]);
} else {
    $stmt = $db->prepare("
        SELECT id, image_path, calories, protein, carbs, fat,
               foods, meal_type, confidence, coach_comment, created_at
        FROM nutrition_logs
        WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$client['id'], $days]);
}

$logs = $stmt->fetchAll();

foreach ($logs as &$log) {
    $log['foods'] = json_decode($log['foods'] ?? '[]', true);
    if ($log['image_path']) {
        $log['image_url'] = UPLOAD_URL . $log['image_path'];
    }
}
unset($log);

// Obtener objetivos
$goalStmt = $db->prepare("SELECT calories, protein, carbs, fat FROM nutrition_goals WHERE client_id = ?");
$goalStmt->execute([$client['id']]);
$goals = $goalStmt->fetch() ?: ['calories' => 2200, 'protein' => 150, 'carbs' => 250, 'fat' => 65];

// Calcular totales del dia (hoy)
$todayStmt = $db->prepare("
    SELECT COALESCE(SUM(calories),0) as cal,
           COALESCE(SUM(protein),0) as prot,
           COALESCE(SUM(carbs),0) as carb,
           COALESCE(SUM(fat),0) as fat,
           COUNT(*) as meals
    FROM nutrition_logs
    WHERE client_id = ? AND DATE(created_at) = CURDATE()
");
$todayStmt->execute([$client['id']]);
$today = $todayStmt->fetch();

respond([
    'ok'    => true,
    'logs'  => $logs,
    'goals' => $goals,
    'today' => [
        'calories' => (int) $today['cal'],
        'protein'  => (float) $today['prot'],
        'carbs'    => (float) $today['carb'],
        'fat'      => (float) $today['fat'],
        'meals'    => (int) $today['meals'],
    ],
]);

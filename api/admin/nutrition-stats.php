<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Admin: Nutrition Stats
 * GET /api/admin/nutrition-stats?days=30
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');
$admin = authenticateAdmin();

$db   = getDB();
$days = min(180, max(1, (int) ($_GET['days'] ?? 30)));
$since = date('Y-m-d', strtotime("-{$days} days"));

// Stats
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(DATE(created_at) = CURDATE()) AS today,
        COUNT(DISTINCT client_id) AS unique_clients,
        ROUND(AVG(calories)) AS avg_calories
    FROM nutrition_logs
    WHERE created_at >= ?
");
$statsStmt->execute([$since]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Recent logs with client name
$logsStmt = $db->prepare("
    SELECT n.id, n.client_id, n.meal_type, n.calories, n.protein, n.carbs, n.fat,
           n.ai_raw, n.created_at,
           c.name AS client_name
    FROM nutrition_logs n
    LEFT JOIN clients c ON c.id = n.client_id
    WHERE n.created_at >= ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$logsStmt->execute([$since]);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Decode ai_raw JSON
foreach ($logs as &$log) {
    if (is_string($log['ai_raw'])) {
        $log['ai_raw'] = json_decode($log['ai_raw'], true) ?: [];
    }
}

respond([
    'ok'    => true,
    'stats' => $stats,
    'logs'  => $logs,
]);

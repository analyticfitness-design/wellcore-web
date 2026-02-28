<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Admin: Webhook Logs
 * GET /api/admin/webhook-logs?limit=50&type=onboarding
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');
$admin = authenticateAdmin();

$db = getDB();
$type  = trim($_GET['type'] ?? '');
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 50)));

$sql = "SELECT id, webhook_type, payload, status, created_at FROM webhook_logs";
$params = [];

if ($type) {
    $sql .= " WHERE webhook_type = ?";
    $params[] = $type;
}
$sql .= " ORDER BY created_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Decode payload JSON
foreach ($logs as &$log) {
    $log['payload'] = json_decode($log['payload'] ?? '{}', true);
}

// Stats
$statsStmt = $db->prepare("
    SELECT webhook_type,
           COUNT(*) as total,
           MAX(created_at) as last_call,
           SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
    FROM webhook_logs
    GROUP BY webhook_type
");
$statsStmt->execute();
$stats = $statsStmt->fetchAll();

respond([
    'ok'    => true,
    'logs'  => $logs,
    'stats' => $stats,
    'total' => count($logs),
]);

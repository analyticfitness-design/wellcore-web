<?php
/**
 * WellCore — Admin: API Logs
 * GET /api/admin/api-logs?days=7&endpoint=&limit=50&offset=0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
if (!in_array($admin['role'], ['admin', 'jefe', 'superadmin'], true)) {
    respondError('No autorizado', 403);
}

$db     = getDB();
$days   = min(90, max(1, (int) ($_GET['days'] ?? 7)));
$limit  = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$epFilter = trim($_GET['endpoint'] ?? '');
$since  = date('Y-m-d', strtotime("-{$days} days"));

$where  = " WHERE created_at >= ?";
$params = [$since];

if ($epFilter) {
    $where .= " AND endpoint LIKE ?";
    $params[] = "%$epFilter%";
}

// Stats
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        ROUND(AVG(duration_ms)) AS avg_ms,
        SUM(status_code >= 400) AS errors,
        COUNT(DISTINCT ip) AS unique_ips
    FROM api_logs $where
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Top endpoints
$topStmt = $db->prepare("
    SELECT endpoint, COUNT(*) AS hits, ROUND(AVG(duration_ms)) AS avg_ms
    FROM api_logs $where
    GROUP BY endpoint ORDER BY hits DESC LIMIT 10
");
$topStmt->execute($params);
$topEndpoints = $topStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent logs
$logsStmt = $db->prepare("
    SELECT id, endpoint, method, ip, user_id, user_type, status_code, duration_ms, created_at
    FROM api_logs $where
    ORDER BY created_at DESC LIMIT ? OFFSET ?
");
$logParams = array_merge($params, [$limit, $offset]);
$logsStmt->execute($logParams);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

respond([
    'ok'            => true,
    'stats'         => $stats,
    'top_endpoints' => $topEndpoints,
    'logs'          => $logs,
    'limit'         => $limit,
    'offset'        => $offset,
]);

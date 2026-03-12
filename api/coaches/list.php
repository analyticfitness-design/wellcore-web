<?php
/**
 * WellCore Fitness — Coach Applications List (Admin)
 * GET /api/coaches/list.php[?status=pending|approved|rejected]
 * Requires: Bearer admin token
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
authenticateAdmin();

$VALID_STATUSES = ['pending', 'approved', 'rejected'];
$statusFilter   = $_GET['status'] ?? '';
$search         = $_GET['search'] ?? '';
$limit          = min(100, max(1, (int) ($_GET['limit']  ?? 25)));
$offset         = max(0, (int) ($_GET['offset'] ?? 0));

if ($statusFilter !== '' && !in_array($statusFilter, $VALID_STATUSES, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'status debe ser uno de: pending, approved, rejected']);
    exit;
}

// ─── MySQL (primario) ─────────────────────────────────────────────────────────
try {
    $db = getDB();

    $where  = " WHERE 1=1";
    $params = [];

    if ($statusFilter !== '') {
        $where   .= " AND status = ?";
        $params[] = $statusFilter;
    }

    if ($search !== '') {
        $where   .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Total count (for pagination UI)
    $countStmt = $db->prepare("SELECT COUNT(*) FROM coach_applications" . $where);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();

    // Paginated results
    $sql = "SELECT * FROM coach_applications" . $where
         . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $applications = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        'ok'     => true,
        'count'  => count($applications),
        'total'  => $totalCount,
        'limit'  => $limit,
        'offset' => $offset,
        'data'   => $applications,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\Exception $e) {
    error_log('[WellCore] coaches/list DB error: ' . $e->getMessage());
}

// ─── Fallback JSON ────────────────────────────────────────────────────────────
$file = dirname(__DIR__) . '/data/coach-applications.json';
$applications = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

if ($statusFilter !== '') {
    $applications = array_values(array_filter($applications, fn($a) => ($a['status'] ?? '') === $statusFilter));
}
if ($search !== '') {
    $applications = array_values(array_filter($applications, fn($a) =>
        stripos($a['name'] ?? $a['full_name'] ?? '', $search) !== false ||
        stripos($a['email'] ?? '', $search) !== false
    ));
}
usort($applications, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$totalCount = count($applications);
$applications = array_slice($applications, $offset, $limit);

http_response_code(200);
echo json_encode([
    'ok'     => true,
    'count'  => count($applications),
    'total'  => $totalCount,
    'limit'  => $limit,
    'offset' => $offset,
    'data'   => $applications
], JSON_UNESCAPED_UNICODE);

<?php
// GET /api/admin/payments — list payments + KPIs
// Query params: ?limit=50&offset=0&status=approved&plan=rise

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
$db = getDB();

$limit  = min(500, max(1, (int) ($_GET['limit']  ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$statusFilter = $_GET['status'] ?? '';
$planFilter   = $_GET['plan']   ?? '';

// Build WHERE clause
$where  = " WHERE 1=1";
$params = [];

if ($statusFilter) {
    $where .= " AND p.status = ?";
    $params[] = $statusFilter;
}
if ($planFilter) {
    $where .= " AND p.plan = ?";
    $params[] = $planFilter;
}

// Payments list
$sql = "
    SELECT p.id, p.client_id, p.email, p.plan, p.amount, p.currency,
           p.status, p.payment_method, p.buyer_name, p.buyer_phone,
           p.wompi_reference, p.wompi_transaction_id,
           p.created_at, p.updated_at,
           c.name AS client_name, c.client_code
    FROM payments p
    LEFT JOIN clients c ON c.id = p.client_id
    $where
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total count
$countSql = "SELECT COUNT(*) FROM payments p $where";
$countParams = array_slice($params, 0, -2); // remove limit/offset
$countStmt = $db->prepare($countSql);
$countStmt->execute($countParams);
$totalCount = (int) $countStmt->fetchColumn();

// KPIs
// MRR: sum of approved payments this month
$mrrStmt = $db->query("
    SELECT COALESCE(SUM(amount), 0) AS mrr
    FROM payments
    WHERE status = 'approved'
      AND MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at) = YEAR(CURDATE())
");
$mrr = (float) $mrrStmt->fetchColumn();

// Total approved (all time)
$totalStmt = $db->query("
    SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
    FROM payments WHERE status = 'approved'
");
$totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
$totalApproved = (float) $totalRow['total'];
$totalApprovedCount = (int) $totalRow['count'];

// Active clients count (clients with at least 1 approved payment this month)
$activeStmt = $db->query("
    SELECT COUNT(DISTINCT client_id) AS active
    FROM payments
    WHERE status = 'approved'
      AND MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at) = YEAR(CURDATE())
      AND client_id IS NOT NULL
");
$activeClients = (int) $activeStmt->fetchColumn();

respond([
    'payments' => $payments,
    'total'    => $totalCount,
    'kpis'     => [
        'mrr'                  => $mrr,
        'arr'                  => $mrr * 12,
        'total_approved'       => $totalApproved,
        'total_approved_count' => $totalApprovedCount,
        'active_clients'       => $activeClients,
    ],
]);

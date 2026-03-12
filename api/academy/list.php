<?php
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('GET');

// Auto-detect role: peek user_type in auth_tokens BEFORE calling authenticate*
// (authenticateAdmin/Client call exit() on failure — try/catch won't work)
$rawToken = getTokenFromHeader();
if (!$rawToken) {
    respondError('Token requerido', 401);
}

$db = getDB();
$peekStmt = $db->prepare("SELECT user_type FROM auth_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
$peekStmt->execute([$rawToken]);
$tokenMeta = $peekStmt->fetch(PDO::FETCH_ASSOC);
if (!$tokenMeta) {
    respondError('Token inválido o expirado', 401);
}

$isAdmin = ($tokenMeta['user_type'] === 'admin');
$user = $isAdmin ? authenticateAdmin() : authenticateClient();

// Pagination
$page    = max(1, (int)($_GET['page']    ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 12)));
$offset  = ($page - 1) * $perPage;

// Optional filters
$typeFilter = trim($_GET['content_type'] ?? '');
$catFilter  = trim($_GET['category'] ?? '');

$validTypes = ['video', 'pdf', 'article', 'guide'];

// Build WHERE clauses
$where  = [];
$params = [];

if ($isAdmin) {
    // Admins see everything
} else {
    // Clients: only active content accessible for their plan
    $where[]  = 'ac.active = 1';
    $clientPlan = $user['plan'] ?? '';
    if (!$clientPlan) {
        respondError('Plan no asignado. Contacta a tu coach.', 403);
    }
    // plan_access is a MySQL SET column — use FIND_IN_SET
    $where[]  = 'FIND_IN_SET(?, ac.plan_access) > 0';
    $params[] = $clientPlan;
}

if ($typeFilter && in_array($typeFilter, $validTypes, true)) {
    $where[]  = 'ac.content_type = ?';
    $params[] = $typeFilter;
}

if ($catFilter !== '') {
    $where[]  = 'ac.category = ?';
    $params[] = $catFilter;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM academy_content ac $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch page
$listParams   = array_merge($params, [$perPage, $offset]);
$stmt = $db->prepare("
    SELECT ac.id, ac.title, ac.description, ac.content_type,
           ac.content_url, ac.thumbnail_url, ac.category,
           ac.plan_access, ac.active, ac.sort_order, ac.created_at
    FROM academy_content ac
    $whereSQL
    ORDER BY ac.sort_order ASC, ac.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($listParams);
$rows = $stmt->fetchAll();

// plan_access is a MySQL SET column — return as array
foreach ($rows as &$row) {
    $pa = $row['plan_access'] ?? '';
    $row['plan_access'] = $pa !== '' ? explode(',', $pa) : [];
    $row['active'] = (bool)$row['active'];
}
unset($row);

respond([
    'items'    => $rows,
    'total'    => $total,
    'page'     => $page,
    'per_page' => $perPage,
    'pages'    => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
]);

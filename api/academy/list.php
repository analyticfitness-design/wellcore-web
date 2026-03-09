<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

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
$tagFilter  = trim($_GET['tag'] ?? '');

$validTypes = ['video', 'pdf', 'article', 'exercise'];

// Build WHERE clauses
$where  = [];
$params = [];

if ($isAdmin) {
    // Admins see everything
} else {
    // Clients: only published content accessible for their plan
    $where[]  = 'ac.is_published = 1';
    $clientPlan = $user['plan'] ?? '';
    if (!$clientPlan) {
        respondError('Plan no asignado. Contacta a tu coach.', 403);
    }
    $where[]  = 'JSON_CONTAINS(ac.plan_access, JSON_QUOTE(?)) = 1';
    $params[] = $clientPlan;
}

if ($typeFilter && in_array($typeFilter, $validTypes, true)) {
    $where[]  = 'ac.content_type = ?';
    $params[] = $typeFilter;
}

if ($tagFilter !== '') {
    $where[]  = 'FIND_IN_SET(?, ac.tags) > 0';
    $params[] = $tagFilter;
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
           ac.content_url, ac.tags, ac.is_published,
           ac.plan_access, ac.created_at
    FROM academy_content ac
    $whereSQL
    ORDER BY ac.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($listParams);
$rows = $stmt->fetchAll();

// Decode plan_access JSON for each row
foreach ($rows as &$row) {
    $row['plan_access']  = json_decode($row['plan_access'] ?? '[]', true) ?? [];
    $row['is_published'] = (bool)$row['is_published'];
}
unset($row);

respond([
    'ok'       => true,
    'items'    => $rows,
    'total'    => $total,
    'page'     => $page,
    'per_page' => $perPage,
    'pages'    => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
    'is_admin' => $isAdmin,
]);

<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

// Auto-detect role: try admin first, then client
$user = null;
$isAdmin = false;
try {
    $user = authenticateAdmin();
    $isAdmin = true;
} catch (Exception $e) {
    try {
        $user = authenticateClient();
    } catch (Exception $e2) {
        respondError('Acceso denegado', 401);
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    respondError('Parámetro id requerido', 400);
}

$db = getDB();

$stmt = $db->prepare("
    SELECT id, title, description, content_type, content_url, content_body,
           plan_access, tags, is_published, created_by, created_at, updated_at
    FROM academy_content
    WHERE id = ?
");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    respondError('Contenido no encontrado', 404);
}

if (!$isAdmin) {
    // Clients: must be published
    if (!$row['is_published']) {
        respondError('Contenido no disponible', 404);
    }
    // Clients: must have plan access
    $planAccess = json_decode($row['plan_access'] ?? '[]', true) ?? [];
    $clientPlan = $user['plan'] ?? '';
    if ($clientPlan && !in_array($clientPlan, $planAccess, true)) {
        respondError('Tu plan no tiene acceso a este contenido', 403);
    }
}

$row['plan_access']  = json_decode($row['plan_access'] ?? '[]', true) ?? [];
$row['is_published'] = (bool)$row['is_published'];

respond([
    'ok'   => true,
    'item' => $row,
]);

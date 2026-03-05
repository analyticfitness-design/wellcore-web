<?php
// DELETE /api/admin/cleanup-test-clients.php
// Script temporal de limpieza — eliminar despues de usar
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$admin = requireAdminRole('superadmin');

$db = getDB();

$input = getJsonBody();
$ids = $input['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    respondError('ids requerido (array)', 400);
}

$deleted = [];
foreach ($ids as $id) {
    $id = (int)$id;
    // Borrar en orden correcto por FK
    $db->prepare("DELETE FROM rise_programs WHERE client_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM auth_tokens WHERE user_type='client' AND user_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
    $deleted[] = $id;
}

respond(['deleted' => $deleted, 'count' => count($deleted)]);
?>

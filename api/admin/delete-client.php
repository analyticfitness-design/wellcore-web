<?php
// POST /api/admin/delete-client.php
// Body: {client_id}
// Elimina un cliente y todos sus datos asociados
// Requiere rol admin, jefe o superadmin

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$admin = requireAdminRole('admin', 'jefe', 'superadmin');

$input = getJsonBody();
$client_id = (int)($input['client_id'] ?? 0);

if (!$client_id) {
    respondError('client_id requerido', 400);
}

$db = getDB();

// Verificar que el cliente existe
$stmt = $db->prepare("SELECT id, name, email, plan FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    respondError('Cliente no encontrado', 404);
}

$db->beginTransaction();
try {
    // Borrar en orden por FK
    $db->prepare("DELETE FROM rise_programs WHERE client_id = ?")->execute([$client_id]);
    $db->prepare("DELETE FROM auth_tokens WHERE user_type = 'client' AND user_id = ?")->execute([$client_id]);
    $db->prepare("DELETE FROM client_profiles WHERE client_id = ?")->execute([$client_id]);
    $db->prepare("DELETE FROM checkins WHERE client_id = ?")->execute([$client_id]);
    $db->prepare("DELETE FROM payments WHERE client_id = ?")->execute([$client_id]);
    $db->prepare("DELETE FROM clients WHERE id = ?")->execute([$client_id]);

    $db->commit();

    respond([
        'success'    => true,
        'message'    => 'Cliente eliminado correctamente',
        'deleted'    => [
            'id'    => $client_id,
            'name'  => $client['name'],
            'email' => $client['email'],
            'plan'  => $client['plan'],
        ],
        'deleted_by' => $admin['username'] ?? 'admin',
    ]);
} catch (PDOException $e) {
    $db->rollBack();
    respondError('Error al eliminar cliente: ' . $e->getMessage(), 500);
}
?>

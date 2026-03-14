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
    // Borrar todas las tablas relacionadas en orden por FK
    $tables = [
        'xp_events'              => 'client_id',
        'client_xp'              => 'client_id',
        'challenge_participants'  => 'client_id',
        'biometric_logs'         => 'client_id',
        'habit_logs'             => 'client_id',
        'training_logs'          => 'client_id',
        'weight_logs'            => 'client_id',
        'progress_photos'        => 'client_id',
        'coach_notes'            => 'client_id',
        'push_subscriptions'     => 'client_id',
        'notification_log'       => 'client_id',
        'chat_messages'          => 'client_id',
        'assigned_plans'         => 'client_id',
        'referrals'              => 'referrer_id',
        'video_checkins'         => 'client_id',
        'academy_progress'       => 'client_id',
        'rise_programs'          => 'client_id',
        'daily_missions'         => 'client_id',
        'onboarding_steps'       => 'client_id',
        'weekly_summaries'       => 'client_id',
        'celebrations'           => 'client_id',
        'chat_weekly_limits'     => 'client_id',
        'auth_tokens'            => null, // special: user_type filter
        'client_profiles'        => 'client_id',
        'checkins'               => 'client_id',
        'payments'               => 'client_id',
    ];

    foreach ($tables as $table => $column) {
        try {
            if ($table === 'auth_tokens') {
                $db->prepare("DELETE FROM auth_tokens WHERE user_type = 'client' AND user_id = ?")->execute([$client_id]);
            } else {
                $db->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?")->execute([$client_id]);
            }
        } catch (PDOException $tableErr) {
            // Table may not exist yet (not all migrations run) — skip safely
            if (strpos($tableErr->getMessage(), "doesn't exist") !== false
                || strpos($tableErr->getMessage(), '42S02') !== false) {
                continue;
            }
            throw $tableErr; // Re-throw real errors
        }
    }

    // Finalmente borrar el cliente
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
    error_log('delete-client.php error: ' . $e->getMessage());
    respondError('Error interno al eliminar cliente', 500);
}
?>

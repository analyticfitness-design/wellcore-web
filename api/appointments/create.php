<?php
/**
 * POST /api/appointments/create
 * Cliente Elite agenda una cita con su coach.
 *
 * Body: { date: 'YYYY-MM-DD', time: 'HH:MM', title?, notes? }
 * Auth: cliente plan elite
 * Responde: { id, scheduled_at, status: 'pending' }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('POST');

$client = authenticateClient();
if (strtolower($client['plan'] ?? '') !== 'elite') {
    respondError('Booking disponible solo para plan Elite', 403);
}

$db        = getDB();
$client_id = $client['id'];
$body      = json_decode(file_get_contents('php://input'), true) ?? [];

$date  = trim($body['date'] ?? '');
$time  = trim($body['time'] ?? '');
$title = trim($body['title'] ?? 'Sesión 1:1');
$notes = trim($body['notes'] ?? '');

if (!$date || !$time) respondError('date y time son requeridos', 400);

$scheduled_at = $date . ' ' . $time . ':00';
if (!strtotime($scheduled_at)) respondError('Fecha/hora inválida', 400);

// Verificar que el slot está en el futuro
if (strtotime($scheduled_at) <= time()) {
    respondError('La cita debe ser en el futuro', 400);
}

// Obtener coach del cliente
$cr = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
$cr->execute([$client_id]);
$coach_id = $cr->fetchColumn();
if (!$coach_id) respondError('No tienes coach asignado', 400);

// Verificar que el slot está disponible
$conflict = $db->prepare("
    SELECT COUNT(*) FROM appointments
    WHERE coach_id = ?
      AND ABS(TIMESTAMPDIFF(MINUTE, scheduled_at, ?)) < 30
      AND status NOT IN ('cancelled')
");
$conflict->execute([$coach_id, $scheduled_at]);
if ((int)$conflict->fetchColumn() > 0) {
    respondError('Este horario ya está ocupado', 409);
}

// Verificar que está dentro del horario disponible del coach
$day_of_w = (int)date('w', strtotime($scheduled_at));
$t        = date('H:i', strtotime($scheduled_at));
$avail = $db->prepare("
    SELECT COUNT(*) FROM coach_availability
    WHERE coach_id = ? AND day_of_week = ? AND time_start <= ? AND time_end > ? AND is_active = 1
");
$avail->execute([$coach_id, $day_of_w, $t, $t]);
if ((int)$avail->fetchColumn() === 0) {
    respondError('El coach no tiene disponibilidad en ese horario', 409);
}

$db->prepare("
    INSERT INTO appointments (coach_id, client_id, scheduled_at, title, notes, status)
    VALUES (?, ?, ?, ?, ?, 'pending')
")->execute([$coach_id, $client_id, $scheduled_at, $title, $notes ?: null]);

$new_id = (int)$db->lastInsertId();

respond([
    'id'           => $new_id,
    'scheduled_at' => $scheduled_at,
    'status'       => 'pending',
    'message'      => 'Cita solicitada. Tu coach la confirmará pronto.',
]);

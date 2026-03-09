<?php
/**
 * GET  /api/appointments/availability?date=YYYY-MM-DD  — Slots disponibles del coach
 * POST /api/appointments/availability                   — Coach configura horarios
 *
 * GET Auth: cliente (solo plan elite)
 * POST Auth: coach
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Coach puede ver su propio horario con ?manage=1
    if (!empty($_GET['manage'])) {
        $coach    = authenticateCoach();
        $coach_id = $coach['id'];
        $stmt     = $db->prepare("SELECT day_of_week, time_start, time_end, is_active FROM coach_availability WHERE coach_id = ? ORDER BY day_of_week, time_start");
        $stmt->execute([$coach_id]);
        respond(['schedule' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $client = authenticateClient();
    if (strtolower($client['plan'] ?? '') !== 'elite') {
        respondError('Booking disponible solo para plan Elite', 403);
    }

    $date     = $_GET['date'] ?? date('Y-m-d');
    $day_of_w = (int)date('w', strtotime($date)); // 0=Dom

    // Obtener coach del cliente
    $cr = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
    $cr->execute([$client['id']]);
    $coach_id = $cr->fetchColumn();
    if (!$coach_id) respond(['slots' => []]);

    // Horarios del coach para ese día
    $slots_q = $db->prepare("
        SELECT time_start, time_end
        FROM coach_availability
        WHERE coach_id = ? AND day_of_week = ? AND is_active = 1
        ORDER BY time_start
    ");
    $slots_q->execute([$coach_id, $day_of_w]);
    $ranges = $slots_q->fetchAll(PDO::FETCH_ASSOC);

    // Citas ya tomadas ese día
    $booked_q = $db->prepare("
        SELECT TIME(scheduled_at) AS t, duration_min
        FROM appointments
        WHERE coach_id = ? AND DATE(scheduled_at) = ? AND status NOT IN ('cancelled')
    ");
    $booked_q->execute([$coach_id, $date]);
    $booked = $booked_q->fetchAll(PDO::FETCH_ASSOC);

    $booked_times = [];
    foreach ($booked as $b) {
        $start = strtotime($b['t']);
        for ($t = $start; $t < $start + ($b['duration_min'] * 60); $t += 1800) {
            $booked_times[] = date('H:i', $t);
        }
    }

    // Generar slots de 30 min
    $slots = [];
    foreach ($ranges as $r) {
        $s = strtotime($r['time_start']);
        $e = strtotime($r['time_end']);
        while ($s + 1800 <= $e) {
            $slot = date('H:i', $s);
            $slots[] = ['time' => $slot, 'available' => !in_array($slot, $booked_times, true)];
            $s += 1800;
        }
    }

    respond(['date' => $date, 'slots' => $slots]);

} elseif ($method === 'POST') {
    $coach    = authenticateCoach();
    $coach_id = $coach['id'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

    // Reemplazar disponibilidad completa del coach
    $schedule = $body['schedule'] ?? [];
    if (!is_array($schedule)) respondError('schedule debe ser array', 400);

    $db->prepare("DELETE FROM coach_availability WHERE coach_id = ?")->execute([$coach_id]);

    $ins = $db->prepare("INSERT INTO coach_availability (coach_id, day_of_week, time_start, time_end) VALUES (?, ?, ?, ?)");
    foreach ($schedule as $s) {
        $day   = (int)($s['day_of_week'] ?? -1);
        $start = $s['time_start'] ?? '';
        $end   = $s['time_end'] ?? '';
        if ($day < 0 || $day > 6 || !$start || !$end) continue;
        $ins->execute([$coach_id, $day, $start, $end]);
    }

    respond(['success' => true, 'saved' => count($schedule)]);

} else {
    respondError('Método no permitido', 405);
}

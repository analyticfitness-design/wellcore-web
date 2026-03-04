<?php
// GET /api/rise/get-details.php
// Obtener detalles de un programa RISE individual o metricas agregadas
// Params: ?program_id=N (detalle individual)
//         ?stats=1 (metricas agregadas para admin dashboard)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

$db = getDB();

// Si se solicitan estadisticas agregadas (para el admin dashboard)
if (isset($_GET['stats'])) {
    // Requiere token de admin
    $auth = authenticateAdmin();

    $total_clients   = $db->query("SELECT COUNT(*) FROM clients WHERE plan = 'rise'")->fetchColumn();
    $active_programs = $db->query("SELECT COUNT(*) FROM rise_programs WHERE status = 'active'")->fetchColumn();
    $completed       = $db->query("SELECT COUNT(*) FROM rise_programs WHERE status = 'completed'")->fetchColumn();

    $recent = $db->query("
        SELECT rp.id, c.name, c.email, rp.experience_level,
               rp.training_location, rp.status, rp.enrollment_date
        FROM rise_programs rp
        JOIN clients c ON rp.client_id = c.id
        ORDER BY rp.enrollment_date DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    respond([
        'success'           => true,
        'total_clients'     => (int) $total_clients,
        'active_programs'   => (int) $active_programs,
        'completed_programs'=> (int) $completed,
        'recent_enrollments'=> $recent
    ]);
}

// Detalle individual por program_id
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : null;
if (!$program_id) {
    respondError('Parametro requerido: program_id o stats', 400);
}

$stmt = $db->prepare("
    SELECT
        rp.id, rp.client_id, rp.start_date, rp.end_date,
        rp.experience_level, rp.training_location, rp.gender, rp.status,
        rp.enrollment_date, rp.personalized_program,
        c.name, c.email,
        (SELECT COUNT(*) FROM rise_daily_logs WHERE rise_program_id = rp.id AND workout_completed = TRUE) as workouts_completed,
        (SELECT COUNT(DISTINCT log_date) FROM rise_daily_logs WHERE rise_program_id = rp.id) as days_logged
    FROM rise_programs rp
    JOIN clients c ON rp.client_id = c.id
    WHERE rp.id = ?
");

$stmt->execute([$program_id]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    respondError('Programa no encontrado', 404);
}

respond(['success' => true, 'program' => $program]);
?>

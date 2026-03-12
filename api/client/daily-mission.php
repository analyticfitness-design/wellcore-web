<?php
/**
 * WellCore — Daily Mission
 * GET  /api/client/daily-mission.php  — Get today's missions (auto-generates if none)
 * POST /api/client/daily-mission.php  — Mark a mission as completed { index: 0|1|2 }
 *
 * +10 XP when all 3 missions completed.
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$client = authenticateClient();
$clientId = (int)$client['id'];
$plan = $client['plan'] ?? 'esencial';
$db = getDB();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Check if missions exist for today
    $stmt = $db->prepare("SELECT * FROM daily_missions WHERE client_id = ? AND mission_date = ?");
    $stmt->execute([$clientId, $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Generate missions
        $missions = generateMissions($db, $clientId, $plan);
        $db->prepare("
            INSERT INTO daily_missions (client_id, mission_date, missions, completed, total)
            VALUES (?, ?, ?, 0, ?)
        ")->execute([$clientId, $today, json_encode($missions, JSON_UNESCAPED_UNICODE), count($missions)]);

        $row = [
            'mission_date' => $today,
            'missions'     => json_encode($missions, JSON_UNESCAPED_UNICODE),
            'completed'    => 0,
            'total'        => count($missions),
            'xp_awarded'   => 0,
        ];
    }

    $missions = json_decode($row['missions'], true) ?: [];

    respond([
        'ok'        => true,
        'date'      => $row['mission_date'],
        'missions'  => $missions,
        'completed' => (int)$row['completed'],
        'total'     => (int)$row['total'],
        'xp_awarded'=> (bool)$row['xp_awarded'],
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = getJsonBody();
    $index = (int)($body['index'] ?? -1);
    if ($index < 0 || $index > 2) respondError('index invalido (0-2)', 422);

    // Get today's missions
    $stmt = $db->prepare("SELECT * FROM daily_missions WHERE client_id = ? AND mission_date = ?");
    $stmt->execute([$clientId, $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) respondError('No hay misiones para hoy', 404);

    $missions = json_decode($row['missions'], true) ?: [];
    if (!isset($missions[$index])) respondError('Mision no encontrada', 404);

    if (!empty($missions[$index]['done'])) {
        respond(['ok' => true, 'message' => 'Ya completada', 'completed' => (int)$row['completed'], 'total' => (int)$row['total']]);
    }

    // Mark as done
    $missions[$index]['done'] = true;
    $missions[$index]['done_at'] = date('Y-m-d H:i:s');
    $completed = count(array_filter($missions, function($m) { return !empty($m['done']); }));

    $xpAwarded = (int)$row['xp_awarded'];
    $xpGained = 0;

    // Award XP if all missions done
    if ($completed >= (int)$row['total'] && !$xpAwarded) {
        $xpAwarded = 1;
        $xpGained = 10;
        // Award XP via xp_events table if it exists
        try {
            $db->prepare("
                INSERT INTO xp_events (client_id, event_type, xp_amount, description, created_at)
                VALUES (?, 'daily_mission_complete', 10, 'Todas las misiones del dia completadas', NOW())
            ")->execute([$clientId]);
            // Update client_xp total
            $db->prepare("
                INSERT INTO client_xp (client_id, total_xp, weekly_xp, current_streak)
                VALUES (?, 10, 10, 0)
                ON DUPLICATE KEY UPDATE total_xp = total_xp + 10, weekly_xp = weekly_xp + 10
            ")->execute([$clientId]);
        } catch (\Throwable $e) {
            // XP tables might not exist yet — don't break
        }
    }

    $db->prepare("
        UPDATE daily_missions SET missions = ?, completed = ?, xp_awarded = ?
        WHERE client_id = ? AND mission_date = ?
    ")->execute([json_encode($missions, JSON_UNESCAPED_UNICODE), $completed, $xpAwarded, $clientId, $today]);

    respond([
        'ok'        => true,
        'completed' => $completed,
        'total'     => (int)$row['total'],
        'xp_gained' => $xpGained,
        'missions'  => $missions,
    ]);

} else {
    respondError('Method not allowed', 405);
}

// ── Mission generator ───────────────────────────────────────────

function generateMissions(PDO $db, int $clientId, string $plan): array {
    $missions = [];
    $dayOfWeek = (int)date('N'); // 1=Mon, 7=Sun

    // Mission 1: Training related (if not rest day)
    $trainingMission = getTrainingMission($db, $clientId, $dayOfWeek);
    $missions[] = $trainingMission;

    // Mission 2: Habits related
    $missions[] = [
        'title'       => 'Registra tus habitos diarios',
        'description' => 'Completa el registro de habitos del dia',
        'icon'        => 'fa-check-circle',
        'type'        => 'habits',
        'done'        => false,
    ];

    // Mission 3: Plan-specific
    $planMissions = [
        'esencial' => [
            ['title' => 'Toma 2.5L de agua hoy', 'description' => 'Hidratacion es clave para rendimiento', 'icon' => 'fa-tint', 'type' => 'hydration'],
            ['title' => 'Lee un articulo de la academia', 'description' => 'Aprende algo nuevo hoy', 'icon' => 'fa-graduation-cap', 'type' => 'education'],
            ['title' => 'Registra tu peso en metricas', 'description' => 'Seguimiento semanal de composicion', 'icon' => 'fa-weight-scale', 'type' => 'metrics'],
        ],
        'metodo' => [
            ['title' => 'Toma 3L de agua hoy', 'description' => 'Objetivo de hidratacion nivel Pro', 'icon' => 'fa-tint', 'type' => 'hydration'],
            ['title' => 'Registra tu nutricion del dia', 'description' => 'Sube una foto de tu comida', 'icon' => 'fa-utensils', 'type' => 'nutrition'],
            ['title' => 'Revisa tu plan de entrenamiento', 'description' => 'Asegurate de saber que toca manana', 'icon' => 'fa-clipboard-list', 'type' => 'plan_review'],
        ],
        'elite' => [
            ['title' => 'Toma 3.5L de agua hoy', 'description' => 'Elite hydration', 'icon' => 'fa-tint', 'type' => 'hydration'],
            ['title' => 'Registra nutricion completa', 'description' => 'Todas las comidas del dia', 'icon' => 'fa-utensils', 'type' => 'nutrition'],
            ['title' => 'Revisa el feedback de tu coach', 'description' => 'Lee los comentarios de tu check-in', 'icon' => 'fa-comments', 'type' => 'coach_feedback'],
        ],
    ];

    $pool = $planMissions[$plan] ?? $planMissions['esencial'];
    // Rotate based on day of week
    $idx = ($dayOfWeek - 1) % count($pool);
    $missions[] = array_merge($pool[$idx], ['done' => false]);

    return $missions;
}

function getTrainingMission(PDO $db, int $clientId, int $dayOfWeek): array {
    // Try to get today's workout from plan
    $dayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo'];
    $dayName = $dayNames[$dayOfWeek] ?? 'Hoy';

    // Check if there's an active plan with schedule
    try {
        $stmt = $db->prepare("
            SELECT content FROM plans
            WHERE client_id = ? AND type = 'entrenamiento' AND status = 'active'
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$clientId]);
        $planContent = $stmt->fetchColumn();

        if ($planContent) {
            // Try to extract day info from plan JSON
            $planData = json_decode($planContent, true);
            if (is_array($planData)) {
                foreach ($planData as $item) {
                    $label = $item['day'] ?? $item['title'] ?? '';
                    if (stripos($label, $dayName) !== false || stripos($label, "Dia " . $dayOfWeek) !== false) {
                        $muscleGroup = $item['focus'] ?? $item['muscles'] ?? $label;
                        return [
                            'title'       => "Completar entrenamiento: $muscleGroup",
                            'description' => "Dia $dayOfWeek de tu programa semanal",
                            'icon'        => 'fa-dumbbell',
                            'type'        => 'training',
                            'done'        => false,
                        ];
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Plan table structure might differ — fallback gracefully
    }

    // Fallback: generic training mission
    if ($dayOfWeek <= 5) {
        return [
            'title'       => 'Completa tu entrenamiento de hoy',
            'description' => 'Sigue tu programa y registra tu sesion',
            'icon'        => 'fa-dumbbell',
            'type'        => 'training',
            'done'        => false,
        ];
    }

    // Weekend: optional
    return [
        'title'       => 'Dia de recuperacion activa',
        'description' => 'Camina 30 min o haz movilidad/stretching',
        'icon'        => 'fa-walking',
        'type'        => 'recovery',
        'done'        => false,
    ];
}

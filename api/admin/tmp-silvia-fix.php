<?php
/**
 * TEMPORAL — Actualiza intake de Silvia y regenera plan RISE
 * Eliminar despues de usar.
 * GET /api/admin/tmp-silvia-fix.php?action=update_intake
 * GET /api/admin/tmp-silvia-fix.php?action=regenerate
 * GET /api/admin/tmp-silvia-fix.php?action=check
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? 'check';
$db = getDB();

if ($action === 'update_intake') {
    // Actualizar personalized_program de Silvia (client_id=15)
    $intake = [
        'measurements' => ['waist' => '', 'hips' => '', 'chest' => '', 'arms' => '', 'thighs' => '', 'bodyFat' => ''],
        'training' => [
            'years' => '1',
            'trainingType' => ['pesas', 'funcional'],
            'exercisesToAvoid' => ['Peso muerto (deadlift) — LESION, no incluir bajo ninguna circunstancia', 'Cualquier variante de peso muerto (rumano, sumo, convencional)']
        ],
        'availability' => [
            'place' => 'gym',
            'days' => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'],
            'time' => '60',
            'equipment' => ['Barras', 'Mancuernas', 'Maquinas', 'Poleas', 'Smith', 'Hip thrust', 'Leg press', 'Hack squat', 'Banco', 'TRX', 'Bandas']
        ],
        'nutrition' => [
            'diet' => 'omnivora',
            'meals' => 4,
            'allergies' => 'ninguna',
            'supplements' => 'ninguno'
        ],
        'lifestyle' => [
            'sleep' => '7',
            'stress' => 'moderado',
            'job' => 'oficina'
        ],
        'goals' => [
            'primary' => 'Tonificar y ganar masa muscular en gluteos y piernas',
            'secondary' => 'Mejorar tren superior'
        ],
        'coach_instructions' => 'SPLIT OBLIGATORIO (no cambiar): Lunes=Cuadriceps y Gluteo | Martes=Tren Superior | Miercoles=Gluteo aislado y Abdomen | Jueves=Tren Superior (lo que falte del martes) | Viernes=Gluteo y Femoral. Entrenar en GIMNASIO con todo el equipo disponible. PROHIBIDO: peso muerto y todas sus variantes (lesion). Priorizar hip thrust, sentadillas, prensa, hack squat para gluteo. Incluir ejercicios de aislamiento para gluteo medio y menor.'
    ];

    $json = json_encode($intake, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare("UPDATE rise_programs SET personalized_program = ? WHERE client_id = 15 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$json]);

    // Desactivar plan anterior
    $db->prepare("UPDATE assigned_plans SET active = 0 WHERE client_id = 15")->execute();

    echo json_encode(['ok' => true, 'message' => 'Intake actualizado y plan anterior desactivado', 'rows' => $stmt->rowCount()]);

} elseif ($action === 'check') {
    // Ver intake actual
    $r = $db->query("SELECT personalized_program FROM rise_programs WHERE client_id=15 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $plans = $db->query("SELECT id, plan_type, active, ai_generation_id, created_at FROM assigned_plans WHERE client_id=15 ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    $gens = $db->query("SELECT id, status, prompt_tokens, completion_tokens, created_at FROM ai_generations WHERE client_id=15 ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['intake' => json_decode($r['personalized_program'] ?? '{}', true), 'plans' => $plans, 'generations' => $gens], JSON_UNESCAPED_UNICODE);

} else {
    echo json_encode(['error' => 'action must be: update_intake, check']);
}

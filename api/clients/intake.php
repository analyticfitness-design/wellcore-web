<?php
// GET  /api/clients/intake  → get intake questionnaire data
// PUT  /api/clients/intake  → save/update intake questionnaire data

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'PUT');
$client = authenticateClient();
$db = getDB();
$cid = (int)$client['id'];
$plan = strtolower($client['plan'] ?? 'esencial');

// ── GET ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // For RISE clients, also fetch from rise_programs.personalized_program
    $riseIntake = null;
    if ($plan === 'rise') {
        $stmt = $db->prepare("
            SELECT personalized_program, experience_level, training_location, gender
            FROM rise_programs
            WHERE client_id = ? AND status = 'active'
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$cid]);
        $rp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rp && $rp['personalized_program']) {
            $riseIntake = json_decode($rp['personalized_program'], true);
            $riseIntake['_meta'] = [
                'experience_level'  => $rp['experience_level'],
                'training_location' => $rp['training_location'],
                'gender'            => $rp['gender'],
            ];
        }
    }

    // Regular intake data from client_profiles
    $stmt = $db->prepare("SELECT intake_data FROM client_profiles WHERE client_id = ?");
    $stmt->execute([$cid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $profileIntake = $row ? json_decode($row['intake_data'] ?? 'null', true) : null;

    respond([
        'plan'          => $plan,
        'rise_intake'   => $riseIntake,
        'profile_intake' => $profileIntake,
    ]);
}

// ── PUT ─────────────────────────────────────────────────
$body = getJsonBody();

if (empty($body) || !is_array($body)) {
    respondError('Datos del formulario requeridos', 422);
}

// Sanitize: only allow known top-level keys
$allowed = [
    'experiencia', 'dias_entrenamiento', 'equipamiento', 'coach_previo', 'rutina_actual',
    'tipo_entrenamiento', 'duracion_sesion', 'horario', 'ejercicios_evitar',
    'tiene_lesion', 'lesion_detalle', 'condicion_medica', 'medicamentos',
    'dieta_actual', 'alergias', 'num_comidas', 'exp_macros', 'alimentos_no',
    'horario_trabajo', 'come_fuera', 'estres', 'sueno',
    'como_conocio', 'expectativas', 'genero', 'objetivo',
    // Presencial intake fields
    'edad', 'peso', 'talla', 'inicio_semana', 'dia_tipico', 'agua',
    'suplementos_actuales', 'notas',
    // RISE-specific fields
    'measurements', 'training', 'availability', 'nutrition', 'lifestyle', 'motivation',
];

$intake = [];
foreach ($body as $key => $val) {
    if (in_array($key, $allowed, true)) {
        $intake[$key] = $val;
    }
}
$intake['updated_at'] = date('Y-m-d H:i:s');

$json = json_encode($intake, JSON_UNESCAPED_UNICODE);

// Upsert into client_profiles
$check = $db->prepare("SELECT id FROM client_profiles WHERE client_id = ?");
$check->execute([$cid]);

if ($check->fetchColumn()) {
    $db->prepare("UPDATE client_profiles SET intake_data = ? WHERE client_id = ?")
       ->execute([$json, $cid]);
} else {
    $db->prepare("INSERT INTO client_profiles (client_id, intake_data) VALUES (?, ?)")
       ->execute([$cid, $json]);
}

respond(['message' => 'Formulario inicial guardado', 'intake' => $intake], 200);

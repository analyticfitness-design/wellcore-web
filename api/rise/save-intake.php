<?php
// POST /api/rise/save-intake.php
// Guardar datos detallados del intake form después de inscripción inicial
// Body: { enrollment, measurements, training, availability, nutrition, lifestyle, motivation }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';

requireMethod('POST');

// Rate limit: 10 intentos por IP cada hora
if (!rate_limit_check('rise_intake', 10, 3600)) {
    respondError('Demasiadas solicitudes. Intenta en unos minutos.', 429);
}

$input = getJsonBody();

// Validar programa_id
if (empty($input['enrollment']['program_id'])) {
    respondError('program_id requerido en enrollment', 400);
}

$program_id = intval($input['enrollment']['program_id']);

// Validar campos requeridos
if (empty($input['training']['years'])) {
    respondError('training.years requerido', 400);
}

$db = getDB();

// Verificar que el programa existe
$stmt = $db->prepare("SELECT id, client_id FROM rise_programs WHERE id = ?");
$stmt->execute([$program_id]);
if ($stmt->rowCount() === 0) {
    respondError('Programa RISE no encontrado', 404);
}

$program = $stmt->fetch(PDO::FETCH_ASSOC);
$client_id = $program['client_id'];

// Mapear años de experiencia a niveles
$years = intval($input['training']['years'] ?? 0);
$experience_level = 'principiante';
if ($years >= 2) {
    $experience_level = 'intermedio';
}
if ($years >= 5) {
    $experience_level = 'avanzado';
}

// Preparar datos para guardar
$intake_data = [
    'measurements' => $input['measurements'] ?? [],
    'training' => $input['training'] ?? [],
    'availability' => $input['availability'] ?? [],
    'nutrition' => $input['nutrition'] ?? [],
    'lifestyle' => $input['lifestyle'] ?? [],
    'motivation' => $input['motivation'] ?? [],
    'saved_at' => date('Y-m-d H:i:s')
];

try {
    // Actualizar programa RISE con datos personalizados
    $stmt = $db->prepare("
        UPDATE rise_programs
        SET personalized_program = ?,
            experience_level = IF(? != '', ?, experience_level),
            training_location = IF(? != '', ?, training_location),
            gender = IF(? != '', ?, gender)
        WHERE id = ?
    ");

    $personalized_json = json_encode($intake_data, JSON_UNESCAPED_UNICODE);

    $stmt->execute([
        $personalized_json,
        $experience_level,
        $experience_level,
        $input['availability']['place'] ?? '',
        $input['availability']['place'] ?? '',
        $input['enrollment']['gender'] ?? '',
        $input['enrollment']['gender'] ?? '',
        $program_id
    ]);

    // Respuesta exitosa
    respond([
        'success' => true,
        'message' => 'Datos de intake guardados exitosamente',
        'program_id' => $program_id,
        'client_id' => $client_id,
        'next_step' => 'payment'
    ], 200);

} catch (PDOException $e) {
    // Log the actual error
    error_log('save-intake.php error: ' . $e->getMessage());
    respondError('Error interno al guardar datos', 500);
}
?>

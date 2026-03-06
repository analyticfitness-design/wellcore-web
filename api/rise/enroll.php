<?php
// POST /api/rise/enroll.php
// Inscripcion publica al reto RISE 30 dias
// Body: {email, name, password, experience_level, training_location, gender}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/notify-admin.php';

requireMethod('POST');

if (!rate_limit_check('rise_enroll', 10, 3600)) {
    respondError('Demasiadas solicitudes. Espera un momento.', 429);
}

$input = getJsonBody();

$required = ['email', 'name', 'password', 'experience_level', 'training_location', 'gender'];
foreach ($required as $field) {
    if (empty(trim($input[$field] ?? ''))) {
        respondError("Campo requerido: $field", 400);
    }
}

$email             = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$name              = htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8');
$password          = $input['password'];
$experience_level  = $input['experience_level'];
$training_location = $input['training_location'];
$gender            = $input['gender'];

$valid_experience  = ['principiante', 'intermedio', 'avanzado'];
$valid_location    = ['gym', 'home', 'hybrid'];
$valid_gender      = ['male', 'female', 'other'];

if (!in_array($experience_level, $valid_experience)) respondError('experience_level invalido', 400);
if (!in_array($training_location, $valid_location)) respondError('training_location invalido', 400);
if (!in_array($gender, $valid_gender)) respondError('gender invalido', 400);
if (strlen($password) < 8) respondError('La contrasena debe tener al menos 8 caracteres', 400);

$db = getDB();

$stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) {
    respondError('Este email ya esta registrado', 409);
}

$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$client_code   = 'rise-' . strtoupper(bin2hex(random_bytes(4)));
$start_date    = date('Y-m-d');
$end_date      = date('Y-m-d', strtotime('+30 days'));

$db->beginTransaction();
try {
    $stmt = $db->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, plan, status, created_at)
        VALUES (?, ?, ?, ?, 'rise', 'activo', NOW())
    ");
    $stmt->execute([$client_code, $name, $email, $password_hash]);
    $client_id = $db->lastInsertId();

    $stmt = $db->prepare("
        INSERT INTO rise_programs
        (client_id, start_date, end_date, experience_level, training_location, gender, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$client_id, $start_date, $end_date, $experience_level, $training_location, $gender]);
    $program_id = $db->lastInsertId();

    $db->commit();

    // Notificar al admin
    notifyAdminNewClient([
        'name' => $name, 'email' => $email, 'plan' => 'rise', 'code' => $client_code,
        'gender' => $gender, 'experience_level' => $experience_level, 'training_location' => $training_location,
    ], 'rise_enroll');

    respond([
        'success'  => true,
        'message'  => 'Inscripcion exitosa al reto RISE',
        'client'   => [
            'id'   => $client_id,
            'code' => $client_code,
            'name' => $name,
            'email'=> $email,
            'plan' => 'rise'
        ],
        'program'  => [
            'id'           => $program_id,
            'start_date'   => $start_date,
            'end_date'     => $end_date,
            'duration_days'=> 30
        ]
    ], 201);

} catch (PDOException $e) {
    $db->rollBack();
    respondError('Error en base de datos', 500);
}
?>

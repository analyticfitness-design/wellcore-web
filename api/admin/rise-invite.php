<?php
// POST /api/admin/rise-invite.php
// Inscripcion manual al reto RISE sin pago (admin only)
// Body: {name, email, password, experience_level, training_location, gender}
// Requiere token de admin valido

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';


define('MAILRELAY_API_KEY', 'xbBuSfigCBzk7HUz2y5oumPxDnunxzbEnfeueFp_');
define('MAILRELAY_ENDPOINT', 'https://wellcorefitness.ipzmarketing.com/api/v1/send_emails');

function sendRiseWelcomeEmail(string $toEmail, string $toName, string $password, string $startDate, string $endDate): bool {
    $loginUrl = 'https://wellcorefitness.com/login.html';
    $endFmt   = date('d/m/Y', strtotime($endDate));
    $startFmt = date('d/m/Y', strtotime($startDate));
    $n = htmlspecialchars($toName,   ENT_QUOTES, 'UTF-8');
    $e = htmlspecialchars($toEmail,  ENT_QUOTES, 'UTF-8');
    $p = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');

    $html = "<!DOCTYPE html><html lang=es><head><meta charset=UTF-8></head>"
        . "<body style=\"margin:0;padding:0;background:#0a0a0a;font-family:Arial,sans-serif;\">"
        . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#0a0a0a;padding:40px 20px;\"><tr><td align=\"center\">"
        . "<table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:600px;width:100%;\">"
        . "<tr><td style=\"background:linear-gradient(135deg,#1a0000,#2d0505);border-radius:16px 16px 0 0;padding:36px;text-align:center;border-bottom:3px solid #e31e24;\">"
        . "<div style=\"font-size:12px;font-weight:700;letter-spacing:4px;color:#e31e24;text-transform:uppercase;margin-bottom:10px;\">WellCore Fitness</div>"
        . "<h1 style=\"margin:0;font-size:34px;font-weight:900;color:#fff;\">Reto <span style=\"color:#e31e24;\">RISE</span> 30 D&iacute;as</h1>"
        . "<p style=\"margin:10px 0 0;color:#888;\">Tu transformaci&oacute;n comienza ahora</p></td></tr>"
        . "<tr><td style=\"background:#111;padding:36px;\">"
        . "<p style=\"color:#ccc;font-size:16px;line-height:1.7;margin:0 0 18px;\">Hola <strong style=\"color:#fff;\">{$n}</strong>,</p>"
        . "<p style=\"color:#ccc;font-size:16px;line-height:1.7;margin:0 0 28px;\">Tu acceso al <strong style=\"color:#e31e24;\">Reto RISE 30 d&iacute;as</strong> est&aacute; activado:</p>"
        . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#1a1a1a;border:1px solid #333;border-left:4px solid #e31e24;border-radius:8px;margin-bottom:28px;\">"
        . "<tr><td style=\"padding:22px 26px;\">"
        . "<div style=\"margin-bottom:14px;\"><div style=\"font-size:11px;font-weight:700;color:#e31e24;text-transform:uppercase;margin-bottom:5px;\">Email</div>"
        . "<div style=\"font-size:15px;color:#fff;font-family:monospace;\">{$e}</div></div>"
        . "<div style=\"margin-bottom:14px;\"><div style=\"font-size:11px;font-weight:700;color:#e31e24;text-transform:uppercase;margin-bottom:5px;\">Contrase&ntilde;a temporal</div>"
        . "<div style=\"font-size:15px;color:#fff;font-family:monospace;\">{$p}</div></div>"
        . "<div><div style=\"font-size:11px;font-weight:700;color:#e31e24;text-transform:uppercase;margin-bottom:5px;\">Vigencia</div>"
        . "<div style=\"font-size:14px;color:#aaa;\">{$startFmt} &rarr; {$endFmt}</div></div>"
        . "</td></tr></table>"
        . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin-bottom:26px;\"><tr><td align=\"center\">"
        . "<a href=\"{$loginUrl}\" style=\"display:inline-block;background:linear-gradient(135deg,#e31e24,#c01020);color:#fff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 42px;border-radius:8px;text-transform:uppercase;\">"
        . "Acceder al Portal &rarr;</a></td></tr></table>"
        . "<p style=\"color:#666;font-size:13px;\">Por seguridad cambia tu contrasena al ingresar. Dudas? Respondenos este correo.</p>"
        . "</td></tr><tr><td style=\"background:#0d0d0d;border-radius:0 0 16px 16px;padding:18px 36px;text-align:center;border-top:1px solid #222;\">"
        . "<p style=\"color:#444;font-size:11px;margin:0;text-transform:uppercase;\">WellCore Fitness &mdash; wellcorefitness.com</p>"
        . "</td></tr></table></td></tr></table></body></html>";

    $payload = json_encode([
        'from_name'  => 'WellCore Fitness',
        'from_email' => 'info@wellcorefitness.com',
        'subject'    => 'Tu acceso al Reto RISE 30 Dias esta listo',
        'html_body'  => $html,
        'recipients' => [['name' => $toName, 'email' => $toEmail]]
    ]);

    $ch = curl_init(MAILRELAY_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-AUTH-TOKEN: ' . MAILRELAY_API_KEY],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

requireMethod('POST');

$admin = requireAdminRole('admin', 'jefe', 'superadmin');

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

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respondError('Email invalido', 400);
if (!in_array($experience_level, $valid_experience)) respondError('experience_level invalido', 400);
if (!in_array($training_location, $valid_location)) respondError('training_location invalido', 400);
if (!in_array($gender, $valid_gender)) respondError('gender invalido', 400);
if (strlen($password) < 6) respondError('La contrasena debe tener al menos 6 caracteres', 400);

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

    // Enviar email de bienvenida (no bloqueante)
    $emailSent = sendRiseWelcomeEmail($email, $name, $password, $start_date, $end_date);

    respond([
        'success'  => true,
        'message'  => 'Acceso RISE creado correctamente',
        'invited_by' => $admin['username'] ?? 'admin',
        'email_sent' => $emailSent,
        'client'   => [
            'id'    => $client_id,
            'code'  => $client_code,
            'name'  => $name,
            'email' => $email,
            'plan'  => 'rise'
        ],
        'program'  => [
            'id'            => $program_id,
            'start_date'    => $start_date,
            'end_date'      => $end_date,
            'duration_days' => 30
        ]
    ], 201);

} catch (PDOException $e) {
    $db->rollBack();
    respondError('Error en base de datos', 500);
}
?>
